<?php
session_start();
require 'db.php';

$mensaje = '';

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];


$loginAttempts = $_SESSION['login_attempts_admin'] ?? ['count' => 0, 'ts' => time()];
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

            $sql = "SELECT * FROM Administrador WHERE Correo = :correo AND Activo = 1 LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([ ':correo' => $correo ]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['Password'])) {

                $_SESSION['login_attempts_admin'] = ['count' => 0, 'ts' => time()];
                $_SESSION['admin_id'] = $admin['Id_Admin'];
                $_SESSION['admin_nombre'] = $admin['Nombre'];
                $_SESSION['rol'] = $admin['Rol'];
                registrar_auditoria($pdo, [
                    'usuario_id' => $admin['Id_Admin'],
                    'rol' => 'ADMIN',
                    'accion' => 'LOGIN',
                    'entidad' => 'Administrador',
                    'entidad_id' => $admin['Id_Admin'],
                    'datos' => ['correo' => $correo]
                ]);


                header("Location: panel_admin.php");
                exit;
            } else {
                $mensaje = 'Correo o contraseña incorrectos.';
                $loginAttempts['count']++;
                $_SESSION['login_attempts_admin'] = $loginAttempts;
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
    <title>Login Administrador</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/theme.css">
</head>
<body class="bg-body-tertiary">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="panel_admin.php">Administración</a>
            <span class="badge bg-secondary">Acceso interno</span>
            <div class="ms-auto d-flex gap-2">
                <a href="productos_cliente.php" class="btn btn-outline-light btn-sm icon-inline">
                    <i class="bi bi-shop" aria-hidden="true"></i>
                    <span>Ver tienda</span>
                </a>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <h1 class="h4 fw-bold mb-1 icon-inline justify-content-center">
                                <i class="bi bi-shield-lock" aria-hidden="true"></i>
                                <span>Panel administrativo</span>
                            </h1>
                            <p class="text-muted mb-0">Ingresa con tus credenciales para gestionar el sistema.</p>
                        </div>

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
                            ¿Eres cliente? <a href="login_cliente.php">Inicia sesión aquí</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
