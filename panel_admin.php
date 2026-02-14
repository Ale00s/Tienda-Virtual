<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: login_admin.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Panel de Administración</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/theme.css">
</head>
<body class="bg-body-tertiary">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="panel_admin.php">Administración</a>
            <div class="text-white-50 small">
                <?php echo htmlspecialchars($_SESSION['admin_nombre']); ?> · <?php echo htmlspecialchars($_SESSION['rol']); ?>
            </div>
            <div class="ms-auto d-flex gap-2">
                <a href="productos_cliente.php" class="btn btn-outline-light btn-sm icon-inline">
                    <i class="bi bi-shop" aria-hidden="true"></i>
                    <span>Ver tienda</span>
                </a>
                <a href="logout.php" class="btn btn-outline-light btn-sm icon-inline text-white">
                    <i class="bi bi-door-open" aria-hidden="true"></i>
                    <span>Cerrar sesión</span>
                </a>
            </div>
        </div>
    </nav>

    <main class="container py-5">
        <div class="mb-4 hero-gradient shadow-soft">
            <div class="d-flex flex-wrap align-items-center gap-3 mt-2">
                <h1 class="fw-bold mb-0 icon-inline">
                    <i class="bi bi-speedometer" aria-hidden="true"></i>
                    <span>Panel principal</span>
                </h1>
                <span class="text-muted">Gestiona el catálogo, inventarios, usuarios y transacciones desde aquí.</span>
            </div>
        </div>

        <div class="row g-4">
            <?php
            $rolSesion = $_SESSION['rol'] ?? 'SECUNDARIO';
            $secciones = [
                ['title' => 'Proveedores', 'desc' => 'Registra y actualiza tus proveedores.', 'link' => 'admin_proveedores.php', 'icon' => 'bi-truck'],
                ['title' => 'Productos', 'desc' => 'Administra el catálogo con fotos, precios y descripciones.', 'link' => 'admin_productos.php', 'icon' => 'bi-box-seam'],
                ['title' => 'Categorías', 'desc' => 'Organiza las familias del catálogo.', 'link' => 'admin_categorias.php', 'icon' => 'bi-collection'],
                ['title' => 'Inventario', 'desc' => 'Controla existencias, ubicaciones y movimientos.', 'link' => 'admin_inventario.php', 'icon' => 'bi-clipboard-data'],
                ['title' => 'Clientes', 'desc' => 'Consulta la información de clientes registrados.', 'link' => 'admin_clientes.php', 'icon' => 'bi-people'],
            ];

            if ($rolSesion === 'PRINCIPAL') {
                $secciones[] = ['title' => 'Administradores', 'desc' => 'Gestiona las cuentas del equipo interno.', 'link' => 'admin_usuarios.php', 'icon' => 'bi-shield-lock'];
            }

            $secciones = array_merge($secciones, [
                ['title' => 'Ventas', 'desc' => 'Revisa ventas recientes y su detalle.', 'link' => 'ventas_admin.php', 'icon' => 'bi-receipt'],
                ['title' => 'Envíos', 'desc' => 'Supervisa direcciones y métodos de entrega.', 'link' => 'admin_envios.php', 'icon' => 'bi-send'],
                ['title' => 'Devoluciones', 'desc' => 'Ver devoluciones.', 'link' => 'devoluciones_admin.php', 'icon' => 'bi-arrow-counterclockwise'],
                ['title' => 'Auditoría', 'desc' => 'Consulta el historial de operaciones.', 'link' => 'auditoria_admin.php', 'icon' => 'bi-clipboard-check'],
            ]);

            foreach ($secciones as $seccion): ?>
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title icon-inline">
                                <i class="bi <?php echo $seccion['icon']; ?>" aria-hidden="true"></i>
                                <span><?php echo $seccion['title']; ?></span>
                            </h5>
                            <p class="card-text text-muted flex-grow-1"><?php echo $seccion['desc']; ?></p>
                            <a href="<?php echo $seccion['link']; ?>" class="btn btn-primary mt-3 icon-inline">
                                <i class="bi bi-arrow-right-circle" aria-hidden="true"></i>
                                <span>Administrar</span>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
