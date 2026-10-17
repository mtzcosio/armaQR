<?php
/**
 * CREAR QR DESDE CSV (planes con "Generación masiva")
 * --------------------------------------------------------------------
 * Columnas: nombre y destino (obligatorias); tipo, color y fondo (opcionales).
 * Acepta lo que guarda Excel en español: separador ";" o ",", codificación
 * UTF-8 (con o sin BOM) o Windows-1252, y campos entre comillas.
 * Todo o nada: si una fila tiene error no se crea ningún QR (lo decide quien llama).
 * Estas funciones no tocan la BD.
 */

declare(strict_types=1);

require_once __DIR__ . '/qr.php';

const QR_CSV_MAX_FILAS = 500;
const QR_CSV_MAX_BYTES = 1048576;
/** Nombres de columna aceptados (sin acentos ni mayúsculas) => columna. */
const QR_CSV_COLUMNAS = [
    'nombre' => 'nombre', 'name' => 'nombre',
    'destino' => 'destino', 'url' => 'destino', 'liga' => 'destino', 'enlace' => 'destino', 'link' => 'destino',
    'tipo' => 'tipo',
    'color' => 'color', 'fondo' => 'fondo',
];

/** Normaliza un encabezado: minúsculas, sin acentos ni espacios extra. */
function qr_csv_columna(string $texto): string
{
    $t = mb_strtolower(trim($texto), 'UTF-8');
    return strtr($t, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
}

/**
 * Lee el archivo y devuelve ['filas' => [['fila' => n, 'nombre' => ..., 'destino' => ..., 'tipo' => ..., 'color' => ..., 'fondo' => ...]],
 * 'error' => null|mensaje]. "fila" es el número de fila como lo ve Excel (el encabezado es la 1).
 */
function qr_csv_leer(string $bytes, int $maxFilas = QR_CSV_MAX_FILAS): array
{
    $bytes = preg_replace('/^\xEF\xBB\xBF/', '', $bytes) ?? $bytes;           // BOM de UTF-8
    if (!mb_check_encoding($bytes, 'UTF-8')) {
        $bytes = mb_convert_encoding($bytes, 'UTF-8', 'Windows-1252');       // Excel en Windows
    }
    if (trim($bytes) === '') {
        return ['filas' => [], 'error' => 'El archivo está vacío.'];
    }

    // Separador: el que más aparece en la primera línea.
    $primera = strtok($bytes, "\r\n");
    $separador = ';';
    $mejor = -1;
    foreach ([';', ',', "\t"] as $candidato) {
        if (substr_count((string) $primera, $candidato) > $mejor) {
            $mejor = substr_count((string) $primera, $candidato);
            $separador = $candidato;
        }
    }

    $flujo = fopen('php://temp', 'r+');
    fwrite($flujo, $bytes);
    rewind($flujo);

    $columnas = null;
    $filas = [];
    $numero = 0;
    while (($registro = fgetcsv($flujo, 0, $separador, '"', '')) !== false) {
        $numero++;
        if ($registro === [null] || implode('', array_map('trim', $registro)) === '') {
            continue; // fila vacía
        }
        if ($columnas === null) {
            $columnas = array_map(fn($c) => QR_CSV_COLUMNAS[qr_csv_columna((string) $c)] ?? null, $registro);
            if (!in_array('nombre', $columnas, true) || !in_array('destino', $columnas, true)) {
                fclose($flujo);
                return ['filas' => [], 'error' => 'La primera fila debe tener los encabezados "nombre" y "destino". Descarga la plantilla para ver el formato.'];
            }
            continue;
        }
        $fila = ['fila' => $numero, 'nombre' => '', 'destino' => '', 'tipo' => '', 'color' => '', 'fondo' => ''];
        foreach ($columnas as $i => $columna) {
            if ($columna !== null) {
                $fila[$columna] = trim((string) ($registro[$i] ?? ''));
            }
        }
        $filas[] = $fila;
        if (count($filas) > $maxFilas) {
            fclose($flujo);
            return ['filas' => [], 'error' => "El archivo tiene más de {$maxFilas} filas. Divídelo en varios archivos."];
        }
    }
    fclose($flujo);

    if ($columnas === null) {
        return ['filas' => [], 'error' => 'El archivo está vacío.'];
    }
    if (!$filas) {
        return ['filas' => [], 'error' => 'El archivo solo tiene encabezados: agrega al menos una fila con nombre y destino.'];
    }
    return ['filas' => $filas, 'error' => null];
}

/**
 * Valida las filas. $tipoPredeterminado se usa cuando la fila no trae tipo.
 * Devuelve ['qr' => [[nombre, tipo, contenido, diseno, fila]], 'errores' => ['Fila 3: ...']].
 */
function qr_csv_validar(array $filas, string $tipoPredeterminado, bool $permiteDinamicos): array
{
    $tipos = ['dinamico' => 'dinamico', 'dinámico' => 'dinamico', 'estatico' => 'estatico', 'estático' => 'estatico'];
    $hex = fn(string $v) => (bool) preg_match('/^#?[0-9a-fA-F]{6}$/', $v);
    $qr = [];
    $errores = [];

    foreach ($filas as $f) {
        $problemas = [];
        $nombre = mb_substr($f['nombre'], 0, 120);
        if ($nombre === '') {
            $problemas[] = 'falta el nombre';
        }
        $tipo = $f['tipo'] === '' ? $tipoPredeterminado : ($tipos[mb_strtolower($f['tipo'], 'UTF-8')] ?? null);
        if ($tipo === null) {
            $problemas[] = "el tipo \"{$f['tipo']}\" no existe (usa dinámico o estático)";
        } elseif ($tipo === 'dinamico' && !$permiteDinamicos) {
            $problemas[] = 'tu plan no incluye QR dinámicos';
        }
        $contenido = null;
        try {
            $contenido = qr_contenido('url', ['url' => $f['destino']]);
        } catch (QrInvalido $e) {
            $problemas[] = $f['destino'] === '' ? 'falta el destino' : "el destino \"{$f['destino']}\" no es una dirección web válida";
        }
        foreach (['color', 'fondo'] as $c) {
            if ($f[$c] !== '' && !$hex($f[$c])) {
                $problemas[] = "el {$c} \"{$f[$c]}\" debe ser un color como #172036";
            }
        }

        if ($problemas) {
            $errores[] = "Fila {$f['fila']}: " . implode('; ', $problemas) . '.';
            continue;
        }
        $qr[] = [
            'fila' => $f['fila'], 'nombre' => $nombre, 'tipo' => $tipo, 'contenido' => $contenido,
            'diseno' => qr_diseno([
                'color' => $f['color'] === '' ? null : '#' . ltrim($f['color'], '#'),
                'fondo' => $f['fondo'] === '' ? null : '#' . ltrim($f['fondo'], '#'),
            ]),
        ];
    }
    return ['qr' => $qr, 'errores' => $errores];
}

/** Escribe filas en CSV para Excel (BOM UTF-8 y separador ";"). */
function qr_csv_escribir(array $filas): string
{
    $flujo = fopen('php://temp', 'r+');
    fwrite($flujo, "\xEF\xBB\xBF");
    foreach ($filas as $fila) {
        // Evita que Excel interprete como fórmula un valor que empiece con = + - @
        $fila = array_map(fn($v) => preg_match('/^[=+\-@]/', (string) $v) ? "'" . $v : $v, $fila);
        fputcsv($flujo, $fila, ';', '"', '');
    }
    rewind($flujo);
    $salida = stream_get_contents($flujo);
    fclose($flujo);
    return (string) $salida;
}
