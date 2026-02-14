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

$proveedores = [];
try {
    $stmtProv = $pdo->query("SELECT Nit, Nombre_empresa FROM Proveedores WHERE Activo = 1 ORDER BY Nombre_empresa");
    $proveedores = $stmtProv->fetchAll();
} catch (PDOException $e) {
    $mensaje = 'Error al cargar proveedores: ' . htmlspecialchars($e->getMessage());
}

$categorias = [];
try {
    $stmtCat = $pdo->query("SELECT Id_Categoria, Nombre, Activa FROM Categorias ORDER BY Nombre");
    $categorias = $stmtCat->fetchAll();
} catch (PDOException $e) {
    $mensaje .= ($mensaje !== '' ? ' ' : '') . 'Error al cargar categorías: ' . htmlspecialchars($e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_producto   = isset($_POST['id_producto']) ? (int)$_POST['id_producto'] : null;
    $nit           = trim($_POST['nit'] ?? '');
    $nombre        = trim($_POST['nombre'] ?? '');
    $id_categoria  = isset($_POST['id_categoria']) ? (int)$_POST['id_categoria'] : 0;
    $precio        = trim($_POST['precio'] ?? '');
    $descripcion   = trim($_POST['descripcion'] ?? '');
    $fecha_ingreso = trim($_POST['fecha_ingreso'] ?? '');
    $fecha_retiro  = trim($_POST['fecha_retiro'] ?? '');
    $modo          = $_POST['modo'] ?? 'nuevo';
    $imagenActual  = $_POST['imagen_actual'] ?? null;

    if ($nombre === '' || $precio === '') {
        $mensaje = 'Nombre y precio son obligatorios.';
        $accion = ($modo === 'editar') ? 'editar' : 'nuevo';
        $id_editar = ($modo === 'editar') ? $id_producto : null;
    } elseif ($nit === '') {
        $mensaje = 'Debes seleccionar un proveedor para el producto.';
        $accion = ($modo === 'editar') ? 'editar' : 'nuevo';
        $id_editar = ($modo === 'editar') ? $id_producto : null;
    } elseif ($id_categoria <= 0) {
        $mensaje = 'Debes seleccionar una categoría válida.';
        $accion = ($modo === 'editar') ? 'editar' : 'nuevo';
        $id_editar = ($modo === 'editar') ? $id_producto : null;
    } else {

        $nombre = substr($nombre, 0, 120);
        $descripcion = substr($descripcion, 0, 800);
        $precio = filter_var($precio, FILTER_VALIDATE_FLOAT);
        if ($precio === false || $precio <= 0) {
            $mensaje = 'Precio inválido.';
            $accion = ($modo === 'editar') ? 'editar' : 'nuevo';
            $id_editar = ($modo === 'editar') ? $id_producto : null;
        }
        $imagenNombre = $imagenActual ?: null;

        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/uploads/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $tmpName = $_FILES['imagen']['tmp_name'];
                $originalName = $_FILES['imagen']['name'];
                $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                $permitidas = ['jpg', 'jpeg', 'png', 'webp'];
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $tmpName);
                finfo_close($finfo);
                $mimePermitidos = ['image/jpeg', 'image/png', 'image/webp'];

                if (in_array($ext, $permitidas, true) && in_array($mime, $mimePermitidos, true) && $_FILES['imagen']['size'] <= 3 * 1024 * 1024) {
                    $nuevoNombre = bin2hex(random_bytes(8)) . '.' . $ext;
                    $destino = $uploadDir . $nuevoNombre;

                    if (move_uploaded_file($tmpName, $destino)) {
                        if (!empty($imagenActual)) {
                            $anterior = $uploadDir . $imagenActual;
                            if (is_file($anterior)) {
                                @unlink($anterior);
                            }
                        }
                        $imagenNombre = $nuevoNombre;
                    } else {
                        $mensaje = 'Error al guardar la imagen en el servidor.';
                    }
                } else {
                    $mensaje = 'Formato de imagen no permitido o archivo muy grande (máx 3MB). Usa JPG, PNG o WEBP.';
                }
            } else {
                $erroresUpload = [
                    UPLOAD_ERR_INI_SIZE => 'La imagen excede el tamaño máximo permitido por el servidor.',
                    UPLOAD_ERR_FORM_SIZE => 'La imagen excede el límite configurado para el formulario.',
                    UPLOAD_ERR_PARTIAL => 'La imagen se cargó de forma incompleta, inténtalo de nuevo.',
                    UPLOAD_ERR_NO_TMP_DIR => 'Falta el directorio temporal en el servidor.',
                    UPLOAD_ERR_CANT_WRITE => 'No se pudo guardar la imagen en el disco.',
                    UPLOAD_ERR_EXTENSION => 'Una extensión de PHP detuvo la carga de la imagen.'
                ];
                $mensaje = $erroresUpload[$_FILES['imagen']['error']] ?? 'No se pudo subir la imagen. Inténtalo de nuevo.';
            }
        }

        if ($mensaje !== '') {
            $accion = ($modo === 'editar') ? 'editar' : 'nuevo';
            $id_editar = ($modo === 'editar') ? $id_producto : null;
        }

        if ($mensaje === '') {
            try {
                if ($modo === 'nuevo') {
                    $sql = "INSERT INTO Productos
                            (Nit, Nombre, Id_Categoria, Precio, Descripcion, Imagen, Fecha_ingreso, Fecha_retiro)
                        VALUES
                        (:nit, :nombre, :id_categoria, :precio, :descripcion, :imagen, :fecha_ingreso, :fecha_retiro)";
            } else {
                $sql = "UPDATE Productos
                        SET Nit = :nit,
                            Nombre = :nombre,
                            Id_Categoria = :id_categoria,
                            Precio = :precio,
                            Descripcion = :descripcion,
                            Imagen = :imagen,
                            Fecha_ingreso = :fecha_ingreso,
                            Fecha_retiro = :fecha_retiro
                        WHERE Id_Producto = :id_producto";
            }

            $stmt = $pdo->prepare($sql);
            $params = [
                ':nit'           => $nit,
                ':nombre'        => $nombre,
                ':id_categoria'  => $id_categoria,
                ':precio'        => $precio,
                ':descripcion'   => $descripcion,
                ':imagen'        => $imagenNombre,
                ':fecha_ingreso' => $fecha_ingreso !== '' ? $fecha_ingreso : null,
                ':fecha_retiro'  => $fecha_retiro !== '' ? $fecha_retiro : null,
            ];

            if ($modo === 'editar') {
                $params[':id_producto'] = $id_producto;
            }

            $stmt->execute($params);

            $mensaje = ($modo === 'nuevo')
                ? 'Producto creado correctamente ✅'
                : 'Producto actualizado correctamente ✅';

            $accion = 'listar';
                registrar_auditoria($pdo, [
                    'usuario_id' => $_SESSION['admin_id'],
                    'rol' => 'ADMIN',
                    'accion' => $modo === 'nuevo' ? 'CREAR' : 'MODIFICAR',
                    'entidad' => 'Producto',
                    'entidad_id' => $modo === 'nuevo' ? $pdo->lastInsertId() : $id_producto,
                    'datos' => ['nombre' => $nombre, 'precio' => $precio]
                ]);
            } catch (PDOException $e) {
                $mensaje = 'Error al guardar producto: ' . htmlspecialchars($e->getMessage());
                $accion = ($modo === 'editar') ? 'editar' : 'nuevo';
                $id_editar = ($modo === 'editar') ? $id_producto : null;
            }
        }
    }
}

