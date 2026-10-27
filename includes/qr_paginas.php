<?php
/**
 * PÁGINAS DE LOS QR DINÁMICOS
 * --------------------------------------------------------------------
 * Un QR dinámico de tipo página (PDF, menú, cupón, evento…) no redirige: r.php muestra una
 * página propia con sus datos (includes/qr_pagina_vista.php). Facebook e Instagram redirigen
 * al perfil; Aplicaciones redirige a la tienda según el celular o, en otro equipo, muestra ambas.
 *
 * - qr_pagina_contenido() valida el formulario (campos pg[tipo][...] de app/crear.php) y guarda
 *   los archivos en assets/uploads/paginas/. Devuelve ['campos', 'texto'] como qr_contenido():
 *   'texto' es la URL de destino, o '' si el QR muestra su página.
 * - Los archivos solo se guardan en las claves de QR_PAGINA_CAMPOS_ARCHIVO, siempre con rutas
 *   generadas aquí (nunca texto del usuario), para poder borrarlos sin riesgo al reemplazarlos.
 */

declare(strict_types=1);

require_once __DIR__ . '/qr.php';
require_once __DIR__ . '/archivos.php';

const QR_PAGINA_CARPETA = 'paginas';
const QR_PAGINA_CAMPOS_ARCHIVO = ['imagen', 'archivo', 'audio', 'imagenes'];
const QR_PAGINA_MAX_PDF = 10485760;     // 10 MB
const QR_PAGINA_MAX_AUDIO = 15728640;   // 15 MB
const QR_PAGINA_MAX_IMAGEN = 3145728;   // 3 MB
const QR_PAGINA_MAX_IMAGENES = 10;
const QR_PAGINA_MAX_ENLACES = 20;
const QR_PAGINA_MAX_SECCIONES = 20;
const QR_PAGINA_MAX_PLATILLOS = 150;

/** Redes que se pueden enlazar en Redes sociales y Empresa: clave => nombre. */
const QR_PAGINA_REDES = ['facebook' => 'Facebook', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'x' => 'X (Twitter)', 'youtube' => 'YouTube', 'linkedin' => 'LinkedIn'];

/* ---------------------------------------------------------------------
 * Validación de campos
 * ------------------------------------------------------------------- */

/** Texto de un campo: sin espacios a los lados ni caracteres de control (conserva saltos de línea). */
function qr_pagina_texto(array $v, string $campo, int $max): string
{
    $valor = is_scalar($v[$campo] ?? null) ? (string) $v[$campo] : '';
    $valor = (string) preg_replace('/[^\P{Cc}\n]/u', '', str_replace("\r\n", "\n", $valor));
    return mb_substr(trim($valor), 0, $max);
}

/** URL http(s); sin esquema se le agrega https://. '' si viene vacía y no es obligatoria. */
function qr_pagina_url(string $valor, string $etiqueta, bool $obligatoria = false): string
{
    $url = trim($valor);
    if ($url === '') {
        if ($obligatoria) {
            throw new QrInvalido("Escribe {$etiqueta}.");
        }
        return '';
    }
    if (!preg_match('/^[a-z][a-z0-9+.-]*:/i', $url)) {
        $url = 'https://' . $url;
    }
    if (strlen($url) > 2048 || preg_match('/\s/u', $url) || !preg_match('#^https?://[^\s/?\#]+\.[^\s/?\#]+#iu', $url)) {
        throw new QrInvalido("Revisa {$etiqueta}: no es una dirección web válida.");
    }
    return $url;
}

/** Dominio de una URL en minúsculas y sin "www.". */
function qr_pagina_dominio(string $url): string
{
    return (string) preg_replace('/^www\./', '', strtolower((string) parse_url($url, PHP_URL_HOST)));
}

/** ¿El dominio de la URL es alguno de $dominios (o un subdominio suyo)? */
function qr_pagina_url_de(string $url, array $dominios): bool
{
    $host = qr_pagina_dominio($url);
    foreach ($dominios as $d) {
        if ($host === $d || str_ends_with($host, '.' . $d)) {
            return true;
        }
    }
    return false;
}

function qr_pagina_email(string $valor, string $etiqueta, bool $obligatorio = false): string
{
    if ($valor === '' && !$obligatorio) {
        return '';
    }
    if (!filter_var($valor, FILTER_VALIDATE_EMAIL)) {
        throw new QrInvalido($valor === '' ? "Escribe {$etiqueta}." : "Revisa {$etiqueta}: no es un correo válido.");
    }
    return $valor;
}

