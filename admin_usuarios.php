<?php
session_start();
require 'db.php';


if (!isset($_SESSION['admin_id'])) {
    header("Location: login_admin.php");
    exit;
}

$rol_actual = $_SESSION['rol'] ?? 'SECUNDARIO';
if ($rol_actual !== 'PRINCIPAL') {
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Acceso restringido</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/theme.css">
    </head>
    <body class="bg-body-tertiary d-flex align-items-center" style="min-height: 100vh;">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card shadow-sm border-0">
                        <div class="card-body text-center">
                            <span class="badge bg-danger text-uppercase mb-3">Permiso denegado</span>
                            <h1 class="h4 fw-bold mb-2">Acceso restringido</h1>
                            <p class="text-muted mb-4">Solo los administradores con rol <strong>PRINCIPAL</strong> pueden gestionar otros administradores.</p>
                            <a href="panel_admin.php" class="btn btn-primary icon-inline">
                                <i class="bi bi-arrow-left" aria-hidden="true"></i>
                                <span>Volver al panel</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$mensaje = '';
$accion = $_GET['accion'] ?? 'listar';
$id_editar = isset($_GET['id']) ? (int)$_GET['id'] : null;

$esPrincipalLogueado = ($rol_actual === 'PRINCIPAL');


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_admin      = isset($_POST['id_admin']) ? (int)$_POST['id_admin'] : null;
    $identificacion= trim($_POST['identificacion'] ?? '');
    $nombre        = trim($_POST['nombre'] ?? '');
    $telefono      = trim($_POST['telefono'] ?? '');
    $correo        = trim($_POST['correo'] ?? '');
    $usuario       = trim($_POST['usuario'] ?? '');
    $password      = $_POST['password'] ?? '';
    $rol           = trim($_POST['rol'] ?? 'SECUNDARIO');
    $acepta        = isset($_POST['acepta_politica']) ? 1 : 0;
    $modo          = $_POST['modo'] ?? 'nuevo';

    if ($nombre === '' || $correo === '' || $usuario === '') {
        $mensaje = 'Nombre, correo y usuario son obligatorios.';
        $accion = ($modo === 'editar') ? 'editar' : 'nuevo';
        $id_editar = ($modo === 'editar') ? $id_admin : null;
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $mensaje = 'Correo inválido.';
        $accion = ($modo === 'editar') ? 'editar' : 'nuevo';
        $id_editar = ($modo === 'editar') ? $id_admin : null;
    } else {
        try {
            if ($modo === 'nuevo') {
                $stmtDup = $pdo->prepare("SELECT COUNT(*) FROM Administrador WHERE Correo = :correo OR Usuario = :usuario");
                $stmtDup->execute([':correo' => $correo, ':usuario' => $usuario]);
                if ((int)$stmtDup->fetchColumn() > 0) {
                    $mensaje = 'El correo o usuario ya está registrado para otro administrador.';
                    $accion = 'nuevo';
                } elseif (!$esPrincipalLogueado && $rol === 'PRINCIPAL') {
                    $mensaje = 'Solo el administrador PRINCIPAL puede crear otro admin PRINCIPAL.';
                    $accion = 'nuevo';
                } else {
                    if ($password === '') {
                        $mensaje = 'La contraseña es obligatoria al crear un administrador.';
                        $accion = 'nuevo';
                    } elseif (!$acepta) {
                        $mensaje = 'Debes aceptar la política de datos para registrar un administrador.';
                        $accion = 'nuevo';
                    } else {
                        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                        $sql = "INSERT INTO Administrador
                                (Identificacion, Nombre, Telefono, Correo, Usuario, Password, Rol, Acepta_politica)
                                VALUES
                                (:identificacion, :nombre, :telefono, :correo, :usuario, :password, :rol, :acepta)";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([
                            ':identificacion' => $identificacion,
                            ':nombre'         => $nombre,
                            ':telefono'       => $telefono,
                            ':correo'         => $correo,
                            ':usuario'        => $usuario,
                            ':password'       => $passwordHash,
                            ':rol'            => $rol,
                            ':acepta'         => $acepta
                        ]);
                        $mensaje = 'Administrador creado correctamente ✅';
                        $accion = 'listar';
                        registrar_auditoria($pdo, [
                            'usuario_id' => $_SESSION['admin_id'],
                            'rol' => 'ADMIN',
                            'accion' => 'CREAR',
                            'entidad' => 'Administrador',
                            'entidad_id' => $pdo->lastInsertId(),
                            'datos' => ['correo' => $correo, 'rol' => $rol]
                        ]);
                    }
                }
            } else {


                $stmtCheck = $pdo->prepare("SELECT * FROM Administrador WHERE Id_Admin = :id LIMIT 1");
                $stmtCheck->execute([':id' => $id_admin]);
                $admin_target = $stmtCheck->fetch();

                if (!$admin_target) {
                    $mensaje = 'Administrador no encontrado.';
                    $accion = 'listar';
                } else {
                    $esPrincipalTarget = ($admin_target['Rol'] === 'PRINCIPAL');

                    $stmtDup = $pdo->prepare("SELECT COUNT(*) FROM Administrador WHERE (Correo = :correo OR Usuario = :usuario) AND Id_Admin <> :id");
                    $stmtDup->execute([
                        ':correo' => $correo,
                        ':usuario' => $usuario,
                        ':id' => $id_admin
                    ]);
                    if ((int)$stmtDup->fetchColumn() > 0) {
                        $mensaje = 'El correo o usuario ya está registrado para otro administrador.';
                        $accion = 'editar';
                        $id_editar = $id_admin;
                    } else {

                    if ($esPrincipalTarget && !$esPrincipalLogueado) {

                        $rol = 'PRINCIPAL';
                    }

                    if (!$acepta) {
                        $mensaje = 'Debes aceptar la política de datos para actualizar al administrador.';
                        $accion = 'editar';
                        $id_editar = $id_admin;
                    } else {
                    if ($password !== '') {
                        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                        $sql = "UPDATE Administrador
                                SET Identificacion = :identificacion,
                                    Nombre = :nombre,
                                    Telefono = :telefono,
                                    Correo = :correo,
                                    Usuario = :usuario,
                                    Password = :password,
                                    Rol = :rol,
                                    Acepta_politica = :acepta
                                WHERE Id_Admin = :id";
                        $params = [
                            ':identificacion' => $identificacion,
                            ':nombre'         => $nombre,
                            ':telefono'       => $telefono,
                            ':correo'         => $correo,
                            ':usuario'        => $usuario,
                            ':password'       => $passwordHash,
                            ':rol'            => $rol,
                            ':acepta'         => $acepta,
                            ':id'             => $id_admin
                        ];
                    } else {
                        $sql = "UPDATE Administrador
                                SET Identificacion = :identificacion,
                                    Nombre = :nombre,
                                    Telefono = :telefono,
                                    Correo = :correo,
                                    Usuario = :usuario,
                                    Rol = :rol,
                                    Acepta_politica = :acepta
                                WHERE Id_Admin = :id";
                        $params = [
                            ':identificacion' => $identificacion,
                            ':nombre'         => $nombre,
                            ':telefono'       => $telefono,
                            ':correo'         => $correo,
                            ':usuario'        => $usuario,
                            ':rol'            => $rol,
                            ':acepta'         => $acepta,
                            ':id'             => $id_admin
                        ];
                    }

                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);

                    $mensaje = 'Administrador actualizado correctamente ✅';
                    $accion = 'listar';
                    registrar_auditoria($pdo, [
                        'usuario_id' => $_SESSION['admin_id'],
                        'rol' => 'ADMIN',
                        'accion' => 'MODIFICAR',
                        'entidad' => 'Administrador',
                        'entidad_id' => $id_admin,
                        'datos' => ['correo' => $correo, 'rol' => $rol]
                    ]);
                    }
                    }
                }
            }

        } catch (PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) === 1062) {
                $mensaje = 'El correo o usuario ya está registrado para otro administrador.';
            } else {
                $mensaje = 'Error al guardar administrador: ' . htmlspecialchars($e->getMessage());
            }
            $accion = ($modo === 'editar') ? 'editar' : 'nuevo';
            $id_editar = ($modo === 'editar') ? $id_admin : null;
        }
    }
}


