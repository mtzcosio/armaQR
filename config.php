<?php
/**
 * Configuración general del proyecto.
 * APP_NOMBRE y APP_DOMINIO son solo respaldos: el nombre y el dominio visibles se
 * configuran en Administración › Configuración › Identidad (ver nombre_portal() y dominio_portal()).
 */

define('APP_NOMBRE', 'Arma-QR');
define('APP_DOMINIO', 'armaqr.com');       // dominio de las ligas cortas: https://armaqr.com/r/{codigo}
define('APP_MONEDA', 'MXN');

// Base URL calculada automáticamente (funciona en XAMPP: http://localhost/qr-saas/)
$__raiz = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? __DIR__));
$__dir  = str_replace('\\', '/', __DIR__);
$__base = substr($__dir, strlen($__raiz));
define('BASE_URL', rtrim($__base, '/') . '/');

date_default_timezone_set('America/Mexico_City');

/** URL absoluta dentro del proyecto */
function url(string $ruta = ''): string
{
    return BASE_URL . ltrim($ruta, '/');
}

/** Escapa texto para HTML */
function e($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

/** Formato de dinero: $1,490.00 */
function dinero(float $monto, bool $decimales = true): string
{
    return '$' . number_format($monto, $decimales ? 2 : 0);
}

/** Formato de número con separador de miles */
function numero(int|float $n): string
{
    return number_format($n);
}

/** Fecha corta en español: 3 oct 2026 */
function fecha_corta(string $fecha): string
{
    $meses = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
    $t = strtotime($fecha);
    return date('j', $t) . ' ' . $meses[(int) date('n', $t) - 1] . ' ' . date('Y', $t);
}

/** Iniciales para avatar */
function iniciales(string $nombre): string
{
    $partes = preg_split('/\s+/', trim($nombre));
    $ini = mb_substr($partes[0] ?? '', 0, 1) . mb_substr($partes[1] ?? '', 0, 1);
    return mb_strtoupper($ini);
}
