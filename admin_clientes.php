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
    $id_cliente = isset($_POST['id_cliente']) ? (int)$_POST['id_cliente'] : null;
    $nombre     = trim($_POST['nombre'] ?? '');
    $telefono   = trim($_POST['telefono'] ?? '');
    $correo     = trim($_POST['correo'] ?? '');
    $usuario    = trim($_POST['usuario'] ?? '');
    $password   = $_POST['password'] ?? '';
    $direccion  = trim($_POST['direccion'] ?? '');
    $acepta     = isset($_POST['acepta_politica']) ? 1 : 0;
    $modo       = $_POST['modo'] ?? 'nuevo'; // 'nuevo' o 'editar'

    if ($nombre === '' || $correo === '' || $usuario === '') {
        $mensaje = 'Nombre, correo y usuario son obligatorios.';
        $accion = ($modo === 'editar') ? 'editar' : 'nuevo';
        $id_editar = ($modo === 'editar') ? $id_cliente : null;
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $mensaje = 'Correo inválido.';
        $accion = ($modo === 'editar') ? 'editar' : 'nuevo';
        $id_editar = ($modo === 'editar') ? $id_cliente : null;
    } else {
        try {
            if ($modo === 'nuevo') {
                if ($password === '') {
                    $mensaje = 'La contraseña es obligatoria al crear un cliente.';
                    $accion = 'nuevo';
                } elseif (!$acepta) {
                    $mensaje = 'Debes registrar la aceptación de la política de datos para crear un cliente.';
                    $accion = 'nuevo';
                } else {
                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                    $sql = "INSERT INTO Cliente
                            (Nombre, Telefono, Correo, Usuario, Password, Direccion, Acepta_politica)
                            VALUES
                            (:nombre, :telefono, :correo, :usuario, :password, :direccion, :acepta)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        ':nombre'   => $nombre,
                        ':telefono' => $telefono,
                        ':correo'   => $correo,
                        ':usuario'  => $usuario,
                        ':password' => $passwordHash,
                        ':direccion'=> $direccion,
                        ':acepta'   => $acepta
                    ]);

                    $mensaje = 'Cliente creado correctamente ✅';
                    $accion = 'listar';
                    registrar_auditoria($pdo, [
                        'usuario_id' => $_SESSION['admin_id'],
                        'rol' => 'ADMIN',
                        'accion' => 'CREAR',
                        'entidad' => 'Cliente',
                        'entidad_id' => $pdo->lastInsertId(),
                        'datos' => ['correo' => $correo]
                    ]);
                }
            } else {
                if (!$acepta) {
                    $mensaje = 'Debes registrar la aceptación de la política de datos para actualizar al cliente.';
                    $accion = 'editar';
                    $id_editar = $id_cliente;
                } else {

                    if ($password !== '') {
                        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                        $sql = "UPDATE Cliente
                                SET Nombre = :nombre,
                                    Telefono = :telefono,
                                    Correo = :correo,
                                    Usuario = :usuario,
                                    Password = :password,
                                    Direccion = :direccion,
                                    Acepta_politica = :acepta
                                WHERE Id_Cliente = :id";
                        $params = [
                            ':nombre'   => $nombre,
                            ':telefono' => $telefono,
                            ':correo'   => $correo,
                            ':usuario'  => $usuario,
                            ':password' => $passwordHash,
                            ':direccion'=> $direccion,
                            ':acepta'   => $acepta,
                            ':id'       => $id_cliente
                        ];
                    } else {
                        $sql = "UPDATE Cliente
                                SET Nombre = :nombre,
                                    Telefono = :telefono,
                                    Correo = :correo,
                                    Usuario = :usuario,
                                    Direccion = :direccion,
                                    Acepta_politica = :acepta
                                WHERE Id_Cliente = :id";
                        $params = [
                            ':nombre'   => $nombre,
                            ':telefono' => $telefono,
                            ':correo'   => $correo,
                            ':usuario'  => $usuario,
                            ':direccion'=> $direccion,
                            ':acepta'   => $acepta,
                            ':id'       => $id_cliente
                        ];
                    }

                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);

                    $mensaje = 'Cliente actualizado correctamente ✅';
                    $accion = 'listar';
                    registrar_auditoria($pdo, [
                        'usuario_id' => $_SESSION['admin_id'],
                        'rol' => 'ADMIN',
                        'accion' => 'MODIFICAR',
                        'entidad' => 'Cliente',
                        'entidad_id' => $id_cliente,
                        'datos' => ['correo' => $correo]
                    ]);
                }
            }

        } catch (PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) === 1062) {
                $mensaje = 'El correo o usuario ya está registrado para otro cliente.';
            } else {
                $mensaje = 'Error al guardar cliente: ' . htmlspecialchars($e->getMessage());
            }
            $accion = ($modo === 'editar') ? 'editar' : 'nuevo';
            $id_editar = ($modo === 'editar') ? $id_cliente : null;
        }
    }
}


