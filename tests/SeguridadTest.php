<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/seguridad.php';

$fallas = [];

// CSRF
$_SESSION = [];
$token = csrf_token();
if (strlen($token) !== 64 || csrf_token() !== $token) {
    $fallas[] = 'csrf_token() debe generar un token estable de 64 caracteres.';
}
if (!csrf_valido($token) || csrf_valido('otro') || csrf_valido(null) || csrf_valido('')) {
    $fallas[] = 'csrf_valido() no valida correctamente.';
}
$_SESSION = [];
if (csrf_valido('')) {
    $fallas[] = 'Sin token en sesión, nada debe validar.';
}

// Flash
flash('ok', 'Hecho');
$f = flash_tomar();
if (($f['mensaje'] ?? '') !== 'Hecho' || flash_tomar() !== null) {
    $fallas[] = 'flash_tomar() debe devolver el mensaje una sola vez.';
}

// Límite de intentos
$clave = 'prueba:' . bin2hex(random_bytes(6));
for ($i = 0; $i < LIMITE_INTENTOS - 1; $i++) {
    limite_registrar_fallo($clave);
}
if (limite_bloqueado($clave)) {
    $fallas[] = 'No debe bloquear antes de ' . LIMITE_INTENTOS . ' intentos.';
}
limite_registrar_fallo($clave);
if (!limite_bloqueado($clave)) {
    $fallas[] = 'Debe bloquear al llegar a ' . LIMITE_INTENTOS . ' intentos.';
}
limite_limpiar($clave);
if (limite_bloqueado($clave)) {
    $fallas[] = 'limite_limpiar() debe quitar el bloqueo.';
}

if ($fallas) {
    fwrite(STDERR, implode("\n", $fallas) . "\n");
    exit(1);
}
echo "Seguridad (CSRF, flash, límite de intentos): OK\n";
