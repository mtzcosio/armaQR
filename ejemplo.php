<?php
/**
 * Ejemplo de la página de un QR dinámico con datos ficticios (?t=tipo). Lo muestra el teléfono
 * de tipo.php. No usa la base de datos ni cuenta escaneos; los botones no salen de la página.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/qr_tipos_info.php';
require_once __DIR__ . '/includes/qr_pagina_vista.php';

$tipo = (string) ($_GET['t'] ?? '');
if (qr_tipo_info($tipo) === null) {
    http_response_code(404);
    exit;
}

header('X-Robots-Tag: noindex');
qr_pagina_mostrar([
    'tipo_contenido' => $tipo,
    'campos' => qr_tipo_ejemplo_campos($tipo),
    'codigo_corto' => '',
    'diseno' => json_encode(qr_diseno([])),
    'marca_agua' => 0,
    'activo' => 1,
], 'telefono');
