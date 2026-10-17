<?php
/**
 * RECUPERACIÓN DE CONTRASEÑA
 * --------------------------------------------------------------------
 * 1. recuperacion_solicitar(): crea un token de un solo uso y envía el correo
 *    RECUPERAR_CONTRASENA con la liga restablecer.php?token=...
 * 2. recuperacion_usuario_por_token(): valida la liga (existe, no usada, no vencida).
 * 3. recuperacion_restablecer(): cambia la contraseña y gasta el token.
 * La mecánica de tokens (hash, vencimiento, un solo uso) está en includes/tokens.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/correo.php';
require_once __DIR__ . '/tokens.php';

const RECUPERACION_MINUTOS = 60;

/** Usuario activo por correo, o null. */
function recuperacion_usuario_por_correo(string $email): ?array
{
    return datos_consulta(
        "SELECT id, nombre, email FROM usuarios
         WHERE email = :email AND eliminado = 0 AND estatus = 'activo' LIMIT 1",
        [':email' => strtolower(trim($email))]
    )[0] ?? null;
}

/** Anula las ligas de recuperación pendientes de un usuario. */
function recuperacion_anular_tokens(int $usuarioId): void
{
    token_anular($usuarioId, TOKEN_RECUPERACION);
}

/** Crea una liga nueva (anulando las anteriores) y devuelve el token en claro. */
function recuperacion_crear_token(int $usuarioId): string
{
    return token_crear($usuarioId, TOKEN_RECUPERACION, RECUPERACION_MINUTOS);
}

/** Crea la liga y envía el correo. $usuario: id, nombre, email. Devuelve [bool, mensaje]. */
function recuperacion_solicitar(array $usuario): array
{
    $token = recuperacion_crear_token((int) $usuario['id']);
    return correo_recuperar_contrasena($usuario, correo_url('restablecer.php?token=' . $token), RECUPERACION_MINUTOS);
}

/** Usuario dueño de una liga vigente, o null. */
function recuperacion_usuario_por_token(string $token): ?array
{
    return token_usuario($token, TOKEN_RECUPERACION);
}

/** Cambia la contraseña si la liga sigue vigente y la gasta. Devuelve el usuario o null. */
function recuperacion_restablecer(string $token, string $password): ?array
{
    return token_usar($token, TOKEN_RECUPERACION, function (array $usuario) use ($password): void {
        datos_pdo()->prepare(
            'UPDATE usuarios SET password_hash = :p, usuario_modificacion = :u, fecha_modificacion = NOW() WHERE id = :u2'
        )->execute([':p' => password_hash($password, PASSWORD_DEFAULT), ':u' => $usuario['id'], ':u2' => $usuario['id']]);
    });
}
