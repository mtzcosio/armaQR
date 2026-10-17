<?php

declare(strict_types=1);

// Lógica de los QR (includes/qr.php). No escribe en la BD.
require_once dirname(__DIR__) . '/includes/qr.php';

$fallas = [];
$prueba = function (string $nombre, bool $ok, string $detalle = '') use (&$fallas) {
    if (!$ok) $fallas[] = $nombre . ($detalle !== '' ? "  [{$detalle}]" : '');
};
$texto = fn(string $tipo, array $v) => qr_contenido($tipo, $v)['texto'];
$falla = function (string $tipo, array $v) {
    try { qr_contenido($tipo, $v); return null; } catch (QrInvalido $e) { return $e->getMessage(); }
};

// --- Mismo texto que la vista previa (valores leídos del QR en el navegador) ---
$casos = [
    ['url', ['url' => 'tacoselguero.mx/menú'], 'https://tacoselguero.mx/menú'],
    ['whatsapp', ['telefono' => '55 1234 5678', 'mensaje' => '¡Hola! Quiero un pedido'], 'https://wa.me/525512345678?text=%C2%A1Hola!%20Quiero%20un%20pedido'],
    ['wifi', ['red' => 'Café;Invitados', 'clave' => 'clave:123', 'seguridad' => 'WPA'], 'WIFI:T:WPA;S:Café\\;Invitados;P:clave\\:123;;'],
    ['wifi', ['red' => 'Abierta', 'seguridad' => 'nopass'], 'WIFI:T:nopass;S:Abierta;;'],
    ['vcard', ['vc_nombre' => 'Ana López', 'vc_empresa' => 'Tacos, S.A.', 'vc_telefono' => '5512345678', 'vc_email' => 'ana@x.mx'],
        "BEGIN:VCARD\nVERSION:3.0\nN:;Ana López;;;\nFN:Ana López\nORG:Tacos\\, S.A.\nTEL:5512345678\nEMAIL:ana@x.mx\nEND:VCARD"],
    ['email', ['email' => 'hola@armaqr.com', 'asunto' => 'Duda de facturación'], 'mailto:hola@armaqr.com?subject=Duda%20de%20facturaci%C3%B3n'],
    ['texto', ['texto' => 'Mesa 4 · Terraza — Señal niño ñandú'], 'Mesa 4 · Terraza — Señal niño ñandú'],
];
foreach ($casos as [$tipo, $v, $esperado]) {
    $obtenido = $texto($tipo, $v);
    $prueba("{$tipo}: mismo texto que la vista previa", $obtenido === $esperado, $obtenido);
}
$prueba('url: conserva http:// si se escribe', $texto('url', ['url' => 'http://x.mx']) === 'http://x.mx');
$prueba('whatsapp: número internacional se respeta', $texto('whatsapp', ['telefono' => '+1 415 555 0100']) === 'https://wa.me/14155550100');

// --- Validaciones ---
$prueba('url vacía', $falla('url', ['url' => '  ']) !== null);
$prueba('url sin dominio', $falla('url', ['url' => 'hola']) !== null);
$prueba('url con espacios', $falla('url', ['url' => 'https://mi sitio.mx']) !== null);
$prueba('whatsapp con pocos dígitos', $falla('whatsapp', ['telefono' => '12345']) !== null);
$prueba('wifi sin nombre', $falla('wifi', ['red' => '', 'clave' => 'x']) !== null);
$prueba('wifi con seguridad y sin clave', $falla('wifi', ['red' => 'R', 'seguridad' => 'WPA']) !== null);
$prueba('vcard sin nombre', $falla('vcard', ['vc_nombre' => '']) !== null);
$prueba('vcard con correo inválido', $falla('vcard', ['vc_nombre' => 'A', 'vc_email' => 'no']) !== null);
$prueba('email inválido', $falla('email', ['email' => 'no-es-correo']) !== null);
$prueba('texto vacío', $falla('texto', ['texto' => '']) !== null);
$prueba('tipo desconocido', $falla('sms', []) !== null);
$prueba('texto se recorta a 500', mb_strlen($texto('texto', ['texto' => str_repeat('é', 600)])) === 500);
$prueba('Los dinámicos solo admiten tipos que abren una página', QR_TIPOS_DINAMICOS === ['url', 'whatsapp']);

