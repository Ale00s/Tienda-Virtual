<?php
session_start();
require 'db.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login_admin.php");
    exit;
}

$mensaje = '';

try {
    $sql = "SELECT e.Id_envio, e.Id_venta, e.Direccion_envio, e.Ciudad, e.Metodo_entrega,
                   c.Nombre AS NombreCliente, c.Correo, v.Fecha AS FechaVenta
            FROM Envio e
            LEFT JOIN Ventas v ON e.Id_venta = v.Id_venta
            LEFT JOIN Cliente c ON v.Id_cliente = c.Id_Cliente
            ORDER BY e.Id_envio DESC";
    $stmt = $pdo->query($sql);
    $envios = $stmt->fetchAll();
} catch (PDOException $e) {
    $mensaje = 'Error al cargar envíos: ' . htmlspecialchars($e->getMessage());
    $envios = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Envíos registrados</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/theme.css">
</head>
<body class="bg-body-tertiary">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="panel_admin.php">Administración</a>
            <span class="badge bg-secondary">Envíos</span>
            <div class="ms-auto d-flex gap-2">
                <a href="panel_admin.php" class="btn btn-outline-light btn-sm icon-inline"><i class="bi bi-speedometer" aria-hidden="true"></i><span>Panel</span></a>
                <a href="logout.php" class="btn btn-outline-light btn-sm icon-inline text-white"><i class="bi bi-door-open" aria-hidden="true"></i><span>Cerrar sesión</span></a>
            </div>
        </div>
    </nav>

    <main class="container py-5">
        <div class="mb-4">
            <h1 class="fw-bold mb-1">Envíos registrados</h1>
            <p class="text-muted mb-0">Consulta direcciones, métodos de entrega y compras asociadas.</p>
        </div>

        <?php if ($mensaje !== ''): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($mensaje); ?></div>
        <?php endif; ?>

        <?php if (count($envios) === 0): ?>
            <div class="alert alert-info">Aún no hay envíos registrados.</div>
        <?php else: ?>
            <div class="card shadow-sm">
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Cliente</th>
                                <th>Correo</th>
                                <th>ID Venta / Fecha</th>
                                <th>Dirección</th>
                                <th>Ciudad</th>
                                <th>Método</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($envios as $envio): ?>
                                <tr>
                                    <td>#<?php echo (int)$envio['Id_envio']; ?></td>
                                    <td><?php echo htmlspecialchars($envio['NombreCliente'] ?? 'Cliente eliminado'); ?></td>
                                    <td class="text-muted small"><?php echo htmlspecialchars($envio['Correo'] ?? ''); ?></td>
                                    <td>
                                        <?php if (!empty($envio['Id_venta'])): ?>
                                            <div class="fw-semibold">#<?php echo (int)$envio['Id_venta']; ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($envio['FechaVenta']); ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">Sin venta</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($envio['Direccion_envio']); ?></td>
                                    <td><?php echo htmlspecialchars($envio['Ciudad']); ?></td>
                                    <td><span class="badge bg-primary"><?php echo htmlspecialchars($envio['Metodo_entrega']); ?></span></td>
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
