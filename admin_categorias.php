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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_categoria = isset($_POST['id_categoria']) ? (int)$_POST['id_categoria'] : null;
    $nombre       = trim($_POST['nombre'] ?? '');
    $descripcion  = trim($_POST['descripcion'] ?? '');
    $activa       = isset($_POST['activa']) ? 1 : 0;
    $modo         = $_POST['modo'] ?? 'nuevo';
    $imagenActual = $_POST['imagen_actual'] ?? null;

    if ($nombre === '') {
        $mensaje = 'El nombre de la categoría es obligatorio.';
        $accion = $modo === 'editar' ? 'editar' : 'nuevo';
        $id_editar = $modo === 'editar' ? $id_categoria : null;
    } else {
        $nombre = substr($nombre, 0, 120);
        $descripcion = substr($descripcion, 0, 800);
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
                    $nuevoNombre = 'cat_' . bin2hex(random_bytes(6)) . '.' . $ext;
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
                        $mensaje = 'Error al subir la imagen de la categoría.';
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
            $accion = $modo === 'editar' ? 'editar' : 'nuevo';
            $id_editar = $modo === 'editar' ? $id_categoria : null;
        }

        if ($mensaje === '') {
            try {
                if ($modo === 'nuevo') {
                    $sql = "INSERT INTO Categorias (Nombre, Descripcion, Imagen, Activa)
                            VALUES (:nombre, :descripcion, :imagen, :activa)";
                } else {
                    $sql = "UPDATE Categorias
                            SET Nombre = :nombre,
                                Descripcion = :descripcion,
                                Imagen = :imagen,
                                Activa = :activa
                            WHERE Id_Categoria = :id";
                }

                $stmt = $pdo->prepare($sql);
                $params = [
                    ':nombre'      => $nombre,
                    ':descripcion' => $descripcion !== '' ? $descripcion : null,
                    ':imagen'      => $imagenNombre,
                    ':activa'      => $activa,
                ];

                if ($modo === 'editar') {
                    $params[':id'] = $id_categoria;
                }

                $stmt->execute($params);

                $mensaje = $modo === 'nuevo'
                    ? 'Categoría creada correctamente ✅'
                    : 'Categoría actualizada correctamente ✅';

                $accion = 'listar';
                registrar_auditoria($pdo, [
                    'usuario_id' => $_SESSION['admin_id'],
                    'rol' => 'ADMIN',
                    'accion' => $modo === 'nuevo' ? 'CREAR' : 'MODIFICAR',
                    'entidad' => 'Categoria',
                    'entidad_id' => $modo === 'nuevo' ? $pdo->lastInsertId() : $id_categoria,
                    'datos' => ['nombre' => $nombre, 'activa' => $activa]
                ]);
            } catch (PDOException $e) {
                $mensaje = 'Error al guardar la categoría: ' . htmlspecialchars($e->getMessage());
                $accion = $modo === 'editar' ? 'editar' : 'nuevo';
                $id_editar = $modo === 'editar' ? $id_categoria : null;
            }
        }
    }
}

if ($accion === 'toggle' && $id_editar !== null) {
    try {
        $stmtEstado = $pdo->prepare("SELECT Activa FROM Categorias WHERE Id_Categoria = :id LIMIT 1");
        $stmtEstado->execute([':id' => $id_editar]);
        $categoria = $stmtEstado->fetch();

        if ($categoria) {
            $nuevoEstado = $categoria['Activa'] ? 0 : 1;
            $stmt = $pdo->prepare("UPDATE Categorias SET Activa = :estado WHERE Id_Categoria = :id");
            $stmt->execute([':estado' => $nuevoEstado, ':id' => $id_editar]);
            $mensaje = $nuevoEstado ? 'Categoría activada.' : 'Categoría desactivada.';
            registrar_auditoria($pdo, [
                'usuario_id' => $_SESSION['admin_id'],
                'rol' => 'ADMIN',
                'accion' => 'MODIFICAR',
                'entidad' => 'Categoria',
                'entidad_id' => $id_editar,
                'datos' => ['activa' => $nuevoEstado]
            ]);
        } else {
            $mensaje = 'Categoría no encontrada.';
        }
    } catch (PDOException $e) {
        $mensaje = 'No se pudo cambiar el estado: ' . htmlspecialchars($e->getMessage());
    }
    $accion = 'listar';
}

