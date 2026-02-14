<?php
session_start();
require 'db.php';

$clienteId = $_SESSION['cliente_id'] ?? null;
$clienteNombre = $_SESSION['cliente_nombre'] ?? 'Invitado';
$mensaje = $_SESSION['mensaje_catalogo'] ?? '';
unset($_SESSION['mensaje_catalogo']);
$navActivo = 'catalogo';

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$categoriaSeleccionada = isset($_GET['categoria']) ? max(0, (int)$_GET['categoria']) : 0;
$categorias = [];
$categoriaNombreActual = null;

try {
    $stmtCat = $pdo->query("SELECT Id_Categoria, Nombre, Imagen, Descripcion FROM Categorias WHERE Activa = 1 ORDER BY Nombre");
    $categorias = $stmtCat->fetchAll();
    if ($categoriaSeleccionada > 0) {
        foreach ($categorias as $cat) {
            if ((int)$cat['Id_Categoria'] === $categoriaSeleccionada) {
                $categoriaNombreActual = $cat['Nombre'];
                break;
            }
        }
    }
} catch (PDOException $e) {
    $mensaje = 'Error al cargar categorías: ' . htmlspecialchars($e->getMessage());
}

try {
    $sql = "SELECT p.Id_Producto, p.Nombre, c.Nombre AS CategoriaNombre, p.Precio, p.Descripcion, p.Imagen,
                   COALESCE(SUM(i.Cantidad), 0) AS Stock
            FROM Productos p
            LEFT JOIN Categorias c ON p.Id_Categoria = c.Id_Categoria
            LEFT JOIN Inventario i ON i.Id_Producto = p.Id_Producto
            WHERE (p.Fecha_retiro IS NULL OR p.Fecha_retiro > CURDATE())
              AND p.Activo = 1
              AND (p.Id_Categoria IS NULL OR c.Activa = 1)";

    $params = [];
    if ($categoriaSeleccionada > 0) {
        $sql .= " AND p.Id_Categoria = :categoria";
        $params[':categoria'] = $categoriaSeleccionada;
    }

    $sql .= " GROUP BY p.Id_Producto, p.Nombre, c.Nombre, p.Precio, p.Descripcion, p.Imagen
              ORDER BY p.Nombre";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $productos = $stmt->fetchAll();
} catch (PDOException $e) {
    $mensaje = 'Error al cargar productos: ' . htmlspecialchars($e->getMessage());
    $productos = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tienda | Zip Undercover</title>
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
                        <a class="nav-link text-white icon-inline <?php echo $navActivo === 'catalogo' ? 'active' : ''; ?>" href="productos_cliente.php" aria-current="<?php echo $navActivo === 'catalogo' ? 'page' : 'false'; ?>">
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
                        <a class="nav-link text-white icon-inline <?php echo $clienteId ? '' : 'disabled opacity-50'; ?>" href="compras_cliente.php">
                            <i class="bi bi-receipt-cutoff" aria-hidden="true"></i>
                            <span>Mis compras</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white icon-inline <?php echo $clienteId ? '' : 'disabled opacity-50'; ?>" href="panel_cliente.php">
                            <i class="bi bi-person-circle" aria-hidden="true"></i>
                            <span>Mi cuenta</span>
                        </a>
                    </li>
                </ul>
                <div class="d-flex gap-2">
                    <?php if ($clienteId): ?>
                        <a href="logout.php" class="btn btn-light btn-sm text-primary icon-inline">
                            <i class="bi bi-door-open" aria-hidden="true"></i>
                            <span>Cerrar sesión</span>
                        </a>
                    <?php else: ?>
                        <a href="login_cliente.php" class="btn btn-outline-light btn-sm icon-inline">
                            <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>
                            <span>Iniciar sesión</span>
                        </a>
                        <a href="registrar_cliente.php" class="btn btn-light btn-sm text-primary icon-inline">
                            <i class="bi bi-person-plus" aria-hidden="true"></i>
                            <span>Registrarme</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

<main class="container py-5" id="catalogo">
        <header class="text-center mb-5">
            <p class="text-uppercase text-primary fw-semibold mb-1">Novedades</p>
            <h1 class="fw-bold">Explora nuestros productos</h1>
            <?php if (!$clienteId): ?>
                <p class="text-muted mb-0">Agrega lo que te guste al carrito. Solo necesitas iniciar sesión cuando estés listo para comprar.</p>
            <?php endif; ?>
        </header>

        <?php if (count($categorias) > 0): ?>
            <section class="mb-5">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h5 mb-0">Explora por categoría</h2>
                    <?php if ($categoriaNombreActual): ?>
                        <a href="productos_cliente.php" class="btn btn-sm btn-outline-secondary">Ver todas</a>
                    <?php endif; ?>
                </div>
                <div class="row g-3">
                    <div class="col-6 col-md-3 col-lg-2">
                        <a href="productos_cliente.php#catalogo" class="text-decoration-none">
                            <div class="card h-100 text-center <?php echo $categoriaSeleccionada === 0 ? 'border border-primary shadow-sm' : 'border-0 shadow-sm'; ?>">
                                <div class="card-img-top bg-dark text-white d-flex align-items-center justify-content-center" style="height:120px;">
                                    <span class="fw-bold">Todas</span>
                                </div>
                                <div class="card-body p-2">
                                    <span class="small fw-semibold text-dark">Todas las categorías</span>
                                </div>
                            </div>
                        </a>
                    </div>
                                <?php foreach ($categorias as $cat): ?>
                                    <?php
                                        $esActual = ($categoriaSeleccionada === (int)$cat['Id_Categoria']);
                                        $cardClass = $esActual ? 'border border-primary shadow-sm' : 'border-0 shadow-sm';
                                        $descripcion = trim($cat['Descripcion'] ?? '');
                                        $descripcionTooltip = $descripcion !== '' ? preg_replace('/\s+/', ' ', $descripcion) : '';
                                        $tooltip = $descripcionTooltip !== '' ? $descripcionTooltip : 'Sin descripción disponible';
                                    ?>
                                    <div class="col-6 col-md-3 col-lg-2">
                                        <a href="productos_cliente.php?categoria=<?php echo (int)$cat['Id_Categoria']; ?>#catalogo" class="text-decoration-none">
                                            <div class="card h-100 text-center <?php echo $cardClass; ?>"
                                                 data-bs-toggle="tooltip"
                                                 data-bs-placement="top"
                                                 title="<?php echo htmlspecialchars($tooltip); ?>">
                                    <?php if (!empty($cat['Imagen'])): ?>
                                        <img src="uploads/<?php echo htmlspecialchars($cat['Imagen']); ?>" class="card-img-top" style="height:120px;object-fit:cover;" alt="Categoría <?php echo htmlspecialchars($cat['Nombre']); ?>">
                                    <?php else: ?>
                                        <div class="card-img-top bg-light d-flex align-items-center justify-content-center text-muted" style="height:120px;">
                                            Sin imagen
                                        </div>
                                    <?php endif; ?>
                                    <div class="card-body p-2">
                                        <span class="small fw-semibold text-dark"><?php echo htmlspecialchars($cat['Nombre']); ?></span>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($categoriaNombreActual): ?>
            <div class="alert alert-secondary d-flex justify-content-between align-items-center">
                <span>Filtrando por categoría: <strong><?php echo htmlspecialchars($categoriaNombreActual); ?></strong></span>
                <a href="productos_cliente.php" class="btn btn-sm btn-outline-dark">Quitar filtro</a>
            </div>
        <?php endif; ?>

        <?php if ($mensaje !== ''): ?>
            <div class="alert alert-info shadow-sm" role="alert">
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <?php if (count($productos) === 0): ?>
            <div class="alert alert-warning">No hay productos disponibles por el momento.</div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($productos as $p): ?>
                    <div class="col-12 col-sm-6 col-lg-4">
                        <div class="card h-100 shadow-sm product-card">
                            <?php if (!empty($p['Imagen'])): ?>
                                <img src="uploads/<?php echo htmlspecialchars($p['Imagen']); ?>" class="card-img-top product-image" alt="Imagen de <?php echo htmlspecialchars($p['Nombre']); ?>">
                            <?php else: ?>
                                <div class="card-img-top product-image-placeholder">
                                    Sin imagen
                                </div>
                            <?php endif; ?>
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title"><?php echo htmlspecialchars($p['Nombre']); ?></h5>
                                <?php if (!empty($p['CategoriaNombre'])): ?>
                                    <span class="badge bg-secondary mb-2"><?php echo htmlspecialchars($p['CategoriaNombre']); ?></span>
                                <?php endif; ?>
                                <p class="text-primary fw-bold mb-2">$<?php echo number_format($p['Precio'], 2); ?></p>
                                <p class="card-text text-muted small flex-grow-1"><?php echo nl2br(htmlspecialchars($p['Descripcion'])); ?></p>
                                <span class="badge <?php echo ($p['Stock'] ?? 0) > 0 ? 'bg-success' : 'bg-secondary'; ?>">
                                    <?php echo ($p['Stock'] ?? 0) > 0 ? (int)$p['Stock'] . ' disponibles' : 'Agotado'; ?>
                                </span>
                                <form method="post" action="carrito.php" class="mt-3">
                                    <input type="hidden" name="accion" value="agregar">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                    <input type="hidden" name="id_producto" value="<?php echo (int)$p['Id_Producto']; ?>">
                                    <label class="form-label small text-muted mb-1">Cantidad</label>
                                    <input type="number"
                                           name="cantidad"
                                           class="form-control"
                                           min="1"
                                           max="<?php echo max(1, (int)$p['Stock']); ?>"
                                           value="1"
                                           <?php echo ($p['Stock'] ?? 0) < 1 ? 'disabled' : ''; ?>>
                                    <button type="submit"
                                            class="btn btn-primary w-100 mt-3 icon-inline"
                                            <?php echo ($p['Stock'] ?? 0) < 1 ? 'disabled' : ''; ?>>
                                        <i class="bi bi-cart-plus" aria-hidden="true"></i>
                                        <span>Agregar al carrito</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <footer class="footer-dark text-white text-center py-3 mt-5">
        <p class="mb-0 small text-white-50 icon-inline justify-content-center">
            <i class="bi bi-lightning-charge" aria-hidden="true"></i>
            <span>Zip Undercover &copy; <?php echo date('Y'); ?></span>
        </p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const tooltipTriggers = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        [...tooltipTriggers].forEach(el => new bootstrap.Tooltip(el));
    </script>
</body>
</html>
