<?php
session_start();
require 'db.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login_admin.php");
    exit;
}

$mensaje = '';
$accion = $_GET['accion'] ?? 'listar';
$id_editar = isset($_GET['id']) ? (int)$_GET['id'] : null;

$productos = [];
try {
    $stmtProd = $pdo->query("SELECT p.Id_Producto, p.Nombre, c.Nombre AS CategoriaNombre
                             FROM Productos p
                             LEFT JOIN Categorias c ON p.Id_Categoria = c.Id_Categoria
                             WHERE p.Activo = 1
                             ORDER BY c.Nombre, p.Nombre");
    $productos = $stmtProd->fetchAll();
} catch (PDOException $e) {
    $mensaje = 'Error al cargar productos: ' . htmlspecialchars($e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_inventario = isset($_POST['id_inventario']) ? (int)$_POST['id_inventario'] : null;
    $id_producto   = isset($_POST['id_producto']) ? (int)$_POST['id_producto'] : null;
    $cantidad      = filter_var($_POST['cantidad'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 999999]]) ?: 0;
    $ubicacion     = substr(trim($_POST['ubicacion'] ?? ''), 0, 120);
    $modo          = $_POST['modo'] ?? 'nuevo';

    if ($id_producto <= 0) {
        $mensaje = 'Debes seleccionar un producto.';
        $accion = ($modo === 'editar') ? 'editar' : 'nuevo';
        $id_editar = ($modo === 'editar') ? $id_inventario : null;
    } else {
        try {
            if ($modo === 'nuevo') {
                $sql = "INSERT INTO Inventario
                        (Id_Producto, Cantidad, Ubicacion, Fecha_actualizacion)
                        VALUES
                        (:id_producto, :cantidad, :ubicacion, NOW())";
            } else {
                $sql = "UPDATE Inventario
                        SET Id_Producto = :id_producto,
                            Cantidad = :cantidad,
                            Ubicacion = :ubicacion,
                            Fecha_actualizacion = NOW()
                        WHERE Id_Inventario = :id_inventario";
            }

            $stmt = $pdo->prepare($sql);
            $params = [
                ':id_producto' => $id_producto,
                ':cantidad'    => $cantidad,
                ':ubicacion'   => $ubicacion,
            ];
            if ($modo === 'editar') {
                $params[':id_inventario'] = $id_inventario;
            }

            $stmt->execute($params);

            $mensaje = ($modo === 'nuevo')
                ? 'Registro de inventario creado correctamente ✅'
                : 'Registro de inventario actualizado correctamente ✅';

            $accion = 'listar';
            registrar_auditoria($pdo, [
                'usuario_id' => $_SESSION['admin_id'],
                'rol' => 'ADMIN',
                'accion' => $modo === 'nuevo' ? 'CREAR' : 'MODIFICAR',
                'entidad' => 'Inventario',
                'entidad_id' => $modo === 'nuevo' ? $pdo->lastInsertId() : $id_inventario,
                'datos' => ['producto' => $id_producto, 'cantidad' => $cantidad, 'ubicacion' => $ubicacion]
            ]);
        } catch (PDOException $e) {
            $mensaje = 'Error al guardar inventario: ' . htmlspecialchars($e->getMessage());
            $accion = ($modo === 'editar') ? 'editar' : 'nuevo';
            $id_editar = ($modo === 'editar') ? $id_inventario : null;
        }
    }
}

if ($accion === 'eliminar' && $id_editar !== null) {
    try {
        $sql = "DELETE FROM Inventario WHERE Id_Inventario = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id_editar]);

        $mensaje = 'Registro de inventario eliminado correctamente ✅';
        $accion = 'listar';
        registrar_auditoria($pdo, [
            'usuario_id' => $_SESSION['admin_id'],
            'rol' => 'ADMIN',
            'accion' => 'ELIMINAR',
            'entidad' => 'Inventario',
            'entidad_id' => $id_editar,
            'datos' => ['motivo' => 'Eliminado desde panel']
        ]);
    } catch (PDOException $e) {
        $mensaje = 'Error al eliminar inventario: ' . htmlspecialchars($e->getMessage());
        $accion = 'listar';
    }
}

$inv_editar = null;
if ($accion === 'editar' && $id_editar !== null) {
    $sql = "SELECT * FROM Inventario WHERE Id_Inventario = :id LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id_editar]);
    $inv_editar = $stmt->fetch();

    if (!$inv_editar) {
        $mensaje = 'Registro de inventario no encontrado.';
        $accion = 'listar';
    }
}

$lista_inv = [];
if ($accion === 'listar') {
    $sql = "SELECT i.Id_Inventario, i.Cantidad, i.Ubicacion, i.Fecha_actualizacion,
                   p.Nombre AS NombreProducto, c.Nombre AS CategoriaNombre
            FROM Inventario i
            JOIN Productos p ON i.Id_Producto = p.Id_Producto
            LEFT JOIN Categorias c ON p.Id_Categoria = c.Id_Categoria
            WHERE p.Activo = 1
            ORDER BY c.Nombre, p.Nombre";
    $stmt = $pdo->query($sql);
    $lista_inv = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Administrar Inventario</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/theme.css">
</head>
<body class="bg-body-tertiary">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="panel_admin.php">Administración</a>
            <span class="badge bg-secondary">Inventario</span>
            <div class="ms-auto d-flex gap-2">
                <a href="panel_admin.php" class="btn btn-outline-light btn-sm icon-inline"><i class="bi bi-speedometer" aria-hidden="true"></i><span>Panel</span></a>
                <a href="logout.php" class="btn btn-outline-light btn-sm icon-inline text-white"><i class="bi bi-door-open" aria-hidden="true"></i><span>Cerrar sesión</span></a>
            </div>
        </div>
    </nav>

    <main class="container py-5">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start mb-4 gap-3">
            <div>
                <h1 class="fw-bold mb-1">Inventario</h1>
                <p class="text-muted mb-0">Control de existencias y ubicaciones.</p>
            </div>
            <div class="btn-group">
                <a href="admin_inventario.php" class="btn btn-outline-secondary">Listado</a>
                <a href="admin_inventario.php?accion=nuevo" class="btn btn-primary">Nuevo registro</a>
            </div>
        </div>

        <?php if ($mensaje !== ''): ?>
            <div class="alert alert-info"><?php echo htmlspecialchars($mensaje); ?></div>
        <?php endif; ?>

        <?php if ($accion === 'listar'): ?>
            <?php if (count($lista_inv) === 0): ?>
                <div class="alert alert-warning">No hay registros de inventario.</div>
            <?php else: ?>
                <div class="card shadow-sm">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Producto</th>
                                    <th>Categoría</th>
                                    <th>Cantidad</th>
                                    <th>Ubicación</th>
                                    <th>Fecha actualización</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lista_inv as $i): ?>
                                    <tr>
                                        <td>#<?php echo (int)$i['Id_Inventario']; ?></td>
                                        <td><?php echo htmlspecialchars($i['NombreProducto']); ?></td>
                                        <td><?php echo htmlspecialchars($i['CategoriaNombre'] ?? 'Sin categoría'); ?></td>
                                        <td><?php echo (int)$i['Cantidad']; ?></td>
                                        <td><?php echo htmlspecialchars($i['Ubicacion']); ?></td>
                                        <td><?php echo htmlspecialchars($i['Fecha_actualizacion']); ?></td>
                                        <td class="text-end">
                                            <a href="admin_inventario.php?accion=editar&id=<?php echo (int)$i['Id_Inventario']; ?>" class="btn btn-sm btn-outline-primary">Editar</a>
                                            <a href="admin_inventario.php?accion=eliminar&id=<?php echo (int)$i['Id_Inventario']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Seguro que deseas eliminar este registro de inventario?');">Eliminar</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        <?php elseif ($accion === 'nuevo' || $accion === 'editar'): ?>
            <?php $esEditar = ($accion === 'editar' && $inv_editar); ?>
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><?php echo $esEditar ? 'Editar inventario' : 'Nuevo registro'; ?></h5>
                </div>
                <div class="card-body">
                    <form method="post" action="admin_inventario.php" class="row g-3">
                        <input type="hidden" name="modo" value="<?php echo $esEditar ? 'editar' : 'nuevo'; ?>">
                        <?php if ($esEditar): ?>
                            <input type="hidden" name="id_inventario" value="<?php echo (int)$inv_editar['Id_Inventario']; ?>">
                        <?php endif; ?>

                        <div class="col-md-6">
                            <label class="form-label">Producto</label>
                            <select name="id_producto" class="form-select" required>
                                <option value="">-- Selecciona un producto --</option>
                                <?php foreach ($productos as $p): ?>
                                        <option value="<?php echo (int)$p['Id_Producto']; ?>"
                                        <?php
                                        $selected = '';
                                        if ($esEditar && (int)$inv_editar['Id_Producto'] === (int)$p['Id_Producto']) {
                                            $selected = 'selected';
                                        }
                                        echo $selected;
                                        ?>>
                                        <?php
                                            $etiqueta = $p['Nombre'];
                                            if (!empty($p['CategoriaNombre'])) {
                                                $etiqueta .= ' · ' . $p['CategoriaNombre'];
                                            }
                                            echo htmlspecialchars($etiqueta);
                                        ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Cantidad</label>
                            <input type="number" name="cantidad" min="0" class="form-control" value="<?php echo $esEditar ? (int)$inv_editar['Cantidad'] : 0; ?>">
                        </div>

                        <div class="col-12">
                            <label class="form-label">Ubicación</label>
                            <input type="text" name="ubicacion" class="form-control" value="<?php echo $esEditar ? htmlspecialchars($inv_editar['Ubicacion']) : ''; ?>">
                        </div>

                        <div class="col-12 d-flex gap-2">
                            <button type="submit" class="btn btn-primary"><?php echo $esEditar ? 'Guardar cambios' : 'Crear registro'; ?></button>
                            <a href="admin_inventario.php" class="btn btn-outline-secondary">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