if ($accion === 'eliminar' && $id_editar !== null) {
    try {

        $stmtCheck = $pdo->prepare("SELECT Rol FROM Administrador WHERE Id_Admin = :id LIMIT 1");
        $stmtCheck->execute([':id' => $id_editar]);
        $row = $stmtCheck->fetch();

        if (!$row) {
            $mensaje = 'Administrador no encontrado.';
        } elseif ($row['Rol'] === 'PRINCIPAL') {
            $mensaje = 'No se puede eliminar un administrador con rol PRINCIPAL.';
        } else {
            $sql = "UPDATE Administrador
                    SET Activo = 0,
                        Eliminado_en = NOW(),
                        Eliminado_por = :admin,
                        Eliminado_motivo = 'Desactivado desde panel'
                    WHERE Id_Admin = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $id_editar, ':admin' => $_SESSION['admin_id']]);
            $mensaje = 'Administrador desactivado correctamente ✅';
            registrar_auditoria($pdo, [
                'usuario_id' => $_SESSION['admin_id'],
                'rol' => 'ADMIN',
                'accion' => 'ELIMINAR',
                'entidad' => 'Administrador',
                'entidad_id' => $id_editar,
                'datos' => ['motivo' => 'Desactivado desde panel']
            ]);
        }

        $accion = 'listar';
    } catch (PDOException $e) {
        $mensaje = 'Error al desactivar administrador: ' . htmlspecialchars($e->getMessage());
        $accion = 'listar';
    }
}


