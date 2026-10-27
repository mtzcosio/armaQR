<?php
/**
 * Formularios de los QR dinámicos con página propia (app/crear.php).
 * Cada tipo es un <fieldset data-panel-tipo> con campos pg[tipo][...]. El JS habilita solo el del
 * tipo elegido, así los demás (y sus archivos) no se envían. Las listas (enlaces, secciones y
 * platillos del menú) se repiten con <template data-lista-plantilla> y un marcador (__i__, __j__).
 */

declare(strict_types=1);

require_once __DIR__ . '/qr_paginas.php';

function qr_pagina_nombre(string $tipo, array $ruta): string
{
    return e('pg[' . $tipo . '][' . implode('][', $ruta) . ']');
}

function qr_pagina_id(string $tipo, array $ruta): string
{
    return e('pg-' . $tipo . '-' . implode('-', $ruta));
}

/** Etiqueta con "(opcional)" cuando el campo no es obligatorio. */
function qr_pagina_etiqueta(string $id, string $texto, bool $requerido): string
{
    return '<label for="' . $id . '">' . e($texto) . ($requerido ? '' : ' <span class="opcional">(opcional)</span>') . '</label>';
}

/**
 * Campo de texto. $o: tipo (text, email, tel, date, datetime-local), max, placeholder, ayuda,
 * requerido, completo (ocupa toda la fila), modo (inputmode).
 */
function qr_pagina_entrada(string $tipo, array $ruta, string $etiqueta, string $valor, array $o = []): string
{
    $id = qr_pagina_id($tipo, $ruta);
    $requerido = !empty($o['requerido']);
    $attrs = ' type="' . ($o['tipo'] ?? 'text') . '"'
        . (isset($o['max']) ? ' maxlength="' . (int) $o['max'] . '"' : '')
        . (isset($o['placeholder']) ? ' placeholder="' . e($o['placeholder']) . '"' : '')
        . (isset($o['modo']) ? ' inputmode="' . e($o['modo']) . '"' : '')
        . ($requerido ? ' required' : '');
    return '<div class="campo' . (!empty($o['completo']) ? ' campo-completo' : '') . '">'
        . qr_pagina_etiqueta($id, $etiqueta, $requerido)
        . '<input class="entrada" id="' . $id . '" name="' . qr_pagina_nombre($tipo, $ruta) . '" value="' . e($valor) . '"' . $attrs . '>'
        . (isset($o['ayuda']) ? '<small>' . e($o['ayuda']) . '</small>' : '') . '</div>';
}

function qr_pagina_area(string $tipo, array $ruta, string $etiqueta, string $valor, array $o = []): string
{
    $id = qr_pagina_id($tipo, $ruta);
    $requerido = !empty($o['requerido']);
    return '<div class="campo campo-completo">' . qr_pagina_etiqueta($id, $etiqueta, $requerido)
        . '<textarea class="entrada" id="' . $id . '" name="' . qr_pagina_nombre($tipo, $ruta) . '" maxlength="' . (int) ($o['max'] ?? 1000) . '"'
        . (isset($o['placeholder']) ? ' placeholder="' . e($o['placeholder']) . '"' : '') . ($requerido ? ' required' : '') . '>' . e($valor) . '</textarea>'
        . (isset($o['ayuda']) ? '<small>' . e($o['ayuda']) . '</small>' : '') . '</div>';
}

/**
 * Archivo. $actual: ruta ya guardada (al editar). Si el archivo es obligatorio y ya hay uno, el
 * campo deja de ser requerido: subir otro lo reemplaza. Si es opcional, se puede quitar.
 */
