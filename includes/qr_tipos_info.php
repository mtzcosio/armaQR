<?php
/**
 * PÁGINAS EXPLICATIVAS DE LOS TIPOS DINÁMICOS (tipo.php y ejemplo.php)
 * --------------------------------------------------------------------
 * Para cada tipo de QR_TIPOS_DINAMICOS: título, introducción, qué incluye, para qué sirve, notas
 * y un ejemplo con datos ficticios. El ejemplo pasa por qr_pagina_contenido() igual que un QR
 * real (así siempre tiene los mismos campos que la página pública); sus archivos viven en
 * assets/ejemplos/ y se pasan como "anteriores", por lo que nunca se suben ni se borran.
 */

declare(strict_types=1);

require_once __DIR__ . '/qr_paginas.php';

const QR_EJEMPLOS_CARPETA = 'assets/ejemplos/';

/**
 * Textos de la página explicativa de un tipo, o null si no existe.
 * Claves: titulo, intro, incluye[], usos[], notas[].
 */
function qr_tipo_info(string $tipo): ?array
{
    $info = [
        'url' => [
            'titulo' => 'Código QR para sitio web',
            'intro' => 'Lleva a cualquier página web: tu tienda en línea, tu menú en otro sitio o un formulario. Como es dinámico, puedes cambiar la dirección cuando quieras y el código impreso seguirá funcionando.',
            'incluye' => ['La dirección de cualquier sitio web', 'Cambio de destino sin reimprimir', 'Historial de destinos anteriores', 'Estadísticas de escaneos'],
            'usos' => ['Volantes y carteles que llevan a una promoción', 'Empaques que llevan a instrucciones o garantía', 'Tarjetas que llevan a tu tienda en línea'],
            'notas' => ['Al escanearlo, el celular abre el sitio de inmediato: no hay página intermedia.'],
        ],
        'pdf' => [
            'titulo' => 'Código QR para PDF',
            'intro' => 'Muestra un documento PDF en el celular de quien escanea: un catálogo, una lista de precios, un manual o un reglamento. Si cambia el documento, subes el nuevo y el QR impreso muestra la versión actualizada.',
            'incluye' => ['El archivo PDF (hasta ' . QR_PAGINA_MAX_PDF / 1048576 . ' MB)', 'Título y descripción', 'Botones para ver y descargar el documento', 'Tus colores'],
            'usos' => ['Catálogos y listas de precios', 'Manuales e instrucciones en el empaque', 'Menús en PDF', 'Reglamentos y avisos'],
            'notas' => ['En la computadora el PDF se ve dentro de la página; en el celular se abre con el visor del teléfono.'],
        ],
        'enlaces' => [
            'titulo' => 'Código QR con lista de enlaces',
            'intro' => 'Reúne varios enlaces en una sola página: tu tienda, tus redes, reservaciones y lo que necesites. Quien escanea elige a dónde ir, y tú cambias o reordenas los botones cuando quieras.',
            'incluye' => ['Hasta ' . QR_PAGINA_MAX_ENLACES . ' botones con su texto', 'Tu logo o imagen', 'Título y descripción', 'Tus colores'],
            'usos' => ['Mostrador o caja de un negocio', 'Perfil de creadores de contenido', 'Ferias y exposiciones', 'Firmas de correo e impresos'],
            'notas' => ['Si un enlace deja de servir, lo quitas desde tu cuenta y el QR impreso sigue sirviendo.'],
        ],
        'vcard' => [
            'titulo' => 'Código QR para tarjeta de presentación (vCard)',
            'intro' => 'Comparte tus datos de contacto en una página con tu foto, teléfono, correo y dirección. Con un botón, quien escanea guarda tu contacto en su celular. Si cambias de puesto o de número, actualizas la página sin reimprimir tus tarjetas.',
            'incluye' => ['Foto, nombre, cargo y empresa', 'Teléfono, correo, sitio web y dirección', 'Botón «Guardar contacto» (archivo .vcf)', 'Una nota breve'],
            'usos' => ['Tarjetas de presentación', 'Gafetes de eventos', 'Firmas de correo', 'Vendedores y asesores'],
            'notas' => ['La foto se incluye en el contacto si es JPG o PNG de hasta 1 MB.', 'Un vCard estático también es gratis, pero no se puede editar ni tiene página.'],
        ],
        'menu' => [
            'titulo' => 'Código QR para menú de restaurante',
            'intro' => 'Crea el menú de tu restaurante, café o taquería con secciones, platillos, descripciones y precios. Cambias precios o el platillo del día desde tu cuenta, sin reimprimir los porta-menús.',
            'incluye' => ['Secciones (Entradas, Bebidas, Postres…)', 'Platillos con descripción y precio', 'Logo, nombre y descripción del lugar', 'Tus colores'],
            'usos' => ['Mesas de restaurantes y cafés', 'Puestos y food trucks', 'Servicio a domicilio', 'Barras y bares'],
            'notas' => ['Admite hasta ' . QR_PAGINA_MAX_PLATILLOS . ' platillos. Los precios se muestran en pesos.'],
        ],
        'cupon' => [
            'titulo' => 'Código QR para cupones de descuento',
            'intro' => 'Comparte un cupón con su descuento, el código para canjearlo, la fecha de vigencia y las condiciones. Cuando vence, la página lo indica sola, y puedes cambiar la promoción por una nueva con el mismo QR.',
            'incluye' => ['Descuento destacado y código para copiar', 'Fecha de vigencia (avisa cuando vence)', 'Condiciones', 'Botón opcional para usarlo en línea'],
            'usos' => ['Volantes y bolsas de compra', 'Tickets de compra para la siguiente visita', 'Vitrinas y mostradores', 'Promociones de temporada'],
            'notas' => ['Con las estadísticas sabes cuántas personas vieron el cupón.'],
        ],
        'wifi' => [
            'titulo' => 'Código QR para Wi-Fi',
            'intro' => 'Muestra el nombre de tu red y la contraseña con un botón para copiarla. Si cambias la contraseña, la actualizas en tu cuenta y el código pegado en la pared sigue sirviendo.',
            'incluye' => ['Nombre de la red y contraseña', 'Botón para copiar la contraseña', 'Instrucciones para conectarse', 'Tus colores'],
            'usos' => ['Cafés, restaurantes y hoteles', 'Salas de espera y consultorios', 'Oficinas para visitantes', 'Rentas vacacionales'],
            'notas' => ['El QR de Wi-Fi estático (gratis) conecta el celular directamente, pero si cambias la contraseña hay que reimprimirlo.'],
        ],
        'evento' => [
            'titulo' => 'Código QR para eventos',
            'intro' => 'Promociona un concierto, una boda, una conferencia o una inauguración con su fecha, hora y lugar. Quien escanea lo agrega a su calendario con un botón y llega con el mapa.',
            'incluye' => ['Imagen, nombre y descripción', 'Fecha y hora de inicio y fin', 'Lugar con enlace al mapa', 'Botón «Agregar a mi calendario» y botón de boletos o registro'],
            'usos' => ['Carteles y volantes', 'Invitaciones de boda o XV años', 'Conferencias y talleres', 'Inauguraciones'],
            'notas' => ['El archivo de calendario funciona en iPhone, Android, Google Calendar y Outlook.'],
        ],
        'empresa' => [
            'titulo' => 'Código QR con la información de tu negocio',
            'intro' => 'Una página con lo esencial de tu negocio: a qué se dedica, horario, dirección, teléfono, correo y redes sociales. Ideal para que tus clientes te encuentren y te contacten en un toque.',
            'incluye' => ['Logo, nombre y descripción', 'Botones para llamar y visitar tu sitio', 'Horario, dirección con mapa, teléfono y correo', 'Enlaces a tus redes sociales'],
            'usos' => ['Fachadas y puertas', 'Vehículos de reparto', 'Tarjetas y bolsas', 'Directorios y plazas comerciales'],
            'notas' => ['Si cambias de horario o de teléfono, solo actualizas la página.'],
        ],
        'video' => [
            'titulo' => 'Código QR para video',
            'intro' => 'Muestra un video de YouTube o Vimeo en una página con su título y descripción. Quien escanea lo reproduce ahí mismo, sin buscarlo.',
            'incluye' => ['Video de YouTube o Vimeo', 'Título y descripción', 'Tus colores'],
            'usos' => ['Instrucciones de armado o uso', 'Recorridos de propiedades', 'Presentaciones de producto', 'Mensajes de bienvenida'],
            'notas' => ['Los videos de YouTube se muestran en modo de privacidad mejorada (youtube-nocookie).'],
        ],
        'imagenes' => [
            'titulo' => 'Código QR para galería de imágenes',
            'intro' => 'Comparte varias fotos en una galería: una propiedad en venta, tus trabajos, un producto desde varios ángulos o las fotos de un evento.',
            'incluye' => ['Hasta ' . QR_PAGINA_MAX_IMAGENES . ' imágenes', 'Título y descripción', 'Cada foto se abre en grande', 'Tus colores'],
            'usos' => ['Letreros de «Se vende» o «Se renta»', 'Portafolios', 'Fotos de bodas y eventos', 'Catálogos de producto'],
            'notas' => ['Cada imagen puede pesar hasta ' . QR_PAGINA_MAX_IMAGEN / 1048576 . ' MB (PNG, JPG, WebP o GIF).'],
        ],
        'facebook' => [
            'titulo' => 'Código QR para Facebook',
            'intro' => 'Lleva directo a tu página de Facebook para que te sigan, te escriban o dejen una reseña. Si cambias de página, actualizas el enlace sin reimprimir.',
            'incluye' => ['Enlace a tu página o perfil', 'Cambio de destino sin reimprimir', 'Estadísticas de escaneos'],
            'usos' => ['Mostradores y vitrinas', 'Volantes y tarjetas', 'Empaques'],
            'notas' => ['Basta con escribir el nombre de usuario de tu página: el enlace se arma solo.'],
        ],
        'instagram' => [
            'titulo' => 'Código QR para Instagram',
            'intro' => 'Lleva directo a tu perfil de Instagram para ganar seguidores desde tus impresos. Escribes tu usuario (por ejemplo @tunegocio) y listo.',
            'incluye' => ['Enlace a tu perfil', 'Cambio de destino sin reimprimir', 'Estadísticas de escaneos'],
            'usos' => ['Mostradores y espejos de tiendas', 'Etiquetas y empaques', 'Eventos y pop-ups'],
            'notas' => ['Si el celular tiene la app de Instagram, el perfil se abre ahí.'],
        ],
        'redes' => [
            'titulo' => 'Código QR para redes sociales',
            'intro' => 'Reúne todas tus redes en una página: Facebook, Instagram, TikTok, X, YouTube, LinkedIn y tu sitio web. Quien escanea elige dónde seguirte.',
            'incluye' => ['Botones a ' . count(QR_PAGINA_REDES) . ' redes sociales y tu sitio web', 'Tu imagen o logo', 'Título y descripción', 'Tus colores'],
            'usos' => ['Mostradores y empaques', 'Creadores de contenido', 'Eventos y conferencias'],
            'notas' => ['Solo aparecen las redes que llenes.'],
        ],
        'whatsapp' => [
            'titulo' => 'Código QR para WhatsApp',
            'intro' => 'Abre un chat de WhatsApp contigo, con un mensaje ya escrito si quieres. Ideal para pedidos, citas y cotizaciones. Si cambias de número, lo actualizas sin reimprimir.',
            'incluye' => ['Tu número a 10 dígitos', 'Mensaje inicial opcional', 'Cambio de número sin reimprimir', 'Estadísticas de escaneos'],
            'usos' => ['Pedidos a domicilio', 'Citas en consultorios y estéticas', 'Cotizaciones', 'Atención a clientes'],
            'notas' => ['Funciona con WhatsApp y WhatsApp Business.'],
        ],
        'mp3' => [
            'titulo' => 'Código QR para audio y MP3',
            'intro' => 'Comparte un archivo de audio con su portada: un episodio de podcast, una canción, una audioguía o un mensaje. Se escucha en la misma página.',
            'incluye' => ['Audio MP3, M4A, OGG o WAV (hasta ' . QR_PAGINA_MAX_AUDIO / 1048576 . ' MB)', 'Portada, título y descripción', 'Reproductor y botón de descarga', 'Tus colores'],
            'usos' => ['Audioguías de museos y recorridos', 'Podcasts', 'Músicos y bandas', 'Mensajes en regalos'],
            'notas' => ['El audio se reproduce en el navegador: no hace falta instalar nada.'],
        ],
        'apps' => [
            'titulo' => 'Código QR para descargar tu app',
            'intro' => 'Un solo código para tu aplicación: en iPhone abre App Store y en Android, Google Play. En una computadora muestra una página con ambas tiendas.',
            'incluye' => ['Enlaces a App Store y Google Play', 'Detección automática del celular', 'Ícono, nombre y descripción de tu app', 'Tus colores'],
            'usos' => ['Mostradores y tickets', 'Anuncios impresos', 'Empaques de producto'],
            'notas' => ['Si solo tienes la app en una tienda, deja la otra vacía.'],
        ],
        'email' => [
            'titulo' => 'Código QR para correo electrónico',
            'intro' => 'Una página con un botón que abre un correo nuevo dirigido a ti, con el asunto y el mensaje ya escritos. Facilita que te pidan cotizaciones o te dejen comentarios.',
            'incluye' => ['Correo de destino', 'Asunto y mensaje ya escritos', 'Título de la página', 'Tus colores'],
            'usos' => ['Cotizaciones', 'Buzón de sugerencias', 'Soporte y garantías', 'Solicitudes de empleo'],
            'notas' => ['El correo se abre en la aplicación de correo del celular.'],
        ],
    ];
    return isset(QR_TIPOS_DINAMICOS[$tipo]) ? ($info[$tipo] ?? null) : null;
}

