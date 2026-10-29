<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * Descarga la base de ubicaciones por IP "IP to City Lite" de DB-IP (gratuita, licencia
 * CC BY 4.0: la atribución va en app/estadisticas.php) y la deja en database/geoip/ciudades.mmdb.
 * DB-IP publica una versión nueva cada mes: conviene correrlo con cron el día 2 o 3.
 * Solo reemplaza la base si la nueva se puede leer; si falla, la anterior sigue funcionando.
 *
 *   php database/actualizar_geoip.php
 */

require_once dirname(__DIR__) . '/includes/geoip.php';

$carpeta = __DIR__ . '/geoip';
if (!is_dir($carpeta) && !mkdir($carpeta, 0755, true)) {
    fwrite(STDERR, "No se pudo crear {$carpeta}.\n");
    exit(1);
}
$gz = "{$carpeta}/descarga.mmdb.gz";
$nueva = "{$carpeta}/nueva.mmdb";

/** Descarga $url en $destino; devuelve el código HTTP. */
$descargar = function (string $url, string $destino): int {
    $archivo = fopen($destino, 'wb');
    $curl = curl_init($url);
    curl_setopt_array($curl, [CURLOPT_FILE => $archivo, CURLOPT_FOLLOWLOCATION => true, CURLOPT_FAILONERROR => true,
                              CURLOPT_CONNECTTIMEOUT => 20, CURLOPT_TIMEOUT => 600, CURLOPT_USERAGENT => 'Arma-QR/1.0']);
    curl_exec($curl);
    $codigo = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    fclose($archivo);
    return $codigo;
};

// La del mes actual; los primeros días puede no estar publicada todavía: entonces la del mes anterior.
$descargada = null;
foreach ([new DateTimeImmutable('first day of this month'), new DateTimeImmutable('first day of last month')] as $mes) {
    $url = 'https://download.db-ip.com/free/dbip-city-lite-' . $mes->format('Y-m') . '.mmdb.gz';
    echo "Descargando {$url}…\n";
    if ($descargar($url, $gz) === 200 && filesize($gz) > 1048576) {
        $descargada = $mes->format('Y-m');
        break;
    }
}
if ($descargada === null) {
    @unlink($gz);
    fwrite(STDERR, "No se pudo descargar la base de DB-IP. La anterior sigue en uso.\n");
    exit(1);
}

// Descomprime por partes (la base pesa más de 100 MB).
$entrada = gzopen($gz, 'rb');
$salida = fopen($nueva, 'wb');
while (!gzeof($entrada)) {
    fwrite($salida, (string) gzread($entrada, 1048576));
}
gzclose($entrada);
fclose($salida);
unlink($gz);

// Antes de reemplazar, comprueba que se puede leer y que ubica una IP conocida.
try {
    $prueba = geoip_buscar('8.8.8.8', $nueva);
    if (($prueba['pais'] ?? null) === null) {
        throw new RuntimeException('no ubicó la IP de prueba');
    }
} catch (Throwable $exception) {
    @unlink($nueva);
    fwrite(STDERR, "La base descargada no es válida ({$exception->getMessage()}). La anterior sigue en uso.\n");
    exit(1);
}
if (!rename($nueva, GEOIP_ARCHIVO)) {
    @unlink($nueva);
    fwrite(STDERR, "No se pudo reemplazar la base. La anterior sigue en uso.\n");
    exit(1);
}
printf("Base de ubicaciones %s lista (%.0f MB).\n", $descargada, filesize(GEOIP_ARCHIVO) / 1048576);