function qr_pagina_archivo(string $tipo, string $campo, string $etiqueta, string $accept, string $ayuda, ?string $actual, bool $requerido): string
{
    $id = qr_pagina_id($tipo, [$campo]);
    $html = '<div class="campo campo-completo">' . qr_pagina_etiqueta($id, $etiqueta, $requerido)
        . '<input class="entrada" id="' . $id . '" name="' . qr_pagina_nombre($tipo, [$campo]) . '" type="file" accept="' . e($accept) . '"' . ($requerido && $actual === null ? ' required' : '') . '>'
        . '<small>' . e($ayuda) . '</small>';
    if ($actual !== null) {
        $html .= '<p class="archivo-actual texto-chico"><a class="enlace" href="' . e(url($actual)) . '" target="_blank" rel="noopener">Ver el archivo actual</a>'
            . ($requerido ? ' · Sube otro para reemplazarlo.' : '') . '</p>';
        if (!$requerido) {
            $html .= '<label class="check texto-chico"><input type="checkbox" name="' . qr_pagina_nombre($tipo, ['quitar_' . $campo]) . '" value="1"> Quitar ' . e(mb_strtolower($etiqueta)) . '</label>';
        }
    }
    return $html . '</div>';
}

/** Lista repetible: $items ya renderizados, $plantilla con el marcador $token. */
function qr_pagina_lista(string $etiqueta, string $items, string $plantilla, string $token, int $max, string $agregar): string
{
    return '<div class="lista-repetible campo-completo" data-lista data-token="' . $token . '" data-max="' . $max . '">'
        . ($etiqueta !== '' ? '<span class="etiqueta">' . e($etiqueta) . '</span>' : '')
        . '<div class="lista-items" data-lista-items>' . $items . '</div>'
        . '<template data-lista-plantilla>' . $plantilla . '</template>'
        . '<button type="button" class="boton boton-fantasma boton-chico" data-lista-agregar>' . icono('mas', 15) . ' ' . e($agregar) . '</button></div>';
}

function qr_pagina_boton_quitar(string $texto): string
{
    return '<button type="button" class="boton-icono boton-icono-peligro" data-lista-quitar aria-label="' . e($texto) . '" title="' . e($texto) . '">' . icono('x', 16) . '</button>';
}

function qr_pagina_item_enlace(string $i, array $e): string
{
    return '<div class="lista-item" data-lista-item><div class="fila-campos">'
        . qr_pagina_entrada('enlaces', ['enlaces', $i, 'texto'], 'Texto del botón', (string) ($e['texto'] ?? ''), ['max' => 80, 'placeholder' => 'Nuestra tienda en línea', 'requerido' => true])
        . qr_pagina_entrada('enlaces', ['enlaces', $i, 'url'], 'Dirección', (string) ($e['url'] ?? ''), ['max' => 2048, 'placeholder' => 'https://tunegocio.mx/tienda', 'modo' => 'url', 'requerido' => true])
        . '</div>' . qr_pagina_boton_quitar('Quitar enlace') . '</div>';
}

function qr_pagina_item_platillo(string $i, string $j, array $p): string
{
    $ruta = fn(string $campo) => ['secciones', $i, 'platillos', $j, $campo];
    return '<div class="lista-item lista-item-chico" data-lista-item><div class="fila-campos">'
        . qr_pagina_entrada('menu', $ruta('nombre'), 'Platillo', (string) ($p['nombre'] ?? ''), ['max' => 120, 'placeholder' => 'Tacos al pastor', 'requerido' => true])
        . qr_pagina_entrada('menu', $ruta('precio'), 'Precio', (string) ($p['precio'] ?? ''), ['max' => 20, 'placeholder' => '95'])
        . qr_pagina_entrada('menu', $ruta('descripcion'), 'Descripción', (string) ($p['descripcion'] ?? ''), ['max' => 300, 'placeholder' => 'Orden de 4 con piña, cebolla y cilantro', 'completo' => true])
        . '</div>' . qr_pagina_boton_quitar('Quitar platillo') . '</div>';
}

function qr_pagina_item_seccion(string $i, array $s): string
{
    $platillos = is_array($s['platillos'] ?? null) && $s['platillos'] ? $s['platillos'] : ['0' => []];
    $items = '';
    foreach ($platillos as $j => $p) {
        $items .= qr_pagina_item_platillo($i, (string) $j, is_array($p) ? $p : []);
    }
    return '<div class="lista-item lista-seccion" data-lista-item><div class="lista-item-cuerpo">'
        . qr_pagina_entrada('menu', ['secciones', $i, 'nombre'], 'Sección', (string) ($s['nombre'] ?? ''), ['max' => 80, 'placeholder' => 'Entradas, Bebidas, Postres…', 'requerido' => true])
        . qr_pagina_lista('', $items, qr_pagina_item_platillo($i, '__j__', []), '__j__', QR_PAGINA_MAX_PLATILLOS, 'Agregar platillo')
        . '</div>' . qr_pagina_boton_quitar('Quitar sección') . '</div>';
}

