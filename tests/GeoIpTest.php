<?php

declare(strict_types=1);

// Ubicación por IP (includes/geoip.php): lector MMDB con bases mínimas que arma esta prueba
// (registros de 24, 28 y 32 bits; bases IPv4 e IPv6), encabezados de Cloudflare, nombres en
// español y las piezas de estadísticas (mapa de calor y ubicación). No usa la BD ni la red.
require_once dirname(__DIR__) . '/includes/estadisticas_vista.php';
require_once dirname(__DIR__) . '/includes/qr_tipos_info.php';

$fallas = [];
$prueba = function (string $nombre, bool $ok, string $detalle = '') use (&$fallas) {
    if (!$ok) $fallas[] = $nombre . ($detalle !== '' ? "  [{$detalle}]" : '');
};

/* ---------- Escritor MMDB mínimo (solo para la prueba) ---------- */
function mmdb_valor(mixed $v): string
{
    $control = fn(int $tipo, int $tam) => $tipo <= 7 ? chr(($tipo << 5) | $tam) : chr($tam) . chr($tipo - 7);
    if (is_string($v)) return $control(2, strlen($v)) . $v;
    if (is_bool($v)) return $control(14, $v ? 1 : 0);
    if (is_int($v)) {
        $bytes = ltrim(pack('N', $v), "\0");
        return $control(6, strlen($bytes)) . $bytes;
    }
    if (array_is_list($v)) return $control(11, count($v)) . implode('', array_map('mmdb_valor', $v));
    $salida = $control(7, count($v));
    foreach ($v as $k => $x) $salida .= mmdb_valor((string) $k) . mmdb_valor($x);
    return $salida;
}

/** $redes: ['1.2.3.0/24' => datos]. Devuelve la ruta del archivo generado. */
function mmdb_crear(array $redes, int $tamRegistro, int $versionIp): string
{
    $nodos = [[null, null]];
    $datos = '';
    foreach ($redes as $red => $valor) {
        [$ip, $prefijo] = explode('/', $red);
        $binario = inet_pton($ip);
        $prefijo = (int) $prefijo;
        if ($versionIp === 6 && strlen($binario) === 4) {
            $binario = str_repeat("\0", 12) . $binario;
            $prefijo += 96;
        }
        $offset = strlen($datos);
        $datos .= mmdb_valor($valor);
        $nodo = 0;
        for ($i = 0; $i < $prefijo; $i++) {
            $bit = (ord($binario[$i >> 3]) >> (7 - ($i & 7))) & 1;
            if ($i === $prefijo - 1) {
                $nodos[$nodo][$bit] = ['d', $offset];
            } else {
                if (!is_array($nodos[$nodo][$bit]) || $nodos[$nodo][$bit][0] !== 'n') {
                    $nodos[] = [null, null];
                    $nodos[$nodo][$bit] = ['n', count($nodos) - 1];
                }
                $nodo = $nodos[$nodo][$bit][1];
            }
        }
    }
    $total = count($nodos);
    $valorRegistro = fn(?array $r) => $r === null ? $total : ($r[0] === 'n' ? $r[1] : $total + 16 + $r[1]);
    $arbol = '';
    foreach ($nodos as [$izq, $der]) {
        $a = $valorRegistro($izq);
        $b = $valorRegistro($der);
        $arbol .= match ($tamRegistro) {
            24 => substr(pack('N', $a), 1) . substr(pack('N', $b), 1),
            28 => substr(pack('N', $a), 1) . chr((($a >> 24) << 4) | ($b >> 24)) . substr(pack('N', $b), 1),
            32 => pack('N', $a) . pack('N', $b),
        };
    }
    $meta = mmdb_valor(['node_count' => $total, 'record_size' => $tamRegistro, 'ip_version' => $versionIp, 'database_type' => 'Prueba',
                        'languages' => ['en', 'es'], 'binary_format_major_version' => 2, 'binary_format_minor_version' => 0, 'build_epoch' => 1]);
    $ruta = tempnam(sys_get_temp_dir(), 'mmdb');
    file_put_contents($ruta, $arbol . str_repeat("\0", 16) . $datos . "\xAB\xCD\xEFMaxMind.com" . $meta);
    return $ruta;
}

