<?php
session_start();
require 'db.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login_admin.php");
    exit;
}

$idVenta = isset($_GET['id_venta']) ? (int)$_GET['id_venta'] : 0;
$mensaje = '';

if ($idVenta <= 0) {
    $mensaje = 'Venta no válida.';
} else {
    try {
        $sql = "SELECT v.*, c.Nombre AS NombreCliente
                FROM Ventas v
                JOIN Cliente c ON v.Id_cliente = c.Id_Cliente
                WHERE v.Id_venta = :id LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $idVenta]);
        $venta = $stmt->fetch();

        if (!$venta) {
            $mensaje = 'Venta no encontrada.';
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
        $mensaje = 'Error al cargar la venta: ' . htmlspecialchars($e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Detalle de venta</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/theme.css">
</head>
<body class="bg-body-tertiary">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="panel_admin.php">Administración</a>
            <span class="badge bg-secondary">Ventas</span>
            <div class="ms-auto d-flex gap-2">
                <a href="ventas_admin.php" class="btn btn-outline-light btn-sm">Volver</a>
                <a href="logout.php" class="btn btn-light btn-sm text-dark icon-inline"><i class="bi bi-door-open" aria-hidden="true"></i><span>Cerrar sesión</span></a>
            </div>
        </div>
    </nav>

    <main class="container py-5">
        <div class="mb-4">
            <h1 class="fw-bold mb-1">Detalle de venta</h1>
            <p class="text-muted mb-0">Información de la transacción y sus productos asociados.</p>
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
                            <h5 class="mb-0">#<?php echo (int)$venta['Id_venta']; ?></h5>
                        </div>
                        <div class="col-md-3">
                            <p class="text-muted mb-1">Fecha</p>
                            <h5 class="mb-0"><?php echo htmlspecialchars($venta['Fecha']); ?></h5>
                        </div>
                        <div class="col-md-3">
                            <p class="text-muted mb-1">Cliente</p>
                            <h5 class="mb-0"><?php echo htmlspecialchars($venta['NombreCliente']); ?></h5>
                        </div>
                        <div class="col-md-3">
                            <p class="text-muted mb-1">Total</p>
                            <h5 class="text-success mb-0">$<?php echo number_format($venta['Total'], 2); ?></h5>
                        </div>
                    </div>
                    <div class="row g-3 mt-2">
                        <div class="col-md-3">
                            <p class="text-muted mb-1">Subtotal</p>
                            <h6 class="mb-0">$<?php echo number_format($venta['Subtotal'] ?? 0, 2); ?></h6>
                        </div>
                        <div class="col-md-3">
                            <p class="text-muted mb-1">Impuestos</p>
                            <h6 class="mb-0">$<?php echo number_format($venta['Impuestos'] ?? 0, 2); ?></h6>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!$detalles || count($detalles) === 0): ?>
                <div class="alert alert-info">No hay detalles para esta venta.</div>
            <?php else: ?>
                <div class="card shadow-sm">
                    <div class="table-responsive">
                        <table class="table table-striped mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Producto</th>
                                    <th>Cantidad</th>
                                    <th>Precio unitario</th>
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
        <?php endif; ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
