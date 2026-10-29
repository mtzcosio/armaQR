<?php

declare(strict_types=1);

// Páginas de los QR dinámicos (includes/qr_paginas.php): validación, destinos y archivos .vcf/.ics.
// No escribe en la BD ni sube archivos.
require_once dirname(__DIR__) . '/includes/layout.php';
require_once dirname(__DIR__) . '/includes/qr_paginas_form.php';
require_once dirname(__DIR__) . '/includes/qr_pagina_vista.php';

$fallas = [];
$prueba = function (string $nombre, bool $ok, string $detalle = '') use (&$fallas) {
    if (!$ok) $fallas[] = $nombre . ($detalle !== '' ? "  [{$detalle}]" : '');
};
$pagina = fn(string $tipo, array $v, array $anterior = []) => qr_pagina_contenido($tipo, ['pg' => [$tipo => $v]], [], $anterior);
$falla = function (string $tipo, array $v) use ($pagina) {
    try { $pagina($tipo, $v); return null; } catch (QrInvalido $e) { return $e->getMessage(); }
};

// --- Redirecciones ---
$prueba('Sitio web usa los campos del estático', qr_pagina_contenido('url', ['url' => 'tunegocio.mx'], [])['texto'] === 'https://tunegocio.mx');
$prueba('Facebook: nombre de página', $pagina('facebook', ['url' => 'cafeluna'])['texto'] === 'https://www.facebook.com/cafeluna');
$prueba('Facebook: enlace completo', $pagina('facebook', ['url' => 'https://m.facebook.com/cafeluna'])['texto'] === 'https://m.facebook.com/cafeluna');
$prueba('Facebook: otro dominio no', $falla('facebook', ['url' => 'https://evil.com/facebook.com']) !== null);
$prueba('Instagram: @usuario', $pagina('instagram', ['url' => '@cafe.luna'])['texto'] === 'https://www.instagram.com/cafe.luna/');
$prueba('Instagram: otro dominio no', $falla('instagram', ['url' => 'https://instagram.com.evil.mx/x']) !== null);

// --- Páginas: 'texto' vacío (r.php muestra la página) ---
$enlaces = $pagina('enlaces', ['titulo' => ' Café Luna ', 'enlaces' => [
    ['texto' => 'Tienda', 'url' => 'tienda.cafeluna.mx'], ['texto' => '', 'url' => ''], ['texto' => '', 'url' => 'https://cafeluna.mx/blog'],
]]);
$prueba('Enlaces: sin URL de destino', $enlaces['texto'] === '');
$prueba('Enlaces: título recortado', $enlaces['campos']['titulo'] === 'Café Luna');
$prueba('Enlaces: ignora filas vacías y completa https', $enlaces['campos']['enlaces'] === [
    ['texto' => 'Tienda', 'url' => 'https://tienda.cafeluna.mx'], ['texto' => 'cafeluna.mx', 'url' => 'https://cafeluna.mx/blog'],
], json_encode($enlaces['campos']['enlaces']));
$prueba('Enlaces: sin enlaces', $falla('enlaces', ['titulo' => 'X', 'enlaces' => [['texto' => '', 'url' => '']]]) !== null);
$prueba('Enlaces: texto sin URL', $falla('enlaces', ['titulo' => 'X', 'enlaces' => [['texto' => 'Tienda', 'url' => '']]]) !== null);
$prueba('Enlaces: javascript: no', $falla('enlaces', ['titulo' => 'X', 'enlaces' => [['texto' => 'a', 'url' => 'javascript:alert(1)']]]) !== null);
$prueba('Enlaces: máximo ' . QR_PAGINA_MAX_ENLACES, count($pagina('enlaces', ['titulo' => 'X', 'enlaces' => array_fill(0, 30, ['texto' => 'a', 'url' => 'a.mx'])])['campos']['enlaces']) === QR_PAGINA_MAX_ENLACES);

