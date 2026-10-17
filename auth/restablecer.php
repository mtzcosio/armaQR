<?php

declare(strict_types=1);

/** Guarda la contraseña nueva usando una liga de recuperación vigente. */

require_once dirname(__DIR__) . '/includes/seguridad.php';
require_once dirname(__DIR__) . '/includes/recuperacion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('recuperar.php');
}

$token = (string) ($_POST['token'] ?? '');
$volver = fn(string $error) => redirigir('restablecer.php?token=' . urlencode($token) . '&error=' . $error);

if (!csrf_valido($_POST['csrf_token'] ?? null)) {
    $volver('csrf');
}

$password = (string) ($_POST['password'] ?? '');
$confirmacion = (string) ($_POST['password_confirmacion'] ?? '');
if (strlen($password) < 8) {
    $volver('corta');
}
if (strlen($password) > 72) {
    $volver('larga'); // password_hash (bcrypt) solo usa los primeros 72 bytes
}
if (!hash_equals($password, $confirmacion)) {
    $volver('distintas');
}

$usuario = recuperacion_restablecer($token, $password);
if ($usuario === null) {
    $volver('vencida');
}

// Con la contraseña nueva, se levanta cualquier bloqueo de intentos de inicio de sesión.
limite_limpiar('login-email:' . strtolower((string) $usuario['email']));
unset($_SESSION['csrf_token']);

redirigir('login.php?restablecida=1');
