<?php
/**
 * Subida de imágenes (logo y favicon del portal, logos de los QR) y de los archivos de las
 * páginas de los QR (PDF, audio e imágenes en assets/uploads/paginas/).
 * Valida el tipo real del archivo (no la extensión que manda el navegador), el tamaño
 * y las dimensiones, y lo guarda con un nombre aleatorio. No acepta SVG: puede llevar código.
 */

declare(strict_types=1);

const IMAGEN_TIPOS = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/gif' => 'gif'];

/**
 * Guarda una imagen subida en assets/uploads/{carpeta}/ y devuelve su ruta relativa
 * (assets/uploads/...), o null si no se envió archivo. Lanza RuntimeException si no es válida.
 */
function imagen_guardar_subida(array $file, string $carpeta, string $campo, int $maxBytes = 2097152, int $maxLado = 4096): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? null) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException("El archivo de {$campo} no se recibió bien. Vuelve a elegirlo.");
    }
    if (($file['size'] ?? 0) <= 0 || (int) $file['size'] > $maxBytes) {
        throw new RuntimeException("{$campo} supera el tamaño máximo de " . round($maxBytes / 1048576, 1) . ' MB.');
    }

    $mime = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $file['tmp_name']);
    if ($mime === false || !isset(IMAGEN_TIPOS[$mime])) {
        throw new RuntimeException("{$campo} no es una imagen soportada (PNG, JPG, WebP o GIF).");
    }
    $info = getimagesize($file['tmp_name']);
    if ($info === false || $info[0] <= 0 || $info[1] <= 0) {
        throw new RuntimeException("{$campo} no contiene una imagen válida.");
    }
    if ($info[0] > $maxLado || $info[1] > $maxLado) {
        throw new RuntimeException("{$campo} supera las dimensiones permitidas ({$maxLado} × {$maxLado} px).");
    }

    $directorio = dirname(__DIR__) . '/assets/uploads/' . $carpeta;
    if (!is_dir($directorio) && !mkdir($directorio, 0755, true) && !is_dir($directorio)) {
        throw new RuntimeException('No se pudo crear el directorio de imágenes.');
    }
    $nombre = bin2hex(random_bytes(16)) . '.' . IMAGEN_TIPOS[$mime];
    if (!move_uploaded_file($file['tmp_name'], $directorio . '/' . $nombre)) {
        throw new RuntimeException('No se pudo guardar la imagen.');
    }
    chmod($directorio . '/' . $nombre, 0644);
    return 'assets/uploads/' . $carpeta . '/' . $nombre;
}

/** Borra una imagen subida por su ruta relativa (solo dentro de assets/uploads/). */
function imagen_borrar(?string $ruta): void
{
    archivo_borrar($ruta);
}

/** Documentos y audio de las páginas de los QR: tipo real (finfo) => extensión con que se guarda. */
const ARCHIVO_TIPOS_PDF = ['application/pdf' => 'pdf'];
const ARCHIVO_TIPOS_AUDIO = [
    'audio/mpeg' => 'mp3', 'audio/mp3' => 'mp3', 'audio/x-mp3' => 'mp3',
    'audio/mp4' => 'm4a', 'audio/x-m4a' => 'm4a', 'audio/aac' => 'm4a',
    'audio/ogg' => 'ogg', 'audio/wav' => 'wav', 'audio/x-wav' => 'wav', 'audio/vnd.wave' => 'wav',
];

/**
 * Guarda un archivo subido (no imagen) en assets/uploads/{carpeta}/ si su tipo real está en $tipos
 * (mime => extensión). Devuelve la ruta relativa, o null si no se envió. Lanza RuntimeException.
 */
function archivo_guardar_subida(array $file, string $carpeta, string $campo, array $tipos, string $descripcionTipos, int $maxBytes): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (in_array($file['error'] ?? null, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
        throw new RuntimeException("{$campo} supera el tamaño máximo de " . round($maxBytes / 1048576, 1) . ' MB.');
    }
    if (($file['error'] ?? null) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException("El archivo de {$campo} no se recibió bien. Vuelve a elegirlo.");
    }
    if (($file['size'] ?? 0) <= 0 || (int) $file['size'] > $maxBytes) {
        throw new RuntimeException("{$campo} supera el tamaño máximo de " . round($maxBytes / 1048576, 1) . ' MB.');
    }
    $mime = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $file['tmp_name']);
    if ($mime === false || !isset($tipos[$mime])) {
        throw new RuntimeException("{$campo} debe ser {$descripcionTipos}.");
    }

    $directorio = dirname(__DIR__) . '/assets/uploads/' . $carpeta;
    if (!is_dir($directorio) && !mkdir($directorio, 0755, true) && !is_dir($directorio)) {
        throw new RuntimeException('No se pudo crear el directorio de archivos.');
    }
    $nombre = bin2hex(random_bytes(16)) . '.' . $tipos[$mime];
    if (!move_uploaded_file($file['tmp_name'], $directorio . '/' . $nombre)) {
        throw new RuntimeException('No se pudo guardar el archivo.');
    }
    chmod($directorio . '/' . $nombre, 0644);
    return 'assets/uploads/' . $carpeta . '/' . $nombre;
}

/** Borra un archivo subido por su ruta relativa (solo nombres generados dentro de assets/uploads/). */
function archivo_borrar(?string $ruta): void
{
    if ($ruta === null || !preg_match('#^assets/uploads/[a-z]+/[a-f0-9]{32}\.(png|jpg|webp|gif|pdf|mp3|m4a|ogg|wav)$#', $ruta)) {
        return;
    }
    @unlink(dirname(__DIR__) . '/' . $ruta);
}