function qr_pagina_telefono(string $valor, string $etiqueta): string
{
    if ($valor === '') {
        return '';
    }
    $digitos = preg_replace('/\D/', '', $valor);
    if (strlen($digitos) < 7 || strlen($digitos) > 15) {
        throw new QrInvalido("Revisa {$etiqueta}: escribe el número a 10 dígitos (o con lada internacional).");
    }
    return $valor;
}

/** Fecha 'Y-m-d' o fecha y hora 'Y-m-d H:i' a partir de lo que manda un <input type="date|datetime-local">. */
function qr_pagina_fecha(string $valor, string $etiqueta, bool $conHora, bool $obligatoria = false): string
{
    if ($valor === '') {
        if ($obligatoria) {
            throw new QrInvalido("Escribe {$etiqueta}.");
        }
        return '';
    }
    $formatos = $conHora ? ['Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i'] : ['Y-m-d'];
    foreach ($formatos as $f) {
        $d = DateTimeImmutable::createFromFormat('!' . $f, $valor);
        if ($d !== false && $d->format($f) === $valor) {
            return $d->format($conHora ? 'Y-m-d H:i' : 'Y-m-d');
        }
    }
    throw new QrInvalido("Revisa {$etiqueta}: la fecha no es válida.");
}

/** URL para incrustar un video de YouTube o Vimeo, o null si no es de esos sitios. */
function qr_pagina_video_incrustado(string $url): ?string
{
    if (preg_match('~^https?://(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:[^#]*&)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})~i', $url, $m)) {
        return 'https://www.youtube-nocookie.com/embed/' . $m[1];
    }
    if (preg_match('~^https?://(?:www\.|player\.)?vimeo\.com/(?:video/)?(\d{5,12})~i', $url, $m)) {
        return 'https://player.vimeo.com/video/' . $m[1];
    }
    return null;
}

/** Enlaces de redes (claves de QR_PAGINA_REDES) que vienen en $v['redes']. */
function qr_pagina_redes(array $v): array
{
    $entrada = is_array($v['redes'] ?? null) ? $v['redes'] : [];
    $redes = [];
    foreach (QR_PAGINA_REDES as $clave => $nombre) {
        $url = qr_pagina_url(qr_pagina_texto($entrada, $clave, 2048), "el enlace de {$nombre}");
        if ($url !== '') {
            $redes[$clave] = $url;
        }
    }
    return $redes;
}

/* ---------------------------------------------------------------------
 * Archivos
 * ------------------------------------------------------------------- */

/**
 * Archivos subidos de un tipo, a partir de $_FILES (campos pg[tipo][campo] y pg[tipo][campo][]):
 * [campo => archivo] o [campo => [archivo, ...]] con la forma de un elemento de $_FILES.
 */
function qr_pagina_archivos_subidos(array $files, string $tipo): array
{
    $pg = $files['pg'] ?? null;
    if (!is_array($pg) || !is_array($pg['name'][$tipo] ?? null)) {
        return [];
    }
    $uno = fn(array $ruta) => [
        'name' => qr_pagina_dato($pg['name'], $ruta), 'type' => qr_pagina_dato($pg['type'], $ruta),
        'tmp_name' => qr_pagina_dato($pg['tmp_name'], $ruta), 'error' => (int) (qr_pagina_dato($pg['error'], $ruta) ?? UPLOAD_ERR_NO_FILE),
        'size' => (int) (qr_pagina_dato($pg['size'], $ruta) ?? 0),
    ];
    $res = [];
    foreach ($pg['name'][$tipo] as $campo => $nombre) {
        if (is_array($nombre)) {
            foreach (array_keys($nombre) as $i) {
                $res[$campo][] = $uno([$tipo, $campo, $i]);
            }
        } else {
            $res[$campo] = $uno([$tipo, $campo]);
        }
    }
    return $res;
}

/** Valor anidado de un arreglo, o null. */
function qr_pagina_dato(mixed $arreglo, array $ruta): mixed
{
    foreach ($ruta as $clave) {
        if (!is_array($arreglo) || !array_key_exists($clave, $arreglo)) {
            return null;
        }
        $arreglo = $arreglo[$clave];
    }
    return $arreglo;
}

