<?php
/**
 * Utilidades de seguridad compartidas: redirecciones sin caché, tokens CSRF,
 * mensajes flash y límite de intentos de inicio de sesión.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli') {
    session_start();
}

/** Redirige (303 por defecto) a una ruta del proyecto sin permitir caché y termina. */
function redirigir(string $ruta, int $codigo = 303): never
{
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Location: ' . url($ruta), true, $codigo);
    exit;
}

/** Token CSRF de la sesión; se crea la primera vez que se pide. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Campo oculto listo para insertar en un formulario. */
function csrf_campo(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_valido(?string $enviado): bool
{
    $guardado = (string) ($_SESSION['csrf_token'] ?? '');
    return $guardado !== '' && is_string($enviado) && hash_equals($guardado, $enviado);
}

/** Guarda un mensaje para mostrarlo en la siguiente página. $tipo: ok | error | info */
function flash(string $tipo, string $mensaje): void
{
    $_SESSION['flash'] = ['tipo' => $tipo, 'mensaje' => $mensaje];
}

/** Devuelve y elimina el mensaje pendiente. */
function flash_tomar(): ?array
{
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($f) ? $f : null;
}

/* ---------------------------------------------------------------------
 * Límite de intentos fallidos (por IP y por correo).
 * Se guarda en archivos temporales para no depender de la sesión,
 * que el atacante puede descartar en cada intento.
 * ------------------------------------------------------------------- */

const LIMITE_INTENTOS = 5;
const LIMITE_VENTANA  = 900; // segundos (15 minutos)

function limite_directorio(): string
{
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'armaqr-limites';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    return $dir;
}

function limite_archivo(string $clave): string
{
    return limite_directorio() . DIRECTORY_SEPARATOR . hash('sha256', $clave) . '.json';
}

/** Intentos fallidos vigentes (dentro de la ventana) para una clave. */
function limite_intentos(string $clave): array
{
    $archivo = limite_archivo($clave);
    $datos = is_file($archivo) ? json_decode((string) file_get_contents($archivo), true) : [];
    $desde = time() - LIMITE_VENTANA;
    return array_values(array_filter(is_array($datos) ? $datos : [], fn($t) => is_int($t) && $t > $desde));
}

function limite_bloqueado(string ...$claves): bool
{
    foreach ($claves as $clave) {
        if (count(limite_intentos($clave)) >= LIMITE_INTENTOS) {
            return true;
        }
    }
    return false;
}

function limite_registrar_fallo(string ...$claves): void
{
    foreach ($claves as $clave) {
        $intentos = limite_intentos($clave);
        $intentos[] = time();
        file_put_contents(limite_archivo($clave), json_encode($intentos), LOCK_EX);
    }
}

function limite_limpiar(string ...$claves): void
{
    foreach ($claves as $clave) {
        @unlink(limite_archivo($clave));
    }
}