$menu = $pagina('menu', ['titulo' => 'Taquería', 'secciones' => [
    ['nombre' => 'Tacos', 'platillos' => [['nombre' => 'Pastor', 'precio' => '95', 'descripcion' => ''], ['nombre' => '', 'precio' => '', 'descripcion' => '']]],
    ['nombre' => '', 'platillos' => [['nombre' => '', 'precio' => '', 'descripcion' => '']]],
]]);
$prueba('Menú: secciones y platillos vacíos se ignoran', count($menu['campos']['secciones']) === 1 && count($menu['campos']['secciones'][0]['platillos']) === 1);
$prueba('Menú: sin platillos', $falla('menu', ['titulo' => 'X', 'secciones' => [['nombre' => 'Tacos', 'platillos' => []]]]) !== null);
$prueba('Menú: platillo sin nombre', $falla('menu', ['titulo' => 'X', 'secciones' => [['nombre' => 'T', 'platillos' => [['precio' => '10']]]]]) !== null);
$prueba('Menú: sección sin nombre', $falla('menu', ['titulo' => 'X', 'secciones' => [['nombre' => '', 'platillos' => [['nombre' => 'A']]]]]) !== null);
$prueba('Precio numérico con formato', qr_pagina_precio('95') === '$95' && qr_pagina_precio('1,250.5') === '$1,250.50');
$prueba('Precio en texto se respeta', qr_pagina_precio('Desde 80') === 'Desde 80');

$cupon = $pagina('cupon', ['titulo' => '2x1', 'codigo' => 'LUNA20', 'vigencia' => '2026-12-31']);
$prueba('Cupón: vigencia', $cupon['campos']['vigencia'] === '2026-12-31');
$prueba('Cupón: fecha inválida', $falla('cupon', ['titulo' => 'X', 'vigencia' => '2026-02-30']) !== null);

$evento = $pagina('evento', ['titulo' => 'Jazz', 'inicio' => '2026-11-07T20:00', 'fin' => '', 'lugar' => 'Foro', 'direccion' => 'Centro']);
$prueba('Evento: fecha de datetime-local', $evento['campos']['inicio'] === '2026-11-07 20:00');
$prueba('Evento: sin inicio', $falla('evento', ['titulo' => 'Jazz']) !== null);
$prueba('Evento: termina antes de empezar', $falla('evento', ['titulo' => 'J', 'inicio' => '2026-11-07T20:00', 'fin' => '2026-11-07T19:00']) !== null);

$wifi = $pagina('wifi', ['red' => 'Clientes', 'clave' => 'secreta', 'seguridad' => 'WPA']);
$prueba('Wi-Fi: página con red y clave', $wifi['texto'] === '' && $wifi['campos']['red'] === 'Clientes' && $wifi['campos']['clave'] === 'secreta');
$prueba('Wi-Fi: sin clave', $falla('wifi', ['red' => 'R', 'seguridad' => 'WPA']) !== null);

