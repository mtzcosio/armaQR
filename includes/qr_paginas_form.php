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

function qr_pagina_item_actividad(string $i, string $j, array $a): string
{
    $ruta = fn(string $campo) => ['dias', $i, 'actividades', $j, $campo];
    return '<div class="lista-item lista-item-chico" data-lista-item><div class="fila-campos">'
        . qr_pagina_entrada('itinerario', $ruta('hora'), 'Hora', (string) ($a['hora'] ?? ''), ['tipo' => 'time', 'requerido' => true])
        . qr_pagina_entrada('itinerario', $ruta('fin'), 'Termina', (string) ($a['fin'] ?? ''), ['tipo' => 'time'])
        . qr_pagina_entrada('itinerario', $ruta('titulo'), 'Actividad', (string) ($a['titulo'] ?? ''), ['max' => 120, 'placeholder' => 'Ceremonia religiosa', 'requerido' => true, 'completo' => true])
        . qr_pagina_entrada('itinerario', $ruta('lugar'), 'Lugar', (string) ($a['lugar'] ?? ''), ['max' => 120, 'placeholder' => 'Jardín principal'])
        . qr_pagina_entrada('itinerario', $ruta('descripcion'), 'Detalle', (string) ($a['descripcion'] ?? ''), ['max' => 300, 'placeholder' => 'Llega 15 minutos antes'])
        . '</div>' . qr_pagina_boton_quitar('Quitar actividad') . '</div>';
}

