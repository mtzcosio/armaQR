<?php
/**
 * VERIFICACIÓN DE CORREO
 * --------------------------------------------------------------------
 * Mismo patrón que la recuperación de contraseña (includes/tokens.php):
 * 1. verificacion_solicitar(): crea un token de un solo uso y envía VERIFICAR_CORREO
 *    con la liga verificar.php?token=...
 * 2. verificar.php muestra un botón; la confirmación es por POST para que los
 *    revisores automáticos de enlaces de algunos correos no la "usen" al abrir el mensaje.
 * 3. verificacion_confirmar(): marca el correo como verificado, gasta el token y
 *    envía la bienvenida.
 * La cuenta funciona desde el registro: verificar no bloquea el acceso.
 */

declare(strict_types=1);

require_once __DIR__ . '/correo.php';
require_once __DIR__ . '/tokens.php';

const VERIFICACION_HORAS = 24;

/** Crea la liga y envía el correo. $usuario: id, nombre, email. Devuelve [bool, mensaje]. */
function verificacion_solicitar(array $usuario): array
{
    $token = token_crear((int) $usuario['id'], TOKEN_VERIFICACION, VERIFICACION_HORAS * 60);
    return correo_verificar_correo($usuario, correo_url('verificar.php?token=' . $token), VERIFICACION_HORAS);
}

/** Usuario dueño de una liga vigente, o null. */
function verificacion_usuario_por_token(string $token): ?array
{
    return token_usuario($token, TOKEN_VERIFICACION);
}

/** Marca el correo como verificado (si no lo estaba). */
function verificacion_marcar(int $usuarioId, ?int $adminId = null): void
{
    datos_pdo()->prepare(
        'UPDATE usuarios SET email_verificado_en = NOW(), usuario_modificacion = :quien, fecha_modificacion = NOW()
         WHERE id = :id AND email_verificado_en IS NULL'
    )->execute([':quien' => $adminId ?? $usuarioId, ':id' => $usuarioId]);
    token_anular($usuarioId, TOKEN_VERIFICACION);
}

/**
 * Confirma el correo con una liga vigente y la gasta. Si es la primera vez, envía la
 * bienvenida (sin interrumpir si el correo falla). Devuelve el usuario o null.
 */
function verificacion_confirmar(string $token): ?array
{
    $usuario = token_usar($token, TOKEN_VERIFICACION, function (array $usuario): void {
        datos_pdo()->prepare(
            'UPDATE usuarios SET email_verificado_en = NOW(), fecha_modificacion = NOW()
             WHERE id = :id AND email_verificado_en IS NULL'
        )->execute([':id' => $usuario['id']]);
    });

    if ($usuario !== null && $usuario['email_verificado_en'] === null) {
        try {
            $codigoPlan = datos_usuario((int) $usuario['id'])['plan'] ?? 'gratis';
            $plan = datos_planes()[$codigoPlan]['nombre'] ?? 'Gratis';
            [$ok, $detalle] = correo_bienvenida($usuario + ['plan' => $plan]);
            if (!$ok) {
                error_log("Correo de bienvenida no enviado a {$usuario['email']}: {$detalle}");
            }
        } catch (Throwable $exception) {
            error_log('Correo de bienvenida con error: ' . $exception->getMessage());
        }
    }
    return $usuario;
}