function qr_pagina_hay_archivo(?array $file): bool
{
    return $file !== null && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
}

/** Rutas de los archivos que usa una página (solo de las claves de archivo). */
function qr_pagina_rutas(array $campos): array
{
    $rutas = [];
    foreach (QR_PAGINA_CAMPOS_ARCHIVO as $clave) {
        foreach ((array) ($campos[$clave] ?? []) as $ruta) {
            if (is_string($ruta) && str_starts_with($ruta, 'assets/uploads/' . QR_PAGINA_CARPETA . '/')) {
                $rutas[] = $ruta;
            }
        }
    }
    return array_values(array_unique($rutas));
}

/* ---------------------------------------------------------------------
 * Contenido de cada tipo
 * ------------------------------------------------------------------- */

/**
 * Valida el contenido de un QR dinámico. $post y $files son $_POST y $_FILES; $anterior, los
 * campos guardados del mismo tipo (para conservar archivos al editar). Devuelve
 * ['campos' => [...], 'texto' => URL de destino o '']. Lanza QrInvalido; si falla, borra los
 * archivos que alcanzó a guardar.
 */
function qr_pagina_contenido(string $tipo, array $post, array $files, array $anterior = []): array
{
    if (!isset(QR_TIPOS_DINAMICOS[$tipo])) {
        throw new QrInvalido('Elige un tipo de contenido válido.');
    }
    if ($tipo === 'url' || $tipo === 'whatsapp') {
        return qr_contenido($tipo, $post); // mismos campos que el estático
    }

    $v = is_array($post['pg'][$tipo] ?? null) ? $post['pg'][$tipo] : [];
    $subidos = qr_pagina_archivos_subidos($files, $tipo);
    $nuevos = [];
    try {
        $campos = qr_pagina_campos($tipo, $v, $subidos, $anterior, $nuevos);
    } catch (Throwable $exception) {
        array_map('archivo_borrar', $nuevos);
        throw $exception instanceof QrInvalido || !$exception instanceof RuntimeException
            ? $exception
            : new QrInvalido($exception->getMessage());
    }
    $texto = match ($tipo) {
        'facebook', 'instagram' => $campos['url'],
        default => '',
    };
    return ['campos' => $campos, 'texto' => $texto];
}

