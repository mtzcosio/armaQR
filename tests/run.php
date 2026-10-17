<?php
/**
 * Ejecuta todas las pruebas: php tests/run.php
 * Cada prueba es un script independiente que termina con código 0 si pasa.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$php = escapeshellarg(PHP_BINARY);
$fallidas = 0;
$archivos = glob(__DIR__ . '/*Test.php');

foreach ($archivos as $archivo) {
    $salida = [];
    exec($php . ' ' . escapeshellarg($archivo) . ' 2>&1', $salida, $codigo);
    $nombre = basename($archivo, '.php');
    if ($codigo === 0) {
        echo "  OK    {$nombre}\n";
    } else {
        $fallidas++;
        echo "  FALLA {$nombre}\n        " . implode("\n        ", $salida) . "\n";
    }
}

echo "\n" . (count($archivos) - $fallidas) . ' de ' . count($archivos) . " pruebas pasaron.\n";
exit($fallidas === 0 ? 0 : 1);
