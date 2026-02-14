<?php
session_start();
require 'db.php';

if (!isset($_SESSION['cliente_id'])) {
    header("Location: login_cliente.php");
    exit;
}

$csrf_token = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;

$clienteId = (int)$_SESSION['cliente_id'];
$nombre = $_SESSION['cliente_nombre'];
$mensaje = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'eliminar_cuenta') {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $mensaje = 'Solicitud no válida, recarga la página.';
    } else {
    try {
        $sql = "UPDATE Cliente
                SET Activo = 0,
                    Anonimizado = 1,
                    Anonimizado_en = NOW(),
                    Anonimizado_por = :id_cliente_set,
                    Eliminado_en = NOW(),
                    Eliminado_motivo = 'Solicitud del titular',
                    Nombre = CONCAT('Anónimo ', Id_Cliente),
                    Telefono = NULL,
                    Correo = NULL,
                    Usuario = CONCAT('anon_', Id_Cliente),
                    Password = NULL,
                    Direccion = NULL
                WHERE Id_Cliente = :id_cliente_where";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id_cliente_set' => $clienteId,
            ':id_cliente_where' => $clienteId,
        ]);

        registrar_auditoria($pdo, [
            'usuario_id' => $clienteId,
            'rol' => 'CLIENTE',
            'accion' => 'ELIMINAR',
            'entidad' => 'Cliente',
            'entidad_id' => $clienteId,
            'datos' => ['motivo' => 'Solicitud del titular']
        ]);

        session_unset();
        session_destroy();
        header("Location: login_cliente.php");
        exit;
    } catch (PDOException $e) {
        $mensaje = 'No se pudo eliminar la cuenta: ' . htmlspecialchars($e->getMessage());
    }
}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') !== 'eliminar_cuenta') {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $mensaje = 'Solicitud no válida, recarga la página.';
    } else {
    $nuevoNombre  = substr(trim($_POST['nombre'] ?? ''), 0, 120);
    $telefono     = substr(trim($_POST['telefono'] ?? ''), 0, 20);
    $correo       = substr(trim($_POST['correo'] ?? ''), 0, 150);
    $direccion    = substr(trim($_POST['direccion'] ?? ''), 0, 160);
    $ciudad       = substr(trim($_POST['ciudad'] ?? ''), 0, 120);
    $password     = $_POST['password'] ?? '';

    if ($nuevoNombre === '' || $correo === '') {
        $mensaje = 'Nombre y correo son obligatorios.';
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $mensaje = 'Correo inválido.';
    } else {
        try {
            $campos = [
                'Nombre' => $nuevoNombre,
                'Telefono' => $telefono !== '' ? $telefono : null,
                'Correo' => $correo,
                'Direccion' => $direccion !== '' ? $direccion : null,
                'Ciudad' => $ciudad !== '' ? $ciudad : null,
            ];

            $setParts = [];
            $params = [];
            foreach ($campos as $campo => $valor) {
                $setParts[] = "$campo = :$campo";
                $params[":$campo"] = $valor;
            }

            if ($password !== '') {
                $setParts[] = "Password = :Password";
                $params[':Password'] = password_hash($password, PASSWORD_DEFAULT);
            }

            $params[':id'] = $clienteId;
            $setClause = implode(', ', $setParts);

            $sql = "UPDATE Cliente SET $setClause WHERE Id_Cliente = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $_SESSION['cliente_nombre'] = $nuevoNombre;
            $nombre = $nuevoNombre;
            $mensaje = 'Perfil actualizado correctamente ✅';
            registrar_auditoria($pdo, [
                'usuario_id' => $clienteId,
                'rol' => 'CLIENTE',
                'accion' => 'MODIFICAR',
                'entidad' => 'Cliente',
                'entidad_id' => $clienteId,
                'datos' => ['correo' => $correo, 'ciudad' => $ciudad]
            ]);
        } catch (PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) === 1062) {
                $mensaje = 'Ese correo ya está registrado. Usa uno diferente.';
            } else {
                $mensaje = 'Error al actualizar perfil: ' . htmlspecialchars($e->getMessage());
            }
        }
    }
}
}

