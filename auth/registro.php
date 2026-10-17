<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/seguridad.php';
require_once dirname(__DIR__) . '/app/Models/DatabaseRepository.php';
require_once dirname(__DIR__) . '/includes/verificacion.php';

use App\Models\DatabaseRepository;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('registro.php');
}

if (!csrf_valido($_POST['csrf_token'] ?? null)) {
    redirigir('registro.php?error=csrf');
}

$claveIp = 'registro-ip:' . ($_SERVER['REMOTE_ADDR'] ?? 'desconocida');
if (limite_bloqueado($claveIp)) {
    redirigir('registro.php?error=bloqueado');
}

$nombre = trim((string) ($_POST['nombre'] ?? ''));
$email = strtolower(trim((string) ($_POST['email'] ?? '')));
$password = (string) ($_POST['password'] ?? '');

if ($nombre === '' || mb_strlen($nombre) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
    redirigir('registro.php?error=invalid');
}

$repository = new DatabaseRepository();
if ($repository->usuarioPorEmail($email) !== null) {
    limite_registrar_fallo($claveIp);
    redirigir('registro.php?error=duplicate');
}

try {
    // La cuenta funciona desde ahora; email_verificado_en se llena cuando confirma su correo.
    $usuarioId = $repository->registrarUsuario([
        'nombre' => $nombre,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'rol' => 'cliente',
        'estatus' => 'activo',
        'zona_horaria' => 'America/Mexico_City',
    ]);
} catch (Throwable $exception) {
    error_log('Registro fallido: ' . $exception->getMessage());
    redirigir('registro.php?error=internal');
}

// Correo para confirmar la dirección (VERIFICAR_CORREO); la bienvenida sale al confirmarla.
// Si falla, la cuenta ya existe: no se interrumpe el registro, solo se deja constancia en el log.
try {
    [$enviado, $detalle] = verificacion_solicitar(['id' => $usuarioId, 'nombre' => $nombre, 'email' => $email]);
    if (!$enviado) {
        error_log("Correo de verificación no enviado a {$email}: {$detalle}");
    }
} catch (Throwable $exception) {
    error_log('Correo de verificación con error: ' . $exception->getMessage());
}

redirigir('login.php?registered=1');
