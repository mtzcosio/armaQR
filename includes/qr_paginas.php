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
const QR_PAGINA_MAX_DIAS = 10;
const QR_PAGINA_MAX_ACTIVIDADES = 100;

/** Redes que se pueden enlazar en Redes sociales y Empresa: clave => nombre. */
const QR_PAGINA_REDES = ['facebook' => 'Facebook', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'x' => 'X (Twitter)', 'youtube' => 'YouTube', 'linkedin' => 'LinkedIn'];

/** Paletas de la página (color principal, color de fondo): la primera es la predeterminada. */
const QR_PAGINA_PALETAS = [
    ['#172036', '#f4f1ea'], ['#2563eb', '#eaf0fd'], ['#0f766e', '#e7f5f2'], ['#db2777', '#fdf0f6'],
    ['#ea580c', '#fff3e8'], ['#7c3aed', '#f2edfe'], ['#15803d', '#ebf6ee'], ['#111827', '#ffffff'],
];

/* ---------------------------------------------------------------------
 * Modo borrador (vista previa en vivo de app/crear.php)
 * ------------------------------------------------------------------- */

/**
 * En modo borrador la página se arma con lo que haya aunque falten datos o tengan errores:
 * los obligatorios faltantes y los valores inválidos quedan vacíos en lugar de lanzar QrInvalido,
 * y los archivos elegidos (aún sin subir) se muestran con su URL blob: del navegador.
 */
function qr_pagina_borrador(?bool $activar = null): bool
{
    static $activo = false;
    if ($activar !== null) {
        $activo = $activar;
    }
    return $activo;
}

/** Falta un dato o no es válido: error al guardar, se ignora en la vista previa. */
function qr_pagina_falta(string $mensaje): void
{
    if (!qr_pagina_borrador()) {
        throw new QrInvalido($mensaje);
    }
}

/** URL blob: del navegador para la vista previa de un archivo que todavía no se sube. */
function qr_pagina_es_blob(mixed $valor): bool
{
    return is_string($valor) && preg_match('#^blob:https?://[A-Za-z0-9.\-:\[\]]+/[A-Za-z0-9\-]{8,64}$#', $valor) === 1;
}

/** Colores de la página (pagina_estilo[primario|secundario]); si no vienen o no son válidos, la paleta predeterminada. */
function qr_pagina_estilo(array $post): array
{
    $e = is_array($post['pagina_estilo'] ?? null) ? $post['pagina_estilo'] : [];
    $color = fn(string $clave, string $defecto) => is_string($e[$clave] ?? null) && preg_match('/^#[0-9a-f]{6}$/i', $e[$clave]) ? strtolower($e[$clave]) : $defecto;
    return ['primario' => $color('primario', QR_PAGINA_PALETAS[0][0]), 'secundario' => $color('secundario', QR_PAGINA_PALETAS[0][1])];
}

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
            qr_pagina_falta("Escribe {$etiqueta}.");
        }
        return '';
    }
    if (!preg_match('/^[a-z][a-z0-9+.-]*:/i', $url)) {
        $url = 'https://' . $url;
    }
    if (strlen($url) > 2048 || preg_match('/\s/u', $url) || !preg_match('#^https?://[^\s/?\#]+\.[^\s/?\#]+#iu', $url)) {
        qr_pagina_falta("Revisa {$etiqueta}: no es una dirección web válida.");
        return '';
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
        qr_pagina_falta($valor === '' ? "Escribe {$etiqueta}." : "Revisa {$etiqueta}: no es un correo válido.");
        return '';
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
        qr_pagina_falta("Revisa {$etiqueta}: escribe el número a 10 dígitos (o con lada internacional).");
    }
    return $valor;
}