$redes = [
    '189.188.0.0/16' => ['country' => ['iso_code' => 'MX', 'names' => ['en' => 'Mexico', 'es' => 'México']],
                         'subdivisions' => [['names' => ['en' => 'Mexico City']]], 'city' => ['names' => ['en' => 'Mexico City']]],
    '187.190.0.0/16' => ['country' => ['iso_code' => 'MX'], 'subdivisions' => [['names' => ['en' => 'San Luis Potosí']]], 'city' => ['names' => ['en' => 'San Luis Potosí City']]],
    '8.8.8.0/24' => ['country' => ['iso_code' => 'US'], 'subdivisions' => [['names' => ['en' => 'California']]], 'city' => ['names' => ['en' => 'Mountain View', 'es' => 'Mountain View (es)']]],
];
$archivos = [];
foreach ([[24, 4], [28, 4], [32, 4], [24, 6], [28, 6]] as [$tam, $version]) {
    $ruta = $archivos[] = mmdb_crear($redes, $tam, $version);
    $etiqueta = "MMDB {$tam} bits IPv{$version}";
    $mx = geoip_buscar('189.188.103.229', $ruta);
    $prueba("$etiqueta: ubica una IP", $mx === ['pais' => 'MX', 'region' => 'Ciudad de México', 'ciudad' => 'Ciudad de México'], json_encode($mx, JSON_UNESCAPED_UNICODE));
    $prueba("$etiqueta: nombre en español si existe", (geoip_buscar('8.8.8.8', $ruta)['ciudad'] ?? '') === 'Mountain View (es)');
    $prueba("$etiqueta: quita «City» en países hispanos", (geoip_buscar('187.190.1.1', $ruta)['ciudad'] ?? '') === 'San Luis Potosí');
    $prueba("$etiqueta: IP fuera de la base", geoip_buscar('9.9.9.9', $ruta) === null);
    $prueba("$etiqueta: IP privada", geoip_buscar('192.168.1.10', $ruta) === null && geoip_buscar('127.0.0.1', $ruta) === null);
}
$prueba('IPv6 en base IPv4', geoip_buscar('2001:4860:4860::8888', $archivos[0]) === null);
$prueba('Sin base, sin ubicación', geoip_buscar('8.8.8.8', sys_get_temp_dir() . '/no-existe.mmdb') === null);
ini_set('error_log', sys_get_temp_dir() . '/geoip-prueba.log'); // el aviso esperado no ensucia la salida
$basura = tempnam(sys_get_temp_dir(), 'mmdb');
file_put_contents($basura, 'no es una base');
$prueba('Archivo inválido no truena', @geoip_buscar('8.8.8.8', $basura) === null);
$archivos[] = $basura;

// Encabezados de Cloudflare y respaldo con la base local.
$base = $archivos[0];
$prueba('Cloudflare con ciudad', geoip_ubicacion(['HTTP_CF_IPCOUNTRY' => 'MX', 'HTTP_CF_IPCITY' => 'Mérida', 'HTTP_CF_REGION' => 'Yucatán', 'REMOTE_ADDR' => '8.8.8.8'], $base)
    === ['pais' => 'MX', 'region' => 'Yucatán', 'ciudad' => 'Mérida']);
$prueba('Cloudflare sin ciudad usa la IP real', geoip_ubicacion(['HTTP_CF_IPCOUNTRY' => 'MX', 'HTTP_CF_CONNECTING_IP' => '189.188.1.1', 'REMOTE_ADDR' => '8.8.8.8'], $base)['ciudad'] === 'Ciudad de México');
$prueba('Cloudflare desconocido (XX)', geoip_ubicacion(['HTTP_CF_IPCOUNTRY' => 'XX', 'REMOTE_ADDR' => '8.8.8.8'], $base)['pais'] === null);
$prueba('Sin Cloudflare usa REMOTE_ADDR', geoip_ubicacion(['REMOTE_ADDR' => '8.8.8.8'], $base)['pais'] === 'US');
$prueba('Sin IP', geoip_ubicacion([], $base) === ['pais' => null, 'region' => null, 'ciudad' => null]);
array_map('unlink', $archivos);

