<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/database/Database.php';

use App\Core\Database;

$password = $argv[1] ?? '';
$email = strtolower(trim($argv[2] ?? (getenv('ADMIN_EMAIL') ?: 'admin@armaqr.com')));
if ($password === '') {
    fwrite(STDERR, "La contraseña es obligatoria.\n");
    exit(1);
}

$pdo = Database::connection();
$stmt = $pdo->prepare(
    'SELECT nombre, email, password_hash, rol, estatus, email_verificado_en
     FROM usuarios
     WHERE email = :email AND eliminado = 0'
);
$stmt->execute([':email' => $email]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row === false) {
    fwrite(STDERR, "El administrador no existe.\n");
    exit(1);
}

if ($row['rol'] !== 'admin' || $row['estatus'] !== 'activo') {
    fwrite(STDERR, "El usuario no tiene un rol administrador activo.\n");
    exit(1);
}

if ($row['email_verificado_en'] === null) {
    fwrite(STDERR, "El correo del administrador no está verificado.\n");
    exit(1);
}

if (!password_verify($password, $row['password_hash'])) {
    fwrite(STDERR, "La contraseña almacenada no coincide.\n");
    exit(1);
}

echo "Administrador verificado: " . $row['email'] . PHP_EOL;
echo "Rol: " . $row['rol'] . PHP_EOL;
echo "Estado: " . $row['estatus'] . PHP_EOL;
echo "Hash almacenado: presente" . PHP_EOL;
