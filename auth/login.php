<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/seguridad.php';
require_once dirname(__DIR__) . '/app/Models/DatabaseRepository.php';

use App\Models\DatabaseRepository;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('login.php');
}

if (!csrf_valido($_POST['csrf_token'] ?? null)) {
    redirigir('login.php?error=csrf');
}

$email = strtolower(trim((string) ($_POST['email'] ?? '')));
$password = (string) ($_POST['password'] ?? '');
$claveIp = 'login-ip:' . ($_SERVER['REMOTE_ADDR'] ?? 'desconocida');
$claveEmail = 'login-email:' . $email;

if (limite_bloqueado($claveIp, $claveEmail)) {
    redirigir('login.php?error=bloqueado');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
    limite_registrar_fallo($claveIp, $claveEmail);
    redirigir('login.php?error=invalid');
}

$repository = new DatabaseRepository();
$usuario = $repository->usuarioPorEmail($email);

if ($usuario === null || !password_verify($password, $usuario['password_hash'])) {
    limite_registrar_fallo($claveIp, $claveEmail);
    redirigir('login.php?error=invalid');
}

if ($usuario['estatus'] === 'pendiente') {
    redirigir('login.php?error=pendiente');
}

if ($usuario['estatus'] !== 'activo' || (int) ($usuario['eliminado'] ?? 0) === 1) {
    redirigir('login.php?error=suspended');
}

limite_limpiar($claveIp, $claveEmail);

session_regenerate_id(true);
$_SESSION['usuario_id'] = (int) $usuario['id'];
$_SESSION['usuario_email'] = $usuario['email'];
$_SESSION['usuario_rol'] = $usuario['rol'];
$_SESSION['usuario_nombre'] = $usuario['nombre'];
unset($_SESSION['csrf_token']);

$repository->actualizarUltimoAcceso((int) $usuario['id']);

redirigir($usuario['rol'] === 'admin' ? 'admin/index.php' : 'app/index.php');
