<?php
session_start();
require 'db.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login_admin.php");
    exit;
}

$mensaje = '';
$fechaDesde = $_GET['desde'] ?? '';
$fechaHasta = $_GET['hasta'] ?? '';

$where = '';
$params = [];

if ($fechaDesde !== '' && $fechaHasta !== '') {
    $where = 'WHERE v.Fecha BETWEEN :desde AND :hasta';
    $params[':desde'] = $fechaDesde . ' 00:00:00';
    $params[':hasta'] = $fechaHasta . ' 23:59:59';
}

try {
    $sql = "SELECT v.Id_venta, v.Fecha, v.Subtotal, v.Impuestos, v.Total,
                   c.Nombre AS NombreCliente
            FROM Ventas v
            JOIN Cliente c ON v.Id_cliente = c.Id_Cliente
            $where
            ORDER BY v.Fecha DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $ventas = $stmt->fetchAll();
} catch (PDOException $e) {
    $mensaje = 'Error al cargar ventas: ' . htmlspecialchars($e->getMessage());
    $ventas = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ventas (Admin)</title>
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
                <a href="panel_admin.php" class="btn btn-outline-light btn-sm icon-inline"><i class="bi bi-speedometer" aria-hidden="true"></i><span>Panel</span></a>
                <a href="logout.php" class="btn btn-light btn-sm text-dark icon-inline"><i class="bi bi-door-open" aria-hidden="true"></i><span>Cerrar sesión</span></a>
            </div>
        </div>
    </nav>

    <main class="container py-5">
        <div class="mb-4">
            <h1 class="fw-bold mb-1">Ventas registradas</h1>
            <p class="text-muted mb-0">Filtra por fecha y revisa el detalle de cada operación.</p>
        </div>

        <form method="get" class="card card-body shadow-sm mb-4">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Desde</label>
                    <input type="date" name="desde" class="form-control" value="<?php echo htmlspecialchars($fechaDesde); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Hasta</label>
                    <input type="date" name="hasta" class="form-control" value="<?php echo htmlspecialchars($fechaHasta); ?>">
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">Filtrar</button>
                    <a href="ventas_admin.php" class="btn btn-outline-secondary">Quitar filtros</a>
                </div>
            </div>
        </form>

        <?php if ($mensaje !== ''): ?>
            <div class="alert alert-danger"><?php echo $mensaje; ?></div>
        <?php endif; ?>

        <?php if (count($ventas) === 0): ?>
            <div class="alert alert-info">No hay ventas registradas para los filtros dados.</div>
        <?php else: ?>
            <div class="card shadow-sm">
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID Venta</th>
                                <th>Fecha</th>
                                <th>Cliente</th>
                                <th>Subtotal</th>
                                <th>Impuestos</th>
                                <th>Total</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ventas as $v): ?>
                                <tr>
                                    <td>#<?php echo (int)$v['Id_venta']; ?></td>
                                    <td><?php echo htmlspecialchars($v['Fecha']); ?></td>
                                    <td><?php echo htmlspecialchars($v['NombreCliente']); ?></td>
                                    <td>$<?php echo number_format($v['Subtotal'], 2); ?></td>
                                    <td>$<?php echo number_format($v['Impuestos'], 2); ?></td>
                                    <td class="fw-bold">$<?php echo number_format($v['Total'], 2); ?></td>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-outline-primary" href="detalle_venta_admin.php?id_venta=<?php echo (int)$v['Id_venta']; ?>">Ver detalle</a>
                                    </td>
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