// --- Diseño ---
$prueba('Diseño válido se conserva (en minúsculas)', qr_diseno(['color' => '#E4007C', 'fondo' => '#FFFFFF']) === ['color' => '#e4007c', 'fondo' => '#ffffff']);
$prueba('Diseño inválido usa los predeterminados', qr_diseno(['color' => 'red; x', 'fondo' => '']) === ['color' => '#172036', 'fondo' => '#ffffff']);

// --- Códigos cortos ---
$codigos = array_map(fn() => qr_codigo_aleatorio(), range(1, 500));
$prueba('Códigos de 6 caracteres sin 0/o/1/l/i ni mayúsculas', !array_filter($codigos, fn($c) => !preg_match('/^[a-hjkmnp-z2-9]{6}$/', $c)));
$prueba('Códigos variados', count(array_unique($codigos)) > 495);

// --- Dispositivo ---
$iphone = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';
$android = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Mobile Safari/537.36';
$tablet = 'Mozilla/5.0 (Linux; Android 13; SM-X200) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36';
$pc = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36 Edg/120.0';
$prueba('iPhone', qr_detectar_agente($iphone) === ['dispositivo' => 'movil', 'sistema_operativo' => 'iOS', 'navegador' => 'Safari']);
$prueba('Android móvil', qr_detectar_agente($android) === ['dispositivo' => 'movil', 'sistema_operativo' => 'Android', 'navegador' => 'Chrome']);
$prueba('Tableta Android', qr_detectar_agente($tablet)['dispositivo'] === 'tablet');
$prueba('PC con Edge', qr_detectar_agente($pc) === ['dispositivo' => 'escritorio', 'sistema_operativo' => 'Windows', 'navegador' => 'Edge']);
$prueba('Vista previa de WhatsApp no cuenta', qr_es_robot('WhatsApp/2.23.20.0 A'));
$prueba('Googlebot no cuenta', qr_es_robot('Mozilla/5.0 (compatible; Googlebot/2.1)'));
$prueba('Un celular sí cuenta', !qr_es_robot($iphone));

// --- ¿Redirige? ---
$base = ['eliminado' => 0, 'dueno_eliminado' => 0, 'activo' => 1, 'estatus_dueno' => 'activo', 'plan_id' => 2, 'max_qr_dinamicos' => 50, 'url_destino' => 'https://x.mx'];
$prueba('Activo con plan Pro: redirige', qr_motivo_sin_redireccion($base) === null);
$prueba('Plan Negocio (ilimitado): redirige', qr_motivo_sin_redireccion(['max_qr_dinamicos' => null] + $base) === null);
$prueba('No existe', qr_motivo_sin_redireccion(null) === 'no_existe');
$prueba('Eliminado', qr_motivo_sin_redireccion(['eliminado' => 1] + $base) === 'no_existe');
$prueba('Dueño eliminado', qr_motivo_sin_redireccion(['dueno_eliminado' => 1] + $base) === 'no_existe');
$prueba('Pausado', qr_motivo_sin_redireccion(['activo' => 0] + $base) === 'pausado');
$prueba('Dueño suspendido', qr_motivo_sin_redireccion(['estatus_dueno' => 'suspendido'] + $base) === 'pausado');
$prueba('Dueño bajó a gratis', qr_motivo_sin_redireccion(['max_qr_dinamicos' => 0] + $base) === 'pausado');
$prueba('Dueño sin suscripción', qr_motivo_sin_redireccion(['plan_id' => null, 'max_qr_dinamicos' => null] + $base) === 'pausado');
$prueba('Código con formato inválido no consulta la BD', qr_por_codigo("x' OR 1=1") === null);

if ($fallas) {
    fwrite(STDERR, "Fallaron:\n  - " . implode("\n  - ", $fallas) . "\n");
    exit(1);
}
echo "QR: contenido idéntico a la vista previa, validaciones, códigos, dispositivos y redirección: OK\n";