/** Campos de redes sociales (pg[tipo][redes][red]). */
function qr_pagina_campos_redes(string $tipo, array $redes): string
{
    $html = '';
    foreach (QR_PAGINA_REDES as $red => $nombre) {
        $html .= qr_pagina_entrada($tipo, ['redes', $red], $nombre, (string) ($redes[$red] ?? ''), ['max' => 2048, 'modo' => 'url', 'placeholder' => 'https://…']);
    }
    return $html;
}

/**
 * Todos los paneles. $valores[tipo]: lo capturado (tras un error) o lo guardado de ese tipo.
 * $guardados[tipo]: los campos guardados del QR que se edita (para mostrar sus archivos).
 */
function qr_pagina_formularios(array $valores, array $guardados): void
{
    $img = 'image/png,image/jpeg,image/webp,image/gif';
    $ayudaImg = 'PNG, JPG, WebP o GIF. Máximo 3 MB.';
    foreach (QR_TIPOS_DINAMICOS as $tipo => $info) {
        if ($tipo === 'url' || $tipo === 'whatsapp') {
            continue; // usan los mismos campos que el estático
        }
        $v = is_array($valores[$tipo] ?? null) ? $valores[$tipo] : [];
        $g = is_array($guardados[$tipo] ?? null) ? $guardados[$tipo] : [];
        $val = fn(string ...$ruta) => is_scalar($x = qr_pagina_dato($v, $ruta)) ? (string) $x : '';
        $actual = fn(string $campo) => is_string($g[$campo] ?? null) ? $g[$campo] : null;
        $titulo = fn(string $etiqueta, bool $requerido, string $placeholder) => qr_pagina_entrada($tipo, ['titulo'], $etiqueta, $val('titulo'), ['max' => 120, 'requerido' => $requerido, 'placeholder' => $placeholder, 'completo' => true]);
        $descripcion = fn(string $placeholder = '') => qr_pagina_area($tipo, ['descripcion'], 'Descripción', $val('descripcion'), ['max' => 1000, 'placeholder' => $placeholder]);

        echo '<fieldset class="panel-tipo fila-campos" data-panel-tipo="' . $tipo . '" data-panel-modo="dinamico" data-panel-pagina hidden disabled>';
        echo '<legend class="oculto-visual">' . e($info['nombre']) . '</legend>';
        echo '<p class="ayuda campo-completo">' . e(match ($info['modo']) {
            'pagina' => 'Al escanear se abre una página con esta información. La puedes cambiar cuando quieras.',
            default => $tipo === 'apps'
                ? 'En iPhone abre App Store y en Android, Google Play. En otros equipos muestra una página con ambas.'
                : 'Al escanear se abre tu perfil. Puedes cambiarlo cuando quieras sin reimprimir.',
        }) . '</p>';

        switch ($tipo) {
            case 'pdf':
                echo $titulo('Título', false, 'Catálogo de temporada');
                echo $descripcion();
                echo qr_pagina_archivo($tipo, 'archivo', 'Archivo PDF', 'application/pdf', 'Máximo ' . QR_PAGINA_MAX_PDF / 1048576 . ' MB.', $actual('archivo'), true);
                break;

            case 'enlaces':
                echo $titulo('Título', true, 'Café Luna');
                echo $descripcion('Encuentra aquí todo lo que tenemos para ti');
                $enlaces = is_array($v['enlaces'] ?? null) && $v['enlaces'] ? $v['enlaces'] : ['0' => []];
                $items = '';
                foreach ($enlaces as $i => $e) {
                    $items .= qr_pagina_item_enlace((string) $i, is_array($e) ? $e : []);
                }
                echo qr_pagina_lista('Enlaces', $items, qr_pagina_item_enlace('__i__', []), '__i__', QR_PAGINA_MAX_ENLACES, 'Agregar enlace');
                echo qr_pagina_archivo($tipo, 'imagen', 'Imagen o logo', $img, $ayudaImg, $actual('imagen'), false);
                break;

            case 'vcard':
                echo qr_pagina_entrada($tipo, ['nombre'], 'Nombre', $val('nombre'), ['max' => 120, 'requerido' => true]);
                echo qr_pagina_entrada($tipo, ['cargo'], 'Cargo', $val('cargo'), ['max' => 120, 'placeholder' => 'Directora comercial']);
                echo qr_pagina_entrada($tipo, ['empresa'], 'Empresa', $val('empresa'), ['max' => 120]);
                echo qr_pagina_entrada($tipo, ['telefono'], 'Teléfono', $val('telefono'), ['max' => 30, 'tipo' => 'tel', 'placeholder' => '55 1234 5678']);
                echo qr_pagina_entrada($tipo, ['email'], 'Correo', $val('email'), ['max' => 190, 'tipo' => 'email']);
                echo qr_pagina_entrada($tipo, ['web'], 'Sitio web', $val('web'), ['max' => 2048, 'modo' => 'url', 'placeholder' => 'tunegocio.mx']);
                echo qr_pagina_entrada($tipo, ['direccion'], 'Dirección', $val('direccion'), ['max' => 250, 'completo' => true]);
                echo qr_pagina_area($tipo, ['nota'], 'Nota', $val('nota'), ['max' => 500]);
                echo qr_pagina_archivo($tipo, 'imagen', 'Foto', $img, $ayudaImg . ' Se incluye en el contacto si es JPG o PNG de hasta 1 MB.', $actual('imagen'), false);
                break;

            case 'menu':
                echo $titulo('Nombre del restaurante o del menú', true, 'Taquería Don Chuy');
                echo $descripcion('Horario, servicio a domicilio, etc.');
                $secciones = is_array($v['secciones'] ?? null) && $v['secciones'] ? $v['secciones'] : ['0' => []];
                $items = '';
                foreach ($secciones as $i => $s) {
                    $items .= qr_pagina_item_seccion((string) $i, is_array($s) ? $s : []);
                }
                echo qr_pagina_lista('Secciones del menú', $items, qr_pagina_item_seccion('__i__', []), '__i__', QR_PAGINA_MAX_SECCIONES, 'Agregar sección');
                echo qr_pagina_archivo($tipo, 'imagen', 'Logo', $img, $ayudaImg, $actual('imagen'), false);
                break;

            case 'cupon':
                echo $titulo('Título', true, '2x1 en cafés de especialidad');
                echo qr_pagina_entrada($tipo, ['descuento'], 'Descuento', $val('descuento'), ['max' => 40, 'placeholder' => '20% de descuento']);
                echo qr_pagina_entrada($tipo, ['codigo'], 'Código del cupón', $val('codigo'), ['max' => 40, 'placeholder' => 'LUNA20']);
                echo $descripcion();
                echo qr_pagina_entrada($tipo, ['vigencia'], 'Válido hasta', $val('vigencia'), ['tipo' => 'date']);
                echo qr_pagina_area($tipo, ['condiciones'], 'Condiciones', $val('condiciones'), ['max' => 1000, 'placeholder' => 'Válido en sucursales participantes. No acumulable con otras promociones.']);
                echo qr_pagina_entrada($tipo, ['boton_texto'], 'Texto del botón', $val('boton_texto'), ['max' => 40, 'placeholder' => 'Comprar en línea']);
                echo qr_pagina_entrada($tipo, ['boton_url'], 'Enlace del botón', $val('boton_url'), ['max' => 2048, 'modo' => 'url', 'placeholder' => 'https://tunegocio.mx/tienda']);
                echo qr_pagina_archivo($tipo, 'imagen', 'Imagen', $img, $ayudaImg, $actual('imagen'), false);
                break;

            case 'wifi':
                $seguridad = $val('seguridad') ?: 'WPA';
                echo $titulo('Título', false, 'Wi-Fi para clientes');
                echo qr_pagina_entrada($tipo, ['red'], 'Nombre de la red', $val('red'), ['max' => 32, 'requerido' => true]);
                echo qr_pagina_entrada($tipo, ['clave'], 'Contraseña', $val('clave'), ['max' => 63]);
                echo '<div class="campo"><label for="pg-wifi-seguridad">Seguridad</label><select class="entrada" id="pg-wifi-seguridad" name="pg[wifi][seguridad]">';
                foreach (['WPA' => 'WPA / WPA2', 'WEP' => 'WEP', 'nopass' => 'Sin contraseña'] as $valor => $texto) {
                    echo '<option value="' . $valor . '"' . ($seguridad === $valor ? ' selected' : '') . '>' . $texto . '</option>';
                }
                echo '</select></div>';
                break;

            case 'evento':
                echo $titulo('Nombre del evento', true, 'Noche de jazz');
                echo $descripcion();
                echo qr_pagina_entrada($tipo, ['inicio'], 'Empieza', str_replace(' ', 'T', $val('inicio')), ['tipo' => 'datetime-local', 'requerido' => true]);
                echo qr_pagina_entrada($tipo, ['fin'], 'Termina', str_replace(' ', 'T', $val('fin')), ['tipo' => 'datetime-local']);
                echo qr_pagina_entrada($tipo, ['lugar'], 'Lugar', $val('lugar'), ['max' => 120, 'placeholder' => 'Foro Luna']);
                echo qr_pagina_entrada($tipo, ['direccion'], 'Dirección', $val('direccion'), ['max' => 250, 'placeholder' => 'Av. Juárez 10, Centro, CDMX']);
                echo qr_pagina_entrada($tipo, ['boton_texto'], 'Texto del botón', $val('boton_texto'), ['max' => 40, 'placeholder' => 'Comprar boletos']);
                echo qr_pagina_entrada($tipo, ['boton_url'], 'Enlace de boletos o registro', $val('boton_url'), ['max' => 2048, 'modo' => 'url']);
                echo qr_pagina_archivo($tipo, 'imagen', 'Imagen', $img, $ayudaImg, $actual('imagen'), false);
                break;

            case 'empresa':
                echo qr_pagina_entrada($tipo, ['nombre'], 'Nombre del negocio', $val('nombre'), ['max' => 120, 'requerido' => true, 'completo' => true]);
                echo $descripcion('A qué se dedica tu negocio');
                echo qr_pagina_entrada($tipo, ['telefono'], 'Teléfono', $val('telefono'), ['max' => 30, 'tipo' => 'tel']);
                echo qr_pagina_entrada($tipo, ['email'], 'Correo', $val('email'), ['max' => 190, 'tipo' => 'email']);
                echo qr_pagina_entrada($tipo, ['web'], 'Sitio web', $val('web'), ['max' => 2048, 'modo' => 'url']);
                echo qr_pagina_entrada($tipo, ['direccion'], 'Dirección', $val('direccion'), ['max' => 250]);
                echo qr_pagina_area($tipo, ['horario'], 'Horario', $val('horario'), ['max' => 500, 'placeholder' => "Lunes a viernes de 9:00 a 19:00\nSábado de 10:00 a 14:00"]);
                echo qr_pagina_campos_redes($tipo, is_array($v['redes'] ?? null) ? $v['redes'] : []);
                echo qr_pagina_archivo($tipo, 'imagen', 'Logo', $img, $ayudaImg, $actual('imagen'), false);
                break;

            case 'video':
                echo qr_pagina_entrada($tipo, ['url'], 'Enlace del video', $val('url'), ['max' => 2048, 'modo' => 'url', 'requerido' => true, 'completo' => true, 'placeholder' => 'https://www.youtube.com/watch?v=…', 'ayuda' => 'De YouTube o Vimeo.']);
                echo $titulo('Título', false, '');
                echo $descripcion();
                break;

            case 'imagenes':
                echo $titulo('Título', false, 'Nuestros trabajos');
                echo $descripcion();
                $actuales = array_filter((array) ($g['imagenes'] ?? []), 'is_string');
                if ($actuales) {
                    echo '<div class="campo campo-completo"><span class="etiqueta">Imágenes actuales</span><div class="galeria-editar">';
                    foreach ($actuales as $ruta) {
                        echo '<label><img src="' . e(url($ruta)) . '" alt=""><span class="check texto-chico"><input type="checkbox" name="pg[imagenes][quitar_imagenes][]" value="' . e($ruta) . '"> Quitar</span></label>';
                    }
                    echo '</div></div>';
                }
                echo '<div class="campo campo-completo">' . qr_pagina_etiqueta('pg-imagenes-imagenes', $actuales ? 'Agregar imágenes' : 'Imágenes', !$actuales)
                    . '<input class="entrada" id="pg-imagenes-imagenes" name="pg[imagenes][imagenes][]" type="file" multiple accept="' . $img . '"' . ($actuales ? '' : ' required') . '>'
                    . '<small>Hasta ' . QR_PAGINA_MAX_IMAGENES . ' imágenes en total. ' . e($ayudaImg) . '</small></div>';
                break;

            case 'facebook':
                echo qr_pagina_entrada($tipo, ['url'], 'Tu página de Facebook', $val('url'), ['max' => 2048, 'modo' => 'url', 'requerido' => true, 'completo' => true, 'placeholder' => 'https://www.facebook.com/tunegocio']);
                break;

            case 'instagram':
                echo qr_pagina_entrada($tipo, ['url'], 'Tu usuario de Instagram', $val('url'), ['max' => 2048, 'requerido' => true, 'completo' => true, 'placeholder' => '@tunegocio', 'ayuda' => 'También puedes pegar el enlace de tu perfil.']);
                break;

            case 'redes':
                echo $titulo('Tu nombre o el de tu negocio', true, 'Café Luna');
                echo $descripcion('Síguenos para enterarte de promociones');
                echo qr_pagina_campos_redes($tipo, is_array($v['redes'] ?? null) ? $v['redes'] : []);
                echo qr_pagina_entrada($tipo, ['web'], 'Sitio web', $val('web'), ['max' => 2048, 'modo' => 'url']);
                echo qr_pagina_archivo($tipo, 'imagen', 'Imagen o logo', $img, $ayudaImg, $actual('imagen'), false);
                break;

            case 'mp3':
                echo $titulo('Título', true, 'Episodio 1: Nuestra historia');
                echo $descripcion();
                echo qr_pagina_archivo($tipo, 'audio', 'Archivo de audio', 'audio/mpeg,audio/mp4,audio/x-m4a,audio/ogg,audio/wav,.mp3,.m4a,.ogg,.wav', 'MP3, M4A, OGG o WAV. Máximo ' . QR_PAGINA_MAX_AUDIO / 1048576 . ' MB.', $actual('audio'), true);
                echo qr_pagina_archivo($tipo, 'imagen', 'Portada', $img, $ayudaImg, $actual('imagen'), false);
                break;

            case 'apps':
                echo $titulo('Nombre de la aplicación', true, 'Café Luna');
                echo $descripcion('Pide desde tu celular y acumula puntos');
                echo qr_pagina_entrada($tipo, ['ios'], 'Enlace de App Store', $val('ios'), ['max' => 2048, 'modo' => 'url', 'placeholder' => 'https://apps.apple.com/mx/app/…']);
                echo qr_pagina_entrada($tipo, ['android'], 'Enlace de Google Play', $val('android'), ['max' => 2048, 'modo' => 'url', 'placeholder' => 'https://play.google.com/store/apps/details?id=…']);
                echo qr_pagina_archivo($tipo, 'imagen', 'Ícono de la app', $img, $ayudaImg, $actual('imagen'), false);
                break;

            case 'email':
                echo qr_pagina_entrada($tipo, ['email'], 'Correo de destino', $val('email'), ['max' => 190, 'tipo' => 'email', 'requerido' => true]);
                echo qr_pagina_entrada($tipo, ['asunto'], 'Asunto', $val('asunto'), ['max' => 200, 'placeholder' => 'Quiero una cotización']);
                echo $titulo('Título de la página', false, 'Escríbenos');
                echo qr_pagina_area($tipo, ['mensaje'], 'Mensaje inicial', $val('mensaje'), ['max' => 1000, 'ayuda' => 'Aparece ya escrito al abrir el correo.']);
                break;
        }
        echo '</fieldset>';
    }
}