/** Campos de cada tipo. Los archivos guardados se agregan a $nuevos. */
function qr_pagina_campos(string $tipo, array $v, array $subidos, array $anterior, array &$nuevos): array
{
    $t = fn(string $campo, int $max) => qr_pagina_texto($v, $campo, $max);

    // Archivo de un campo: el nuevo, el anterior (si no se pidió quitarlo) o null.
    $archivo = function (string $campo, string $etiqueta, string $clase, bool $obligatorio) use ($v, $subidos, $anterior, &$nuevos): ?string {
        $file = is_array($subidos[$campo] ?? null) && isset($subidos[$campo]['error']) ? $subidos[$campo] : null;
        if (qr_pagina_hay_archivo($file)) {
            $ruta = match ($clase) {
                'pdf' => archivo_guardar_subida($file, QR_PAGINA_CARPETA, $etiqueta, ARCHIVO_TIPOS_PDF, 'un archivo PDF', QR_PAGINA_MAX_PDF),
                'audio' => archivo_guardar_subida($file, QR_PAGINA_CARPETA, $etiqueta, ARCHIVO_TIPOS_AUDIO, 'un audio MP3, M4A, OGG o WAV', QR_PAGINA_MAX_AUDIO),
                default => imagen_guardar_subida($file, QR_PAGINA_CARPETA, $etiqueta, QR_PAGINA_MAX_IMAGEN, 4096),
            };
            $nuevos[] = $ruta;
            return $ruta;
        }
        $previo = is_string($anterior[$campo] ?? null) ? $anterior[$campo] : null;
        if ($previo !== null && !$obligatorio && !empty($v['quitar_' . $campo])) {
            $previo = null;
        }
        if ($previo === null && $obligatorio) {
            throw new QrInvalido("Sube {$etiqueta}.");
        }
        return $previo;
    };

    switch ($tipo) {
        case 'pdf':
            $c = ['titulo' => $t('titulo', 120), 'descripcion' => $t('descripcion', 1000)];
            $c['archivo'] = $archivo('archivo', 'el archivo PDF', 'pdf', true);
            return $c;

        case 'enlaces':
            $c = ['titulo' => $t('titulo', 120), 'descripcion' => $t('descripcion', 1000), 'enlaces' => []];
            if ($c['titulo'] === '') {
                throw new QrInvalido('Escribe el título de tu lista de enlaces.');
            }
            foreach (array_slice(is_array($v['enlaces'] ?? null) ? $v['enlaces'] : [], 0, QR_PAGINA_MAX_ENLACES) as $e) {
                if (!is_array($e)) continue;
                $texto = qr_pagina_texto($e, 'texto', 80);
                $url = qr_pagina_url(qr_pagina_texto($e, 'url', 2048), 'el enlace' . ($texto !== '' ? " «{$texto}»" : ''));
                if ($url === '') {
                    if ($texto !== '') throw new QrInvalido("Escribe la dirección del enlace «{$texto}».");
                    continue;
                }
                $c['enlaces'][] = ['texto' => $texto !== '' ? $texto : qr_pagina_dominio($url), 'url' => $url];
            }
            if (!$c['enlaces']) {
                throw new QrInvalido('Agrega al menos un enlace.');
            }
            $c['imagen'] = $archivo('imagen', 'la imagen', 'imagen', false);
            return $c;

        case 'vcard':
            $c = ['nombre' => $t('nombre', 120), 'cargo' => $t('cargo', 120), 'empresa' => $t('empresa', 120),
                  'telefono' => qr_pagina_telefono($t('telefono', 30), 'el teléfono'),
                  'email' => qr_pagina_email($t('email', 190), 'el correo'),
                  'web' => qr_pagina_url($t('web', 2048), 'el sitio web'),
                  'direccion' => $t('direccion', 250), 'nota' => $t('nota', 500)];
            if ($c['nombre'] === '') {
                throw new QrInvalido('Escribe el nombre del contacto.');
            }
            $c['imagen'] = $archivo('imagen', 'la foto', 'imagen', false);
            return $c;

        case 'menu':
            $c = ['titulo' => $t('titulo', 120), 'descripcion' => $t('descripcion', 1000), 'secciones' => []];
            if ($c['titulo'] === '') {
                throw new QrInvalido('Escribe el nombre de tu restaurante o del menú.');
            }
            $platillos = 0;
            foreach (array_slice(is_array($v['secciones'] ?? null) ? $v['secciones'] : [], 0, QR_PAGINA_MAX_SECCIONES) as $s) {
                if (!is_array($s)) continue;
                $seccion = ['nombre' => qr_pagina_texto($s, 'nombre', 80), 'platillos' => []];
                foreach (is_array($s['platillos'] ?? null) ? $s['platillos'] : [] as $p) {
                    if (!is_array($p)) continue;
                    $platillo = ['nombre' => qr_pagina_texto($p, 'nombre', 120), 'descripcion' => qr_pagina_texto($p, 'descripcion', 300), 'precio' => qr_pagina_texto($p, 'precio', 20)];
                    if (implode('', $platillo) === '') continue;
                    if ($platillo['nombre'] === '') {
                        throw new QrInvalido('Cada platillo del menú necesita un nombre.');
                    }
                    $seccion['platillos'][] = $platillo;
                    $platillos++;
                }
                if ($seccion['nombre'] === '' && !$seccion['platillos']) continue;
                if ($seccion['nombre'] === '') {
                    throw new QrInvalido('Escribe el nombre de cada sección del menú (por ejemplo, Entradas).');
                }
                $c['secciones'][] = $seccion;
            }
            if ($platillos === 0) {
                throw new QrInvalido('Agrega al menos un platillo al menú.');
            }
            if ($platillos > QR_PAGINA_MAX_PLATILLOS) {
                throw new QrInvalido('El menú admite hasta ' . QR_PAGINA_MAX_PLATILLOS . ' platillos.');
            }
            $c['imagen'] = $archivo('imagen', 'el logo', 'imagen', false);
            return $c;

        case 'cupon':
            $c = ['titulo' => $t('titulo', 120), 'descuento' => $t('descuento', 40), 'descripcion' => $t('descripcion', 1000),
                  'codigo' => $t('codigo', 40), 'vigencia' => qr_pagina_fecha($t('vigencia', 10), 'la vigencia', false),
                  'condiciones' => $t('condiciones', 1000), 'boton_texto' => $t('boton_texto', 40),
                  'boton_url' => qr_pagina_url($t('boton_url', 2048), 'el enlace del botón')];
            if ($c['titulo'] === '') {
                throw new QrInvalido('Escribe el título del cupón.');
            }
            $c['imagen'] = $archivo('imagen', 'la imagen', 'imagen', false);
            return $c;

        case 'wifi':
            $wifi = qr_contenido('wifi', ['red' => $t('red', 32), 'clave' => $t('clave', 63), 'seguridad' => $t('seguridad', 10)]);
            return ['titulo' => $t('titulo', 120)] + $wifi['campos'];

        case 'evento':
            $c = ['titulo' => $t('titulo', 120), 'descripcion' => $t('descripcion', 1500),
                  'inicio' => qr_pagina_fecha($t('inicio', 19), 'la fecha y hora de inicio', true, true),
                  'fin' => qr_pagina_fecha($t('fin', 19), 'la fecha y hora de fin', true),
                  'lugar' => $t('lugar', 120), 'direccion' => $t('direccion', 250),
                  'boton_texto' => $t('boton_texto', 40), 'boton_url' => qr_pagina_url($t('boton_url', 2048), 'el enlace de boletos o registro')];
            if ($c['titulo'] === '') {
                throw new QrInvalido('Escribe el nombre del evento.');
            }
            if ($c['fin'] !== '' && $c['fin'] < $c['inicio']) {
                throw new QrInvalido('El evento no puede terminar antes de empezar.');
            }
            $c['imagen'] = $archivo('imagen', 'la imagen', 'imagen', false);
            return $c;

        case 'empresa':
            $c = ['nombre' => $t('nombre', 120), 'descripcion' => $t('descripcion', 1500),
                  'telefono' => qr_pagina_telefono($t('telefono', 30), 'el teléfono'),
                  'email' => qr_pagina_email($t('email', 190), 'el correo'),
                  'web' => qr_pagina_url($t('web', 2048), 'el sitio web'),
                  'direccion' => $t('direccion', 250), 'horario' => $t('horario', 500), 'redes' => qr_pagina_redes($v)];
            if ($c['nombre'] === '') {
                throw new QrInvalido('Escribe el nombre de tu negocio.');
            }
            $c['imagen'] = $archivo('imagen', 'el logo', 'imagen', false);
            return $c;

        case 'video':
            $c = ['titulo' => $t('titulo', 120), 'descripcion' => $t('descripcion', 1000),
                  'url' => qr_pagina_url($t('url', 2048), 'el enlace del video', true)];
            if (qr_pagina_video_incrustado($c['url']) === null) {
                throw new QrInvalido('Pega el enlace de un video de YouTube o Vimeo.');
            }
            return $c;

        case 'imagenes':
            $c = ['titulo' => $t('titulo', 120), 'descripcion' => $t('descripcion', 1000)];
            $quitar = array_filter((array) ($v['quitar_imagenes'] ?? []), 'is_string');
            $conservar = array_values(array_filter((array) ($anterior['imagenes'] ?? []), fn($r) => is_string($r) && !in_array($r, $quitar, true)));
            $nuevas = array_values(array_filter(is_array($subidos['imagenes'] ?? null) ? $subidos['imagenes'] : [], fn($f) => is_array($f) && qr_pagina_hay_archivo($f)));
            if (count($conservar) + count($nuevas) > QR_PAGINA_MAX_IMAGENES) {
                throw new QrInvalido('La galería admite hasta ' . QR_PAGINA_MAX_IMAGENES . ' imágenes.');
            }
            foreach ($nuevas as $i => $file) {
                $ruta = imagen_guardar_subida($file, QR_PAGINA_CARPETA, 'La imagen ' . ($i + 1), QR_PAGINA_MAX_IMAGEN, 4096);
                $nuevos[] = $ruta;
                $conservar[] = $ruta;
            }
            if (!$conservar) {
                throw new QrInvalido('Sube al menos una imagen.');
            }
            return $c + ['imagenes' => $conservar];

        case 'facebook':
            $valor = ltrim($t('url', 2048), '@');
            if (preg_match('/^[A-Za-z0-9.\-]{2,80}$/', $valor) && !str_contains($valor, '.com')) {
                $valor = 'https://www.facebook.com/' . $valor;
            }
            $url = qr_pagina_url($valor, 'el enlace de tu página de Facebook', true);
            if (!qr_pagina_url_de($url, ['facebook.com', 'fb.com', 'fb.me'])) {
                throw new QrInvalido('Pega el enlace de tu página de Facebook (facebook.com/…).');
            }
            return ['url' => $url];

        case 'instagram':
            $valor = ltrim($t('url', 2048), '@');
            if (preg_match('/^[A-Za-z0-9._]{1,30}$/', $valor) && !preg_match('/\.(com|me)$/i', $valor)) {
                $valor = 'https://www.instagram.com/' . $valor . '/';
            }
            $url = qr_pagina_url($valor, 'tu usuario o enlace de Instagram', true);
            if (!qr_pagina_url_de($url, ['instagram.com', 'instagr.am'])) {
                throw new QrInvalido('Escribe tu usuario de Instagram (por ejemplo @tunegocio) o pega el enlace de tu perfil.');
            }
            return ['url' => $url];

        case 'redes':
            $c = ['titulo' => $t('titulo', 120), 'descripcion' => $t('descripcion', 1000), 'redes' => qr_pagina_redes($v),
                  'web' => qr_pagina_url($t('web', 2048), 'el sitio web')];
            if ($c['titulo'] === '') {
                throw new QrInvalido('Escribe tu nombre o el de tu negocio.');
            }
            if (!$c['redes'] && $c['web'] === '') {
                throw new QrInvalido('Agrega el enlace de al menos una red social.');
            }
            $c['imagen'] = $archivo('imagen', 'la imagen', 'imagen', false);
            return $c;

        case 'mp3':
            $c = ['titulo' => $t('titulo', 120), 'descripcion' => $t('descripcion', 1000)];
            if ($c['titulo'] === '') {
                throw new QrInvalido('Escribe el título del audio.');
            }
            $c['audio'] = $archivo('audio', 'el archivo de audio', 'audio', true);
            $c['imagen'] = $archivo('imagen', 'la portada', 'imagen', false);
            return $c;

        case 'apps':
            $c = ['titulo' => $t('titulo', 120), 'descripcion' => $t('descripcion', 1000),
                  'ios' => qr_pagina_url($t('ios', 2048), 'el enlace de App Store'),
                  'android' => qr_pagina_url($t('android', 2048), 'el enlace de Google Play')];
            if ($c['titulo'] === '') {
                throw new QrInvalido('Escribe el nombre de tu aplicación.');
            }
            if ($c['ios'] === '' && $c['android'] === '') {
                throw new QrInvalido('Pega el enlace de tu app en App Store, en Google Play o en ambas.');
            }
            if ($c['ios'] !== '' && !qr_pagina_url_de($c['ios'], ['apps.apple.com', 'itunes.apple.com'])) {
                throw new QrInvalido('El enlace de App Store debe ser de apps.apple.com.');
            }
            if ($c['android'] !== '' && !qr_pagina_url_de($c['android'], ['play.google.com'])) {
                throw new QrInvalido('El enlace de Google Play debe ser de play.google.com.');
            }
            $c['imagen'] = $archivo('imagen', 'el ícono', 'imagen', false);
            return $c;

        case 'email':
            return ['titulo' => $t('titulo', 120), 'email' => qr_pagina_email($t('email', 190), 'el correo de destino', true),
                    'asunto' => $t('asunto', 200), 'mensaje' => $t('mensaje', 1000)];
    }
    throw new QrInvalido('Elige un tipo de contenido válido.');
}

