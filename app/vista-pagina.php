<?php
/**
 * Vista previa en vivo de la página de un QR dinámico (el teléfono de app/crear.php).
 * Recibe por POST el formulario tal como va (sin archivos: los elegidos llegan como URL blob:
 * del navegador en pg[tipo][vista_campo]) y devuelve el HTML de la página. No guarda nada.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/seguridad.php';
require_once __DIR__ . '/../includes/qr_pagina_vista.php';
require_once __DIR__ . '/../app/Core/Auth.php';

use App\Core\Auth;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}
$sesion = Auth::requireUser();
if (!csrf_valido($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit;
}

$usuario = datos_usuario_actual();
$tipo = (string) ($_POST['tipo_contenido'] ?? '');
if (!isset(QR_TIPOS_DINAMICOS[$tipo])) {
    http_response_code(422);
    exit;
}

// Al editar, los archivos ya guardados del mismo tipo se siguen viendo.
$existente = (int) ($_POST['id'] ?? 0) > 0 ? qr_del_usuario((int) $_POST['id'], (int) $usuario['id']) : null;
$anterior = $existente && $existente['tipo_contenido'] === $tipo ? qr_contenido_guardado($existente['contenido'])['campos'] : [];

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
qr_pagina_mostrar([
    'tipo_contenido' => $tipo,
    'campos' => qr_pagina_vista_previa($tipo, $_POST, $anterior),
    'codigo_corto' => '',
    'diseno' => json_encode(qr_diseno($_POST)),
    'marca_agua' => $usuario['plan_datos']['marca_agua'] ? 1 : 0,
    'activo' => 1,
], 'telefono');
