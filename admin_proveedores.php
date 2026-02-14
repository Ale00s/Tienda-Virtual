<?php
session_start();
require 'db.php';


if (!isset($_SESSION['admin_id'])) {
    header("Location: login_admin.php");
    exit;
}

$mensaje = '';
$accion = $_GET['accion'] ?? 'listar';
$nit_editar = $_GET['nit'] ?? null;


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nit          = trim($_POST['nit'] ?? '');
    $nombre_emp   = trim($_POST['nombre_empresa'] ?? '');
    $direccion    = trim($_POST['direccion'] ?? '');
    $telefono     = trim($_POST['telefono'] ?? '');
    $correo       = trim($_POST['correo'] ?? '');
    $banco        = trim($_POST['banco'] ?? '');
    $num_cuenta   = trim($_POST['numero_cuenta'] ?? '');
    $tipo_cuenta  = trim($_POST['tipo_cuenta'] ?? '');
    $modo         = $_POST['modo'] ?? 'nuevo'; // 'nuevo' o 'editar'

    if ($nit === '' || $nombre_emp === '') {
        $mensaje = 'NIT y Nombre de empresa son obligatorios.';
        $accion = ($modo === 'editar') ? 'editar' : 'nuevo';
        $nit_editar = ($modo === 'editar') ? $nit : null;
    } elseif ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $mensaje = 'Correo inválido.';
        $accion = ($modo === 'editar') ? 'editar' : 'nuevo';
        $nit_editar = ($modo === 'editar') ? $nit : null;
    } else {
        try {
            if ($modo === 'nuevo') {
                $sql = "INSERT INTO Proveedores
                        (Nit, Nombre_empresa, Direccion, Telefono, Correo, Banco, Numero_Cuenta, Tipo_Cuenta, Activo)
                        VALUES
                        (:nit, :nombre_empresa, :direccion, :telefono, :correo, :banco, :numero_cuenta, :tipo_cuenta, 1)";
            } else {
                $sql = "UPDATE Proveedores
                        SET Nombre_empresa = :nombre_empresa,
                            Direccion = :direccion,
                            Telefono = :telefono,
                            Correo = :correo,
                            Banco = :banco,
                            Numero_Cuenta = :numero_cuenta,
                            Tipo_Cuenta = :tipo_cuenta
                        WHERE Nit = :nit";
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':nit'            => $nit,
                ':nombre_empresa' => $nombre_emp,
                ':direccion'      => $direccion,
                ':telefono'       => $telefono,
                ':correo'         => $correo,
                ':banco'          => $banco,
                ':numero_cuenta'  => $num_cuenta,
                ':tipo_cuenta'    => $tipo_cuenta
            ]);

            $mensaje = ($modo === 'nuevo')
                ? 'Proveedor creado correctamente ✅'
                : 'Proveedor actualizado correctamente ✅';

            $accion = 'listar'; // volvemos a la lista
            registrar_auditoria($pdo, [
                'usuario_id' => $_SESSION['admin_id'],
                'rol' => 'ADMIN',
                'accion' => $modo === 'nuevo' ? 'CREAR' : 'MODIFICAR',
                'entidad' => 'Proveedor',
                'entidad_id' => null, // NIT no es numérico, lo guardamos en datos
                'datos' => ['nit' => $nit, 'nombre' => $nombre_emp, 'correo' => $correo]
            ]);

        } catch (PDOException $e) {
            $mensaje = 'Error al guardar proveedor: ' . htmlspecialchars($e->getMessage());
            $accion = ($modo === 'editar') ? 'editar' : 'nuevo';
        }
    }
}


if ($accion === 'eliminar' && $nit_editar !== null) {
    try {
        $sql = "UPDATE Proveedores
                SET Activo = 0,
                    Eliminado_en = NOW(),
                    Eliminado_por = :admin,
                    Eliminado_motivo = 'Desactivado desde panel'
                WHERE Nit = :nit";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':nit' => $nit_editar, ':admin' => $_SESSION['admin_id']]);

        $mensaje = 'Proveedor desactivado correctamente ✅';
        $accion = 'listar';
        registrar_auditoria($pdo, [
            'usuario_id' => $_SESSION['admin_id'],
            'rol' => 'ADMIN',
            'accion' => 'ELIMINAR',
            'entidad' => 'Proveedor',
            'entidad_id' => null,
            'datos' => ['nit' => $nit_editar, 'motivo' => 'Desactivado desde panel']
        ]);
    } catch (PDOException $e) {
        $mensaje = 'Error al desactivar proveedor (verifica dependencias): ' . htmlspecialchars($e->getMessage());
        $accion = 'listar';
    }
}


$proveedor_editar = null;
if ($accion === 'editar' && $nit_editar !== null) {
    $sql = "SELECT * FROM Proveedores WHERE Nit = :nit AND Activo = 1 LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':nit' => $nit_editar]);
    $proveedor_editar = $stmt->fetch();

    if (!$proveedor_editar) {
        $mensaje = 'Proveedor no encontrado.';
        $accion = 'listar';
    }
}


