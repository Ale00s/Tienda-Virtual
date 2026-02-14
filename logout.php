<?php
session_start();

$esAdmin = isset($_SESSION['admin_id']);
$esCliente = isset($_SESSION['cliente_id']);
if ($esAdmin || $esCliente) {
    require 'db.php';
    if ($esAdmin) {
        registrar_auditoria($pdo, [
            'usuario_id' => $_SESSION['admin_id'],
            'rol' => 'ADMIN',
            'accion' => 'LOGOUT',
            'entidad' => 'Administrador',
            'entidad_id' => $_SESSION['admin_id']
        ]);
    } else {
        registrar_auditoria($pdo, [
            'usuario_id' => $_SESSION['cliente_id'],
            'rol' => 'CLIENTE',
            'accion' => 'LOGOUT',
            'entidad' => 'Cliente',
            'entidad_id' => $_SESSION['cliente_id']
        ]);
    }
}


session_unset();
session_destroy();

$destino = $esAdmin ? 'login_admin.php' : 'login_cliente.php';
header("Location: $destino");
exit;