function qr_pagina_item_dia(string $i, array $d): string
{
    $actividades = is_array($d['actividades'] ?? null) && $d['actividades'] ? $d['actividades'] : ['0' => []];
    $items = '';
    foreach ($actividades as $j => $a) {
        $items .= qr_pagina_item_actividad($i, (string) $j, is_array($a) ? $a : []);
    }
    return '<div class="lista-item lista-seccion" data-lista-item><div class="lista-item-cuerpo"><div class="fila-campos">'
        . qr_pagina_entrada('itinerario', ['dias', $i, 'nombre'], 'Nombre del día', (string) ($d['nombre'] ?? ''), ['max' => 80, 'placeholder' => 'Día 1 · Bienvenida'])
        . qr_pagina_entrada('itinerario', ['dias', $i, 'fecha'], 'Fecha', (string) ($d['fecha'] ?? ''), ['tipo' => 'date'])
        . '</div>'
        . qr_pagina_lista('', $items, qr_pagina_item_actividad($i, '__j__', []), '__j__', QR_PAGINA_MAX_ACTIVIDADES, 'Agregar actividad')
        . '</div>' . qr_pagina_boton_quitar('Quitar día') . '</div>';
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
 * Sección plegable del formulario (como en los generadores de páginas): título, subtítulo e
 * ícono. El JS la abre sola si un campo obligatorio de adentro quedó vacío.
 */
function qr_pagina_grupo(string $titulo, string $subtitulo, string $icono, string $contenido, bool $abierto = true): string
{
    return '<details class="acordeon"' . ($abierto ? ' open' : '') . '><summary>'
        . '<span class="acordeon-icono">' . icono($icono, 20) . '</span>'
        . '<span class="acordeon-titulo"><strong>' . e($titulo) . '</strong>' . ($subtitulo !== '' ? '<small>' . e($subtitulo) . '</small>' : '') . '</span>'
        . '<span class="acordeon-flecha">' . icono('derecha', 18) . '</span>'
        . '</summary><div class="acordeon-cuerpo fila-campos">' . $contenido . '</div></details>';
}

/**
 * Sección "Diseño de la página": paletas (color principal y de fondo) y colores a la medida.
 * Es una sola para todos los tipos de página (pagina_estilo[...]); crear.php la muestra solo en ellos.
 */
function qr_pagina_grupo_diseno(array $estilo): string
{
    $primario = $estilo['primario'] ?? QR_PAGINA_PALETAS[0][0];
    $secundario = $estilo['secundario'] ?? QR_PAGINA_PALETAS[0][1];
    $paletas = '';
    foreach (QR_PAGINA_PALETAS as $i => [$p, $s]) {
        $elegida = $p === $primario && $s === $secundario;
        $paletas .= '<button type="button" class="paleta" data-paleta="' . $p . ' ' . $s . '" aria-pressed="' . ($elegida ? 'true' : 'false') . '" aria-label="Paleta ' . ($i + 1) . '">'
            . '<span style="background:' . $p . '"></span><span style="background:' . $s . '"></span></button>';
    }
    $contenido = '<div class="campo campo-completo"><span class="etiqueta">Paleta de colores</span><div class="paletas">' . $paletas . '</div></div>'
        . '<div class="campo"><label for="pagina-primario">Color principal</label><input class="entrada-color" id="pagina-primario" name="pagina_estilo[primario]" type="color" value="' . e($primario) . '" data-pagina-color>'
        . '<small>Cabecera y botones.</small></div>'
        . '<div class="campo"><label for="pagina-secundario">Color de fondo</label><input class="entrada-color" id="pagina-secundario" name="pagina_estilo[secundario]" type="color" value="' . e($secundario) . '" data-pagina-color>'
        . '<small>Lo que rodea la tarjeta.</small></div>';
    return qr_pagina_grupo('Diseño de la página', 'Elige los colores de tu página', 'paleta', $contenido);
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
        $imagen = fn(string $etiqueta, string $ayuda = '') => qr_pagina_archivo($tipo, 'imagen', $etiqueta, $img, $ayuda !== '' ? $ayuda : $ayudaImg, $actual('imagen'), false);
        $boton = fn(string $placeholder, string $etiquetaUrl) => qr_pagina_entrada($tipo, ['boton_texto'], 'Texto del botón', $val('boton_texto'), ['max' => 40, 'placeholder' => $placeholder])
            . qr_pagina_entrada($tipo, ['boton_url'], $etiquetaUrl, $val('boton_url'), ['max' => 2048, 'modo' => 'url', 'placeholder' => 'https://tunegocio.mx/…']);
        $grupo = 'qr_pagina_grupo';

        echo '<fieldset class="panel-tipo" data-panel-tipo="' . $tipo . '" data-panel-modo="dinamico" data-panel-pagina hidden disabled>';
        echo '<legend class="oculto-visual">' . e($info['nombre']) . '</legend>';

        switch ($tipo) {
            case 'pdf':
                echo $grupo('Archivo PDF', 'Sube el documento que quieres mostrar', 'archivo',
                    qr_pagina_archivo($tipo, 'archivo', 'Archivo PDF', 'application/pdf', 'Máximo ' . QR_PAGINA_MAX_PDF / 1048576 . ' MB.', $actual('archivo'), true));
                echo $grupo('Información del PDF', 'Título y descripción de la página', 'texto', $titulo('Título', false, 'Catálogo de temporada') . $descripcion());
                break;

            case 'enlaces':
                $enlaces = is_array($v['enlaces'] ?? null) && $v['enlaces'] ? $v['enlaces'] : ['0' => []];
                $items = '';
                foreach ($enlaces as $i => $e) {
                    $items .= qr_pagina_item_enlace((string) $i, is_array($e) ? $e : []);
                }
                echo $grupo('Información de la página', 'Logo, título y descripción', 'texto',
                    $imagen('Imagen o logo') . $titulo('Título', true, 'Café Luna') . $descripcion('Encuentra aquí todo lo que tenemos para ti'));
                echo $grupo('Enlaces', 'Hasta ' . QR_PAGINA_MAX_ENLACES . ' botones a tus sitios', 'enlace',
                    qr_pagina_lista('', $items, qr_pagina_item_enlace('__i__', []), '__i__', QR_PAGINA_MAX_ENLACES, 'Agregar enlace'));
                break;

            case 'vcard':
                echo $grupo('Datos personales', 'Foto, nombre y puesto', 'contacto',
                    $imagen('Foto', $ayudaImg . ' Se incluye en el contacto si es JPG o PNG de hasta 1 MB.')
                    . qr_pagina_entrada($tipo, ['nombre'], 'Nombre', $val('nombre'), ['max' => 120, 'requerido' => true, 'completo' => true])
                    . qr_pagina_entrada($tipo, ['cargo'], 'Cargo', $val('cargo'), ['max' => 120, 'placeholder' => 'Directora comercial'])
                    . qr_pagina_entrada($tipo, ['empresa'], 'Empresa', $val('empresa'), ['max' => 120]));
                echo $grupo('Contacto', 'Cómo pueden comunicarse contigo', 'telefono',
                    qr_pagina_entrada($tipo, ['telefono'], 'Teléfono', $val('telefono'), ['max' => 30, 'tipo' => 'tel', 'placeholder' => '55 1234 5678'])
                    . qr_pagina_entrada($tipo, ['email'], 'Correo', $val('email'), ['max' => 190, 'tipo' => 'email'])
                    . qr_pagina_entrada($tipo, ['web'], 'Sitio web', $val('web'), ['max' => 2048, 'modo' => 'url', 'placeholder' => 'tunegocio.mx'])
                    . qr_pagina_entrada($tipo, ['direccion'], 'Dirección', $val('direccion'), ['max' => 250])
                    . qr_pagina_area($tipo, ['nota'], 'Nota', $val('nota'), ['max' => 500]));
                break;

            case 'menu':
                $secciones = is_array($v['secciones'] ?? null) && $v['secciones'] ? $v['secciones'] : ['0' => []];
                $items = '';
                foreach ($secciones as $i => $s) {
                    $items .= qr_pagina_item_seccion((string) $i, is_array($s) ? $s : []);
                }
                echo $grupo('Información del restaurante', 'Logo, nombre y descripción', 'edificio',
                    $imagen('Logo') . $titulo('Nombre del restaurante o del menú', true, 'Taquería Don Chuy') . $descripcion('Horario, servicio a domicilio, etc.'));
                echo $grupo('Menú', 'Secciones y platillos con su precio', 'comida',
                    qr_pagina_lista('', $items, qr_pagina_item_seccion('__i__', []), '__i__', QR_PAGINA_MAX_SECCIONES, 'Agregar sección'));
                break;

            case 'cupon':
                echo $grupo('Información del cupón', 'Imagen, promoción y código', 'cupon',
                    $imagen('Imagen')
                    . $titulo('Título', true, '2x1 en cafés de especialidad')
                    . qr_pagina_entrada($tipo, ['descuento'], 'Descuento', $val('descuento'), ['max' => 40, 'placeholder' => '20% de descuento'])
                    . qr_pagina_entrada($tipo, ['codigo'], 'Código del cupón', $val('codigo'), ['max' => 40, 'placeholder' => 'LUNA20'])
                    . $descripcion());
                echo $grupo('Vigencia y condiciones', 'Hasta cuándo vale y sus restricciones', 'calendario',
                    qr_pagina_entrada($tipo, ['vigencia'], 'Válido hasta', $val('vigencia'), ['tipo' => 'date'])
                    . qr_pagina_area($tipo, ['condiciones'], 'Condiciones', $val('condiciones'), ['max' => 1000, 'placeholder' => 'Válido en sucursales participantes. No acumulable con otras promociones.']), false);
                echo $grupo('Botón', 'Un botón para usar el cupón en línea', 'externo', $boton('Comprar en línea', 'Enlace del botón'), false);
                break;

            case 'wifi':
                $seguridad = $val('seguridad') ?: 'WPA';
                $select = '<div class="campo"><label for="pg-wifi-seguridad">Seguridad</label><select class="entrada" id="pg-wifi-seguridad" name="pg[wifi][seguridad]">';
                foreach (['WPA' => 'WPA / WPA2', 'WEP' => 'WEP', 'nopass' => 'Sin contraseña'] as $valor => $texto) {
                    $select .= '<option value="' . $valor . '"' . ($seguridad === $valor ? ' selected' : '') . '>' . $texto . '</option>';
                }
                echo $grupo('Red Wi-Fi', 'Los datos para conectarse', 'wifi',
                    qr_pagina_entrada($tipo, ['red'], 'Nombre de la red', $val('red'), ['max' => 32, 'requerido' => true])
                    . qr_pagina_entrada($tipo, ['clave'], 'Contraseña', $val('clave'), ['max' => 63])
                    . $select . '</select></div>');
                echo $grupo('Información de la página', 'El título que ven al escanear', 'texto', $titulo('Título', false, 'Wi-Fi para clientes'), false);
                break;

            case 'evento':
                echo $grupo('Información del evento', 'Imagen, nombre y descripción', 'calendario',
                    $imagen('Imagen') . $titulo('Nombre del evento', true, 'Noche de jazz') . $descripcion());
                echo $grupo('Fecha y lugar', 'Cuándo y dónde', 'calendario',
                    qr_pagina_entrada($tipo, ['inicio'], 'Empieza', str_replace(' ', 'T', $val('inicio')), ['tipo' => 'datetime-local', 'requerido' => true])
                    . qr_pagina_entrada($tipo, ['fin'], 'Termina', str_replace(' ', 'T', $val('fin')), ['tipo' => 'datetime-local'])
                    . qr_pagina_entrada($tipo, ['lugar'], 'Lugar', $val('lugar'), ['max' => 120, 'placeholder' => 'Foro Luna'])
                    . qr_pagina_entrada($tipo, ['direccion'], 'Dirección', $val('direccion'), ['max' => 250, 'placeholder' => 'Av. Juárez 10, Centro, CDMX']));
                echo $grupo('Boletos o registro', 'Un botón para comprar o registrarse', 'externo', $boton('Comprar boletos', 'Enlace de boletos o registro'), false);
                break;

            case 'itinerario':
                $dias = is_array($v['dias'] ?? null) && $v['dias'] ? $v['dias'] : ['0' => []];
                $items = '';
                foreach ($dias as $i => $d) {
                    $items .= qr_pagina_item_dia((string) $i, is_array($d) ? $d : []);
                }
                echo $grupo('Información del evento', 'Imagen, nombre, descripción y lugar', 'calendario',
                    $imagen('Imagen')
                    . $titulo('Nombre del evento', true, 'Boda de Ana y Luis')
                    . $descripcion('Todo lo que necesitas saber para disfrutar el día')
                    . qr_pagina_entrada($tipo, ['lugar'], 'Lugar', $val('lugar'), ['max' => 120, 'placeholder' => 'Hacienda San Gabriel'])
                    . qr_pagina_entrada($tipo, ['direccion'], 'Dirección', $val('direccion'), ['max' => 250, 'placeholder' => 'Carretera a Tepoztlán km 5, Morelos']));
                echo $grupo('Programa', 'Días y actividades en el orden en que suceden', 'reloj',
                    '<p class="ayuda campo-completo">Captura las actividades en orden. Las que pasan de medianoche (por ejemplo, a las 02:00) cuentan como de madrugada. Si el evento dura un solo día, deja el nombre y la fecha del día vacíos.</p>'
                    . qr_pagina_lista('', $items, qr_pagina_item_dia('__i__', []), '__i__', QR_PAGINA_MAX_DIAS, 'Agregar día'));
                break;

            case 'empresa':
                echo $grupo('Información del negocio', 'Logo, nombre y a qué se dedica', 'edificio',
                    $imagen('Logo')
                    . qr_pagina_entrada($tipo, ['nombre'], 'Nombre del negocio', $val('nombre'), ['max' => 120, 'requerido' => true, 'completo' => true])
                    . $descripcion('A qué se dedica tu negocio'));
                echo $grupo('Contacto y horario', 'Teléfono, correo, dirección y horario', 'telefono',
                    qr_pagina_entrada($tipo, ['telefono'], 'Teléfono', $val('telefono'), ['max' => 30, 'tipo' => 'tel'])
                    . qr_pagina_entrada($tipo, ['email'], 'Correo', $val('email'), ['max' => 190, 'tipo' => 'email'])
                    . qr_pagina_entrada($tipo, ['web'], 'Sitio web', $val('web'), ['max' => 2048, 'modo' => 'url'])
                    . qr_pagina_entrada($tipo, ['direccion'], 'Dirección', $val('direccion'), ['max' => 250])
                    . qr_pagina_area($tipo, ['horario'], 'Horario', $val('horario'), ['max' => 500, 'placeholder' => "Lunes a viernes de 9:00 a 19:00\nSábado de 10:00 a 14:00"]));
                echo $grupo('Redes sociales', 'Enlaces a tus perfiles', 'compartir', qr_pagina_campos_redes($tipo, is_array($v['redes'] ?? null) ? $v['redes'] : []), false);
                break;

            case 'video':
                echo $grupo('Video', 'El enlace de YouTube o Vimeo', 'video',
                    qr_pagina_entrada($tipo, ['url'], 'Enlace del video', $val('url'), ['max' => 2048, 'modo' => 'url', 'requerido' => true, 'completo' => true, 'placeholder' => 'https://www.youtube.com/watch?v=…']));
                echo $grupo('Información del video', 'Título y descripción', 'texto', $titulo('Título', false, '') . $descripcion());
                break;

            case 'imagenes':
                $actuales = array_filter((array) ($g['imagenes'] ?? []), 'is_string');
                $galeria = '';
                if ($actuales) {
                    $galeria .= '<div class="campo campo-completo"><span class="etiqueta">Imágenes actuales</span><div class="galeria-editar">';
                    foreach ($actuales as $ruta) {
                        $galeria .= '<label><img src="' . e(url($ruta)) . '" alt=""><span class="check texto-chico"><input type="checkbox" name="pg[imagenes][quitar_imagenes][]" value="' . e($ruta) . '"> Quitar</span></label>';
                    }
                    $galeria .= '</div></div>';
                }
                $galeria .= '<div class="campo campo-completo">' . qr_pagina_etiqueta('pg-imagenes-imagenes', $actuales ? 'Agregar imágenes' : 'Imágenes', !$actuales)
                    . '<input class="entrada" id="pg-imagenes-imagenes" name="pg[imagenes][imagenes][]" type="file" multiple accept="' . $img . '"' . ($actuales ? '' : ' required') . '>'
                    . '<small>Hasta ' . QR_PAGINA_MAX_IMAGENES . ' imágenes en total. ' . e($ayudaImg) . '</small></div>';
                echo $grupo('Imágenes', 'Las fotos de tu galería', 'imagen', $galeria);
                echo $grupo('Información de la galería', 'Título y descripción', 'texto', $titulo('Título', false, 'Nuestros trabajos') . $descripcion());
                break;

            case 'facebook':
                echo $grupo('Página de Facebook', 'Al escanear se abre tu página; la cambias sin reimprimir', 'facebook',
                    qr_pagina_entrada($tipo, ['url'], 'Tu página de Facebook', $val('url'), ['max' => 2048, 'modo' => 'url', 'requerido' => true, 'completo' => true, 'placeholder' => 'https://www.facebook.com/tunegocio']));
                break;

            case 'instagram':
                echo $grupo('Perfil de Instagram', 'Al escanear se abre tu perfil; lo cambias sin reimprimir', 'instagram',
                    qr_pagina_entrada($tipo, ['url'], 'Tu usuario de Instagram', $val('url'), ['max' => 2048, 'requerido' => true, 'completo' => true, 'placeholder' => '@tunegocio', 'ayuda' => 'También puedes pegar el enlace de tu perfil.']));
                break;

            case 'redes':
                echo $grupo('Información de la página', 'Imagen, nombre y descripción', 'texto',
                    $imagen('Imagen o logo') . $titulo('Tu nombre o el de tu negocio', true, 'Café Luna') . $descripcion('Síguenos para enterarte de promociones'));
                echo $grupo('Redes sociales', 'Enlaces a tus perfiles', 'compartir',
                    qr_pagina_campos_redes($tipo, is_array($v['redes'] ?? null) ? $v['redes'] : [])
                    . qr_pagina_entrada($tipo, ['web'], 'Sitio web', $val('web'), ['max' => 2048, 'modo' => 'url']));
                break;

            case 'mp3':
                echo $grupo('Audio', 'El archivo y su portada', 'musica',
                    qr_pagina_archivo($tipo, 'audio', 'Archivo de audio', 'audio/mpeg,audio/mp4,audio/x-m4a,audio/ogg,audio/wav,.mp3,.m4a,.ogg,.wav', 'MP3, M4A, OGG o WAV. Máximo ' . QR_PAGINA_MAX_AUDIO / 1048576 . ' MB.', $actual('audio'), true)
                    . $imagen('Portada'));
                echo $grupo('Información del audio', 'Título y descripción', 'texto', $titulo('Título', true, 'Episodio 1: Nuestra historia') . $descripcion());
                break;

            case 'apps':
                echo $grupo('Información de la app', 'Ícono, nombre y descripción', 'celular',
                    $imagen('Ícono de la app') . $titulo('Nombre de la aplicación', true, 'Café Luna') . $descripcion('Pide desde tu celular y acumula puntos'));
                echo $grupo('Tiendas', 'En iPhone abre App Store y en Android, Google Play', 'externo',
                    qr_pagina_entrada($tipo, ['ios'], 'Enlace de App Store', $val('ios'), ['max' => 2048, 'modo' => 'url', 'placeholder' => 'https://apps.apple.com/mx/app/…'])
                    . qr_pagina_entrada($tipo, ['android'], 'Enlace de Google Play', $val('android'), ['max' => 2048, 'modo' => 'url', 'placeholder' => 'https://play.google.com/store/apps/details?id=…']));
                break;

            case 'email':
                echo $grupo('Correo', 'A dónde te escriben y con qué asunto', 'correo',
                    qr_pagina_entrada($tipo, ['email'], 'Correo de destino', $val('email'), ['max' => 190, 'tipo' => 'email', 'requerido' => true])
                    . qr_pagina_entrada($tipo, ['asunto'], 'Asunto', $val('asunto'), ['max' => 200, 'placeholder' => 'Quiero una cotización'])
                    . qr_pagina_area($tipo, ['mensaje'], 'Mensaje inicial', $val('mensaje'), ['max' => 1000, 'ayuda' => 'Aparece ya escrito al abrir el correo.']));
                echo $grupo('Información de la página', 'El título que ven al escanear', 'texto', $titulo('Título de la página', false, 'Escríbenos'), false);
                break;
        }
        echo '</fieldset>';
    }
}