$lista_proveedores = [];
if ($accion === 'listar') {
    $sql = "SELECT * FROM Proveedores WHERE Activo = 1 ORDER BY Nombre_empresa";
    $stmt = $pdo->query($sql);
    $lista_proveedores = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Administrar Proveedores</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/theme.css">
</head>
<body class="bg-body-tertiary">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="panel_admin.php">Administración</a>
            <span class="badge bg-secondary">Proveedores</span>
            <div class="ms-auto d-flex gap-2">
                <a href="panel_admin.php" class="btn btn-outline-light btn-sm icon-inline"><i class="bi bi-speedometer" aria-hidden="true"></i><span>Panel</span></a>
                <a href="logout.php" class="btn btn-outline-light btn-sm icon-inline text-white"><i class="bi bi-door-open" aria-hidden="true"></i><span>Cerrar sesión</span></a>
            </div>
        </div>
    </nav>

    <main class="container py-5">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start mb-4 gap-3">
            <div>
                <h1 class="fw-bold mb-1">Proveedores</h1>
                <p class="text-muted mb-0">Administra la información financiera y de contacto.</p>
            </div>
            <div class="btn-group">
                <a href="admin_proveedores.php" class="btn btn-outline-secondary icon-inline">
                    <i class="bi bi-card-list" aria-hidden="true"></i>
                    <span>Listado</span>
                </a>
                <a href="admin_proveedores.php?accion=nuevo" class="btn btn-primary icon-inline">
                    <i class="bi bi-plus-circle" aria-hidden="true"></i>
                    <span>Nuevo proveedor</span>
                </a>
            </div>
        </div>

        <?php if ($mensaje !== ''): ?>
            <div class="alert alert-info"><?php echo htmlspecialchars($mensaje); ?></div>
        <?php endif; ?>

        <?php if ($accion === 'listar'): ?>
            <?php if (count($lista_proveedores) === 0): ?>
                <div class="alert alert-warning">No hay proveedores registrados.</div>
            <?php else: ?>
                <div class="card shadow-sm">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>NIT</th>
                                    <th>Empresa</th>
                                    <th>Dirección</th>
                                    <th>Teléfono</th>
                                    <th>Correo</th>
                                    <th>Banco</th>
                                    <th>Nº Cuenta</th>
                                    <th>Tipo</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lista_proveedores as $p): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($p['Nit']); ?></td>
                                        <td><?php echo htmlspecialchars($p['Nombre_empresa']); ?></td>
                                        <td><?php echo htmlspecialchars($p['Direccion']); ?></td>
                                        <td><?php echo htmlspecialchars($p['Telefono']); ?></td>
                                        <td><?php echo htmlspecialchars($p['Correo']); ?></td>
                                        <td><?php echo htmlspecialchars($p['Banco']); ?></td>
                                        <td><?php echo htmlspecialchars($p['Numero_Cuenta']); ?></td>
                                        <td><?php echo htmlspecialchars($p['Tipo_Cuenta']); ?></td>
                                        <td class="text-end">
                                            <a href="admin_proveedores.php?accion=editar&nit=<?php echo urlencode($p['Nit']); ?>" class="btn btn-sm btn-outline-primary icon-inline">
                                                <i class="bi bi-pencil-square" aria-hidden="true"></i>
                                                <span>Editar</span>
                                            </a>
                                            <a href="admin_proveedores.php?accion=eliminar&nit=<?php echo urlencode($p['Nit']); ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Seguro que deseas desactivar este proveedor?');">Desactivar</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        <?php elseif ($accion === 'nuevo' || $accion === 'editar'): ?>
            <?php $esEditar = ($accion === 'editar' && $proveedor_editar); ?>
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><?php echo $esEditar ? 'Editar proveedor' : 'Nuevo proveedor'; ?></h5>
                </div>
                <div class="card-body">
                    <form method="post" action="admin_proveedores.php" class="row g-3">
                        <input type="hidden" name="modo" value="<?php echo $esEditar ? 'editar' : 'nuevo'; ?>">

                        <div class="col-md-6">
                            <label class="form-label">NIT</label>
                            <input type="text" name="nit" class="form-control" value="<?php echo $esEditar ? htmlspecialchars($proveedor_editar['Nit']) : ''; ?>" <?php echo $esEditar ? 'readonly' : 'required'; ?>>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nombre de la empresa</label>
                            <input type="text" name="nombre_empresa" class="form-control" value="<?php echo $esEditar ? htmlspecialchars($proveedor_editar['Nombre_empresa']) : ''; ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Dirección</label>
                            <input type="text" name="direccion" class="form-control" value="<?php echo $esEditar ? htmlspecialchars($proveedor_editar['Direccion']) : ''; ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Teléfono</label>
                            <input type="text" name="telefono" class="form-control" value="<?php echo $esEditar ? htmlspecialchars($proveedor_editar['Telefono']) : ''; ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Correo</label>
                            <input type="email" name="correo" class="form-control" value="<?php echo $esEditar ? htmlspecialchars($proveedor_editar['Correo']) : ''; ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Banco</label>
                            <input type="text" name="banco" class="form-control" value="<?php echo $esEditar ? htmlspecialchars($proveedor_editar['Banco']) : ''; ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Número de cuenta</label>
                            <input type="text" name="numero_cuenta" class="form-control" value="<?php echo $esEditar ? htmlspecialchars($proveedor_editar['Numero_Cuenta']) : ''; ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tipo de cuenta</label>
                            <input type="text" name="tipo_cuenta" class="form-control" value="<?php echo $esEditar ? htmlspecialchars($proveedor_editar['Tipo_Cuenta']) : ''; ?>">
                        </div>

                        <div class="col-12 d-flex gap-2">
                            <button type="submit" class="btn btn-primary icon-inline">
                                <i class="bi bi-check-circle" aria-hidden="true"></i>
                                <span><?php echo $esEditar ? 'Guardar cambios' : 'Crear proveedor'; ?></span>
                            </button>
                            <a href="admin_proveedores.php" class="btn btn-outline-secondary">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