$prueba('Video: YouTube', qr_pagina_video_incrustado('https://youtu.be/dQw4w9WgXcQ') === 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ');
$prueba('Video: YouTube con parámetros', qr_pagina_video_incrustado('https://www.youtube.com/watch?si=x&v=dQw4w9WgXcQ&t=3') === 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ');
$prueba('Video: Vimeo', qr_pagina_video_incrustado('https://vimeo.com/76979871') === 'https://player.vimeo.com/video/76979871');
$prueba('Video: otro sitio no', $falla('video', ['url' => 'https://example.com/video.mp4']) !== null);

$redes = $pagina('redes', ['titulo' => 'Café', 'redes' => ['instagram' => 'instagram.com/cafe', 'tiktok' => '', 'otra' => 'https://x.mx']]);
$prueba('Redes: solo las conocidas', $redes['campos']['redes'] === ['instagram' => 'https://instagram.com/cafe']);
$prueba('Redes: sin ninguna', $falla('redes', ['titulo' => 'Café', 'redes' => []]) !== null);

$prueba('Empresa: correo inválido', $falla('empresa', ['nombre' => 'X', 'email' => 'no']) !== null);
$prueba('vCard: sin nombre', $falla('vcard', ['nombre' => '']) !== null);
$prueba('E-mail: obligatorio', $falla('email', ['email' => '']) !== null);

// --- Archivos obligatorios y conservados al editar ---
$prueba('PDF: sin archivo', $falla('pdf', ['titulo' => 'Catálogo']) === 'Sube el archivo PDF.');
$prueba('MP3: sin audio', $falla('mp3', ['titulo' => 'Episodio']) !== null);
$prueba('Imágenes: sin imágenes', $falla('imagenes', []) !== null);
$pdf = 'assets/uploads/paginas/' . str_repeat('a', 32) . '.pdf';
$prueba('PDF: conserva el archivo al editar', $pagina('pdf', ['titulo' => 'Nuevo'], ['archivo' => $pdf])['campos']['archivo'] === $pdf);
$logo = 'assets/uploads/paginas/' . str_repeat('b', 32) . '.png';
$prueba('Imagen opcional: se conserva', $pagina('redes', ['titulo' => 'C', 'web' => 'c.mx'], ['imagen' => $logo])['campos']['imagen'] === $logo);
$prueba('Imagen opcional: se quita', $pagina('redes', ['titulo' => 'C', 'web' => 'c.mx', 'quitar_imagen' => '1'], ['imagen' => $logo])['campos']['imagen'] === null);
$galeria = ['assets/uploads/paginas/' . str_repeat('c', 32) . '.jpg', 'assets/uploads/paginas/' . str_repeat('d', 32) . '.jpg'];
$prueba('Imágenes: quita solo las marcadas', $pagina('imagenes', ['quitar_imagenes' => [$galeria[0], 'assets/uploads/otro.jpg']], ['imagenes' => $galeria])['campos']['imagenes'] === [$galeria[1]]);
$prueba('Rutas: solo de las claves de archivo', qr_pagina_rutas(['imagen' => $logo, 'descripcion' => $pdf, 'imagenes' => $galeria]) === [$logo, ...$galeria]);

// --- Aplicaciones: tienda según el celular ---
$apps = $pagina('apps', ['titulo' => 'Luna', 'ios' => 'https://apps.apple.com/mx/app/id1', 'android' => 'https://play.google.com/store/apps/details?id=mx.luna']);
$iphone = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1';
$android = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 Chrome/120.0 Mobile Safari/537.36';
$prueba('Apps: iPhone va a App Store', qr_pagina_destino_app($apps['campos'], $iphone) === 'https://apps.apple.com/mx/app/id1');
$prueba('Apps: Android va a Google Play', qr_pagina_destino_app($apps['campos'], $android) === 'https://play.google.com/store/apps/details?id=mx.luna');
$prueba('Apps: computadora ve la página', qr_pagina_destino_app($apps['campos'], 'Mozilla/5.0 (Windows NT 10.0)') === null);
$prueba('Apps: enlace que no es de la tienda', $falla('apps', ['titulo' => 'L', 'ios' => 'https://apple.com.evil.mx/app']) !== null);

// --- Archivos .vcf e .ics ---
$vcard = $pagina('vcard', ['nombre' => 'Ana López', 'empresa' => 'Tacos, S.A.', 'telefono' => '55 1234 5678', 'direccion' => 'Reforma 1; CDMX'])['campos'];
$vcf = qr_pagina_vcard($vcard);
$prueba('vCard: CRLF y escapes', str_contains($vcf, "FN:Ana López\r\n") && str_contains($vcf, 'ORG:Tacos\\, S.A.') && str_contains($vcf, 'ADR;TYPE=WORK:;;Reforma 1\\; CDMX;;;;'), $vcf);
$prueba('Nombre del archivo sin acentos', qr_pagina_nombre_archivo('Ana López Ñúñez') === 'ana-lopez-nunez');
$prueba('Líneas de máximo 75 bytes', !array_filter(explode("\r\n", qr_pagina_doblar(str_repeat('ñ', 100))), fn($l) => strlen($l) > 75));
$ics = qr_pagina_ics($evento['campos'], 'abc123@armaqr.mx');
$prueba('Evento: hora de CDMX a UTC', str_contains($ics, 'DTSTART:20261108T020000Z') && str_contains($ics, 'DTEND:20261108T040000Z'), $ics);
$prueba('Evento: lugar', str_contains($ics, 'LOCATION:Foro\\, Centro'));

// --- Formularios: un panel por tipo con página y sin campos fuera de pg[tipo] ---
ob_start();
qr_pagina_formularios(['menu' => ['secciones' => ['5' => ['nombre' => 'Tacos <b>', 'platillos' => [['nombre' => 'Pastor']]]]]], []);
$html = ob_get_clean();
$prueba('Un panel por cada tipo con campos propios', substr_count($html, 'data-panel-pagina') === count(QR_TIPOS_DINAMICOS) - 2);
$prueba('Paneles ocultos y deshabilitados al cargar', substr_count($html, 'hidden disabled') === count(QR_TIPOS_DINAMICOS) - 2);
$prueba('Lo capturado se escapa', str_contains($html, 'value="Tacos &lt;b&gt;"') && !str_contains($html, 'Tacos <b>'));
preg_match_all('/name="([^"]+)"/', $html, $nombres);
$prueba('Todos los campos van en pg[tipo]', !array_filter($nombres[1], fn($n) => !str_starts_with($n, 'pg[')));

// --- Página pública: se escapa lo del cliente ---
$qr = ['tipo_contenido' => 'enlaces', 'codigo_corto' => 'abc234', 'activo' => 1, 'marca_agua' => 1, 'diseno' => '{"color":"#e4007c"}',
       'contenido' => json_encode(['tipo' => 'enlaces', 'campos' => ['titulo' => '<script>x</script>', 'descripcion' => '', 'enlaces' => [['texto' => 'Uno', 'url' => 'https://a.mx/"x']], 'imagen' => null]])];
ob_start();
qr_pagina_mostrar($qr);
$publica = ob_get_clean();
$prueba('Página: título escapado', str_contains($publica, '&lt;script&gt;x&lt;/script&gt;') && !str_contains($publica, '<script>x'));
$prueba('Página: enlace escapado', str_contains($publica, 'href="https://a.mx/&quot;x"'));
$prueba('Página: color del QR como acento', str_contains($publica, '--acento: #e4007c'));
$prueba('Texto sobre acento oscuro es blanco', qr_pagina_sobre_acento('#172036') === '#ffffff' && qr_pagina_sobre_acento('#ffe680') === '#172036');

// --- Itinerario ---
$actividad = fn(string $hora, string $titulo, string $fin = '') => ['hora' => $hora, 'fin' => $fin, 'titulo' => $titulo, 'descripcion' => '', 'lugar' => ''];
$itinerario = $pagina('itinerario', ['titulo' => 'Boda', 'dias' => [
    ['nombre' => 'La boda', 'fecha' => '2026-11-07', 'actividades' => [$actividad('17:00', 'Ceremonia'), $actividad('', ''), $actividad('21:00', 'Fiesta', '03:00'), $actividad('02:00:00', 'Camiones')]],
    ['nombre' => '', 'fecha' => '', 'actividades' => [$actividad('', '')]],
]])['campos'];
$prueba('Itinerario: ignora días y actividades vacíos', count($itinerario['dias']) === 1 && count($itinerario['dias'][0]['actividades']) === 3);
$prueba('Itinerario: respeta el orden y quita segundos', array_column($itinerario['dias'][0]['actividades'], 'hora') === ['17:00', '21:00', '02:00']);
$prueba('Itinerario: termina después de medianoche', $itinerario['dias'][0]['actividades'][1]['fin'] === '03:00');
$prueba('Itinerario: sin actividades', $falla('itinerario', ['titulo' => 'Boda', 'dias' => [['nombre' => 'Día 1']]]) !== null);
$prueba('Itinerario: actividad sin hora', $falla('itinerario', ['titulo' => 'Boda', 'dias' => [['actividades' => [$actividad('', 'Cena')]]]]) !== null);
$prueba('Itinerario: hora inválida', $falla('itinerario', ['titulo' => 'Boda', 'dias' => [['actividades' => [$actividad('25:00', 'Cena')]]]]) !== null);
$prueba('Itinerario: sin título', $falla('itinerario', ['dias' => [['actividades' => [$actividad('20:00', 'Cena')]]]]) !== null);
$dia = $itinerario['dias'][0];
$en = fn(string $momento) => qr_pagina_actividad_actual($dia, new DateTimeImmutable($momento));
$prueba('Itinerario: antes de empezar no hay actividad en curso', $en('2026-11-07 16:00') === null);
$prueba('Itinerario: en curso', $en('2026-11-07 18:30') === 0 && $en('2026-11-07 23:00') === 1);
$prueba('Itinerario: de madrugada es del día siguiente', $en('2026-11-08 02:30') === 2);
$prueba('Itinerario: la última sin fin dura dos horas', $en('2026-11-08 04:30') === null);
$prueba('Itinerario: otro día no marca nada', $en('2026-11-10 18:30') === null);

// --- Colores de la página (paletas) ---
$prueba('Estilo: colores válidos', qr_pagina_estilo(['pagina_estilo' => ['primario' => '#2563EB', 'secundario' => '#eaf0fd']]) === ['primario' => '#2563eb', 'secundario' => '#eaf0fd']);
$prueba('Estilo: inválido usa la paleta predeterminada', qr_pagina_estilo(['pagina_estilo' => ['primario' => 'red;x', 'secundario' => ['#fff']]]) === ['primario' => QR_PAGINA_PALETAS[0][0], 'secundario' => QR_PAGINA_PALETAS[0][1]]);
$conEstilo = qr_pagina_contenido('cupon', ['pg' => ['cupon' => ['titulo' => '2x1']], 'pagina_estilo' => ['primario' => '#0f766e', 'secundario' => '#e7f5f2']], []);
$prueba('Estilo: se guarda en las páginas', ($conEstilo['campos']['estilo']['primario'] ?? '') === '#0f766e');
$prueba('Estilo: no en los que redirigen', !isset(qr_pagina_contenido('facebook', ['pg' => ['facebook' => ['url' => 'cafeluna']], 'pagina_estilo' => ['primario' => '#0f766e']], [])['campos']['estilo']));
$qr['contenido'] = json_encode(['tipo' => 'cupon', 'campos' => $conEstilo['campos']]);
$qr['tipo_contenido'] = 'cupon';
ob_start();
qr_pagina_mostrar($qr);
$publica = ob_get_clean();
$prueba('Página: usa los colores elegidos', str_contains($publica, '--acento: #0f766e') && str_contains($publica, '--fondo-pagina: #e7f5f2'));

// --- Vista previa en vivo (modo borrador) ---
$prueba('Borrador: faltan datos y no falla', qr_pagina_vista_previa('menu', ['pg' => ['menu' => []]])['secciones'] === []);
$prueba('Borrador: URL inválida queda vacía', qr_pagina_vista_previa('cupon', ['pg' => ['cupon' => ['titulo' => 'X', 'boton_url' => 'no es url']]])['boton_url'] === '');
$prueba('Borrador: se apaga al terminar', !qr_pagina_borrador() && $falla('menu', []) !== null);
$prueba('Borrador: destino de los que redirigen', qr_pagina_vista_previa('instagram', ['pg' => ['instagram' => ['url' => '@cafeluna']]]) === ['destino' => 'https://www.instagram.com/cafeluna/']);
$blob = 'blob:http://localhost:8012/0b8f7a2e-1c3d-4e5f-9a8b-7c6d5e4f3a2b';
$prueba('Blob: solo URL blob: del navegador', qr_pagina_es_blob($blob) && !qr_pagina_es_blob('javascript:alert(1)') && !qr_pagina_es_blob('blob:http://x/"><script>'));
$vistaMp3 = qr_pagina_vista_previa('mp3', ['pg' => ['mp3' => ['titulo' => 'E1', 'vista_audio' => $blob, 'vista_imagen' => 'assets/uploads/paginas/ajeno.png']]]);
$prueba('Blob: el archivo elegido se ve en la vista previa', $vistaMp3['audio'] === $blob && $vistaMp3['imagen'] === null);
$prueba('Blob: nunca al guardar', $falla('mp3', ['titulo' => 'E1', 'vista_audio' => $blob]) !== null);
$prueba('Blob: galería con máximo de imágenes', count(qr_pagina_vista_previa('imagenes', ['pg' => ['imagenes' => ['vista_imagenes' => array_fill(0, 15, $blob)]]])['imagenes']) === QR_PAGINA_MAX_IMAGENES);
ob_start();
qr_pagina_mostrar(['tipo_contenido' => 'video', 'campos' => qr_pagina_vista_previa('video', ['pg' => ['video' => []]]), 'codigo_corto' => '', 'diseno' => '{}', 'marca_agua' => 0, 'activo' => 1], 'telefono');
$telefono = ob_get_clean();
$prueba('Teléfono: lo que falta se señala', str_contains($telefono, 'micrositio-vacio') && !str_contains($telefono, 'micrositio-aviso') && !str_contains($telefono, 'micrositio-marca'));

// --- Formulario: secciones plegables y diseño de la página ---
$prueba('Secciones plegables en cada panel', substr_count($html, '<details class="acordeon"') >= count(QR_TIPOS_DINAMICOS) - 2);
$diseno = qr_pagina_grupo_diseno(['primario' => '#2563eb', 'secundario' => '#eaf0fd']);
$prueba('Diseño: marca la paleta elegida', substr_count($diseno, 'aria-pressed="true"') === 1 && str_contains($diseno, 'name="pagina_estilo[primario]"'));

if ($fallas) {
    fwrite(STDERR, "Fallaron:\n  - " . implode("\n  - ", $fallas) . "\n");
    exit(1);
}
echo "Páginas de QR: validación de los " . count(QR_TIPOS_DINAMICOS) . " tipos, archivos, tiendas de apps, .vcf/.ics, formularios y escape: OK\n";
