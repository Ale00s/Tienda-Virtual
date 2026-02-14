<?php
session_start();

$referer = $_SERVER['HTTP_REFERER'] ?? '';
if ($referer === '' || strpos($referer, 'politica_datos.php') !== false) {
    $referer = 'productos_cliente.php';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Política de Tratamiento de Datos</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/theme.css">
</head>
<body class="bg-body-tertiary">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="productos_cliente.php">Zip Undercover</a>
        </div>
    </nav>

    <main class="container py-5">
        <div class="mb-5 text-center">
            <span class="badge bg-dark text-uppercase">Protección de datos</span>
            <h1 class="fw-bold mt-3">Política de tratamiento de datos personales</h1>
            <p class="text-muted mb-0">Última actualización: <?php echo date('d/m/Y'); ?></p>
        </div>

        <article class="bg-white shadow-sm rounded-4 p-4 p-md-5">
            <section class="mb-4">
                <h2 class="h4 fw-bold">1. Responsable del tratamiento</h2>
                <p>
                    Zip Undercover es responsable del tratamiento de tus datos personales. Si tienes dudas,
                    solicitudes o reclamos, puedes escribirnos al correo
                    <a href="mailto:alejandro.osorio3@utp.edu.co">alejandro.osorio3@utp.edu.co</a>
                    o comunicarte al teléfono +57 300 466 9975 en horario laboral.
            </p>
        </section>

        <section class="mb-4">
            <h2 class="h4 fw-bold">2. Finalidades del tratamiento</h2>
            <p>Recolectamos y usamos tus datos para:</p>
            <ul>
                <li>Crear y administrar tu cuenta de cliente o administrador.</li>
                <li>Gestionar compras, ventas, devoluciones, inventario y envíos.</li>
                <li>Comunicarnos sobre tus pedidos, cambios en el servicio o recordatorios necesarios.</li>
                <li>Cumplir obligaciones legales, contables, fiscales y de seguridad.</li>
                <li>Prevenir fraudes y proteger la integridad de la plataforma.</li>
                <li>Mejorar la experiencia del sitio mediante análisis agregados y métricas de uso.</li>
            </ul>
        </section>

        <section class="mb-4">
            <h2 class="h4 fw-bold">3. Datos recolectados</h2>
            <p>
                Solicitamos datos de identificación (nombre, documento), contacto (correo, teléfono, dirección, ciudad),
                credenciales de acceso y registros de actividad necesarios para ofrecer nuestros servicios.
                En auditoría guardamos eventos con dirección IP y agente de navegador para fines de seguridad y trazabilidad.
            </p>
        </section>

            <section class="mb-4">
                <h2 class="h4 fw-bold">4. Derechos de los titulares</h2>
                <p>Como titular puedes:</p>
                <ul>
                    <li>Conocer, actualizar y rectificar tus datos.</li>
                    <li>Solicitar prueba de la autorización otorgada.</li>
                    <li>Revocar la autorización y/o pedir la supresión del dato cuando no se respeten los principios y obligaciones.</li>
                    <li>Presentar quejas ante la autoridad competente.</li>
                </ul>
            </section>

            <section class="mb-4">
                <h2 class="h4 fw-bold">5. Tiempos de conservación</h2>
                <p>
                    Conservaremos tu información el tiempo necesario para cumplir las finalidades descritas y las
                    exigencias legales aplicables. Posteriormente, los datos serán eliminados o anonimizados.
                Los registros de transacciones y auditoría pueden conservarse por periodos mayores cuando lo requiera la ley o la seguridad del sistema.
            </p>
        </section>

        <section class="mb-4">
            <h2 class="h4 fw-bold">6. Seguridad y transferencia</h2>
                <p>
                    Implementamos controles técnicos y organizacionales para proteger la confidencialidad e integridad
                    de la información. No compartimos datos con terceros no autorizados y solo transferimos información
                    cuando es indispensable para prestar nuestros servicios o por mandato legal. Restringimos el acceso interno
                    a quienes lo necesiten y mantenemos trazas de operaciones relevantes.
            </p>
        </section>

        <section>
                <h2 class="h4 fw-bold">7. Cumplimiento normativo en Colombia</h2>
                <p>
                    Tratamos los datos conforme a la Ley 1581 de 2012, el Decreto 1377 de 2013 y demás normas
                    colombianas de protección de datos personales. Respetamos el derecho de habeas data, incluyendo
                    los derechos de conocer, actualizar, rectificar, suprimir y revocar la autorización otorgada para el
                    tratamiento de la información.
                </p>
            </section>

            <section>
                <h2 class="h4 fw-bold">7. Cambios en la política</h2>
                <p>
                    Cualquier modificación será publicada en esta página, donde siempre tendrás acceso a la versión más
                    reciente. El uso continuo de nuestros servicios implica la aceptación de la política vigente.
                </p>
            </section>
        </article>

        <section class="bg-white shadow-sm rounded-4 p-4 mt-5 text-center">
            <h2 class="h5 fw-bold mb-3">¿Deseas volver al punto anterior?</h2>
            <p class="text-muted mb-4">Regresa fácilmente al formulario o página desde la que accediste a esta política.</p>
            <a href="<?php echo htmlspecialchars($referer); ?>" class="btn btn-outline-dark px-4">Volver</a>
        </section>
    </main>

    <footer class="bg-dark text-white-50 py-4 mt-auto">
        <div class="container text-center small">
            © <?php echo date('Y'); ?> Zip Undercover · Todos los derechos reservados
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
