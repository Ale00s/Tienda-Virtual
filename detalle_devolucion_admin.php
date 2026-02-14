<?php
session_start();
require 'db.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login_admin.php");
    exit;
}

$idDev = isset($_GET['id_dev']) ? (int)$_GET['id_dev'] : 0;
$mensaje = '';

if ($idDev <= 0) {
    $mensaje = 'Devolución no válida.';
} else {
    try {
        $sql = "SELECT d.*, v.Id_venta, v.Fecha AS FechaVenta, c.Nombre AS NombreCliente
                FROM Devolucion d
                JOIN Ventas v ON d.Id_venta = v.Id_venta
                JOIN Cliente c ON v.Id_cliente = c.Id_Cliente
                WHERE d.Id_devolucion = :id LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $idDev]);
        $dev = $stmt->fetch();

        if (!$dev) {
            $mensaje = 'Devolución no encontrada.';
        } else {
            $sqlDet = "SELECT dd.*, p.Nombre
                       FROM DetalleDevolucion dd
                       JOIN Productos p ON dd.Id_producto = p.Id_Producto
                       WHERE dd.Id_devolucion = :id";
            $stmtDet = $pdo->prepare($sqlDet);
            $stmtDet->execute([':id' => $idDev]);
            $detalles = $stmtDet->fetchAll();
        }
    } catch (PDOException $e) {
        $mensaje = 'Error al cargar la devolución: ' . htmlspecialchars($e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Detalle de devolución</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/theme.css">
</head>
<body class="bg-body-tertiary">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="panel_admin.php">Administración</a>
            <span class="badge bg-secondary">Devoluciones</span>
            <div class="ms-auto d-flex gap-2">
                <a href="devoluciones_admin.php" class="btn btn-outline-light btn-sm">Volver</a>
                <a href="logout.php" class="btn btn-light btn-sm text-dark icon-inline"><i class="bi bi-door-open" aria-hidden="true"></i><span>Cerrar sesión</span></a>
            </div>
        </div>
    </nav>

    <main class="container py-5">
        <div class="mb-4">
            <h1 class="fw-bold mb-1">Detalle de devolución</h1>
            <p class="text-muted mb-0">Información del caso y productos relacionados.</p>
        </div>

        <?php if ($mensaje !== ''): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($mensaje); ?></div>
        <?php endif; ?>

        <?php if (!empty($dev) && empty($mensaje)): ?>
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <p class="text-muted mb-1">ID Devolución</p>
                            <h5 class="mb-0">#<?php echo (int)$dev['Id_devolucion']; ?></h5>
                        </div>
                        <div class="col-md-3">
                            <p class="text-muted mb-1">Fecha devolución</p>
                            <h5 class="mb-0"><?php echo htmlspecialchars($dev['Fecha']); ?></h5>
                        </div>
                        <div class="col-md-3">
                            <p class="text-muted mb-1">ID Venta</p>
                            <h5 class="mb-0">#<?php echo (int)$dev['Id_venta']; ?></h5>
                            <small class="text-muted"><?php echo htmlspecialchars($dev['FechaVenta']); ?></small>
                        </div>
                        <div class="col-md-3">
                            <p class="text-muted mb-1">Cliente</p>
                            <h5 class="mb-0"><?php echo htmlspecialchars($dev['NombreCliente']); ?></h5>
                        </div>
                    </div>
                    <div class="mt-3">
                        <p class="text-muted mb-1">Motivo</p>
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($dev['Motivo'])); ?></p>
                    </div>
                </div>
            </div>

            <?php if (!$detalles || count($detalles) === 0): ?>
                <div class="alert alert-info">No hay detalles para esta devolución.</div>
            <?php else: ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Productos devueltos</h5>
                        <p class="text-muted small mb-0">Valores de reembolso incluyen IVA.</p>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Producto</th>
                                    <th>Cantidad</th>
                                    <th>Valor reembolso (con IVA)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($detalles as $d): ?>
                                    <tr>
                                    <td><?php echo htmlspecialchars($d['Nombre']); ?></td>
                                    <td><?php echo (int)$d['Cantidad']; ?></td>
                                    <td class="fw-bold">$<?php echo number_format($d['Valor_reembolso'], 2); ?></td>
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