/** Fecha 'Y-m-d' o fecha y hora 'Y-m-d H:i' a partir de lo que manda un <input type="date|datetime-local">. */
function qr_pagina_fecha(string $valor, string $etiqueta, bool $conHora, bool $obligatoria = false): string
{
    if ($valor === '') {
        if ($obligatoria) {
            qr_pagina_falta("Escribe {$etiqueta}.");
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
    qr_pagina_falta("Revisa {$etiqueta}: la fecha no es válida.");
    return '';
}

/** Hora 'HH:MM' de un <input type="time"> (acepta segundos y los quita). */
function qr_pagina_hora(string $valor, string $etiqueta, bool $obligatoria = false): string
{
    if ($valor === '') {
        if ($obligatoria) {
            qr_pagina_falta("Escribe {$etiqueta}.");
        }
        return '';
    }
    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $valor)) {
        qr_pagina_falta("Revisa {$etiqueta}: la hora no es válida.");
        return '';
    }
    return substr($valor, 0, 5);
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
    if (!qr_tipo_usa_url($tipo)) {
        $campos['estilo'] = qr_pagina_estilo($post);
    }
    return ['campos' => $campos, 'texto' => $texto];
}

/**
 * Campos para la vista previa en vivo: lo que lleva capturado el formulario, sin subir archivos
 * ni exigir datos. En los tipos que redirigen, ['destino' => URL] (o '' si aún no es válida).
 */
