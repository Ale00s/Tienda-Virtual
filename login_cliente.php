<?php
session_start();
require 'db.php';

$mensaje = '';

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];


$loginAttempts = $_SESSION['login_attempts_cliente'] ?? ['count' => 0, 'ts' => time()];
if (time() - $loginAttempts['ts'] > 300) {
    $loginAttempts = ['count' => 0, 'ts' => time()];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $mensaje = 'Solicitud no válida, recarga la página.';
    } else {
    $correo = trim($_POST['correo'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($loginAttempts['count'] >= 5) {
        $mensaje = 'Demasiados intentos. Intenta de nuevo en unos minutos.';
    } elseif ($correo === '' || $password === '') {
        $mensaje = 'Todos los campos son obligatorios.';
    } else {
        try {
            $sql = "SELECT * FROM Cliente WHERE Correo = :correo AND Activo = 1 AND Anonimizado = 0 LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':correo' => $correo]);
            $cliente = $stmt->fetch();

            if ($cliente && password_verify($password, $cliente['Password'])) {
                $_SESSION['login_attempts_cliente'] = ['count' => 0, 'ts' => time()];
                $_SESSION['cliente_id'] = $cliente['Id_Cliente'];
                $_SESSION['cliente_nombre'] = $cliente['Nombre'];
                registrar_auditoria($pdo, [
                    'usuario_id' => $cliente['Id_Cliente'],
                    'rol' => 'CLIENTE',
                    'accion' => 'LOGIN',
                    'entidad' => 'Cliente',
                    'entidad_id' => $cliente['Id_Cliente'],
                    'datos' => ['correo' => $correo]
                ]);

                $redirect = $_SESSION['post_login_redirect'] ?? 'productos_cliente.php';
                unset($_SESSION['post_login_redirect']);

                header("Location: " . $redirect);
                exit;
            } else {
                $mensaje = 'Correo o contraseña incorrectos.';
                $loginAttempts['count']++;
                $_SESSION['login_attempts_cliente'] = $loginAttempts;
                sleep(1);
            }
        } catch (PDOException $e) {
            $mensaje = 'Error al iniciar sesión: ' . htmlspecialchars($e->getMessage());
        }
    }
}
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ingreso de clientes</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/theme.css">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
        <div class="container">
            <a class="navbar-brand text-white" href="productos_cliente.php">Zip Undercover</a>
            <div class="ms-auto">
                <a href="productos_cliente.php" class="btn btn-outline-light btn-sm icon-inline">
                    <i class="bi bi-arrow-left" aria-hidden="true"></i>
                    <span>Volver al catálogo</span>
                </a>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h1 class="h4 mb-3 text-center fw-bold icon-inline justify-content-center">
                            <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>
                            <span>Inicia sesión</span>
                        </h1>

                        <?php if ($mensaje !== ''): ?>
                            <div class="alert alert-danger" role="alert" aria-live="polite"><?php echo htmlspecialchars($mensaje); ?></div>
                        <?php endif; ?>

                        <form method="post" class="vstack gap-3">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <div>
                                <label class="form-label">Correo</label>
                                <input type="email" name="correo" class="form-control" required autofocus>
                            </div>
                            <div>
                                <label class="form-label">Contraseña</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 icon-inline justify-content-center">
                                <i class="bi bi-unlock" aria-hidden="true"></i>
                                <span>Ingresar</span>
                            </button>
                        </form>

                        <div class="mt-4 text-center small text-muted">
                            ¿No tienes cuenta? <a href="registrar_cliente.php">Regístrate</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
