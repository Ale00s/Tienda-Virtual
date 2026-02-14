<?php
session_start();
require 'db.php';

$clienteId = $_SESSION['cliente_id'] ?? null;
$clienteNombre = $_SESSION['cliente_nombre'] ?? 'Invitado';

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

if (!defined('IVA_CO')) {
    define('IVA_CO', 0.19);
}

$envioDefault = [
    'direccion_envio' => '',
    'ciudad' => '',
    'metodo_entrega' => '',
];

if ($clienteId) {
    try {
        $stmtCli = $pdo->prepare("SELECT Direccion, Ciudad FROM Cliente WHERE Id_Cliente = :id LIMIT 1");
        $stmtCli->execute([':id' => $clienteId]);
        $cli = $stmtCli->fetch();
        if ($cli) {
            $envioDefault['direccion_envio'] = $cli['Direccion'] ?? '';
            $envioDefault['ciudad'] = $cli['Ciudad'] ?? '';
        }
    } catch (PDOException $e) {

    }
}
$envio = $_SESSION['envio'] ?? $envioDefault;

if (!isset($_SESSION['carrito'])) {
    $_SESSION['carrito'] = [];
}
$carrito =& $_SESSION['carrito'];

$mensaje = '';
$accion = $_POST['accion'] ?? $_GET['accion'] ?? 'ver';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tokenPost = $_POST['csrf_token'] ?? '';
    if (!hash_equals($csrf_token, $tokenPost)) {
        $mensaje = 'Solicitud no válida, recarga la página.';
        $accion = 'ver';
    }
}

function obtenerProducto(PDO $pdo, int $id): ?array
{
    $sql = "SELECT p.Id_Producto, p.Nombre, p.Precio, p.Descripcion, p.Imagen,
                   COALESCE(SUM(i.Cantidad), 0) AS Stock
            FROM Productos p
            LEFT JOIN Categorias c ON p.Id_Categoria = c.Id_Categoria
            LEFT JOIN Inventario i ON i.Id_Producto = p.Id_Producto
            WHERE p.Id_Producto = :id
              AND p.Activo = 1
              AND (p.Fecha_retiro IS NULL OR p.Fecha_retiro > CURDATE())
              AND (p.Id_Categoria IS NULL OR c.Activa = 1)
            GROUP BY p.Id_Producto, p.Nombre, p.Precio, p.Descripcion, p.Imagen";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    $producto = $stmt->fetch(PDO::FETCH_ASSOC);
    return $producto ?: null;
}

function normalizarCarrito(PDO $pdo, array &$carrito): array
{
    $detalle = [];
    foreach ($carrito as $id => &$item) {
        $producto = obtenerProducto($pdo, (int)$id);
        if (!$producto) {
            unset($carrito[$id]);
            continue;
        }

        $stock = (int)$producto['Stock'];
        if ($stock <= 0) {
            unset($carrito[$id]);
            continue;
        }

        $cantidad = min($stock, (int)$item['cantidad']);
        if ($cantidad < 1) {
            unset($carrito[$id]);
            continue;
        }

        $item['cantidad'] = $cantidad;
        $item['nombre'] = $producto['Nombre'];
        $item['precio'] = $producto['Precio'];
        $item['imagen'] = $producto['Imagen'] ?? null;

        $detalle[$id] = [
            'id' => (int)$producto['Id_Producto'],
            'nombre' => $producto['Nombre'],
            'precio' => (float)$producto['Precio'],
            'cantidad' => $cantidad,
            'stock' => $stock,
            'imagen' => $producto['Imagen'] ?? null,
            'total' => $cantidad * (float)$producto['Precio'],
        ];
    }
    unset($item);

    return $detalle;
}

function calcularTotales(array $items): array
{
    $subtotal = 0;
    foreach ($items as $item) {
        $subtotal += $item['total'];
    }
    $baseImpuesto = $subtotal;
    $impuestos = round($baseImpuesto * IVA_CO, 2);
    $total = $baseImpuesto + $impuestos;

    return [
        'subtotal' => $subtotal,
        'impuestos' => $impuestos,
        'total' => $total,
    ];
}