if ($accion === 'eliminar' && $id_editar !== null) {
    try {
        $sql = "UPDATE Productos
                SET Activo = 0,
                    Eliminado_en = NOW(),
                    Eliminado_por = :admin,
                    Eliminado_motivo = 'Desactivado desde panel'
                WHERE Id_Producto = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id' => $id_editar,
            ':admin' => $_SESSION['admin_id']
        ]);

        $mensaje = 'Producto desactivado correctamente ✅';
        $accion = 'listar';
        registrar_auditoria($pdo, [
            'usuario_id' => $_SESSION['admin_id'],
            'rol' => 'ADMIN',
            'accion' => 'ELIMINAR',
            'entidad' => 'Producto',
            'entidad_id' => $id_editar,
            'datos' => ['motivo' => 'Desactivado desde panel']
        ]);
    } catch (PDOException $e) {
        $mensaje = 'Error al desactivar producto (verifica dependencias como inventario o ventas): ' . htmlspecialchars($e->getMessage());
        $accion = 'listar';
    }
}

$producto_editar = null;
if ($accion === 'editar' && $id_editar !== null) {
    $sql = "SELECT * FROM Productos WHERE Id_Producto = :id AND Activo = 1 LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id_editar]);
    $producto_editar = $stmt->fetch();

    if (!$producto_editar) {
        $mensaje = 'Producto no encontrado.';
        $accion = 'listar';
    }
}

