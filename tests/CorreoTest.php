<?php

declare(strict_types=1);

// Lógica del módulo de plantillas de correo. No envía correos ni escribe en la BD.
require_once dirname(__DIR__) . '/includes/correo.php';

$fallas = [];
$prueba = function (string $nombre, bool $ok) use (&$fallas) {
    if (!$ok) {
        $fallas[] = $nombre;
    }
};

// --- Render ---
$datos = ['nombre' => 'Ana', 'total' => '$10 <b>MXN</b>'];
$prueba('Sustituye variables con o sin espacios y sin distinguir mayúsculas',
    correo_render('Hola {{nombre}}, {{ NOMBRE }} · {{total}}', $datos) === 'Hola Ana, Ana · $10 <b>MXN</b>');
$prueba('Deja visibles las variables sin dato (vista previa)', correo_render('{{falta}}', $datos) === '{{falta}}');
$prueba('Quita las variables sin dato en el envío real', correo_render('A{{falta}}B', $datos, true) === 'AB');
$prueba('Lista variables usadas sin repetir', correo_variables_usadas('{{a}} {{b}} {{ A }}') === ['a', 'b']);
$prueba('Detecta variables desconocidas', correo_variables_desconocidas('{{nombre}} {{cupon}}', ['nombre']) === ['cupon']);

// --- Preheader ---
$html = '<html><body style="x">Hola</body></html>';
$conPre = correo_insertar_preheader($html, 'Vista $1 previa');
$prueba('Inserta el preheader justo después de <body> (respetando "$1" literal)',
    str_contains($conPre, '<body style="x"><div style="display:none;') && str_contains($conPre, 'Vista $1 previa'));
$prueba('Sin <body>, el preheader va al inicio', str_starts_with(correo_insertar_preheader('<p>x</p>', 'Pre'), '<div style="display:none;'));
$prueba('Preheader vacío no cambia nada', correo_insertar_preheader($html, '  ') === $html);

// --- Códigos y nombres ---
$prueba('Código desde texto con acentos', correo_codigo_desde('  Pago recibido: ¡Ñandú! ') === 'PAGO_RECIBIDO_NANDU');
$prueba('Nombre de variable desde texto', correo_nombre_variable_desde('Código de descuento') === 'codigo_de_descuento');
$prueba('Contenido vacío: solo etiquetas o espacios', correo_contenido_vacio('<p><br></p> &nbsp;'));
$prueba('Contenido con imagen no está vacío', !correo_contenido_vacio('<p><img src="https://x/y.png"></p>'));

// --- Catálogo combinado ---
$personalizadas = [
    ['categoria' => 'portal ', 'nombre' => 'cupon', 'descripcion' => '', 'valor_ejemplo' => 'QR10', 'valor' => ''],
    ['categoria' => 'Promociones', 'nombre' => 'fin_promo', 'descripcion' => 'Fin', 'valor_ejemplo' => '', 'valor' => '31 oct'],
];
$catalogo = correo_catalogo($personalizadas);
$prueba('Una categoría personalizada igual a una integrada (sin mayúsculas/espacios) se une a ella',
    isset($catalogo['Portal']['cupon']) && !isset($catalogo['portal ']));
$prueba('Una categoría nueva se agrega', ($catalogo['Promociones']['fin_promo'] ?? '') === 'Fin');
$prueba('Sin descripción se usa "Variable personalizada."', $catalogo['Portal']['cupon'] === 'Variable personalizada.');

// --- Composición ---
$predeterminado = ['asunto' => 'Hola {{nombre}} &amp; bienvenida', 'preheader' => 'Pre {{nombre}}', 'html' => '<body><p>{{nombre}} {{falta}}</p></body>'];
$c = correo_componer(null, ['nombre' => 'Ana &amp; Luis'], $predeterminado);
$prueba('Sin plantilla activa usa el predeterminado', $c['origen'] === 'predeterminado');
$prueba('El asunto es texto plano (entidades decodificadas)', $c['asunto'] === 'Hola Ana & Luis & bienvenida');
$prueba('El HTML lleva preheader y quita variables sin dato', str_contains($c['html'], 'Pre Ana &amp; Luis</div><p>Ana &amp; Luis </p>'));
$plantilla = ['asunto' => 'De plantilla', 'preheader' => '', 'contenido_html' => '<p>P</p>', 'remitente_nombre' => ' Ventas ', 'remitente_correo' => ''];
$c = correo_componer($plantilla, [], $predeterminado);
$prueba('Con plantilla activa la usa, con su remitente', $c['origen'] === 'plantilla' && $c['asunto'] === 'De plantilla' && $c['remitente_nombre'] === 'Ventas');
$prueba('Sin plantilla ni predeterminado no hay correo', correo_componer(null, [], null) === null);

// --- Contrato de los correos del sistema ---
$integradas = correo_variables_integradas();
foreach (correo_codigos_sistema() as $codigo => $info) {
    $usadas = correo_variables_usadas($info['asunto'] . $info['preheader'] . $info['html']);
    $prueba("{$codigo}: su correo predeterminado solo usa variables que declara llenar",
        array_diff($usadas, $info['variables']) === []);
    $prueba("{$codigo}: todas sus variables existen en el catálogo integrado",
        array_diff($info['variables'], $integradas) === []);
    $prueba("{$codigo}: el código es válido", correo_codigo_desde($codigo) === $codigo);
    $prueba("{$codigo}: tiene un tipo conocido", in_array($info['tipo'], CORREO_TIPOS, true));
}

// --- Mensaje SMTP ---
$mensaje = smtp_mensaje('no-responder@arma.mx', 'Arma QR', 'ana@ejemplo.com', "Asunto\r\nBcc: espia@x.com", '<p>Hola <a href="https://x.mx">aquí</a></p>');
[$cabeceras, $cuerpo] = explode("\r\n\r\n", $mensaje, 2);
$prueba('El asunto no permite inyectar cabeceras', !preg_match('/^Bcc:/mi', $cabeceras));
$prueba('Es multipart/alternative con texto y HTML', str_contains($cabeceras, 'multipart/alternative') && substr_count($cuerpo, 'Content-Type: text/') === 2);
$prueba('Ninguna línea del cuerpo empieza con "." ni pasa de 998 caracteres',
    !array_filter(explode("\r\n", $cuerpo), fn($l) => str_starts_with($l, '.') || strlen($l) > 998));
preg_match_all('/base64\r\n\r\n(.*?)\r\n--/s', $cuerpo, $partes);
$prueba('Las partes en base64 conservan el contenido',
    count($partes[1]) === 2 && base64_decode(str_replace("\r\n", '', $partes[1][1])) === '<p>Hola <a href="https://x.mx">aquí</a></p>');
$prueba('La versión de texto conserva los enlaces', smtp_html_a_texto('<p>Hola <a href="https://x.mx">aquí</a></p><style>p{}</style>') === 'Hola aquí (https://x.mx)');

if ($fallas) {
    fwrite(STDERR, "Fallaron:\n  - " . implode("\n  - ", $fallas) . "\n");
    exit(1);
}
echo 'Correos: render, preheader, catálogo, composición, contrato de ' . count(correo_codigos_sistema()) . " código(s) y mensaje SMTP: OK\n";
