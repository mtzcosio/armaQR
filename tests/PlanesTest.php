<?php

declare(strict_types=1);

// Reglas de los planes que no escriben en la BD: formulario de admin, retención y recorte de dinámicos.
require_once dirname(__DIR__) . '/includes/qr.php';

$fallas = [];
$prueba = function (string $nombre, bool $ok, string $detalle = '') use (&$fallas) {
    if (!$ok) $fallas[] = $nombre . ($detalle !== '' ? "  [{$detalle}]" : '');
};

$base = [
    'descripcion' => '  QR dinámicos y analítica  ', 'precio_mensual' => '149.5', 'precio_anual' => '1490',
    'max_qr_estaticos_mes' => '', 'max_qr_dinamicos' => '50', 'dias_retencion_escaneos' => '365', 'max_claves_api' => '',
    'permite_logo' => '1', 'permite_analitica' => '1', 'es_publico' => '1',
];

// --- Formulario válido ---
$d = datos_plan_desde_formulario($base, false);
$prueba('Formulario válido', is_array($d), is_string($d) ? $d : '');
if (is_array($d)) {
    $prueba('Descripción recortada', $d['descripcion'] === 'QR dinámicos y analítica');
    $prueba('Precios con decimales', $d['precio_mensual'] === 149.5 && $d['precio_anual'] === 1490.0);
    $prueba('Límite vacío = ilimitado', $d['max_qr_estaticos_mes'] === null);
    $prueba('Límite numérico', $d['max_qr_dinamicos'] === 50 && $d['dias_retencion_escaneos'] === 365);
    $prueba('Claves API vacías = 0', $d['max_claves_api'] === 0);
    $prueba('Casillas marcadas', $d['permite_logo'] === 1 && $d['permite_analitica'] === 1 && $d['es_publico'] === 1);
    $prueba('Casillas sin marcar', $d['permite_svg_pdf'] === 0 && $d['permite_masivo'] === 0 && $d['marca_agua'] === 0);
}

// --- Gratis siempre cuesta 0 ---
$g = datos_plan_desde_formulario($base, true);
$prueba('Gratis ignora precios', is_array($g) && $g['precio_mensual'] === 0.0 && $g['precio_anual'] === 0.0);

// --- Errores ---
$prueba('Precio negativo', is_string(datos_plan_desde_formulario(['precio_mensual' => '-1'] + $base, false)));
$prueba('Precio no numérico', is_string(datos_plan_desde_formulario(['precio_anual' => 'mil'] + $base, false)));
$prueba('Límite decimal', is_string(datos_plan_desde_formulario(['max_qr_dinamicos' => '2.5'] + $base, false)));
$prueba('Límite negativo', is_string(datos_plan_desde_formulario(['max_qr_dinamicos' => '-3'] + $base, false)));
$prueba('Límite enorme', is_string(datos_plan_desde_formulario(['max_qr_estaticos_mes' => '9999999'] + $base, false)));
$prueba('Retención menor a 7 días', is_string(datos_plan_desde_formulario(['dias_retencion_escaneos' => '3'] + $base, false)));
$prueba('Claves API inválidas', is_string(datos_plan_desde_formulario(['max_claves_api' => 'x'] + $base, false)));

// --- Retención de estadísticas ---
$prueba('Sin límite ve todo', datos_periodo_visible(null, 90) && datos_periodo_visible(null, 180));
$prueba('30 días ve 7 y 30', datos_periodo_visible(30, 7) && datos_periodo_visible(30, 30) && !datos_periodo_visible(30, 90));
$prueba('365 días compara 90 contra 90', datos_periodo_visible(365, 180));

// --- Recorte de QR dinámicos al bajar de plan: se pausan los más nuevos ---
$ids = [11, 12, 13, 14, 15]; // de más antiguo a más nuevo
$prueba('Sobran los más nuevos', qr_sobrantes($ids, 3) === [14, 15]);
$prueba('Dentro del límite', qr_sobrantes($ids, 5) === [] && qr_sobrantes($ids, 10) === []);
$prueba('Ilimitado no recorta', qr_sobrantes($ids, null) === []);

// --- Características públicas: la API no se anuncia mientras no exista ---
$plan = ['max_qr_dinamicos' => null, 'max_qr_estaticos_mes' => null, 'permite_analitica' => true, 'dias_retencion_escaneos' => null,
         'permite_logo' => true, 'permite_svg_pdf' => true, 'permite_masivo' => true, 'max_claves_api' => 5, 'marca_agua' => false];
$prueba('Sin API en características', !preg_grep('/API/i', datos_caracteristicas($plan)), implode(' | ', datos_caracteristicas($plan)));

if ($fallas) {
    fwrite(STDERR, "Fallaron:\n  - " . implode("\n  - ", $fallas) . "\n");
    exit(1);
}
echo "Planes: formulario de admin, Gratis en $0, retención, recorte de dinámicos y características: OK\n";
