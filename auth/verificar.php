<?php

declare(strict_types=1);

/**
 * Verificación de correo (POST con CSRF):
 *  - confirmar: con la liga del correo (pública).
 *  - reenviar:  el propio usuario, desde su panel, pide otra liga.
 */

require_once dirname(__DIR__) . '/includes/seguridad.php';
require_once dirname(__DIR__) . '/includes/verificacion.php';
require_once dirname(__DIR__) . '/app/Core/Auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('login.php');
}

$accion = (string) ($_POST['accion'] ?? 'confirmar');

if ($accion === 'reenviar') {
    $usuario = App\Core\Auth::requireUser();
    if (!csrf_valido($_POST['csrf_token'] ?? null)) {
        flash('error', 'La sesión del formulario expiró. Vuelve a intentarlo.');
        redirigir('app/index.php');
    }
    if ($usuario['email_verificado_en'] !== null) {
        flash('info', 'Tu correo ya está confirmado.');
        redirigir('app/index.php');
    }
    $clave = 'verificar-usuario:' . $usuario['id'];
    if (limite_bloqueado($clave)) {
        flash('error', 'Ya pediste varios correos. Espera 15 minutos antes de pedir otro.');
        redirigir('app/index.php');
    }
    limite_registrar_fallo($clave);
    [$ok, $detalle] = verificacion_solicitar($usuario);
    if (!$ok) {
        error_log("Correo de verificación no enviado a {$usuario['email']}: {$detalle}");
    }
    flash($ok ? 'ok' : 'error', $ok
        ? "Te enviamos un correo a {$usuario['email']}. La liga vence en " . VERIFICACION_HORAS . ' horas.'
        : 'No pudimos enviar el correo en este momento. Inténtalo más tarde.');
    redirigir('app/index.php');
}

// Confirmar con la liga
$token = (string) ($_POST['token'] ?? '');
if (!csrf_valido($_POST['csrf_token'] ?? null)) {
    redirigir('verificar.php?token=' . urlencode($token) . '&error=csrf');
}
$usuario = verificacion_confirmar($token);
redirigir($usuario === null ? 'verificar.php?token=' . urlencode($token) : 'verificar.php?listo=1');