try {
    $stmt = $pdo->prepare("SELECT Nombre, Telefono, Correo, Direccion, Ciudad FROM Cliente WHERE Id_Cliente = :id AND Activo = 1 AND Anonimizado = 0 LIMIT 1");
    $stmt->execute([':id' => $clienteId]);
    $cliente = $stmt->fetch();

    if (!$cliente) {
        session_unset();
        session_destroy();
        header("Location: login_cliente.php");
        exit;
    }
} catch (PDOException $e) {
    $mensaje = 'Error al cargar tus datos: ' . htmlspecialchars($e->getMessage());
    $cliente = [
        'Nombre' => $nombre,
        'Telefono' => '',
        'Correo' => '',
        'Direccion' => '',
    ];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mi Cuenta</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/theme.css">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
        <div class="container">
            <a class="navbar-brand text-white fw-bold" href="productos_cliente.php">Zip Undercover</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navCliente" aria-controls="navCliente" aria-expanded="false" aria-label="Mostrar navegación">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navCliente">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link text-white icon-inline" href="productos_cliente.php">
                            <i class="bi bi-grid-3x3-gap-fill" aria-hidden="true"></i>
                            <span>Catálogo</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white icon-inline" href="carrito.php">
                            <i class="bi bi-bag-heart" aria-hidden="true"></i>
                            <span>Carrito</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white icon-inline" href="compras_cliente.php">
                            <i class="bi bi-receipt-cutoff" aria-hidden="true"></i>
                            <span>Mis compras</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white active icon-inline" aria-current="page" href="panel_cliente.php">
                            <i class="bi bi-person-circle" aria-hidden="true"></i>
                            <span>Mi cuenta</span>
                        </a>
                    </li>
                </ul>
                <div class="d-flex gap-2">
                    <a href="logout.php" class="btn btn-light btn-sm text-primary icon-inline">
                        <i class="bi bi-door-open" aria-hidden="true"></i>
                        <span>Cerrar sesión</span>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main class="container py-5">
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Información personal</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($mensaje !== ''): ?>
                            <div class="alert alert-info" role="alert" aria-live="polite"><?php echo htmlspecialchars($mensaje); ?></div>
                        <?php endif; ?>

                        <form method="post" class="vstack gap-3">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <div>
                                <label class="form-label">Nombre completo *</label>
                                <input type="text" name="nombre" class="form-control" value="<?php echo htmlspecialchars($cliente['Nombre'] ?? ''); ?>" required>
                            </div>
                            <div>
                                <label class="form-label">Teléfono</label>
                                <input type="text" name="telefono" class="form-control" value="<?php echo htmlspecialchars($cliente['Telefono'] ?? ''); ?>">
                            </div>
                            <div>
                                <label class="form-label">Correo electrónico *</label>
                                <input type="email" name="correo" class="form-control" value="<?php echo htmlspecialchars($cliente['Correo'] ?? ''); ?>" required>
                            </div>
                            <div>
                                <label class="form-label">Dirección</label>
                            <input type="text" name="direccion" class="form-control" value="<?php echo htmlspecialchars($cliente['Direccion'] ?? ''); ?>">
                        </div>
                        <div>
                            <label class="form-label">Ciudad</label>
                            <input type="text" name="ciudad" class="form-control" value="<?php echo htmlspecialchars($cliente['Ciudad'] ?? ''); ?>">
                        </div>
                            <div>
                                <label class="form-label">Nueva contraseña (opcional)</label>
                                <input type="password" name="password" class="form-control" placeholder="Déjalo en blanco para mantener la actual">
                            </div>
                            <button type="submit" class="btn btn-primary icon-inline">
                                <i class="bi bi-save" aria-hidden="true"></i>
                                <span>Guardar cambios</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Accesos rápidos</h5>
                    </div>
                    <div class="card-body d-flex flex-column gap-3">
                        <div>
                            <h6 class="fw-bold mb-1">Mi historial de compras</h6>
                            <p class="text-muted small mb-2">Revisa tus pedidos anteriores, descarga comprobantes o inicia devoluciones.</p>
                            <div class="d-flex flex-wrap gap-2 align-items-center">
                                <a href="compras_cliente.php" class="btn btn-outline-primary btn-sm">Ver mis compras</a>
                                <a href="politica_datos.php" class="small link-secondary text-decoration-none" target="_blank" rel="noopener">Política de tratamiento de datos personales</a>
                            </div>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-1">Mi carrito</h6>
                            <p class="text-muted small mb-2">Continúa con tu compra, actualiza cantidades o finaliza el pedido.</p>
                            <a href="carrito.php" class="btn btn-outline-primary btn-sm">Ir al carrito</a>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-1">Volver a la tienda</h6>
                            <p class="text-muted small mb-2">Descubre nuevos productos y añade más artículos a tu próxima compra.</p>
                            <a href="productos_cliente.php" class="btn btn-outline-primary btn-sm">Ir al catálogo</a>
                        </div>
                        <div class="border-top pt-3">
                            <h6 class="fw-bold text-danger mb-1">Eliminar mi cuenta</h6>
                            <p class="text-muted small mb-2">Tu perfil se desactivará y tus datos personales se anonimizarán, pero se conservarán tus transacciones.</p>
                            <form method="post" onsubmit="return confirm('¿Seguro que deseas eliminar tu cuenta? Esta acción es irreversible.');">
                                <input type="hidden" name="accion" value="eliminar_cuenta">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm icon-inline">
                                    <i class="bi bi-trash3" aria-hidden="true"></i>
                                    <span>Eliminar mi cuenta</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
