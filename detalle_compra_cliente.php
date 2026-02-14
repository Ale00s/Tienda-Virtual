<?php
session_start();
require 'db.php';

if (!isset($_SESSION['cliente_id'])) {
    header("Location: login_cliente.php");
    exit;
}

$clienteId = (int)$_SESSION['cliente_id'];
$idVenta = isset($_GET['id_venta']) ? (int)$_GET['id_venta'] : 0;
$mensaje = '';

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
            $sqlDet = "SELECT dv.*, p.Nombre
                       FROM DetalleVenta dv
                       JOIN Productos p ON dv.Id_producto = p.Id_Producto
                       WHERE dv.Id_venta = :id";
            $stmtDet = $pdo->prepare($sqlDet);
            $stmtDet->execute([':id' => $idVenta]);
            $detalles = $stmtDet->fetchAll();
        }
    } catch (PDOException $e) {
        $mensaje = 'Error al cargar venta: ' . htmlspecialchars($e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Detalle de compra</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/theme.css">
</head>
<body class="bg-light">

    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
        <div class="container">
            <a class="navbar-brand text-white fw-bold" href="productos_cliente.php">Zip Undercover</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navDetalle" aria-controls="navDetalle" aria-expanded="false" aria-label="Mostrar navegación">
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
                        <a class="nav-link text-white active icon-inline" aria-current="page" href="compras_cliente.php">
                            <i class="bi bi-receipt-cutoff" aria-hidden="true"></i>
                            <span>Mis compras</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white icon-inline" href="panel_cliente.php">
                            <i class="bi bi-person-circle" aria-hidden="true"></i>
                            <span>Mi cuenta</span>
                        </a>
                    </li>
                </ul>
                <div class="d-flex gap-2">
                    <a href="logout.php" class="btn btn-light btn-sm text-primary icon-inline">
                        <i class="bi bi-door-open" aria-hidden="true"></i>
                        <span>Cerrar sesión</span>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main class="container py-5">
        <div class="mb-4">
            <h1 class="fw-bold mt-2">Detalle de compra</h1>
            <p class="text-muted mb-0">Consulta el estado de tu pedido y solicita devoluciones si lo necesitas.</p>
        </div>

        <?php if ($mensaje !== ''): ?>
            <div class="alert alert-danger"><?php echo $mensaje; ?></div>
        <?php endif; ?>

        <?php if (!empty($venta) && empty($mensaje)): ?>
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <p class="text-muted mb-1">ID Venta</p>
                            <h5 class="mb-0">#<?php echo $venta['Id_venta']; ?></h5>
                        </div>
                        <div class="col-md-3">
                            <p class="text-muted mb-1">Fecha</p>
                            <h5 class="mb-0"><?php echo htmlspecialchars($venta['Fecha']); ?></h5>
                        </div>
                        <div class="col-md-3">
                            <p class="text-muted mb-1">Subtotal</p>
                            <h5 class="mb-0">$<?php echo number_format($venta['Subtotal'] ?? 0, 2); ?></h5>
                        </div>
                        <div class="col-md-3">
                            <p class="text-muted mb-1">Total</p>
                            <h5 class="text-success mb-0">$<?php echo number_format($venta['Total'], 2); ?></h5>
                        </div>
                    </div>
                    <div class="row g-3 mt-2">
                        <div class="col-md-3">
                            <p class="text-muted mb-1">Impuestos</p>
                            <h6 class="mb-0">$<?php echo number_format($venta['Impuestos'] ?? 0, 2); ?></h6>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a class="btn btn-outline-primary btn-sm icon-inline" href="devolucion_cliente.php?id_venta=<?php echo $venta['Id_venta']; ?>">
                            <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
                            <span>Solicitar devolución</span>
                        </a>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Productos comprados</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Producto</th>
                                <th>Cantidad</th>
                                <th>Precio unidad</th>
                                <th>Total línea</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($detalles as $d): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($d['Nombre']); ?></td>
                                    <td><?php echo (int)$d['Cantidad']; ?></td>
                                    <td>$<?php echo number_format($d['Precio_Unitario'], 2); ?></td>
                                    <td class="fw-bold">$<?php echo number_format($d['Cantidad'] * $d['Precio_Unitario'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
