<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/database/Database.php';

use App\Core\Database;

// getenv() devuelve false (no null) cuando la variable no existe, por eso se usa ?:
$name = trim($argv[1] ?? (getenv('ADMIN_NAME') ?: 'Administrador'));
$email = strtolower(trim($argv[2] ?? (getenv('ADMIN_EMAIL') ?: 'admin@armaqr.com')));
$password = $argv[3] ?? (getenv('ADMIN_PASSWORD') ?: null);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "El correo electrónico no es válido.\n");
    exit(1);
}

if ($password === null || strlen($password) < 8) {
    fwrite(STDERR, "La contraseña debe tener al menos 8 caracteres.\n");
    exit(1);
}

$pdo = Database::connection();
$passwordHash = password_hash($password, PASSWORD_DEFAULT);
if ($passwordHash === false) {
    fwrite(STDERR, "No se pudo procesar la contraseña.\n");
    exit(1);
}

$stmt = $pdo->prepare(
    'INSERT INTO usuarios
       (nombre, email, password_hash, rol, estatus, email_verificado_en,
        zona_horaria, usuario_creacion, usuario_modificacion, fecha_creacion,
        fecha_modificacion, eliminado)
       VALUES (:nombre, :email, :password_hash, "admin", "activo", NOW(),
               "America/Mexico_City", 1, 1, NOW(), NOW(), 0)
       ON DUPLICATE KEY UPDATE
           nombre = VALUES(nombre),
           password_hash = VALUES(password_hash),
           rol = "admin",
           estatus = "activo",
           email_verificado_en = NOW(),
           usuario_modificacion = 1,
           fecha_modificacion = NOW(),
           eliminado = 0'
);

$stmt->execute([
    ':nombre' => $name,
    ':email' => $email,
    ':password_hash' => $passwordHash,
]);

echo "Administrador creado: {$email}\n";
echo "Estado: activo\n";
echo "Rol: admin\n";
echo "Contraseña guardada con hash seguro\n";
