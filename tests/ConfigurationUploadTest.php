<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$admin = file_get_contents($root . '/admin/configuracion.php') ?: '';
$endpoint = file_get_contents($root . '/auth/configuracion.php') ?: '';
// La validación de imágenes está en includes/archivos.php (compartida con los logos de los QR).
$validador = file_get_contents($root . '/includes/archivos.php') ?: '';

foreach (['name="portal_logo"', 'name="portal_favicon"', 'enctype="multipart/form-data"', 'accept="image/png,image/jpeg,image/webp,image/gif"'] as $fragment) {
    if (!str_contains($admin, $fragment)) {
        fwrite(STDERR, "Falta soporte de carga: {$fragment}\n");
        exit(1);
    }
}

foreach (['move_uploaded_file', 'is_uploaded_file', 'file[\'size\']', 'image/jpeg', 'image/png'] as $fragment) {
    if (!str_contains($validador, $fragment)) {
        fwrite(STDERR, "Falta validación de archivo: {$fragment}\n");
        exit(1);
    }
}

if (!str_contains($endpoint, "!isset(\$_FILES[\$field]) || \$_FILES[\$field]['error'] === UPLOAD_ERR_NO_FILE")
    || !str_contains($endpoint, "\$_FILES[\$field]['error'] !== UPLOAD_ERR_OK")) {
    fwrite(STDERR, "Falta tratamiento para campos de archivo sin seleccionar\n");
    exit(1);
}

echo "Prueba de carga de logotipo y favicon: OK\n";