if ($accion === 'eliminar' && $id_editar !== null) {
    try {
        $stmt = $pdo->prepare("UPDATE Categorias
                               SET Activa = 0,
                                   Eliminado_en = NOW(),
                                   Eliminado_por = :admin,
                                   Eliminado_motivo = 'Desactivada desde panel'
                               WHERE Id_Categoria = :id");
        $stmt->execute([':id' => $id_editar, ':admin' => $_SESSION['admin_id']]);
        $mensaje = 'Categoría desactivada correctamente ✅';
        registrar_auditoria($pdo, [
            'usuario_id' => $_SESSION['admin_id'],
            'rol' => 'ADMIN',
            'accion' => 'ELIMINAR',
            'entidad' => 'Categoria',
            'entidad_id' => $id_editar,
            'datos' => ['motivo' => 'Desactivada desde panel']
        ]);
    } catch (PDOException $e) {
        $mensaje = 'No se pudo desactivar la categoría. Verifica si hay productos asociados.';
    }
    $accion = 'listar';
}

$categoria_editar = null;
if ($accion === 'editar' && $id_editar !== null) {
    $stmt = $pdo->prepare("SELECT * FROM Categorias WHERE Id_Categoria = :id LIMIT 1");
    $stmt->execute([':id' => $id_editar]);
    $categoria_editar = $stmt->fetch();

    if (!$categoria_editar) {
        $mensaje = 'Categoría no encontrada.';
        $accion = 'listar';
    }
}

$lista_categorias = [];
if ($accion === 'listar') {
    $stmt = $pdo->query("SELECT * FROM Categorias ORDER BY Nombre");
    $lista_categorias = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Administrar Categorías</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/theme.css">
</head>
<body class="bg-body-tertiary">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="panel_admin.php">Administración</a>
            <span class="badge bg-secondary">Categorías</span>
            <div class="ms-auto d-flex gap-2">
                <a href="panel_admin.php" class="btn btn-outline-light btn-sm icon-inline"><i class="bi bi-speedometer" aria-hidden="true"></i><span>Panel</span></a>
                <a href="logout.php" class="btn btn-outline-light btn-sm icon-inline text-white"><i class="bi bi-door-open" aria-hidden="true"></i><span>Cerrar sesión</span></a>
            </div>
        </div>
    </nav>

    <main class="container py-5">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start mb-4 gap-3">
            <div>
                <h1 class="fw-bold mb-1">Categorías</h1>
                <p class="text-muted mb-0">Organiza el catálogo y controla las familias de productos.</p>
            </div>
            <div class="btn-group">
                <a href="admin_categorias.php" class="btn btn-outline-secondary icon-inline">
                    <i class="bi bi-card-list" aria-hidden="true"></i>
                    <span>Listado</span>
                </a>
                <a href="admin_categorias.php?accion=nuevo" class="btn btn-primary icon-inline">
                    <i class="bi bi-plus-circle" aria-hidden="true"></i>
                    <span>Nueva categoría</span>
                </a>
            </div>
        </div>

        <?php if ($mensaje !== ''): ?>
            <div class="alert alert-info"><?php echo htmlspecialchars($mensaje); ?></div>
        <?php endif; ?>

        <?php if ($accion === 'listar'): ?>
            <?php if (count($lista_categorias) === 0): ?>
                <div class="alert alert-warning">No hay categorías registradas.</div>
            <?php else: ?>
                <div class="card shadow-sm">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Descripción</th>
                                    <th>Imagen</th>
                                    <th>Estado</th>
                                    <th>Creado</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lista_categorias as $cat): ?>
                                    <tr>
                                        <td>#<?php echo (int)$cat['Id_Categoria']; ?></td>
                                        <td class="fw-semibold"><?php echo htmlspecialchars($cat['Nombre']); ?></td>
                                        <td class="text-muted small"><?php echo htmlspecialchars($cat['Descripcion']); ?></td>
                                        <td>
                                            <?php if (!empty($cat['Imagen'])): ?>
                                                <img src="uploads/<?php echo htmlspecialchars($cat['Imagen']); ?>" class="rounded" style="width:60px;height:60px;object-fit:cover;" alt="Imagen categoría">
                                            <?php else: ?>
                                                <span class="text-muted small">Sin imagen</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $cat['Activa'] ? 'bg-success' : 'bg-secondary'; ?>">
                                                <?php echo $cat['Activa'] ? 'Activa' : 'Inactiva'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($cat['Creado_en']); ?></td>
                                        <td class="text-end">
                                            <a href="admin_categorias.php?accion=editar&id=<?php echo (int)$cat['Id_Categoria']; ?>" class="btn btn-sm btn-outline-primary icon-inline">
                                                <i class="bi bi-pencil-square" aria-hidden="true"></i>
                                                <span>Editar</span>
                                            </a>
                                            <?php $iconToggle = $cat['Activa'] ? 'bi-eye-slash' : 'bi-eye'; ?>
                                            <a href="admin_categorias.php?accion=toggle&id=<?php echo (int)$cat['Id_Categoria']; ?>" class="btn btn-sm <?php echo $cat['Activa'] ? 'btn-outline-warning' : 'btn-outline-success'; ?> icon-inline">
                                                <i class="bi <?php echo $iconToggle; ?>" aria-hidden="true"></i>
                                                <span><?php echo $cat['Activa'] ? 'Desactivar' : 'Activar'; ?></span>
                                            </a>
                                            <a href="admin_categorias.php?accion=eliminar&id=<?php echo (int)$cat['Id_Categoria']; ?>" class="btn btn-sm btn-outline-danger icon-inline" onclick="return confirm('¿Eliminar esta categoría?');">
                                                <i class="bi bi-trash3" aria-hidden="true"></i>
                                                <span>Eliminar</span>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        <?php elseif ($accion === 'nuevo' || $accion === 'editar'): ?>
            <?php $esEditar = ($accion === 'editar' && $categoria_editar); ?>
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><?php echo $esEditar ? 'Editar categoría' : 'Nueva categoría'; ?></h5>
                </div>
                <div class="card-body">
                    <form method="post" action="admin_categorias.php" enctype="multipart/form-data" class="row g-3">
                        <input type="hidden" name="modo" value="<?php echo $esEditar ? 'editar' : 'nuevo'; ?>">
                        <?php if ($esEditar): ?>
                            <input type="hidden" name="id_categoria" value="<?php echo (int)$categoria_editar['Id_Categoria']; ?>">
                        <?php endif; ?>

                        <div class="col-md-6">
                            <label class="form-label">Nombre</label>
                            <input type="text" name="nombre" class="form-control" value="<?php echo $esEditar ? htmlspecialchars($categoria_editar['Nombre']) : ''; ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Estado</label>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" role="switch" name="activa" id="activaCategoria" <?php echo (!$esEditar || $categoria_editar['Activa']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="activaCategoria">Activa para el catálogo</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descripción</label>
                            <textarea name="descripcion" class="form-control" rows="3"><?php echo $esEditar ? htmlspecialchars($categoria_editar['Descripcion']) : ''; ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Imagen (opcional)</label>
                            <input type="file" name="imagen" accept="image/*" class="form-control">
                        </div>
                        <?php if ($esEditar && !empty($categoria_editar['Imagen'])): ?>
                            <div class="col-md-6">
                                <label class="form-label">Imagen actual</label>
                                <div class="d-flex align-items-center gap-3">
                                    <img src="uploads/<?php echo htmlspecialchars($categoria_editar['Imagen']); ?>" class="rounded" style="width:120px;height:120px;object-fit:cover;" alt="Imagen categoría">
                                    <input type="hidden" name="imagen_actual" value="<?php echo htmlspecialchars($categoria_editar['Imagen']); ?>">
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="col-12 d-flex gap-2">
                            <button type="submit" class="btn btn-primary"><?php echo $esEditar ? 'Guardar cambios' : 'Crear categoría'; ?></button>
                            <a href="admin_categorias.php" class="btn btn-outline-secondary">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <?php if ($accion === 'nuevo' || $accion === 'editar'): ?>
    <script>
        (function() {
            const form = document.querySelector('form[action="admin_categorias.php"]');
            if (!form) return;
            const storageKey = 'admin_categorias_form_cache';
            const fields = Array.from(form.elements).filter(el =>
                ['text', 'textarea', 'select-one'].includes(el.type) && el.name
            );

            try {
                const data = JSON.parse(localStorage.getItem(storageKey) || '{}');
                fields.forEach(el => {
                    if (data[el.name]) el.value = data[el.name];
                });
                if (data.activa) {
                    const activa = form.querySelector('[name="activa"]');
                    if (activa) activa.checked = true;
                }
            } catch (e) {}

            form.addEventListener('input', () => {
                const payload = {};
                fields.forEach(el => payload[el.name] = el.value);
                const activa = form.querySelector('[name="activa"]');
                if (activa) payload.activa = activa.checked;
                localStorage.setItem(storageKey, JSON.stringify(payload));
            });

            form.addEventListener('submit', () => localStorage.removeItem(storageKey));
        })();
    </script>
    <?php endif; ?>
</body>
</html>
