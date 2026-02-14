<?php
session_start();
require 'db.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login_admin.php");
    exit;
}

$mensaje = '';

try {
    $sql = "SELECT d.Id_devolucion, d.Fecha, d.Motivo,
                   v.Id_venta, c.Nombre AS NombreCliente
            FROM Devolucion d
            JOIN Ventas v ON d.Id_venta = v.Id_venta
            JOIN Cliente c ON v.Id_cliente = c.Id_Cliente
            ORDER BY d.Fecha DESC";
    $stmt = $pdo->query($sql);
    $devoluciones = $stmt->fetchAll();
} catch (PDOException $e) {
    $mensaje = 'Error al cargar devoluciones: ' . htmlspecialchars($e->getMessage());
    $devoluciones = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Devoluciones (Admin)</title>
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
                <a href="panel_admin.php" class="btn btn-outline-light btn-sm icon-inline"><i class="bi bi-speedometer" aria-hidden="true"></i><span>Panel</span></a>
                <a href="logout.php" class="btn btn-outline-light btn-sm icon-inline text-white"><i class="bi bi-door-open" aria-hidden="true"></i><span>Cerrar sesión</span></a>
            </div>
        </div>
    </nav>

    <main class="container py-5">
        <div class="mb-4">
            <h1 class="fw-bold mb-1">Devoluciones registradas</h1>
            <p class="text-muted mb-0">Revisa las solicitudes recientes y accede a su detalle.</p>
        </div>

        <?php if ($mensaje !== ''): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($mensaje); ?></div>
        <?php endif; ?>

        <?php if (count($devoluciones) === 0): ?>
            <div class="alert alert-info">No hay devoluciones registradas.</div>
        <?php else: ?>
            <div class="card shadow-sm">
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Fecha</th>
                                <th>Cliente</th>
                                <th>ID Venta</th>
                                <th>Motivo</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($devoluciones as $d): ?>
                                <tr>
                                    <td>#<?php echo (int)$d['Id_devolucion']; ?></td>
                                    <td><?php echo htmlspecialchars($d['Fecha']); ?></td>
                                    <td><?php echo htmlspecialchars($d['NombreCliente']); ?></td>
                                    <td><?php echo (int)$d['Id_venta']; ?></td>
                                    <td class="text-muted"><?php echo htmlspecialchars($d['Motivo']); ?></td>
                                    <td class="text-end">
                                        <a href="detalle_devolucion_admin.php?id_dev=<?php echo (int)$d['Id_devolucion']; ?>" class="btn btn-sm btn-outline-primary">Ver detalle</a>
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