if ($accion === 'eliminar' && $id_editar !== null) {
    try {
        $sql = "UPDATE Cliente
                SET Activo = 0,
                    Anonimizado = 1,
                    Anonimizado_en = NOW(),
                    Anonimizado_por = :admin,
                    Eliminado_en = NOW(),
                    Eliminado_motivo = 'Solicitud de eliminación',
                    Nombre = CONCAT('Anónimo ', Id_Cliente),
                    Telefono = NULL,
                    Correo = NULL,
                    Usuario = CONCAT('anon_', Id_Cliente),
                    Password = NULL,
                    Direccion = NULL
                WHERE Id_Cliente = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id' => $id_editar,
            ':admin' => $_SESSION['admin_id']
        ]);

        $mensaje = 'Cuenta de cliente anonimizada/desactivada correctamente ✅';
        $accion = 'listar';
        registrar_auditoria($pdo, [
            'usuario_id' => $_SESSION['admin_id'],
            'rol' => 'ADMIN',
            'accion' => 'ELIMINAR',
            'entidad' => 'Cliente',
            'entidad_id' => $id_editar,
            'datos' => ['motivo' => 'Solicitud de eliminación']
        ]);
    } catch (PDOException $e) {
        $mensaje = 'Error al anonimizar cliente (tiene ventas u otros datos asociados): ' . htmlspecialchars($e->getMessage());
        $accion = 'listar';
    }
}


$cliente_editar = null;
if ($accion === 'editar' && $id_editar !== null) {
    $sql = "SELECT * FROM Cliente WHERE Id_Cliente = :id AND Activo = 1 AND Anonimizado = 0 LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id_editar]);
    $cliente_editar = $stmt->fetch();

    if (!$cliente_editar) {
        $mensaje = 'Cliente no encontrado.';
        $accion = 'listar';
    }
}


