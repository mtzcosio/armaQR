<?php

declare(strict_types=1);

// Crear QR desde CSV: lectura y validación (no toca la BD).
require_once dirname(__DIR__) . '/includes/qr_csv.php';

$fallas = [];
$prueba = function (string $nombre, bool $ok, string $detalle = '') use (&$fallas) {
    if (!$ok) $fallas[] = $nombre . ($detalle !== '' ? "  [{$detalle}]" : '');
};

// --- Lectura ---
$excel = "\xEF\xBB\xBFNombre;Destino;Tipo;Color;Fondo\r\nMenú terraza;tacoselguero.mx/menú;dinámico;#E4007C;#ffffff\r\n\r\n\"Carta; vinos\";\"https://x.mx/a;b\";estático;;\r\n";
$r = qr_csv_leer($excel);
$prueba('Excel en español (BOM, ";" y CRLF)', $r['error'] === null && count($r['filas']) === 2);
$prueba('Encabezados sin importar mayúsculas', $r['filas'][0]['nombre'] === 'Menú terraza' && $r['filas'][0]['color'] === '#E4007C');
$prueba('Campos entre comillas con ";" dentro', $r['filas'][1]['nombre'] === 'Carta; vinos' && $r['filas'][1]['destino'] === 'https://x.mx/a;b');
$prueba('Números de fila como en Excel (salta la vacía)', $r['filas'][0]['fila'] === 2 && $r['filas'][1]['fila'] === 4);

$r = qr_csv_leer("nombre,url\nUno,https://a.mx\n\"Dos, con coma\",https://b.mx\n");
$prueba('Separador "," y columna "url" como destino', $r['error'] === null && $r['filas'][1]['nombre'] === 'Dos, con coma' && $r['filas'][0]['destino'] === 'https://a.mx');

$r = qr_csv_leer(mb_convert_encoding("nombre;destino\nCafé Señorial;https://café.mx\n", 'Windows-1252', 'UTF-8'));
$prueba('Codificación de Excel en Windows (acentos)', $r['error'] === null && $r['filas'][0]['nombre'] === 'Café Señorial', $r['filas'][0]['nombre'] ?? '');

$r = qr_csv_leer("nombre;destino\n\"Línea 1\nLínea 2\";https://a.mx\n");
$prueba('Salto de línea dentro de una celda', $r['error'] === null && $r['filas'][0]['nombre'] === "Línea 1\nLínea 2");

$r = qr_csv_leer("Nombre\tDestino\nUno\thttps://a.mx\n");
$prueba('Separador tabulador', $r['error'] === null && $r['filas'][0]['destino'] === 'https://a.mx');

$prueba('Archivo vacío', qr_csv_leer("  \n ")['error'] !== null);
$prueba('Sin encabezados requeridos', str_contains((string) qr_csv_leer("titulo;liga2\nx;y\n")['error'], 'nombre'));
$prueba('Solo encabezados', str_contains((string) qr_csv_leer("nombre;destino\n")['error'], 'al menos una fila'));
$muchas = "nombre;destino\n" . str_repeat("x;https://a.mx\n", 6);
$prueba('Más filas que el máximo', str_contains((string) qr_csv_leer($muchas, 5)['error'], 'más de 5 filas'));

// --- Validación ---
$filas = qr_csv_leer("nombre;destino;tipo;color\nUno;a.mx;;\nDos;https://b.mx;estatico;172036\n")['filas'];
$v = qr_csv_validar($filas, 'dinamico', true);
$prueba('Filas válidas sin errores', $v['errores'] === [] && count($v['qr']) === 2);
$prueba('Sin tipo usa el predeterminado', $v['qr'][0]['tipo'] === 'dinamico');
$prueba('Destino sin https:// se completa', $v['qr'][0]['contenido']['texto'] === 'https://a.mx');
$prueba('Color sin # se acepta', $v['qr'][1]['diseno']['color'] === '#172036');

$filas = qr_csv_leer("nombre;destino;tipo;color\n;hola;raro;rojo\nOk;https://ok.mx;;\n")['filas'];
$v = qr_csv_validar($filas, 'dinamico', true);
$prueba('Reporta todos los problemas de una fila, con su número', count($v['errores']) === 1
    && str_starts_with($v['errores'][0], 'Fila 2:') && str_contains($v['errores'][0], 'falta el nombre')
    && str_contains($v['errores'][0], 'no existe') && str_contains($v['errores'][0], 'no es una dirección web')
    && str_contains($v['errores'][0], 'rojo'), $v['errores'][0] ?? '');
$v = qr_csv_validar(qr_csv_leer("nombre;destino;tipo\nUno;https://a.mx;dinámico\n")['filas'], 'estatico', false);
$prueba('Plan sin dinámicos: lo rechaza por fila', str_contains($v['errores'][0] ?? '', 'no incluye QR dinámicos'));

// --- Escritura (resultado y plantilla) ---
$csv = qr_csv_escribir([['nombre', 'liga'], ['=HYPERLINK("x")', 'https://a.mx'], ['Café', 'b']]);
$prueba('El CSV de salida lleva BOM para Excel', str_starts_with($csv, "\xEF\xBB\xBF"));
$prueba('Evita fórmulas en Excel (valores que empiezan con =)', str_contains($csv, "'=HYPERLINK"));
$prueba('Se puede volver a leer', qr_csv_leer("nombre;destino\n" . substr($csv, strpos($csv, "\n") + 1))['filas'][1]['nombre'] === 'Café');

if ($fallas) {
    fwrite(STDERR, "Fallaron:\n  - " . implode("\n  - ", $fallas) . "\n");
    exit(1);
}
echo "CSV: Excel en español, codificaciones, comillas, validación por fila y escritura: OK\n";
