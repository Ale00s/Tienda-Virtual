<?php
session_start();
require 'db.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login_admin.php");
    exit;
}

$rolFiltro = trim($_GET['rol'] ?? '');
$entidadFiltro = trim($_GET['entidad'] ?? '');
$accionFiltro = trim($_GET['accion'] ?? '');
$fechaDesde = trim($_GET['desde'] ?? '');
$fechaHasta = trim($_GET['hasta'] ?? '');

$where = [];
$params = [];
if ($rolFiltro !== '') {
    $where[] = 'Rol = :rol';
    $params[':rol'] = $rolFiltro;
}
if ($entidadFiltro !== '') {
    $where[] = 'Entidad = :entidad';
    $params[':entidad'] = $entidadFiltro;
}
if ($accionFiltro !== '') {
    $where[] = 'Accion = :accion';
    $params[':accion'] = $accionFiltro;
}
if ($fechaDesde !== '' && $fechaHasta !== '') {
    $where[] = 'Fecha BETWEEN :desde AND :hasta';
    $params[':desde'] = $fechaDesde . ' 00:00:00';
    $params[':hasta'] = $fechaHasta . ' 23:59:59';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

try {
    $sql = "SELECT Id, UsuarioId, Rol, Accion, Entidad, EntidadId, Datos, Ip, UserAgent, Fecha
            FROM Auditoria
            $whereSql
            ORDER BY Fecha DESC
            LIMIT 200";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $auditorias = $stmt->fetchAll();
} catch (PDOException $e) {
    $auditorias = [];
    $error = 'Error al cargar auditoría: ' . htmlspecialchars($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Auditoría</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/theme.css">
</head>
<body class="bg-body-tertiary">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="panel_admin.php">Administración</a>
            <span class="badge bg-secondary">Auditoría</span>
            <div class="ms-auto d-flex gap-2">
                <a href="panel_admin.php" class="btn btn-outline-light btn-sm icon-inline"><i class="bi bi-speedometer" aria-hidden="true"></i><span>Panel</span></a>
                <a href="logout.php" class="btn btn-outline-light btn-sm icon-inline text-white"><i class="bi bi-door-open" aria-hidden="true"></i><span>Cerrar sesión</span></a>
            </div>
        </div>
    </nav>

    <main class="container py-5">
        <div class="mb-4">
            <h1 class="fw-bold mb-1">Auditoría</h1>
            <p class="text-muted mb-0">Historial de operaciones (máx. 200 registros recientes).</p>
        </div>

        <form class="card card-body shadow-sm mb-4" method="get">
            <div class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">Rol</label>
                    <select name="rol" class="form-select">
                        <option value="">Todos</option>
                        <option value="ADMIN" <?php echo $rolFiltro === 'ADMIN' ? 'selected' : ''; ?>>Admin</option>
                        <option value="CLIENTE" <?php echo $rolFiltro === 'CLIENTE' ? 'selected' : ''; ?>>Cliente</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Acción</label>
                    <select name="accion" class="form-select">
                        <option value="">Todas</option>
                        <?php foreach (['CREAR','MODIFICAR','ELIMINAR','LOGIN','LOGOUT'] as $ac): ?>
                            <option value="<?php echo $ac; ?>" <?php echo $accionFiltro === $ac ? 'selected' : ''; ?>><?php echo $ac; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Entidad</label>
                    <input type="text" name="entidad" class="form-control" value="<?php echo htmlspecialchars($entidadFiltro); ?>" placeholder="Ej: Producto">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Desde</label>
                    <input type="date" name="desde" class="form-control" value="<?php echo htmlspecialchars($fechaDesde); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Hasta</label>
                    <input type="date" name="hasta" class="form-control" value="<?php echo htmlspecialchars($fechaHasta); ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button class="btn btn-primary me-2" type="submit">Filtrar</button>
                    <a href="auditoria_admin.php" class="btn btn-outline-secondary">Limpiar</a>
                </div>
            </div>
        </form>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger" role="alert" aria-live="polite"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (count($auditorias) === 0): ?>
            <div class="alert alert-info" role="alert" aria-live="polite">No hay registros con los filtros actuales.</div>
        <?php else: ?>
            <div class="card shadow-sm">
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Fecha</th>
                                <th>Rol</th>
                                <th>Usuario</th>
                                <th>Acción</th>
                                <th>Entidad</th>
                                <th>Datos</th>
                                <th>IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($auditorias as $a): ?>
                                <tr>
                                    <td>#<?php echo (int)$a['Id']; ?></td>
                                    <td><?php echo htmlspecialchars($a['Fecha']); ?></td>
                                    <td><span class="badge bg-primary"><?php echo htmlspecialchars($a['Rol'] ?? ''); ?></span></td>
                                    <td><?php echo htmlspecialchars($a['UsuarioId'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($a['Accion']); ?></td>
                                    <td><?php echo htmlspecialchars($a['Entidad']) . ($a['EntidadId'] ? ' #' . (int)$a['EntidadId'] : ''); ?></td>
                                    <td class="small text-break"><?php echo htmlspecialchars($a['Datos']); ?></td>
                                    <td class="text-muted small"><?php echo htmlspecialchars($a['Ip']); ?></td>
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