function descontarInventario(PDO $pdo, int $productoId, int $cantidad): void
{
    $stmt = $pdo->prepare("SELECT Id_Inventario, Cantidad
                           FROM Inventario
                           WHERE Id_Producto = :producto
                           ORDER BY Id_Inventario
                           FOR UPDATE");
    $stmt->execute([':producto' => $productoId]);
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $restante = $cantidad;

    foreach ($registros as $registro) {
        if ($restante <= 0) {
            break;
        }

        $disponible = (int)$registro['Cantidad'];
        if ($disponible <= 0) {
            continue;
        }

        $aDescontar = min($disponible, $restante);
        $stmtUpd = $pdo->prepare("UPDATE Inventario
                                  SET Cantidad = Cantidad - :cantidad,
                                      Fecha_actualizacion = NOW()
                                  WHERE Id_Inventario = :id");
        $stmtUpd->execute([
            ':cantidad' => $aDescontar,
            ':id' => $registro['Id_Inventario'],
        ]);

        $restante -= $aDescontar;
    }

    if ($restante > 0) {
        throw new Exception('Inventario insuficiente para completar la compra.');
    }
}

function registrarEnvio(PDO $pdo, int $ventaId, array $envio): void
{
    $stmt = $pdo->prepare("INSERT INTO Envio
                           (Id_venta, Direccion_envio, Ciudad, Metodo_entrega)
                           VALUES
                           (:venta, :direccion, :ciudad, :metodo)");
    $stmt->execute([
        ':venta'     => $ventaId,
        ':direccion' => $envio['direccion_envio'],
        ':ciudad'    => $envio['ciudad'],
        ':metodo'    => $envio['metodo_entrega'],
    ]);
}

function registrarCarritoDB(PDO $pdo, int $clienteId, array $carrito, array $productos, float $total): void
{
    $stmtCarrito = $pdo->prepare("INSERT INTO Carrito (Id_Cliente, Total, Activo) VALUES (:cliente, :total, 1)");
    $stmtCarrito->execute([
        ':cliente' => $clienteId,
        ':total' => $total
    ]);
    $idCarrito = (int)$pdo->lastInsertId();
    $stmtDetalle = $pdo->prepare("INSERT INTO DetalleCarrito
                                  (Id_carrito, Id_producto, Cantidad, Precio_unitario, Subtotal, Descuento, Total)
                                  VALUES
                                  (:carrito, :producto, :cantidad, :precio, :subtotal, 0, :total)");
    foreach ($carrito as $idProducto => $item) {
        $producto = $productos[$idProducto];
        $cantidad = (int)$item['cantidad'];
        $precio = (float)$producto['Precio'];
        $subtotal = $cantidad * $precio;
        $stmtDetalle->execute([
            ':carrito' => $idCarrito,
            ':producto' => (int)$producto['Id_Producto'],
            ':cantidad' => $cantidad,
            ':precio' => $precio,
            ':subtotal' => $subtotal,
            ':total' => $subtotal
        ]);
    }
}

function finalizarCompra(PDO $pdo, array &$carrito, int $clienteId, array $envio): array
{
    if (empty($carrito)) {
        return ['ok' => false, 'mensaje' => 'Tu carrito está vacío.'];
    }

    try {
        $pdo->beginTransaction();

        $productos = [];
        $lineas = [];

        foreach ($carrito as $idProducto => $item) {
            $producto = obtenerProducto($pdo, (int)$idProducto);
            if (!$producto) {
                throw new Exception('Uno de los productos ya no está disponible.');
            }

            $stock = (int)$producto['Stock'];
            if ($stock < (int)$item['cantidad']) {
                throw new Exception('Solo quedan ' . $stock . ' unidades de ' . $producto['Nombre']);
            }

            $productos[$idProducto] = $producto;
            $lineas[] = [
                'total' => $item['cantidad'] * (float)$producto['Precio'],
            ];
        }

        $totales = calcularTotales($lineas);
        $subtotal = $totales['subtotal'];
        $impuestos = $totales['impuestos'];
        $total = $totales['total'];

        $stmtVenta = $pdo->prepare("INSERT INTO Ventas
                                    (Id_cliente, Fecha, Subtotal, Impuestos, Total)
                                    VALUES
                                    (:cliente, NOW(), :subtotal, :impuestos, :total)");
        $stmtVenta->execute([
            ':cliente' => $clienteId,
            ':subtotal' => $subtotal,
            ':impuestos' => $impuestos,
            ':total' => $total,
        ]);

        $idVenta = (int)$pdo->lastInsertId();
        registrar_auditoria($pdo, [
            'usuario_id' => $clienteId,
            'rol' => 'CLIENTE',
            'accion' => 'CREAR',
            'entidad' => 'Venta',
            'entidad_id' => $idVenta,
            'datos' => ['total' => $total, 'impuestos' => $impuestos, 'ciudad' => $envio['ciudad']]
        ]);
        $stmtDetalle = $pdo->prepare("INSERT INTO DetalleVenta
                                      (Id_venta, Id_producto, Cantidad, Precio_Unitario)
                                      VALUES
                                      (:venta, :producto, :cantidad, :precio)");

        foreach ($carrito as $idProducto => $item) {
            $producto = $productos[$idProducto];
            $stmtDetalle->execute([
                ':venta' => $idVenta,
                ':producto' => $producto['Id_Producto'],
                ':cantidad' => (int)$item['cantidad'],
                ':precio' => (float)$producto['Precio'],
            ]);

            descontarInventario($pdo, (int)$producto['Id_Producto'], (int)$item['cantidad']);
        }

        registrarCarritoDB($pdo, $clienteId, $carrito, $productos, $total);
        registrarEnvio($pdo, $idVenta, $envio);
        registrar_auditoria($pdo, [
            'usuario_id' => $clienteId,
            'rol' => 'CLIENTE',
            'accion' => 'CREAR',
            'entidad' => 'Envio',
            'entidad_id' => $idVenta,
            'datos' => ['metodo' => $envio['metodo_entrega'], 'ciudad' => $envio['ciudad']]
        ]);

        $pdo->commit();
        $carrito = [];

        return [
            'ok' => true,
            'mensaje' => 'Compra registrada correctamente. ¡Gracias por tu pedido!',
            'venta_id' => $idVenta,
        ];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return ['ok' => false, 'mensaje' => $e->getMessage()];
    }
}

switch ($accion) {
    case 'agregar':
        $idProducto = isset($_POST['id_producto']) ? (int)$_POST['id_producto'] : 0;
        $cantidad = isset($_POST['cantidad']) ? (int)$_POST['cantidad'] : 1;
        $producto = $idProducto > 0 ? obtenerProducto($pdo, $idProducto) : null;

        if (!$producto) {
            $mensaje = 'El producto seleccionado no existe.';
            break;
        }

        if ((int)$producto['Stock'] <= 0) {
            $mensaje = 'No hay inventario disponible para ' . $producto['Nombre'] . '.';
            break;
        }

        $cantidad = max(1, $cantidad);
        $existente = $carrito[$idProducto]['cantidad'] ?? 0;
        $nuevaCantidad = min((int)$producto['Stock'], $existente + $cantidad);

        $carrito[$idProducto] = [
            'nombre' => $producto['Nombre'],
            'precio' => $producto['Precio'],
            'cantidad' => $nuevaCantidad,
            'imagen' => $producto['Imagen'] ?? null,
        ];

        $mensaje = 'Producto agregado al carrito.';
        break;

    case 'actualizar':
        $cantidades = $_POST['cantidades'] ?? [];
        foreach ($cantidades as $id => $cantidad) {
            $id = (int)$id;
            if (!isset($carrito[$id])) {
                continue;
            }

            $cantidad = filter_var($cantidad, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 9999]]);
            if ($cantidad === false) {
                $cantidad = 1;
            }
            $producto = obtenerProducto($pdo, $id);
            if (!$producto || (int)$producto['Stock'] <= 0) {
                unset($carrito[$id]);
                continue;
            }

            $carrito[$id]['cantidad'] = min($cantidad, (int)$producto['Stock']);
        }

        $mensaje = 'Cantidades actualizadas.';
        break;

    case 'eliminar':
        $id = isset($_POST['id_producto']) ? (int)$_POST['id_producto'] : 0;
        if (isset($carrito[$id])) {
            unset($carrito[$id]);
            $mensaje = 'Producto eliminado del carrito.';
        }
        break;

    case 'vaciar':
        $carrito = [];
        $mensaje = 'Carrito vaciado.';
        break;

    case 'pagar':
        if (empty($carrito)) {
        $mensaje = 'Tu carrito está vacío.';
        break;
    }

        $envio = [
            'direccion_envio' => trim($_POST['direccion_envio'] ?? $envio['direccion_envio']),
            'ciudad' => trim($_POST['ciudad'] ?? $envio['ciudad']),
            'metodo_entrega' => trim($_POST['metodo_entrega'] ?? $envio['metodo_entrega']),
        ];
        $_SESSION['envio'] = $envio;

        if ($envio['direccion_envio'] === '' || $envio['ciudad'] === '' || $envio['metodo_entrega'] === '') {
            $mensaje = 'Completa los datos de envío antes de finalizar la compra.';
            break;
        }

        $metodosPermitidos = ['Envío estándar', 'Envío exprés'];
        if (!in_array($envio['metodo_entrega'], $metodosPermitidos, true)) {
            $mensaje = 'Selecciona un método de entrega válido.';
            break;
        }

        if (!$clienteId) {
            $_SESSION['envio'] = $envio;
            $_SESSION['mensaje_catalogo'] = 'Inicia sesión para finalizar la compra.';
            $_SESSION['post_login_redirect'] = 'carrito.php?accion=pagar';
            header('Location: login_cliente.php');
            exit;
        }

        $resultado = finalizarCompra($pdo, $carrito, (int)$clienteId, $envio);
        $mensaje = $resultado['mensaje'];
        if ($resultado['ok']) {
            $_SESSION['envio'] = $envioDefault;
            $envio = $envioDefault;
        }
        break;
}

$productosCarrito = normalizarCarrito($pdo, $carrito);
$totales = calcularTotales($productosCarrito);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mi Carrito</title>
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
                        <a class="nav-link text-white active icon-inline" aria-current="page" href="carrito.php">
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

    <main class="container py-5">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start mb-4 gap-2">
            <div>
                <h1 class="fw-bold mb-1">Tu carrito</h1>
                <p class="text-muted mb-0">Gestiona los productos antes de confirmar tu compra.</p>
            </div>
            <a href="productos_cliente.php" class="btn btn-outline-primary mt-1 icon-inline">
                <i class="bi bi-arrow-left-circle" aria-hidden="true"></i>
                <span>Seguir comprando</span>
            </a>
        </div>

        <?php if ($mensaje !== ''): ?>
            <div class="alert alert-info" role="alert" aria-live="polite"><?php echo htmlspecialchars($mensaje); ?></div>
        <?php endif; ?>

        <?php if (!$clienteId): ?>
            <div class="alert alert-warning" role="alert" aria-live="polite">
                Puedes armar tu carrito sin iniciar sesión. Te pediremos tus credenciales solo cuando confirmes la compra.
            </div>
        <?php endif; ?>

        <?php if (count($productosCarrito) === 0): ?>
            <div class="alert alert-secondary" role="alert" aria-live="polite">Tu carrito está vacío. Agrega productos desde el catálogo.</div>
        <?php else: ?>
            <form id="form-actualizar" method="post" action="carrito.php">
                <input type="hidden" name="accion" value="actualizar">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            </form>
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Productos</h5>
                            <div id="status-actualizacion" class="text-muted small mt-1" aria-live="polite"></div>
                        </div>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Producto</th>
                                        <th class="text-center">Cantidad</th>
                                        <th class="text-end">Total</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($productosCarrito as $item): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-3">
                                                    <?php if (!empty($item['imagen'])): ?>
                                                        <img src="uploads/<?php echo htmlspecialchars($item['imagen']); ?>" alt="<?php echo htmlspecialchars($item['nombre']); ?>" class="rounded" style="width: 60px; height: 60px; object-fit: cover;">
                                                    <?php else: ?>
                                                        <div class="rounded bg-light d-flex align-items-center justify-content-center" style="width:60px;height:60px;">
                                                            <span class="text-muted small">Sin imagen</span>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <p class="mb-0 fw-semibold"><?php echo htmlspecialchars($item['nombre']); ?></p>
                                                        <div class="small text-muted">$<?php echo number_format($item['precio'], 2); ?> c/u</div>
                                                        <span class="badge bg-secondary">Stock: <?php echo (int)$item['stock']; ?></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-center" style="max-width:140px;">
                                                <input type="number"
                                                       class="form-control"
                                                       form="form-actualizar"
                                                       name="cantidades[<?php echo $item['id']; ?>]"
                                                       min="1"
                                                       max="<?php echo (int)$item['stock']; ?>"
                                                       value="<?php echo (int)$item['cantidad']; ?>"
                                                       onchange="document.getElementById('form-actualizar').requestSubmit();">
                                            </td>
                                            <td class="text-end fw-bold">$<?php echo number_format($item['total'], 2); ?></td>
                                            <td class="text-end">
                                                <form method="post" action="carrito.php">
                                                    <input type="hidden" name="accion" value="eliminar">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                    <input type="hidden" name="id_producto" value="<?php echo $item['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger icon-inline">
                                                        <i class="bi bi-trash3" aria-hidden="true"></i>
                                                        <span>Eliminar</span>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="card-body d-flex justify-content-between flex-wrap gap-2">
                            <form method="post" action="carrito.php" onsubmit="return confirm('¿Deseas vaciar tu carrito?');" class="mb-0 w-100 d-flex justify-content-center">
                                <input type="hidden" name="accion" value="vaciar">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                <button type="submit" class="btn btn-outline-danger icon-inline">
                                    <i class="bi bi-trash" aria-hidden="true"></i>
                                    <span>Vaciar carrito</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title">Resumen</h5>
                            <div class="d-flex justify-content-between">
                                <span>Subtotal</span>
                                <strong>$<?php echo number_format($totales['subtotal'], 2); ?></strong>
                            </div>
                            <div class="d-flex justify-content-between text-muted mb-3">
                                <span>Impuestos</span>
                                <span>$<?php echo number_format($totales['impuestos'], 2); ?></span>
                            </div>
                            <div class="d-flex justify-content-between border-top pt-3">
                                <span class="fw-bold">Total</span>
                                <span class="fw-bold text-success">$<?php echo number_format($totales['total'], 2); ?></span>
                            </div>
                            <form method="post" action="carrito.php" class="mt-4">
                                <input type="hidden" name="accion" value="pagar">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                <div class="mb-3">
                                    <label class="form-label">Dirección de envío</label>
                                    <input type="text" name="direccion_envio" class="form-control" value="<?php echo htmlspecialchars($envio['direccion_envio']); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Ciudad</label>
                                    <input type="text" name="ciudad" class="form-control" value="<?php echo htmlspecialchars($envio['ciudad']); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Método de entrega</label>
                                    <select name="metodo_entrega" class="form-select" required>
                                        <option value="">Selecciona...</option>
                                        <?php
                                        $metodos = ['Envío estándar', 'Envío exprés'];
                                        foreach ($metodos as $metodo):
                                            $selected = ($envio['metodo_entrega'] === $metodo) ? 'selected' : '';
                                        ?>
                                            <option value="<?php echo htmlspecialchars($metodo); ?>" <?php echo $selected; ?>>
                                                <?php echo htmlspecialchars($metodo); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-success w-100 btn-lg icon-inline">
                                    <i class="bi bi-credit-card" aria-hidden="true"></i>
                                    <span>Proceder al pago</span>
                                </button>
                            </form>
                            <?php if (!$clienteId): ?>
                                <p class="text-muted small mt-2 mb-0">Te pediremos iniciar sesión en el siguiente paso.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (() => {
            const formActualizar = document.getElementById('form-actualizar');
            const status = document.getElementById('status-actualizacion');
            const qtyInputs = document.querySelectorAll('input[name^="cantidades"]');

            if (formActualizar && status && qtyInputs.length) {
                qtyInputs.forEach(inp => {
                    inp.addEventListener('change', () => {
                        status.textContent = 'Actualizando cantidades...';
                    });
                });
                formActualizar.addEventListener('submit', () => {
                    status.textContent = 'Actualizando cantidades...';
                });
                window.addEventListener('DOMContentLoaded', () => {
                    if (status.textContent === '') {
                        status.textContent = '';
                    }
                });
            }
        })();
    </script>
</body>
</html>