function qr_pagina_vista_previa(string $tipo, array $post, array $anterior = []): array
{
    if (qr_tipo_usa_url($tipo) && !in_array($tipo, ['facebook', 'instagram'], true)) {
        try {
            return ['destino' => isset(QR_TIPOS_DINAMICOS[$tipo]) ? qr_contenido($tipo, $post)['texto'] : ''];
        } catch (QrInvalido) {
            return ['destino' => ''];
        }
    }
    $v = is_array($post['pg'][$tipo] ?? null) ? $post['pg'][$tipo] : [];
    $nuevos = [];
    qr_pagina_borrador(true);
    try {
        $campos = qr_pagina_campos($tipo, $v, [], $anterior, $nuevos);
    } finally {
        qr_pagina_borrador(false);
    }
    if (qr_tipo_usa_url($tipo)) {
        return ['destino' => $campos['url'] ?? ''];
    }
    return $campos + ['estilo' => qr_pagina_estilo($post)];
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
        if (qr_pagina_borrador() && qr_pagina_es_blob($v['vista_' . $campo] ?? null)) {
            return $v['vista_' . $campo];
        }
        $previo = is_string($anterior[$campo] ?? null) ? $anterior[$campo] : null;
        if ($previo !== null && !$obligatorio && !empty($v['quitar_' . $campo])) {
            $previo = null;
        }
        if ($previo === null && $obligatorio) {
            qr_pagina_falta("Sube {$etiqueta}.");
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
                qr_pagina_falta('Escribe el título de tu lista de enlaces.');
            }
            foreach (array_slice(is_array($v['enlaces'] ?? null) ? $v['enlaces'] : [], 0, QR_PAGINA_MAX_ENLACES) as $e) {
                if (!is_array($e)) continue;
                $texto = qr_pagina_texto($e, 'texto', 80);
                $url = qr_pagina_url(qr_pagina_texto($e, 'url', 2048), 'el enlace' . ($texto !== '' ? " «{$texto}»" : ''));
                if ($url === '') {
                    if ($texto !== '') qr_pagina_falta("Escribe la dirección del enlace «{$texto}».");
                    continue;
                }
                $c['enlaces'][] = ['texto' => $texto !== '' ? $texto : qr_pagina_dominio($url), 'url' => $url];
            }
            if (!$c['enlaces']) {
                qr_pagina_falta('Agrega al menos un enlace.');
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
                qr_pagina_falta('Escribe el nombre del contacto.');
            }
            $c['imagen'] = $archivo('imagen', 'la foto', 'imagen', false);
            return $c;

        case 'menu':
            $c = ['titulo' => $t('titulo', 120), 'descripcion' => $t('descripcion', 1000), 'secciones' => []];
            if ($c['titulo'] === '') {
                qr_pagina_falta('Escribe el nombre de tu restaurante o del menú.');
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
                        qr_pagina_falta('Cada platillo del menú necesita un nombre.');
                    }
                    $seccion['platillos'][] = $platillo;
                    $platillos++;
                }
                if ($seccion['nombre'] === '' && !$seccion['platillos']) continue;
                if ($seccion['nombre'] === '') {
                    qr_pagina_falta('Escribe el nombre de cada sección del menú (por ejemplo, Entradas).');
                }
                $c['secciones'][] = $seccion;
            }
            if ($platillos === 0) {
                qr_pagina_falta('Agrega al menos un platillo al menú.');
            }
            if ($platillos > QR_PAGINA_MAX_PLATILLOS) {
                qr_pagina_falta('El menú admite hasta ' . QR_PAGINA_MAX_PLATILLOS . ' platillos.');
            }
            $c['imagen'] = $archivo('imagen', 'el logo', 'imagen', false);
            return $c;

        case 'cupon':
            $c = ['titulo' => $t('titulo', 120), 'descuento' => $t('descuento', 40), 'descripcion' => $t('descripcion', 1000),
                  'codigo' => $t('codigo', 40), 'vigencia' => qr_pagina_fecha($t('vigencia', 10), 'la vigencia', false),
                  'condiciones' => $t('condiciones', 1000), 'boton_texto' => $t('boton_texto', 40),
                  'boton_url' => qr_pagina_url($t('boton_url', 2048), 'el enlace del botón')];
            if ($c['titulo'] === '') {
                qr_pagina_falta('Escribe el título del cupón.');
            }
            $c['imagen'] = $archivo('imagen', 'la imagen', 'imagen', false);
            return $c;

        case 'wifi':
            $red = ['red' => $t('red', 32), 'clave' => $t('clave', 63), 'seguridad' => $t('seguridad', 10)];
            try {
                $red = qr_contenido('wifi', $red)['campos'];
            } catch (QrInvalido $exception) {
                qr_pagina_falta($exception->getMessage());
                $red['seguridad'] = in_array($red['seguridad'], ['WPA', 'WEP', 'nopass'], true) ? $red['seguridad'] : 'WPA';
            }
            return ['titulo' => $t('titulo', 120)] + $red;

        case 'evento':
            $c = ['titulo' => $t('titulo', 120), 'descripcion' => $t('descripcion', 1500),
                  'inicio' => qr_pagina_fecha($t('inicio', 19), 'la fecha y hora de inicio', true, true),
                  'fin' => qr_pagina_fecha($t('fin', 19), 'la fecha y hora de fin', true),
                  'lugar' => $t('lugar', 120), 'direccion' => $t('direccion', 250),
                  'boton_texto' => $t('boton_texto', 40), 'boton_url' => qr_pagina_url($t('boton_url', 2048), 'el enlace de boletos o registro')];
            if ($c['titulo'] === '') {
                qr_pagina_falta('Escribe el nombre del evento.');
            }
            if ($c['fin'] !== '' && $c['fin'] < $c['inicio']) {
                qr_pagina_falta('El evento no puede terminar antes de empezar.');
            }
            $c['imagen'] = $archivo('imagen', 'la imagen', 'imagen', false);
            return $c;

        case 'itinerario':
            $c = ['titulo' => $t('titulo', 120), 'descripcion' => $t('descripcion', 1500),
                  'lugar' => $t('lugar', 120), 'direccion' => $t('direccion', 250), 'dias' => []];
            if ($c['titulo'] === '') {
                qr_pagina_falta('Escribe el nombre del evento.');
            }
            $actividades = 0;
            foreach (array_slice(is_array($v['dias'] ?? null) ? $v['dias'] : [], 0, QR_PAGINA_MAX_DIAS) as $d) {
                if (!is_array($d)) continue;
                $dia = ['nombre' => qr_pagina_texto($d, 'nombre', 80),
                        'fecha' => qr_pagina_fecha(qr_pagina_texto($d, 'fecha', 10), 'la fecha de cada día', false),
                        'actividades' => []];
                foreach (is_array($d['actividades'] ?? null) ? $d['actividades'] : [] as $a) {
                    if (!is_array($a)) continue;
                    $actividad = ['hora' => qr_pagina_texto($a, 'hora', 8), 'fin' => qr_pagina_texto($a, 'fin', 8), 'titulo' => qr_pagina_texto($a, 'titulo', 120),
                                  'descripcion' => qr_pagina_texto($a, 'descripcion', 300), 'lugar' => qr_pagina_texto($a, 'lugar', 120)];
                    if (implode('', $actividad) === '') continue;
                    $nombre = $actividad['titulo'] !== '' ? " «{$actividad['titulo']}»" : '';
                    if ($actividad['titulo'] === '') {
                        qr_pagina_falta('Cada actividad del itinerario necesita un título.');
                    }
                    $actividad['hora'] = qr_pagina_hora($actividad['hora'], "la hora de la actividad{$nombre}", true);
                    // Una hora de fin menor que la de inicio termina después de medianoche (fiesta de 21:00 a 03:00).
                    $actividad['fin'] = qr_pagina_hora($actividad['fin'], "la hora de fin de la actividad{$nombre}");
                    if ($actividad['fin'] !== '' && $actividad['fin'] === $actividad['hora']) {
                        qr_pagina_falta("La actividad{$nombre} debe terminar después de empezar.");
                    }
                    $dia['actividades'][] = $actividad;
                    $actividades++;
                }
                // Se respeta el orden capturado: una hora menor que la anterior es de madrugada (después de medianoche).
                if (!$dia['actividades'] && $dia['nombre'] === '' && $dia['fecha'] === '') continue;
                $c['dias'][] = $dia;
            }
            if ($actividades === 0) {
                qr_pagina_falta('Agrega al menos una actividad al itinerario.');
            }
            if ($actividades > QR_PAGINA_MAX_ACTIVIDADES) {
                qr_pagina_falta('El itinerario admite hasta ' . QR_PAGINA_MAX_ACTIVIDADES . ' actividades.');
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
                qr_pagina_falta('Escribe el nombre de tu negocio.');
            }
            $c['imagen'] = $archivo('imagen', 'el logo', 'imagen', false);
            return $c;

        case 'video':
            $c = ['titulo' => $t('titulo', 120), 'descripcion' => $t('descripcion', 1000),
                  'url' => qr_pagina_url($t('url', 2048), 'el enlace del video', true)];
            if (qr_pagina_video_incrustado($c['url']) === null) {
                qr_pagina_falta('Pega el enlace de un video de YouTube o Vimeo.');
            }
            return $c;

        case 'imagenes':
            $c = ['titulo' => $t('titulo', 120), 'descripcion' => $t('descripcion', 1000)];
            $quitar = array_filter((array) ($v['quitar_imagenes'] ?? []), 'is_string');
            $conservar = array_values(array_filter((array) ($anterior['imagenes'] ?? []), fn($r) => is_string($r) && !in_array($r, $quitar, true)));
            $nuevas = array_values(array_filter(is_array($subidos['imagenes'] ?? null) ? $subidos['imagenes'] : [], fn($f) => is_array($f) && qr_pagina_hay_archivo($f)));
            if (count($conservar) + count($nuevas) > QR_PAGINA_MAX_IMAGENES) {
                qr_pagina_falta('La galería admite hasta ' . QR_PAGINA_MAX_IMAGENES . ' imágenes.');
            }
            if (qr_pagina_borrador()) {
                $blobs = array_filter((array) ($v['vista_imagenes'] ?? []), 'qr_pagina_es_blob');
                $conservar = array_slice(array_merge($conservar, array_values($blobs)), 0, QR_PAGINA_MAX_IMAGENES);
            }
            foreach ($nuevas as $i => $file) {
                $ruta = imagen_guardar_subida($file, QR_PAGINA_CARPETA, 'La imagen ' . ($i + 1), QR_PAGINA_MAX_IMAGEN, 4096);
                $nuevos[] = $ruta;
                $conservar[] = $ruta;
            }
            if (!$conservar) {
                qr_pagina_falta('Sube al menos una imagen.');
            }
            return $c + ['imagenes' => $conservar];

        case 'facebook':
            $valor = ltrim($t('url', 2048), '@');
            if (preg_match('/^[A-Za-z0-9.\-]{2,80}$/', $valor) && !str_contains($valor, '.com')) {
                $valor = 'https://www.facebook.com/' . $valor;
            }
            $url = qr_pagina_url($valor, 'el enlace de tu página de Facebook', true);
            if (!qr_pagina_url_de($url, ['facebook.com', 'fb.com', 'fb.me'])) {
                qr_pagina_falta('Pega el enlace de tu página de Facebook (facebook.com/…).');
            }
            return ['url' => $url];

        case 'instagram':
            $valor = ltrim($t('url', 2048), '@');
            if (preg_match('/^[A-Za-z0-9._]{1,30}$/', $valor) && !preg_match('/\.(com|me)$/i', $valor)) {
                $valor = 'https://www.instagram.com/' . $valor . '/';
            }
            $url = qr_pagina_url($valor, 'tu usuario o enlace de Instagram', true);
            if (!qr_pagina_url_de($url, ['instagram.com', 'instagr.am'])) {
                qr_pagina_falta('Escribe tu usuario de Instagram (por ejemplo @tunegocio) o pega el enlace de tu perfil.');
            }
            return ['url' => $url];

        case 'redes':
            $c = ['titulo' => $t('titulo', 120), 'descripcion' => $t('descripcion', 1000), 'redes' => qr_pagina_redes($v),
                  'web' => qr_pagina_url($t('web', 2048), 'el sitio web')];
            if ($c['titulo'] === '') {
                qr_pagina_falta('Escribe tu nombre o el de tu negocio.');
            }
            if (!$c['redes'] && $c['web'] === '') {
                qr_pagina_falta('Agrega el enlace de al menos una red social.');
            }
            $c['imagen'] = $archivo('imagen', 'la imagen', 'imagen', false);
            return $c;

        case 'mp3':
            $c = ['titulo' => $t('titulo', 120), 'descripcion' => $t('descripcion', 1000)];
            if ($c['titulo'] === '') {
                qr_pagina_falta('Escribe el título del audio.');
            }
            $c['audio'] = $archivo('audio', 'el archivo de audio', 'audio', true);
            $c['imagen'] = $archivo('imagen', 'la portada', 'imagen', false);
            return $c;

        case 'apps':
            $c = ['titulo' => $t('titulo', 120), 'descripcion' => $t('descripcion', 1000),
                  'ios' => qr_pagina_url($t('ios', 2048), 'el enlace de App Store'),
                  'android' => qr_pagina_url($t('android', 2048), 'el enlace de Google Play')];
            if ($c['titulo'] === '') {
                qr_pagina_falta('Escribe el nombre de tu aplicación.');
            }
            if ($c['ios'] === '' && $c['android'] === '') {
                qr_pagina_falta('Pega el enlace de tu app en App Store, en Google Play o en ambas.');
            }
            if ($c['ios'] !== '' && !qr_pagina_url_de($c['ios'], ['apps.apple.com', 'itunes.apple.com'])) {
                qr_pagina_falta('El enlace de App Store debe ser de apps.apple.com.');
            }
            if ($c['android'] !== '' && !qr_pagina_url_de($c['android'], ['play.google.com'])) {
                qr_pagina_falta('El enlace de Google Play debe ser de play.google.com.');
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