$lista_clientes = [];
if ($accion === 'listar') {
    $sql = "SELECT Id_Cliente, Nombre, Telefono, Correo, Usuario, Direccion, Acepta_politica, Creado_en
            FROM Cliente
            WHERE Activo = 1 AND Anonimizado = 0
            ORDER BY Creado_en DESC";
    $stmt = $pdo->query($sql);
    $lista_clientes = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Administrar Clientes</title>
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
                <h1 class="fw-bold mb-1">Clientes</h1>
                <p class="text-muted mb-0">Consulta y gestiona a los usuarios registrados en la tienda.</p>
            </div>
            <div class="btn-group">
                <a href="admin_clientes.php" class="btn btn-outline-secondary">Listado</a>
                <a href="admin_clientes.php?accion=nuevo" class="btn btn-primary">Nuevo cliente</a>
            </div>
        </div>

        <?php if ($mensaje !== ''): ?>
            <div class="alert alert-info"><?php echo htmlspecialchars($mensaje); ?></div>
        <?php endif; ?>

        <?php if ($accion === 'listar'): ?>
            <?php if (count($lista_clientes) === 0): ?>
                <div class="alert alert-warning">No hay clientes registrados.</div>
            <?php else: ?>
                <div class="card shadow-sm">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Teléfono</th>
                                    <th>Correo</th>
                                    <th>Usuario</th>
                                    <th>Dirección</th>
                                    <th>Política</th>
                                    <th>Creado</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lista_clientes as $c): ?>
                                    <tr>
                                        <td>#<?php echo (int)$c['Id_Cliente']; ?></td>
                                        <td><?php echo htmlspecialchars($c['Nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($c['Telefono']); ?></td>
                                        <td><?php echo htmlspecialchars($c['Correo']); ?></td>
                                        <td><?php echo htmlspecialchars($c['Usuario']); ?></td>
                                        <td><?php echo htmlspecialchars($c['Direccion']); ?></td>
                                        <td>
                                            <span class="badge <?php echo $c['Acepta_politica'] ? 'bg-success' : 'bg-secondary'; ?>">
                                                <?php echo $c['Acepta_politica'] ? 'Sí' : 'No'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($c['Creado_en']); ?></td>
                                        <td class="text-end">
                                            <a href="admin_clientes.php?accion=editar&id=<?php echo (int)$c['Id_Cliente']; ?>" class="btn btn-sm btn-outline-primary icon-inline">
                                                <i class="bi bi-pencil-square" aria-hidden="true"></i>
                                                <span>Editar</span>
                                            </a>
                                            <a href="admin_clientes.php?accion=eliminar&id=<?php echo (int)$c['Id_Cliente']; ?>" class="btn btn-sm btn-outline-danger icon-inline" onclick="return confirm('¿Seguro que deseas eliminar este cliente?');">
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
            <?php $esEditar = ($accion === 'editar' && $cliente_editar); ?>
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><?php echo $esEditar ? 'Editar cliente' : 'Nuevo cliente'; ?></h5>
                </div>
                <div class="card-body">
                    <form method="post" action="admin_clientes.php" class="row g-3">
                        <input type="hidden" name="modo" value="<?php echo $esEditar ? 'editar' : 'nuevo'; ?>">
                        <?php if ($esEditar): ?>
                            <input type="hidden" name="id_cliente" value="<?php echo (int)$cliente_editar['Id_Cliente']; ?>">
                        <?php endif; ?>

                        <div class="col-md-6">
                            <label class="form-label">Nombre</label>
                            <input type="text" name="nombre" class="form-control" value="<?php echo $esEditar ? htmlspecialchars($cliente_editar['Nombre']) : ''; ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Teléfono</label>
                            <input type="text" name="telefono" class="form-control" value="<?php echo $esEditar ? htmlspecialchars($cliente_editar['Telefono']) : ''; ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Correo</label>
                            <input type="email" name="correo" class="form-control" value="<?php echo $esEditar ? htmlspecialchars($cliente_editar['Correo']) : ''; ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Usuario</label>
                            <input type="text" name="usuario" class="form-control" value="<?php echo $esEditar ? htmlspecialchars($cliente_editar['Usuario']) : ''; ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contraseña <?php echo $esEditar ? '(dejar en blanco para no cambiar)' : ''; ?></label>
                            <input type="password" name="password" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Dirección</label>
                            <input type="text" name="direccion" class="form-control" value="<?php echo $esEditar ? htmlspecialchars($cliente_editar['Direccion']) : ''; ?>">
                        </div>
                        <div class="col-12 form-check">
                            <input class="form-check-input" type="checkbox" id="acepta_politica" name="acepta_politica" <?php echo ($esEditar && $cliente_editar['Acepta_politica']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="acepta_politica">
                                Acepta la <a href="politica_datos.php" target="_blank" rel="noopener">política de tratamiento de datos</a>
                            </label>
                        </div>
                        <div class="col-12 d-flex gap-2">
                            <button type="submit" class="btn btn-primary icon-inline">
                                <i class="bi bi-check-circle" aria-hidden="true"></i>
                                <span><?php echo $esEditar ? 'Guardar cambios' : 'Crear cliente'; ?></span>
                            </button>
                            <a href="admin_clientes.php" class="btn btn-outline-secondary">Cancelar</a>
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
            const form = document.querySelector('form[action="admin_clientes.php"]');
            if (!form) return;
            const storageKey = 'admin_clientes_form_cache';
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
            } catch (e) {}


            form.addEventListener('input', () => {
                const payload = {};
                fields.forEach(el => payload[el.name] = el.value);
                payload.acepta_politica = form.querySelector('[name="acepta_politica"]').checked;
                localStorage.setItem(storageKey, JSON.stringify(payload));
            });


            form.addEventListener('submit', () => localStorage.removeItem(storageKey));
        })();
    </script>
    <?php endif; ?>
</body>
</html>