/* ---------------------------------------------------------------------
 * Al escanear
 * ------------------------------------------------------------------- */

/** A dónde manda un QR de Aplicaciones según el celular; null = mostrar la página con ambas tiendas. */
function qr_pagina_destino_app(array $campos, string $ua): ?string
{
    $so = qr_detectar_agente($ua)['sistema_operativo'];
    if ($so === 'iOS' && ($campos['ios'] ?? '') !== '') {
        return $campos['ios'];
    }
    if ($so === 'Android' && ($campos['android'] ?? '') !== '') {
        return $campos['android'];
    }
    return null;
}

/** Nombre de archivo sin acentos ni símbolos: "Ana López" → "ana-lopez". */
function qr_pagina_nombre_archivo(string $texto): string
{
    $sinAcentos = strtr(mb_strtolower($texto), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
    return trim((string) preg_replace('/[^a-z0-9]+/', '-', $sinAcentos), '-');
}

/** Corta una línea de vCard o iCalendar a 75 bytes (RFC 6350 / 5545) sin partir caracteres UTF-8. */
function qr_pagina_doblar(string $linea): string
{
    $partes = [];
    while (strlen($linea) > 75) {
        $trozo = mb_strcut($linea, 0, empty($partes) ? 75 : 74, 'UTF-8');
        $partes[] = $trozo;
        $linea = substr($linea, strlen($trozo));
    }
    $partes[] = $linea;
    return implode("\r\n ", $partes);
}

/** Archivo .vcf (vCard 3.0) de una página vCard. */
function qr_pagina_vcard(array $c): string
{
    $lineas = ['BEGIN:VCARD', 'VERSION:3.0', 'N:;' . qr_escapar_vcard($c['nombre']) . ';;;', 'FN:' . qr_escapar_vcard($c['nombre'])];
    $extra = ['cargo' => 'TITLE', 'empresa' => 'ORG', 'telefono' => 'TEL;TYPE=CELL', 'email' => 'EMAIL;TYPE=INTERNET', 'web' => 'URL', 'nota' => 'NOTE'];
    foreach ($extra as $campo => $propiedad) {
        if (($c[$campo] ?? '') !== '') {
            $lineas[] = $propiedad . ':' . qr_escapar_vcard($c[$campo]);
        }
    }
    if (($c['direccion'] ?? '') !== '') {
        $lineas[] = 'ADR;TYPE=WORK:;;' . qr_escapar_vcard($c['direccion']) . ';;;;';
    }
    $foto = is_string($c['imagen'] ?? null) ? dirname(__DIR__) . '/' . $c['imagen'] : '';
    if ($foto !== '' && preg_match('/\.(jpg|png)$/', $foto, $m) && is_file($foto) && filesize($foto) <= 1048576) {
        $lineas[] = 'PHOTO;ENCODING=b;TYPE=' . ($m[1] === 'jpg' ? 'JPEG' : 'PNG') . ':' . base64_encode((string) file_get_contents($foto));
    }
    $lineas[] = 'END:VCARD';
    return implode("\r\n", array_map('qr_pagina_doblar', $lineas)) . "\r\n";
}

/** Escapa un texto de iCalendar (\ ; , y saltos de línea). */
function qr_pagina_escapar_ics(string $s): string
{
    return str_replace(["\r\n", "\n"], '\\n', (string) preg_replace('/([\\\\;,])/', '\\\\$1', $s));
}

/** Archivo .ics de una página de Evento (horas de la Ciudad de México convertidas a UTC). */
function qr_pagina_ics(array $c, string $uid): string
{
    $utc = fn(string $fecha) => (new DateTimeImmutable($fecha, new DateTimeZone(date_default_timezone_get())))
        ->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
    $fin = $c['fin'] !== '' ? $c['fin'] : (new DateTimeImmutable($c['inicio']))->modify('+2 hours')->format('Y-m-d H:i');
    $lugar = trim($c['lugar'] . ($c['lugar'] !== '' && $c['direccion'] !== '' ? ', ' : '') . $c['direccion']);
    $lineas = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//' . nombre_portal() . '//QR//ES', 'CALSCALE:GREGORIAN', 'BEGIN:VEVENT',
               'UID:' . $uid, 'DTSTAMP:' . gmdate('Ymd\THis\Z'), 'DTSTART:' . $utc($c['inicio']), 'DTEND:' . $utc($fin),
               'SUMMARY:' . qr_pagina_escapar_ics($c['titulo'])];
    if ($c['descripcion'] !== '') $lineas[] = 'DESCRIPTION:' . qr_pagina_escapar_ics($c['descripcion']);
    if ($lugar !== '') $lineas[] = 'LOCATION:' . qr_pagina_escapar_ics($lugar);
    if ($c['boton_url'] !== '') $lineas[] = 'URL:' . $c['boton_url'];
    array_push($lineas, 'END:VEVENT', 'END:VCALENDAR');
    return implode("\r\n", array_map('qr_pagina_doblar', $lineas)) . "\r\n";
}