$admin_editar = null;
if ($accion === 'editar' && $id_editar !== null) {
    $sql = "SELECT * FROM Administrador WHERE Id_Admin = :id AND Activo = 1 LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id_editar]);
    $admin_editar = $stmt->fetch();

    if (!$admin_editar) {
        $mensaje = 'Administrador no encontrado.';
        $accion = 'listar';
    }
}


$lista_admins = [];
if ($accion === 'listar') {
    $sql = "SELECT Id_Admin, Identificacion, Nombre, Telefono, Correo, Usuario, Rol, Acepta_politica, Creado_en
            FROM Administrador
            WHERE Activo = 1
            ORDER BY Rol DESC, Nombre";
    $stmt = $pdo->query($sql);
    $lista_admins = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Administrar Administradores</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/theme.css">
</head>
<body class="bg-body-tertiary">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="panel_admin.php">Administración</a>
            <div class="ms-auto d-flex gap-2">
                <a href="panel_admin.php" class="btn btn-outline-light btn-sm icon-inline"><i class="bi bi-speedometer" aria-hidden="true"></i><span>Panel</span></a>
                <a href="logout.php" class="btn btn-outline-light btn-sm icon-inline text-white"><i class="bi bi-door-open" aria-hidden="true"></i><span>Cerrar sesión</span></a>
            </div>
        </div>
    </nav>

    <main class="container py-5">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start mb-4 gap-3">
            <div>
                <h1 class="fw-bold mb-1">Administradores</h1>
                <p class="text-muted mb-0">Controla a los usuarios internos y sus roles.</p>
            </div>
            <div class="btn-group">
                <a href="admin_usuarios.php" class="btn btn-outline-secondary icon-inline">
                    <i class="bi bi-card-list" aria-hidden="true"></i>
                    <span>Listado</span>
                </a>
                <a href="admin_usuarios.php?accion=nuevo" class="btn btn-primary icon-inline">
                    <i class="bi bi-plus-circle" aria-hidden="true"></i>
                    <span>Nuevo administrador</span>
                </a>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2 mb-4">
            <a href="panel_admin.php" class="btn btn-outline-secondary btn-sm icon-inline">
                <i class="bi bi-speedometer" aria-hidden="true"></i><span>Panel</span>
            </a>
            <a href="admin_productos.php" class="btn btn-outline-secondary btn-sm icon-inline">
                <i class="bi bi-box-seam" aria-hidden="true"></i><span>Productos</span>
            </a>
            <a href="ventas_admin.php" class="btn btn-outline-secondary btn-sm icon-inline">
                <i class="bi bi-receipt" aria-hidden="true"></i><span>Ventas</span>
            </a>
            <a href="devoluciones_admin.php" class="btn btn-outline-secondary btn-sm icon-inline">
                <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i><span>Devoluciones</span>
            </a>
            <a href="admin_clientes.php" class="btn btn-outline-secondary btn-sm icon-inline">
                <i class="bi bi-people" aria-hidden="true"></i><span>Clientes</span>
            </a>
        </div>

        <?php if ($mensaje !== ''): ?>
            <div class="alert alert-info"><?php echo htmlspecialchars($mensaje); ?></div>
        <?php endif; ?>

        <?php if ($accion === 'listar'): ?>
            <?php if (count($lista_admins) === 0): ?>
                <div class="alert alert-warning">No hay administradores registrados.</div>
            <?php else: ?>
                <div class="card shadow-sm">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Identificación</th>
                                    <th>Nombre</th>
                                    <th>Teléfono</th>
                                    <th>Correo</th>
                                    <th>Usuario</th>
                                    <th>Rol</th>
                                    <th>Política</th>
                                    <th>Creado</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lista_admins as $a): ?>
                                    <tr>
                                        <td>#<?php echo (int)$a['Id_Admin']; ?></td>
                                        <td><?php echo htmlspecialchars($a['Identificacion']); ?></td>
                                        <td><?php echo htmlspecialchars($a['Nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($a['Telefono']); ?></td>
                                        <td><?php echo htmlspecialchars($a['Correo']); ?></td>
                                        <td><?php echo htmlspecialchars($a['Usuario']); ?></td>
                                        <td>
                                            <span class="badge <?php echo $a['Rol'] === 'PRINCIPAL' ? 'bg-danger' : 'bg-secondary'; ?>">
                                                <?php echo htmlspecialchars($a['Rol']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $a['Acepta_politica'] ? 'bg-success' : 'bg-secondary'; ?>">
                                                <?php echo $a['Acepta_politica'] ? 'Sí' : 'No'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($a['Creado_en']); ?></td>
                                        <td class="text-end">
                                            <a href="admin_usuarios.php?accion=editar&id=<?php echo (int)$a['Id_Admin']; ?>" class="btn btn-sm btn-outline-primary icon-inline">
                                                <i class="bi bi-pencil-square" aria-hidden="true"></i>
                                                <span>Editar</span>
                                            </a>
                                            <?php if ($a['Rol'] !== 'PRINCIPAL'): ?>
                                                <a href="admin_usuarios.php?accion=eliminar&id=<?php echo (int)$a['Id_Admin']; ?>" class="btn btn-sm btn-outline-danger icon-inline" onclick="return confirm('¿Seguro que deseas eliminar este administrador?');">
                                                    <i class="bi bi-trash3" aria-hidden="true"></i>
                                                    <span>Eliminar</span>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted small">No eliminable</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

        <?php elseif ($accion === 'nuevo' || $accion === 'editar'): ?>
            <?php $esEditar = ($accion === 'editar' && $admin_editar); ?>
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><?php echo $esEditar ? 'Editar administrador' : 'Nuevo administrador'; ?></h5>
                </div>
                <div class="card-body">
                    <form method="post" action="admin_usuarios.php" class="row g-3">
                        <input type="hidden" name="modo" value="<?php echo $esEditar ? 'editar' : 'nuevo'; ?>">
                        <?php if ($esEditar): ?>
                            <input type="hidden" name="id_admin" value="<?php echo (int)$admin_editar['Id_Admin']; ?>">
                        <?php endif; ?>

                        <div class="col-md-6">
                            <label class="form-label">Identificación</label>
                            <input type="text" name="identificacion" class="form-control" value="<?php echo $esEditar ? htmlspecialchars($admin_editar['Identificacion']) : ''; ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nombre</label>
                            <input type="text" name="nombre" class="form-control" value="<?php echo $esEditar ? htmlspecialchars($admin_editar['Nombre']) : ''; ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Teléfono</label>
                            <input type="text" name="telefono" class="form-control" value="<?php echo $esEditar ? htmlspecialchars($admin_editar['Telefono']) : ''; ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Correo</label>
                            <input type="email" name="correo" class="form-control" value="<?php echo $esEditar ? htmlspecialchars($admin_editar['Correo']) : ''; ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Usuario</label>
                            <input type="text" name="usuario" class="form-control" value="<?php echo $esEditar ? htmlspecialchars($admin_editar['Usuario']) : ''; ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contraseña <?php echo $esEditar ? '(opcional)' : ''; ?></label>
                            <input type="password" name="password" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Rol</label>
                            <?php $rolSelec = $esEditar ? $admin_editar['Rol'] : 'SECUNDARIO'; ?>
                            <select name="rol" class="form-select">
                                <option value="PRINCIPAL" <?php echo ($rolSelec === 'PRINCIPAL') ? 'selected' : ''; ?>>PRINCIPAL</option>
                                <option value="SECUNDARIO" <?php echo ($rolSelec === 'SECUNDARIO') ? 'selected' : ''; ?>>SECUNDARIO</option>
                            </select>
                        </div>
                        <div class="col-12 form-check">
                            <input class="form-check-input" type="checkbox" id="acepta_politica_admin" name="acepta_politica" <?php echo ($esEditar && $admin_editar['Acepta_politica']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="acepta_politica_admin">
                                Acepta la <a href="politica_datos.php" target="_blank" rel="noopener">política de tratamiento de datos</a>
                            </label>
                        </div>
                        <div class="col-12 d-flex gap-2">
                            <button type="submit" class="btn btn-primary icon-inline">
                                <i class="bi bi-check-circle" aria-hidden="true"></i>
                                <span><?php echo $esEditar ? 'Guardar cambios' : 'Crear administrador'; ?></span>
                            </button>
                            <a href="admin_usuarios.php" class="btn btn-outline-secondary icon-inline">
                                <i class="bi bi-x-circle" aria-hidden="true"></i>
                                <span>Cancelar</span>
                            </a>
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
            const form = document.querySelector('form[action="admin_usuarios.php"]');
            if (!form) return;
            const storageKey = 'admin_usuarios_form_cache';
            const fields = Array.from(form.elements).filter(el =>
                ['text', 'email', 'tel', 'textarea', 'select-one'].includes(el.type) && el.name
            );


            try {
                const data = JSON.parse(localStorage.getItem(storageKey) || '{}');
                fields.forEach(el => {
                    if (data[el.name]) el.value = data[el.name];
                });
                if (data.acepta_politica) {
                    form.querySelector('[name="acepta_politica"]').checked = true;
                }
                if (data.rol) {
                    const rolSelect = form.querySelector('[name="rol"]');
                    if (rolSelect) rolSelect.value = data.rol;
                }
            } catch (e) {}


            form.addEventListener('input', () => {
                const payload = {};
                fields.forEach(el => payload[el.name] = el.value);
                payload.acepta_politica = form.querySelector('[name="acepta_politica"]').checked;
                payload.rol = form.querySelector('[name="rol"]').value;
                localStorage.setItem(storageKey, JSON.stringify(payload));
            });

            form.addEventListener('submit', () => localStorage.removeItem(storageKey));
        })();
    </script>
    <?php endif; ?>
</body>
</html>
