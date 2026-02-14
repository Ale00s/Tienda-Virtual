<?php
$host = '127.0.0.1';
$db   = 'zip_undercover';
$user = 'zipuser';
$pass = 'a31415926535B'; // <-- usa la que pusiste en MariaDB
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // errores como excepciones
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // arrays asociativos
    PDO::ATTR_EMULATE_PREPARES   => false,                  // usar consultas preparadas reales
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die('Error de conexión: ' . $e->getMessage());
}


function registrar_auditoria(PDO $pdo, array $data): void
{
    try {
        $entidadId = null;
        if (isset($data['entidad_id']) && is_numeric($data['entidad_id'])) {
            $entidadId = (int)$data['entidad_id'];
        }
        $stmt = $pdo->prepare("INSERT INTO Auditoria
            (UsuarioId, Rol, Accion, Entidad, EntidadId, Datos, Ip, UserAgent)
            VALUES
            (:uid, :rol, :accion, :entidad, :entidadId, :datos, :ip, :ua)");

        $datosJson = null;
        if (isset($data['datos'])) {
            $datosJson = is_string($data['datos']) ? $data['datos'] : json_encode($data['datos'], JSON_UNESCAPED_UNICODE);
        }

        $stmt->execute([
            ':uid'       => $data['usuario_id'] ?? null,
            ':rol'       => $data['rol'] ?? null,
            ':accion'    => $data['accion'] ?? null,
            ':entidad'   => $data['entidad'] ?? null,
            ':entidadId' => $entidadId,
            ':datos'     => $datosJson,
            ':ip'        => $_SERVER['REMOTE_ADDR'] ?? null,
            ':ua'        => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        ]);
    } catch (Throwable $e) {

    }
}
?>
