<?php
/**
 * Catálogo de tipos de contenido de los QR (solo constantes: lo usan datos.php, qr.php y las vistas).
 *  - Estático: el contenido va dentro del código (QR_TIPOS).
 *  - Dinámico: el código lleva a /r/{codigo}. En modo 'redireccion' r.php manda a una URL; en modo
 *    'pagina' r.php muestra una página propia con los datos del QR (includes/qr_paginas.php).
 */

declare(strict_types=1);

const QR_TIPOS = ['url' => 'Sitio web', 'whatsapp' => 'WhatsApp', 'wifi' => 'Wi-Fi', 'vcard' => 'vCard', 'email' => 'E-mail', 'texto' => 'Texto'];

/** Tipos de los QR dinámicos: nombre, descripción, modo (redireccion | pagina) e ícono (icono() de layout.php). */
const QR_TIPOS_DINAMICOS = [
    'url'       => ['nombre' => 'Sitio web',  'descripcion' => 'Enlace a la URL de cualquier sitio web', 'modo' => 'redireccion', 'icono' => 'web'],
    'pdf'       => ['nombre' => 'PDF',        'descripcion' => 'Muestra un PDF', 'modo' => 'pagina', 'icono' => 'archivo'],
    'enlaces'   => ['nombre' => 'Lista de enlaces', 'descripcion' => 'Comparte varios enlaces', 'modo' => 'pagina', 'icono' => 'enlace'],
    'vcard'     => ['nombre' => 'vCard',      'descripcion' => 'Comparte una tarjeta de presentación electrónica', 'modo' => 'pagina', 'icono' => 'contacto'],
    'menu'      => ['nombre' => 'Menú',       'descripcion' => 'Crea el menú de tu restaurante', 'modo' => 'pagina', 'icono' => 'comida'],
    'cupon'     => ['nombre' => 'Cupón',      'descripcion' => 'Comparte un cupón', 'modo' => 'pagina', 'icono' => 'cupon'],
    'wifi'      => ['nombre' => 'Wi-Fi',      'descripcion' => 'Comparte cómo conectarse a tu red Wi-Fi', 'modo' => 'pagina', 'icono' => 'wifi'],
    'evento'    => ['nombre' => 'Evento',     'descripcion' => 'Promociona cualquier evento', 'modo' => 'pagina', 'icono' => 'calendario'],
    'empresa'   => ['nombre' => 'Empresa',    'descripcion' => 'Comparte la información de tu negocio', 'modo' => 'pagina', 'icono' => 'edificio'],
    'video'     => ['nombre' => 'Video',      'descripcion' => 'Muestra un video de YouTube o Vimeo', 'modo' => 'pagina', 'icono' => 'video'],
    'imagenes'  => ['nombre' => 'Imágenes',   'descripcion' => 'Comparte varias imágenes', 'modo' => 'pagina', 'icono' => 'imagen'],
    'facebook'  => ['nombre' => 'Facebook',   'descripcion' => 'Comparte tu página de Facebook', 'modo' => 'redireccion', 'icono' => 'facebook'],
    'instagram' => ['nombre' => 'Instagram',  'descripcion' => 'Comparte tu Instagram', 'modo' => 'redireccion', 'icono' => 'instagram'],
    'redes'     => ['nombre' => 'Redes sociales', 'descripcion' => 'Comparte todas tus redes sociales', 'modo' => 'pagina', 'icono' => 'compartir'],
    'whatsapp'  => ['nombre' => 'WhatsApp',   'descripcion' => 'Recibe mensajes de WhatsApp', 'modo' => 'redireccion', 'icono' => 'mensaje'],
    'mp3'       => ['nombre' => 'MP3',        'descripcion' => 'Comparte un archivo de audio', 'modo' => 'pagina', 'icono' => 'musica'],
    'apps'      => ['nombre' => 'Aplicaciones', 'descripcion' => 'Redirige a la tienda de aplicaciones', 'modo' => 'redireccion', 'icono' => 'celular'],
    'email'     => ['nombre' => 'E-mail',     'descripcion' => 'Recibe correos electrónicos', 'modo' => 'pagina', 'icono' => 'correo'],
];

/** ¿El tipo dinámico redirige siempre a url_destino? Los demás guardan url_destino = '' y r.php decide. */
function qr_tipo_usa_url(string $tipo): bool
{
    return in_array($tipo, ['url', 'whatsapp', 'facebook', 'instagram'], true);
}

/** Nombre legible de un tipo de contenido (estático o dinámico). */
function qr_tipo_nombre(string $tipo): string
{
    return QR_TIPOS_DINAMICOS[$tipo]['nombre'] ?? QR_TIPOS[$tipo] ?? $tipo;
}
