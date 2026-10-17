<?php

declare(strict_types=1);

// Documentos legales: limpieza del HTML y variables (no escribe en la BD).
require_once dirname(__DIR__) . '/includes/legales.php';

$fallas = [];
$prueba = function (string $nombre, bool $ok, string $detalle = '') use (&$fallas) {
    if (!$ok) $fallas[] = $nombre . ($detalle !== '' ? "  [{$detalle}]" : '');
};
$s = fn(string $html) => legal_sanitizar($html);

// --- Lo que debe conservarse ---
$bueno = '<h2>1. Datos</h2><p>Texto con <strong>negritas</strong>, <em>cursivas</em> y <u>subrayado</u>.</p><ul><li>uno</li></ul><ol><li>dos</li></ol><blockquote>cita</blockquote><p><a href="https://armaqr.com/terminos">liga</a> <a href="mailto:hola@armaqr.com">correo</a></p>';
$prueba('Conserva el formato permitido', $s($bueno) === $bueno, $s($bueno));
$prueba('Conserva acentos y ñ', $s('<p>Ñandú, acción, güero</p>') === '<p>Ñandú, acción, güero</p>');
$prueba('Conserva variables en el texto y en los enlaces', $s('<p>{{nombre_portal}} <a href="mailto:{{correo_contacto}}">x</a></p>') === '<p>{{nombre_portal}} <a href="mailto:{{correo_contacto}}">x</a></p>');

// --- Lo que debe quitarse ---
$casos = [
    'script' => '<p>a<script>alert(1)</script>b</p>',
    'style' => '<style>p{display:none}</style><p>ok</p>',
    'iframe' => '<iframe src="https://malo.mx"></iframe><p>ok</p>',
    'img con onerror' => '<p><img src=x onerror="alert(1)">ok</p>',
    'svg con script' => '<svg><script>alert(1)</script></svg><p>ok</p>',
    'onclick' => '<p onclick="alert(1)">ok</p>',
    'style en línea' => '<p style="background:url(javascript:alert(1))">ok</p>',
    'enlace javascript:' => '<p><a href="javascript:alert(1)">ok</a></p>',
    'enlace data:' => '<p><a href="data:text/html,<script>alert(1)</script>">ok</a></p>',
    'enlace con mayúsculas' => '<p><a href="JaVaScRiPt:alert(1)">ok</a></p>',
    'formulario' => '<form action="https://malo.mx"><input name="clave"></form><p>ok</p>',
    'comentario' => '<!-- <script>x</script> --><p>ok</p>',
];
foreach ($casos as $nombre => $html) {
    $limpio = $s($html);
    $prueba("Quita {$nombre}", !preg_match('/<script|<style|<iframe|<img|<svg|<form|<input|on\w+=|style=|javascript:|data:text|<!--/i', $limpio), $limpio);
}
$prueba('Una etiqueta no permitida se quita conservando su texto', $s('<p><span class="x">texto</span> <font color="red">más</font></p>') === '<p>texto más</p>');
$prueba('Vacío se queda vacío', $s('   ') === '');
$prueba('legal_vacio detecta etiquetas sin texto', legal_vacio('<p><br></p><p>&nbsp;</p>') && !legal_vacio('<p>a</p>'));

// --- Variables ---
$valores = ['nombre_portal' => 'Arma-QR', 'correo_contacto' => 'hola@armaqr.com', 'dominio' => 'armaqr.com', 'direccion' => 'CDMX'];
$prueba('Sustituye variables', legal_render('<p>{{nombre_portal}} ({{ dominio }})</p>', $valores) === '<p>Arma-QR (armaqr.com)</p>');
$prueba('Deja visibles las desconocidas', legal_render('<p>{{inventada}}</p>', $valores) === '<p>{{inventada}}</p>');
$prueba('Todas las variables documentadas tienen valor', array_diff(array_keys(LEGAL_VARIABLES), array_keys(legal_valores())) === []);
$prueba('Los valores de configuración se escapan', !str_contains(legal_render('{{nombre_portal}}', ['nombre_portal' => e('<b>x</b>')]), '<b>'));

if ($fallas) {
    fwrite(STDERR, "Fallaron:\n  - " . implode("\n  - ", $fallas) . "\n");
    exit(1);
}
echo "Legales: formato permitido, " . count($casos) . " intentos de código bloqueados y variables: OK\n";