// La base real, si ya se descargó (php database/actualizar_geoip.php).
if (is_file(GEOIP_ARCHIVO)) {
    $prueba('Base real: ubica 8.8.8.8 en EUA', (geoip_buscar('8.8.8.8')['pais'] ?? null) === 'US');
}

$prueba('Bandera', geoip_bandera('MX') === "\u{1F1F2}\u{1F1FD}" && geoip_bandera('mx') === '');
$prueba('Nombre del país', geoip_pais_nombre('MX') === 'México' && geoip_pais_nombre('ZZ') === 'ZZ');

/* ---------- Mapa de calor y ubicación ---------- */
$mapa = array_fill(0, 7, array_fill(0, 24, 0));
$mapa[5][14] = 9;
$mapa[0][9] = 3;
$html = estadisticas_mapa_calor($mapa);
$prueba('Mapa: 7 días × 24 horas', substr_count($html, '<td style="--p:') === 168 && substr_count($html, '<th scope="row">') === 7);
$prueba('Mapa: intensidad proporcional', str_contains($html, '--p:100%" title="Sábado de 14:00 a 15:00: 9 escaneos"') && str_contains($html, '--p:33%" title="Lunes de 09:00 a 10:00: 3 escaneos"'));
$prueba('Mapa: momento con más escaneos', str_contains($html, 'sábado de 14:00 a 15:00'));
$prueba('Mapa vacío', str_contains(estadisticas_mapa_calor(array_fill(0, 7, array_fill(0, 24, 0))), 'Aún no hay escaneos'));

$ubicacion = estadisticas_ubicacion(['paises' => ['MX' => 10, 'Otros' => 2], 'ciudades' => [['ciudad' => '<b>Puebla</b>', 'region' => 'Puebla', 'pais' => 'MX', 'n' => 4]], 'sin_ubicacion' => 3]);
$prueba('Ubicación: país con bandera y nombre', str_contains($ubicacion, "\u{1F1F2}\u{1F1FD} México") && str_contains($ubicacion, 'Otros países'));
$prueba('Ubicación: escapa la ciudad', str_contains($ubicacion, '&lt;b&gt;Puebla') && !str_contains($ubicacion, '<b>Puebla'));
$prueba('Ubicación: sin ubicación y crédito de DB-IP', str_contains($ubicacion, '3 escaneos sin ubicación') && str_contains($ubicacion, 'IP Geolocation by DB-IP'));
$prueba('Ubicación: sin crédito en ejemplos', !str_contains(estadisticas_ubicacion(['paises' => [], 'ciudades' => []], false), 'DB-IP'));

/* ---------- Estadísticas de ejemplo: mapa y ubicación cuadran con el total ---------- */
foreach (['menu', 'evento', 'email'] as $tipo) {
    $est = qr_tipo_ejemplo_estadisticas($tipo, 30, new DateTimeImmutable('2026-11-15'));
    $enMapa = array_sum(array_map('array_sum', $est['mapa']));
    $prueba("$tipo: el mapa suma el total", abs($enMapa - $est['total']) <= 24, "$enMapa vs {$est['total']}");
    $prueba("$tipo: países + sin ubicación = total", array_sum($est['paises']) + $est['sin_ubicacion'] === $est['total']);
}

if ($fallas) {
    fwrite(STDERR, "Fallaron:\n  - " . implode("\n  - ", $fallas) . "\n");
    exit(1);
}
echo "Ubicación por IP (MMDB), Cloudflare, mapa de calor y ubicación en estadísticas: OK\n";
