<?php
session_start();
require 'db.php';

if (!isset($_SESSION['cliente_id'])) {
    header("Location: login_cliente.php");
    exit;
}

$clienteId = (int)$_SESSION['cliente_id'];
$mensaje = '';


try {
    $sql = "SELECT Id_venta, Fecha, Subtotal, Impuestos, Total
            FROM Ventas
            WHERE Id_cliente = :id
            ORDER BY Fecha DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $clienteId]);
    $ventas = $stmt->fetchAll();
} catch (PDOException $e) {
    $mensaje = 'Error al cargar compras: ' . htmlspecialchars($e->getMessage());
    $ventas = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mis compras</title>
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
                        <a class="nav-link text-white active icon-inline" aria-current="page" href="compras_cliente.php">
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
                    <a href="logout.php" class="btn btn-light btn-sm text-primary icon-inline">
                        <i class="bi bi-door-open" aria-hidden="true"></i>
                        <span>Cerrar sesión</span>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main class="container py-5">
        <div class="mb-4 hero-gradient shadow-soft">
            <h1 class="fw-bold mt-2 mb-1 icon-inline">
                <i class="bi bi-receipt" aria-hidden="true"></i>
                <span>Mis compras</span>
            </h1>
            <p class="text-muted mb-0">Revisa el detalle de tus pedidos y gestiona devoluciones.</p>
        </div>

        <?php if ($mensaje !== ''): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($mensaje); ?></div>
        <?php endif; ?>

        <?php if (count($ventas) === 0): ?>
            <div class="alert alert-info">Aún no has realizado compras.</div>
        <?php else: ?>
            <div class="card shadow-sm">
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID Venta</th>
                                <th>Fecha</th>
                                <th>Subtotal</th>
                                <th>Impuestos</th>
                                <th>Total</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ventas as $v): ?>
                                <tr>
                                    <td>#<?php echo (int)$v['Id_venta']; ?></td>
                                    <td><?php echo htmlspecialchars($v['Fecha']); ?></td>
                                    <td>$<?php echo number_format($v['Subtotal'], 2); ?></td>
                                    <td>$<?php echo number_format($v['Impuestos'], 2); ?></td>
                                    <td class="fw-bold">$<?php echo number_format($v['Total'], 2); ?></td>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-outline-primary icon-inline" href="detalle_compra_cliente.php?id_venta=<?php echo (int)$v['Id_venta']; ?>">
                                            <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
                                            <span>Ver detalle</span>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
