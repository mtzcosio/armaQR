<?php

declare(strict_types=1);

/**
 * Solicitud de liga para restablecer la contraseña.
 *  - Pública (recuperar.php): siempre responde lo mismo, exista o no la cuenta,
 *    para no revelar qué correos están registrados.
 *  - Desde el admin (accion=admin): envía la liga a un usuario concreto.
 */

require_once dirname(__DIR__) . '/includes/seguridad.php';
require_once dirname(__DIR__) . '/includes/recuperacion.php';
require_once dirname(__DIR__) . '/app/Core/Auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('recuperar.php');
}

$desdeAdmin = ($_POST['accion'] ?? '') === 'admin';

if (!csrf_valido($_POST['csrf_token'] ?? null)) {
    if ($desdeAdmin) {
        flash('error', 'La sesión del formulario expiró. Vuelve a intentarlo.');
        redirigir('admin/usuarios.php');
    }
    redirigir('recuperar.php?error=csrf');
}

/* ---------- Desde el panel de administración ---------- */
if ($desdeAdmin) {
    App\Core\Auth::requireAdmin();
    $id = (int) ($_POST['usuario_id'] ?? 0);
    $usuario = datos_consulta(
        'SELECT id, nombre, email, estatus FROM usuarios WHERE id = :id AND eliminado = 0',
        [':id' => $id]
    )[0] ?? null;

    if ($usuario === null) {
        flash('error', 'El usuario no existe.');
        redirigir('admin/usuarios.php');
    }
    if ($usuario['estatus'] !== 'activo') {
        flash('error', 'La cuenta no está activa: no podría usar la liga para entrar.');
        redirigir('admin/usuario.php?id=' . $id);
    }
    [$ok, $detalle] = recuperacion_solicitar($usuario);
    flash($ok ? 'ok' : 'error', $ok
        ? "Enviamos a {$usuario['email']} una liga para elegir contraseña nueva (vence en " . RECUPERACION_MINUTOS . ' minutos).'
        : 'No se pudo enviar el correo: ' . $detalle);
    redirigir('admin/usuario.php?id=' . $id);
}

/* ---------- Solicitud pública ---------- */
$email = strtolower(trim((string) ($_POST['email'] ?? '')));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirigir('recuperar.php?error=invalid');
}

// Cada solicitud cuenta (no solo las fallidas): así nadie puede llenar un buzón de correos.
$claveIp = 'recuperar-ip:' . ($_SERVER['REMOTE_ADDR'] ?? 'desconocida');
$claveEmail = 'recuperar-email:' . $email;
if (limite_bloqueado($claveIp, $claveEmail)) {
    redirigir('recuperar.php?error=bloqueado');
}
limite_registrar_fallo($claveIp, $claveEmail);

$usuario = recuperacion_usuario_por_correo($email);
if ($usuario !== null) {
    try {
        [$ok, $detalle] = recuperacion_solicitar($usuario);
        if (!$ok) {
            error_log("Correo de recuperación no enviado a {$email}: {$detalle}");
        }
    } catch (Throwable $exception) {
        error_log('Recuperación de contraseña con error: ' . $exception->getMessage());
    }
}

redirigir('recuperar.php?enviado=1');
