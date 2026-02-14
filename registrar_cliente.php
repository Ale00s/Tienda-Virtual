<?php
session_start();
require 'db.php';

$mensaje = '';

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $mensaje = 'Solicitud no válida, recarga la página.';
    } else {

        $nombre    = substr(trim($_POST['nombre'] ?? ''), 0, 120);
        $telefono  = substr(trim($_POST['telefono'] ?? ''), 0, 20);
        $correo    = substr(trim($_POST['correo'] ?? ''), 0, 150);
        $usuario   = substr(trim($_POST['usuario'] ?? ''), 0, 60);
        $password  = $_POST['password'] ?? '';
        $direccion = substr(trim($_POST['direccion'] ?? ''), 0, 160);
        $ciudad    = substr(trim($_POST['ciudad'] ?? ''), 0, 120);
        $acepta    = $_POST['acepta_politica'] ?? '';


        if ($nombre === '' || $correo === '' || $usuario === '' || $password === '') {
            $mensaje = 'Por favor completa los campos obligatorios (*).';
        } elseif ($acepta !== '1') {
            $mensaje = 'Debes aceptar la política de tratamiento de datos para registrarte.';
        } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $mensaje = 'Correo inválido.';
        } else {
            try {

                $passwordHash = password_hash($password, PASSWORD_DEFAULT);


                $sql = "INSERT INTO Cliente
                        (Nombre, Telefono, Correo, Usuario, Password, Direccion, Ciudad, Acepta_politica)
                        VALUES
                        (:nombre, :telefono, :correo, :usuario, :password, :direccion, :ciudad, :acepta)";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':nombre'    => $nombre,
                    ':telefono'  => $telefono,
                    ':correo'    => $correo,
                    ':usuario'   => $usuario,
                    ':password'  => $passwordHash,
                    ':direccion' => $direccion,
                    ':ciudad'    => $ciudad,
                    ':acepta'    => 1
                ]);

                $mensaje = 'Cliente registrado correctamente ✅';
                registrar_auditoria($pdo, [
                    'usuario_id' => $pdo->lastInsertId(),
                    'rol' => 'CLIENTE',
                    'accion' => 'CREAR',
                    'entidad' => 'Cliente',
                    'entidad_id' => $pdo->lastInsertId(),
                    'datos' => ['correo' => $correo, 'usuario' => $usuario, 'ciudad' => $ciudad]
                ]);
            } catch (PDOException $e) {

                if ((int)($e->errorInfo[1] ?? 0) === 1062) {
                    $mensaje = 'El correo o usuario ya está registrado. Usa datos diferentes.';
                } else {
                    $mensaje = 'Error al registrar el cliente: ' . htmlspecialchars($e->getMessage());
                }
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
    <title>Registro de Cliente</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/theme.css">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
        <div class="container">
            <a class="navbar-brand text-white fw-bold" href="productos_cliente.php">Zip Undercover</a>
            <div class="ms-auto d-flex gap-2">
                <a href="productos_cliente.php" class="btn btn-outline-light btn-sm icon-inline">
                    <i class="bi bi-arrow-left" aria-hidden="true"></i>
                    <span>Volver al catálogo</span>
                </a>
                <a href="login_cliente.php" class="btn btn-light btn-sm text-primary icon-inline">
                    <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>
                    <span>Ya tengo cuenta</span>
                </a>
            </div>
        </div>
    </nav>

    <main class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-6 col-xl-5">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-4">
                        <h1 class="h4 fw-bold text-center mb-3 icon-inline justify-content-center">
                            <i class="bi bi-stars" aria-hidden="true"></i>
                            <span>Crea tu cuenta</span>
                        </h1>
                        <p class="text-muted text-center mb-4">Completa tus datos para empezar a comprar.</p>

                        <?php if ($mensaje !== ''): ?>
                            <div class="alert alert-info" role="alert" aria-live="polite"><?php echo htmlspecialchars($mensaje); ?></div>
                        <?php endif; ?>

                        <form method="post" class="vstack gap-3">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <div>
                                <label class="form-label" for="nombre">Nombre completo *</label>
                                <input type="text" id="nombre" name="nombre" class="form-control" required>
                            </div>
                            <div>
                                <label class="form-label" for="telefono">Teléfono</label>
                                <input type="text" id="telefono" name="telefono" class="form-control">
                            </div>
                            <div>
                                <label class="form-label" for="correo">Correo electrónico *</label>
                                <input type="email" id="correo" name="correo" class="form-control" required>
                            </div>
                            <div>
                                <label class="form-label" for="usuario">Usuario *</label>
                                <input type="text" id="usuario" name="usuario" class="form-control" required>
                            </div>
                            <div>
                                <label class="form-label" for="password">Contraseña *</label>
                                <input type="password" id="password" name="password" class="form-control" required>
                            </div>
                            <div>
                                <label class="form-label" for="direccion">Dirección</label>
                                <input type="text" id="direccion" name="direccion" class="form-control">
                            </div>
                            <div>
                                <label class="form-label" for="ciudad">Ciudad</label>
                                <input type="text" id="ciudad" name="ciudad" class="form-control">
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="acepta_politica" value="1" id="aceptaPolitica">
                                <label class="form-check-label" for="aceptaPolitica">
                                    Acepto la <a href="politica_datos.php" target="_blank" rel="noopener">política de tratamiento de datos personales</a>
                                </label>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 mt-2 icon-inline justify-content-center">
                                <i class="bi bi-person-check" aria-hidden="true"></i>
                                <span>Registrar cliente</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function() {
            const form = document.querySelector('form');
            if (!form) return;
            const storageKey = 'registro_cliente_cache';
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
</body>
</html>
