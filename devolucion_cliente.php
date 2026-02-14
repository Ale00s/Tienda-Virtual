<?php
session_start();
require 'db.php';

if (!isset($_SESSION['cliente_id'])) {
    header("Location: login_cliente.php");
    exit;
}

$IVA_CO = defined('IVA_CO') ? IVA_CO : 0.19; // IVA estándar Colombia
$csrf_token = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;

$clienteId = (int)$_SESSION['cliente_id'];
$idVenta   = isset($_GET['id_venta']) ? (int)$_GET['id_venta'] : 0;
$mensaje   = '';

if ($idVenta <= 0) {
    $mensaje = 'Venta no válida.';
} else {
    try {

        $sql = "SELECT * FROM Ventas WHERE Id_venta = :id AND Id_cliente = :cliente LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $idVenta, ':cliente' => $clienteId]);
        $venta = $stmt->fetch();

        if (!$venta) {
            $mensaje = 'No se encontró la venta o no pertenece a este usuario.';
        } else {

            $stmtDev = $pdo->prepare("SELECT * FROM Devolucion WHERE Id_venta = :id LIMIT 1");
            $stmtDev->execute([':id' => $idVenta]);
            $devExistente = $stmtDev->fetch();

            if ($devExistente) {
                $mensaje = 'Ya existe una devolución registrada para esta venta.';
            } else {

                $sqlDet = "SELECT dv.*, p.Nombre
                           FROM DetalleVenta dv
                           JOIN Productos p ON dv.Id_producto = p.Id_Producto
                           WHERE dv.Id_venta = :id";
                $stmtDet = $pdo->prepare($sqlDet);
                $stmtDet->execute([':id' => $idVenta]);
                $detalles = $stmtDet->fetchAll();

                if (!$detalles) {
                    $mensaje = 'La venta no tiene detalles, no se puede devolver.';
                }
            }
        }
    } catch (PDOException $e) {
        $mensaje = 'Error al cargar la venta: ' . htmlspecialchars($e->getMessage());
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($mensaje)) {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $mensaje = 'Solicitud no válida, recarga la página.';
    } else {
    $motivo = trim($_POST['motivo'] ?? '');
    $cantidades = $_POST['cantidades'] ?? [];

    if ($motivo === '') {
        $mensaje = 'Debes indicar un motivo para la devolución.';
    } else {
        try {
            $pdo->beginTransaction();


            $stmtInsDev = $pdo->prepare("INSERT INTO Devolucion
                                         (Id_venta, Motivo, Fecha)
                                         VALUES
                                         (:venta, :motivo, NOW())");
            $stmtInsDev->execute([
                ':venta'   => $idVenta,
                ':motivo'  => $motivo
            ]);
            $idDevolucion = (int)$pdo->lastInsertId();
            registrar_auditoria($pdo, [
                'usuario_id' => $clienteId,
                'rol' => 'CLIENTE',
                'accion' => 'CREAR',
                'entidad' => 'Devolucion',
                'entidad_id' => $idDevolucion,
                'datos' => ['motivo' => $motivo, 'venta' => $idVenta]
            ]);

            $algoDevuelto = false;


            foreach ($detalles as $item) {
                $idProdVenta = (int)$item['Id_producto'];
                $cantidadVendida = (int)$item['Cantidad'];
                $precioUnitario = (float)$item['Precio_Unitario'];

                $cantDevuelta = isset($cantidades[$idProdVenta]) ? (int)$cantidades[$idProdVenta] : 0;
                if ($cantDevuelta < 0) {
                    $cantDevuelta = 0;
                }
                if ($cantDevuelta > 0) {
                    if ($cantDevuelta > $cantidadVendida) {

                        $pdo->rollBack();
                        $mensaje = 'No puedes devolver más unidades de las compradas para el producto: ' .
                                   htmlspecialchars($item['Nombre']);
                        goto fin;
                    }

                    $algoDevuelto = true;
                    $baseReembolso = $cantDevuelta * $precioUnitario;
                    $impuestoDevuelto = round($baseReembolso * $IVA_CO, 2);
                    $valorReembolso = $baseReembolso + $impuestoDevuelto;


                    $stmtDetDev = $pdo->prepare("INSERT INTO DetalleDevolucion
                                                 (Id_devolucion, Id_producto, Cantidad, Valor_reembolso)
                                                 VALUES
                                                 (:dev, :prod, :cant, :valor)");
                    $stmtDetDev->execute([
                        ':dev'   => $idDevolucion,
                        ':prod'  => $idProdVenta,
                        ':cant'  => $cantDevuelta,
                        ':valor' => $valorReembolso
                    ]);


                    $stmtUpdInv = $pdo->prepare("UPDATE Inventario
                                                 SET Cantidad = Cantidad + :cant,
                                                     Fecha_actualizacion = NOW()
                                                 WHERE Id_Producto = :prod");
                    $stmtUpdInv->execute([
                        ':cant' => $cantDevuelta,
                        ':prod' => $idProdVenta
                    ]);
                }
            }

            if (!$algoDevuelto) {
                $pdo->rollBack();
                $mensaje = 'No seleccionaste ninguna cantidad para devolver.';
            } else {
                $pdo->commit();
                $mensaje = 'Devolución registrada correctamente ✅. Nuestro equipo procesará el reembolso.';
            }

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $mensaje = 'Error al registrar la devolución: ' . htmlspecialchars($e->getMessage());
        }
    }
}
}

fin:
if ($mensaje !== '') {
    $alertClass = 'alert-info';
    if (stripos($mensaje, 'error') !== false || stripos($mensaje, 'no ') === 0) {
        $alertClass = 'alert-danger';
    } elseif (strpos($mensaje, '✅') !== false) {
        $alertClass = 'alert-success';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Solicitud de devolución</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/theme.css">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
        <div class="container">
            <a class="navbar-brand text-white fw-bold" href="productos_cliente.php">Zip Undercover</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navCliente" aria-controls="navCliente" aria-expanded="false" aria-label="Mostrar navegación">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navCliente">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link text-white icon-inline" href="productos_cliente.php">
                            <i class="bi bi-grid-3x3-gap-fill" aria-hidden="true"></i>
                            <span>Catálogo</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white icon-inline" href="carrito.php">
                            <i class="bi bi-bag-heart" aria-hidden="true"></i>
                            <span>Carrito</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white icon-inline active" aria-current="page" href="compras_cliente.php">
                            <i class="bi bi-receipt-cutoff" aria-hidden="true"></i>
                            <span>Mis compras</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white icon-inline <?php echo $clienteId ? '' : 'disabled opacity-50'; ?>" href="panel_cliente.php">
                            <i class="bi bi-person-circle" aria-hidden="true"></i>
                            <span>Mi cuenta</span>
                        </a>
                    </li>
                </ul>
                <div class="d-flex gap-2">
                    <a href="logout.php" class="btn btn-light btn-sm text-primary icon-inline"><i class="bi bi-door-open" aria-hidden="true"></i><span>Cerrar sesión</span></a>
                </div>
            </div>
        </div>
    </nav>

    <main class="container py-5">
        <div class="mb-4">
            <h1 class="fw-bold mt-2">Solicitud de devolución</h1>
            <p class="text-muted mb-0">Indica los productos y cantidades a devolver. Nuestro equipo revisará tu caso.</p>
        </div>

        <?php if ($mensaje !== ''): ?>
            <div class="alert <?php echo $alertClass; ?>" role="alert" aria-live="polite"><?php echo $mensaje; ?></div>
        <?php endif; ?>

        <?php if (!empty($venta) && empty($devExistente) && !empty($detalles) && $_SERVER['REQUEST_METHOD'] !== 'POST'): ?>
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <p class="text-muted mb-1">ID Venta</p>
                            <h5 class="mb-0">#<?php echo (int)$venta['Id_venta']; ?></h5>
                        </div>
                        <div class="col-md-4">
                            <p class="text-muted mb-1">Fecha</p>
                            <h5 class="mb-0"><?php echo htmlspecialchars($venta['Fecha']); ?></h5>
                        </div>
                        <div class="col-md-4">
                            <p class="text-muted mb-1">Total</p>
                            <h5 class="text-success mb-0">$<?php echo number_format($venta['Total'], 2); ?></h5>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-body">
                    <form method="post" class="vstack gap-4">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <div>
                            <h5 class="mb-3">Selecciona las cantidades a devolver</h5>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Producto</th>
                                            <th>Cantidad comprada</th>
                                            <th style="width:140px;">Cantidad a devolver</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($detalles as $d): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($d['Nombre']); ?></td>
                                                <td><?php echo (int)$d['Cantidad']; ?></td>
                                                <td>
                                                    <input type="number"
                                                           class="form-control"
                                                           name="cantidades[<?php echo (int)$d['Id_producto']; ?>]"
                                                           value="0"
                                                           min="0"
                                                           max="<?php echo (int)$d['Cantidad']; ?>">
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div>
                            <h5 class="mb-2">Motivo de la devolución</h5>
                            <textarea name="motivo" class="form-control" rows="4" placeholder="Describe el motivo de la solicitud" required></textarea>
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-primary icon-inline">
                                <i class="bi bi-send-check" aria-hidden="true"></i>
                                <span>Enviar solicitud de devolución</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