/** Datos del ejemplo: [$post (como el formulario), $archivos (rutas de assets/ejemplos)]. */
function qr_tipo_ejemplo_datos(string $tipo): array
{
    $a = fn(string $archivo) => QR_EJEMPLOS_CARPETA . $archivo;
    $hoy = new DateTimeImmutable('today');
    $sabado = $hoy->modify('next saturday');
    $paleta = fn(int $i) => ['primario' => QR_PAGINA_PALETAS[$i][0], 'secundario' => QR_PAGINA_PALETAS[$i][1]];

    return match ($tipo) {
        'url' => [['url' => 'https://www.example.com/tienda'], []],
        'whatsapp' => [['telefono' => '55 1234 5678', 'mensaje' => 'Hola, quiero hacer un pedido'], []],
        'facebook' => [['pg' => ['facebook' => ['url' => 'cafelunaejemplo']]], []],
        'instagram' => [['pg' => ['instagram' => ['url' => '@cafelunaejemplo']]], []],
        'pdf' => [['pg' => ['pdf' => ['titulo' => 'Catálogo de temporada 2026', 'descripcion' => "Nuestras bebidas y panes de temporada.\nPrecios vigentes hasta el 30 de noviembre."]], 'pagina_estilo' => $paleta(0)],
            ['archivo' => $a('catalogo.pdf')]],
        'enlaces' => [['pg' => ['enlaces' => ['titulo' => 'Café Luna', 'descripcion' => 'Café de especialidad en la Roma. Todo lo que tenemos para ti, en un solo lugar.',
            'enlaces' => [['texto' => 'Pedir a domicilio', 'url' => 'https://www.example.com/pedidos'], ['texto' => 'Reservar una mesa', 'url' => 'https://www.example.com/reservas'],
                ['texto' => 'Nuestra tienda en línea', 'url' => 'https://www.example.com/tienda'], ['texto' => 'Únete al equipo', 'url' => 'https://www.example.com/empleo']]]], 'pagina_estilo' => $paleta(4)],
            ['imagen' => $a('logo-cafe.svg')]],
        'vcard' => [['pg' => ['vcard' => ['nombre' => 'Ana López Martínez', 'cargo' => 'Directora comercial', 'empresa' => 'Inmobiliaria Horizonte',
            'telefono' => '55 1234 5678', 'email' => 'ana.lopez@example.com', 'web' => 'https://www.example.com',
            'direccion' => 'Av. Insurgentes Sur 1234, Del Valle, CDMX', 'nota' => 'Atención de lunes a sábado. Escríbeme por WhatsApp para agendar una visita.']], 'pagina_estilo' => $paleta(1)],
            ['imagen' => $a('foto-contacto.svg')]],
        'menu' => [['pg' => ['menu' => ['titulo' => 'Taquería Don Chuy', 'descripcion' => "Abierto de martes a domingo, de 13:00 a 23:00.\nServicio a domicilio en la colonia.",
            'secciones' => [
                ['nombre' => 'Tacos', 'platillos' => [['nombre' => 'Pastor', 'descripcion' => 'Orden de 4, con piña, cebolla y cilantro', 'precio' => '95'],
                    ['nombre' => 'Suadero', 'descripcion' => 'Orden de 4', 'precio' => '90'], ['nombre' => 'Campechano', 'descripcion' => 'Suadero con longaniza, orden de 4', 'precio' => '100']]],
                ['nombre' => 'Bebidas', 'platillos' => [['nombre' => 'Agua de horchata', 'descripcion' => 'Medio litro', 'precio' => '35'], ['nombre' => 'Refresco', 'descripcion' => '', 'precio' => '30']]],
                ['nombre' => 'Postres', 'platillos' => [['nombre' => 'Flan napolitano', 'descripcion' => 'Hecho en casa', 'precio' => '45']]],
            ]]], 'pagina_estilo' => $paleta(4)],
            ['imagen' => $a('logo-taqueria.svg')]],
        'cupon' => [['pg' => ['cupon' => ['titulo' => '2x1 en cafés de especialidad', 'descuento' => '2x1', 'codigo' => 'LUNA2X1',
            'descripcion' => 'Pide dos cafés de especialidad y paga solo uno.', 'vigencia' => $hoy->modify('last day of next month')->format('Y-m-d'),
            'condiciones' => 'Válido de lunes a jueves. No acumulable con otras promociones. Un cupón por visita.',
            'boton_texto' => 'Ver sucursales', 'boton_url' => 'https://www.example.com/sucursales']], 'pagina_estilo' => $paleta(3)],
            ['imagen' => $a('portada-cafe.svg')]],
        'wifi' => [['pg' => ['wifi' => ['titulo' => 'Wi-Fi para clientes', 'red' => 'CafeLuna-Clientes', 'clave' => 'cafeconpan2026', 'seguridad' => 'WPA']], 'pagina_estilo' => $paleta(2)], []],
        'evento' => [['pg' => ['evento' => ['titulo' => 'Noche de jazz en la terraza', 'descripcion' => 'Trío en vivo, coctelería de autor y cena de tres tiempos.',
            'inicio' => $sabado->format('Y-m-d') . 'T20:00', 'fin' => $sabado->format('Y-m-d') . 'T23:30',
            'lugar' => 'Foro Luna', 'direccion' => 'Av. Juárez 10, Centro, CDMX',
            'boton_texto' => 'Comprar boletos', 'boton_url' => 'https://www.example.com/boletos']], 'pagina_estilo' => $paleta(5)],
            ['imagen' => $a('portada-evento.svg')]],
        'empresa' => [['pg' => ['empresa' => ['nombre' => 'Ferretería El Clavo', 'descripcion' => 'Herramienta, material eléctrico y plomería desde 1985. Hacemos duplicado de llaves.',
            'telefono' => '33 3123 4567', 'email' => 'ventas@example.com', 'web' => 'https://www.example.com',
            'direccion' => 'Av. Juárez 450, Centro, Guadalajara, Jal.', 'horario' => "Lunes a viernes de 8:00 a 19:00\nSábado de 9:00 a 14:00",
            'redes' => ['facebook' => 'https://www.facebook.com/example', 'instagram' => 'https://www.instagram.com/example']]], 'pagina_estilo' => $paleta(1)],
            ['imagen' => $a('logo-ferreteria.svg')]],
        'video' => [['pg' => ['video' => ['url' => 'https://vimeo.com/76979871', 'titulo' => 'Conoce nuestro taller',
            'descripcion' => 'Un recorrido de un minuto por el lugar donde hacemos cada pieza.']], 'pagina_estilo' => $paleta(7)], []],
        'imagenes' => [['pg' => ['imagenes' => ['titulo' => 'Casa en venta en Coyoacán', 'descripcion' => '3 recámaras, 2 baños, jardín y cochera para dos autos.']], 'pagina_estilo' => $paleta(6)],
            ['imagenes' => [$a('casa-fachada.svg'), $a('casa-sala.svg'), $a('casa-cocina.svg'), $a('casa-jardin.svg')]]],
        'redes' => [['pg' => ['redes' => ['titulo' => 'Café Luna', 'descripcion' => 'Síguenos para enterarte de promociones y nuevos sabores.',
            'redes' => ['facebook' => 'https://www.facebook.com/example', 'instagram' => 'https://www.instagram.com/example', 'tiktok' => 'https://www.tiktok.com/@example', 'youtube' => 'https://www.youtube.com/@example'],
            'web' => 'https://www.example.com']], 'pagina_estilo' => $paleta(5)],
            ['imagen' => $a('logo-cafe.svg')]],
        'mp3' => [['pg' => ['mp3' => ['titulo' => 'Episodio 1: Cómo empezó todo', 'descripcion' => 'Historias de barrio, el podcast de los negocios de la colonia.']], 'pagina_estilo' => $paleta(2)],
            ['audio' => $a('audio-ejemplo.wav'), 'imagen' => $a('portada-podcast.svg')]],
        'apps' => [['pg' => ['apps' => ['titulo' => 'Luna App', 'descripcion' => 'Pide desde tu celular, paga sin filas y acumula puntos en cada compra.',
            'ios' => 'https://apps.apple.com/mx/app/id0000000000', 'android' => 'https://play.google.com/store/apps/details?id=com.example.luna']], 'pagina_estilo' => $paleta(5)],
            ['imagen' => $a('icono-app.svg')]],
        'email' => [['pg' => ['email' => ['titulo' => 'Pide tu cotización', 'email' => 'cotizaciones@example.com', 'asunto' => 'Quiero una cotización',
            'mensaje' => "Hola, me interesa cotizar:\n\nNombre:\nTeléfono:"]], 'pagina_estilo' => $paleta(0)], []],
        default => [[], []],
    };
}

/** Campos del ejemplo, validados como un QR real. En los que redirigen: ['destino' => URL]. */
function qr_tipo_ejemplo_campos(string $tipo): array
{
    [$post, $archivos] = qr_tipo_ejemplo_datos($tipo);
    $contenido = qr_pagina_contenido($tipo, $post, [], $archivos);
    return qr_tipo_usa_url($tipo) ? ['destino' => $contenido['texto']] : $contenido['campos'];
}