$lista_productos = [];
if ($accion === 'listar') {
    $sql = "SELECT p.*, pr.Nombre_empresa, c.Nombre AS NombreCategoria
            FROM Productos p
            LEFT JOIN Proveedores pr ON p.Nit = pr.Nit
            LEFT JOIN Categorias c ON p.Id_Categoria = c.Id_Categoria
            WHERE p.Activo = 1
            ORDER BY c.Nombre IS NULL, c.Nombre, p.Nombre";
    $stmt = $pdo->query($sql);
    $lista_productos = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Administrar Productos</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/theme.css">
</head>
<body class="bg-body-tertiary">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="panel_admin.php">Administración</a>
            <span class="badge bg-secondary">Productos</span>
            <div class="ms-auto d-flex gap-2">
                <a href="panel_admin.php" class="btn btn-outline-light btn-sm icon-inline"><i class="bi bi-speedometer" aria-hidden="true"></i><span>Panel</span></a>
                <a href="logout.php" class="btn btn-outline-light btn-sm icon-inline text-white"><i class="bi bi-door-open" aria-hidden="true"></i><span>Cerrar sesión</span></a>
            </div>
        </div>
    </nav>

    <main class="container py-5">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start mb-4 gap-3">
            <div>
                <h1 class="fw-bold mb-1">Productos</h1>
                <p class="text-muted mb-0">Gestiona el catálogo disponible para los clientes.</p>
            </div>
            <div class="btn-group">
                <a href="admin_productos.php" class="btn btn-outline-secondary icon-inline">
                    <i class="bi bi-card-list" aria-hidden="true"></i>
                    <span>Listado</span>
                </a>
                <a href="admin_productos.php?accion=nuevo" class="btn btn-primary icon-inline">
                    <i class="bi bi-plus-circle" aria-hidden="true"></i>
                    <span>Nuevo producto</span>
                </a>
            </div>
        </div>

        <?php if ($mensaje !== ''): ?>
            <div class="alert alert-info"><?php echo htmlspecialchars($mensaje); ?></div>
        <?php endif; ?>

        <?php if ($accion === 'listar'): ?>
            <?php if (count($lista_productos) === 0): ?>
                <div class="alert alert-warning">No hay productos registrados.</div>
            <?php else: ?>
                <div class="card shadow-sm">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Categoría</th>
                                    <th>Proveedor</th>
                                    <th>Precio</th>
                                    <th>Descripción</th>
                                    <th>Imagen</th>
                                    <th>Fechas</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lista_productos as $p): ?>
                                    <tr>
                                        <td>#<?php echo (int)$p['Id_Producto']; ?></td>
                                        <td><?php echo htmlspecialchars($p['Nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($p['NombreCategoria'] ?? 'Sin categoría'); ?></td>
                                        <td><?php echo htmlspecialchars($p['Nombre_empresa'] ?? 'Sin proveedor'); ?></td>
                                        <td>$<?php echo number_format($p['Precio'], 2); ?></td>
                                        <td class="small text-muted"><?php echo nl2br(htmlspecialchars($p['Descripcion'])); ?></td>
                                        <td>
                                            <?php if (!empty($p['Imagen'])): ?>
                                                <img src="uploads/<?php echo htmlspecialchars($p['Imagen']); ?>" class="rounded" style="width:70px;height:70px;object-fit:cover;" alt="Imagen de producto">
                                            <?php else: ?>
                                                <span class="text-muted small">Sin imagen</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small">
                                            <div><strong>Ingreso:</strong> <?php echo htmlspecialchars($p['Fecha_ingreso']); ?></div>
                                            <div><strong>Retiro:</strong> <?php echo htmlspecialchars($p['Fecha_retiro']); ?></div>
                                        </td>
                                        <td class="text-end">
                                            <a class="btn btn-sm btn-outline-primary icon-inline" href="admin_productos.php?accion=editar&id=<?php echo (int)$p['Id_Producto']; ?>">
                                                <i class="bi bi-pencil-square" aria-hidden="true"></i>
                                                <span>Editar</span>
                                            </a>
                                            <a class="btn btn-sm btn-outline-danger" href="admin_productos.php?accion=eliminar&id=<?php echo (int)$p['Id_Producto']; ?>" onclick="return confirm('¿Seguro que deseas eliminar este producto?');">Eliminar</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        <?php elseif ($accion === 'nuevo' || $accion === 'editar'): ?>
            <?php
                $esEditar = ($accion === 'editar' && $producto_editar);
                $tituloForm = $esEditar ? 'Editar producto' : 'Nuevo producto';
            ?>
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><?php echo $tituloForm; ?></h5>
                </div>
                <div class="card-body">
                    <form method="post" action="admin_productos.php" enctype="multipart/form-data" class="row g-3">
                        <input type="hidden" name="modo" value="<?php echo $esEditar ? 'editar' : 'nuevo'; ?>">
                        <?php if ($esEditar): ?>
                            <input type="hidden" name="id_producto" value="<?php echo (int)$producto_editar['Id_Producto']; ?>">
                        <?php endif; ?>

                        <div class="col-md-6">
                            <label class="form-label">Proveedor</label>
                            <select name="nit" class="form-select" required>
                                <option value="" disabled <?php echo $esEditar ? '' : 'selected'; ?>>Selecciona un proveedor</option>
                                <?php foreach ($proveedores as $prov): ?>
                                    <option value="<?php echo htmlspecialchars($prov['Nit']); ?>"
                                        <?php
                                        $selected = '';
                                        if ($esEditar && $producto_editar['Nit'] === $prov['Nit']) {
                                            $selected = 'selected';
                                        }
                                        echo $selected;
                                        ?>>
                                        <?php echo htmlspecialchars($prov['Nombre_empresa']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Nombre</label>
                            <input type="text" name="nombre" class="form-control" value="<?php echo $esEditar ? htmlspecialchars($producto_editar['Nombre']) : ''; ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Categoría</label>
                            <select name="id_categoria" class="form-select" required>
                                <option value="" disabled <?php echo ($esEditar && !empty($producto_editar['Id_Categoria'])) ? '' : 'selected'; ?>>Selecciona una categoría</option>
                                <?php foreach ($categorias as $cat): ?>
                                    <?php
                                        $selected = ($esEditar && (int)$producto_editar['Id_Categoria'] === (int)$cat['Id_Categoria']) ? 'selected' : '';
                                        $disabled = (!$cat['Activa'] && !$selected) ? 'disabled' : '';
                                        $label = $cat['Activa'] ? $cat['Nombre'] : $cat['Nombre'] . ' (inactiva)';
                                    ?>
                                    <option value="<?php echo (int)$cat['Id_Categoria']; ?>" <?php echo $selected . ' ' . $disabled; ?>>
                                        <?php echo htmlspecialchars($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (count($categorias) === 0): ?>
                                <div class="form-text text-danger">Crea una categoría antes de registrar productos.</div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Precio</label>
                            <input type="number" step="0.01" name="precio" class="form-control" value="<?php echo $esEditar ? htmlspecialchars($producto_editar['Precio']) : ''; ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Fecha ingreso</label>
                            <input type="date" name="fecha_ingreso" class="form-control" value="<?php echo $esEditar ? htmlspecialchars($producto_editar['Fecha_ingreso']) : ''; ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Fecha retiro</label>
                            <input type="date" name="fecha_retiro" class="form-control" value="<?php echo $esEditar ? htmlspecialchars($producto_editar['Fecha_retiro']) : ''; ?>">
                        </div>

                        <div class="col-12">
                            <label class="form-label">Descripción</label>
                            <textarea name="descripcion" class="form-control" rows="4"><?php echo $esEditar ? htmlspecialchars($producto_editar['Descripcion']) : ''; ?></textarea>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Imagen (opcional)</label>
                            <input type="file" name="imagen" accept="image/*" class="form-control">
                        </div>

                        <?php if ($esEditar && !empty($producto_editar['Imagen'])): ?>
                            <div class="col-md-6">
                                <p class="form-label">Imagen actual</p>
                                <img src="uploads/<?php echo htmlspecialchars($producto_editar['Imagen']); ?>" class="rounded mb-2" style="width:120px;height:120px;object-fit:cover;" alt="Imagen actual">
                                <input type="hidden" name="imagen_actual" value="<?php echo htmlspecialchars($producto_editar['Imagen']); ?>">
                            </div>
                        <?php endif; ?>

                        <div class="col-12 d-flex gap-2">
                            <button type="submit" class="btn btn-primary icon-inline">
                                <i class="bi bi-check-circle" aria-hidden="true"></i>
                                <span><?php echo $esEditar ? 'Actualizar' : 'Crear'; ?></span>
                            </button>
                            <a href="admin_productos.php" class="btn btn-outline-secondary">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
