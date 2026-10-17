<?php
/**
 * TOKENS DE UN SOLO USO (tabla tokens_usuario)
 * --------------------------------------------------------------------
 * Base común de la recuperación de contraseña y la verificación de correo.
 *  - En la BD solo se guarda sha256(token); el token en claro viaja solo en el correo.
 *  - El vencimiento usa la hora de la BD (NOW()), así no depende de la zona horaria de PHP.
 *  - Crear un token nuevo anula los pendientes del mismo tipo; usarlo anula los demás.
 *  - Solo cuentan las cuentas activas y no eliminadas.
 */

declare(strict_types=1);

require_once __DIR__ . '/datos.php';

const TOKEN_RECUPERACION = 'recuperacion_password';
const TOKEN_VERIFICACION = 'verificacion_email';

function token_hash(string $token): string
{
    return hash('sha256', $token);
}

/** Formato válido: 64 caracteres hexadecimales. */
function token_formato_valido(string $token): bool
{
    return (bool) preg_match('/^[a-f0-9]{64}$/', $token);
}

/** Anula los tokens pendientes de un tipo para un usuario. */
function token_anular(int $usuarioId, string $tipo): void
{
    datos_pdo()->prepare(
        'UPDATE tokens_usuario SET usado_en = NOW() WHERE usuario_id = :u AND tipo = :t AND usado_en IS NULL'
    )->execute([':u' => $usuarioId, ':t' => $tipo]);
}

/** Crea un token nuevo (anulando los anteriores del mismo tipo) y devuelve el token en claro. */
function token_crear(int $usuarioId, string $tipo, int $minutos): string
{
    token_anular($usuarioId, $tipo);
    $token = bin2hex(random_bytes(32));
    datos_pdo()->prepare(
        'INSERT INTO tokens_usuario (usuario_id, tipo, token_hash, expira_en)
         VALUES (:u, :t, :h, NOW() + INTERVAL ' . $minutos . ' MINUTE)'
    )->execute([':u' => $usuarioId, ':t' => $tipo, ':h' => token_hash($token)]);
    return $token;
}

/** SQL del dueño de un token vigente (no usado, no vencido, cuenta activa). */
function token_sql_vigente(string $sufijo = ''): string
{
    return "SELECT t.id AS token_id, u.id, u.nombre, u.email, u.email_verificado_en
            FROM tokens_usuario t JOIN usuarios u ON u.id = t.usuario_id
            WHERE t.token_hash = :h AND t.tipo = :t AND t.usado_en IS NULL AND t.expira_en > NOW()
              AND u.eliminado = 0 AND u.estatus = 'activo'
            LIMIT 1{$sufijo}";
}

/** Usuario dueño de un token vigente, o null. */
function token_usuario(string $token, string $tipo): ?array
{
    if (!token_formato_valido($token)) {
        return null;
    }
    return datos_consulta(token_sql_vigente(), [':h' => token_hash($token), ':t' => $tipo])[0] ?? null;
}

/**
 * Gasta un token vigente y ejecuta $accion($usuario) en la misma transacción, con bloqueo
 * para que no pueda usarse dos veces a la vez. Devuelve el usuario o null si ya no sirve.
 */
function token_usar(string $token, string $tipo, callable $accion): ?array
{
    if (!token_formato_valido($token)) {
        return null;
    }
    $pdo = datos_pdo();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(token_sql_vigente(' FOR UPDATE'));
        $stmt->execute([':h' => token_hash($token), ':t' => $tipo]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($usuario === null) {
            $pdo->rollBack();
            return null;
        }
        $accion($usuario);
        token_anular((int) $usuario['id'], $tipo);
        $pdo->commit();
        return $usuario;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}
