<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Auth.php';
require_once dirname(__DIR__) . '/app/Models/DatabaseRepository.php';

use App\Core\Auth;
use App\Models\DatabaseRepository;

// Usa un usuario real de la base en lugar de suponer un id fijo.
$usuarios = (new DatabaseRepository())->usuarios();
if ($usuarios === []) {
    fwrite(STDERR, "No hay usuarios en la base para probar la sesión.\n");
    exit(1);
}
$esperado = (int) $usuarios[0]['id'];

session_id('authentication-test');
session_start();
$_SESSION['usuario_id'] = $esperado;

$user = Auth::user();
if ($user === null || (int) $user['id'] !== $esperado) {
    fwrite(STDERR, "La sesión no devuelve el usuario correcto.\n");
    exit(1);
}

if (Auth::isAdmin() !== (Auth::isActive($user) && Auth::isAdminRole($user))) {
    fwrite(STDERR, "isAdmin() debe exigir rol admin y estatus activo.\n");
    exit(1);
}

if (Auth::isActive(['estatus' => 'suspendido']) || Auth::isActive(null)) {
    fwrite(STDERR, "Un usuario suspendido o inexistente no debe considerarse activo.\n");
    exit(1);
}

$_SESSION = [];
session_destroy();

fwrite(STDOUT, "Authentication test passed: user {$esperado} loaded from session.\n");
