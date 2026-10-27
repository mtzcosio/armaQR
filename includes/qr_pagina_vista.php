<?php
/**
 * Página pública de un QR dinámico de tipo página (la muestra r.php al escanearlo).
 * Toma el color del QR como acento. Todo dato del cliente se escapa con e(); los enlaces
 * ya vienen validados como http(s) desde qr_pagina_contenido().
 */

declare(strict_types=1);

require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/qr_paginas.php';

/** Texto con saltos de línea. */
function qr_pagina_parrafo(string $s): string
{
    return nl2br(e($s), false);
}

/** "sábado 1 de noviembre de 2026, 18:00" */
function qr_pagina_fecha_larga(string $fecha, bool $conHora = true): string
{
    $dias = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
    $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $t = strtotime($fecha);
    return $dias[(int) date('w', $t)] . ' ' . date('j', $t) . ' de ' . $meses[(int) date('n', $t) - 1] . ' de ' . date('Y', $t)
        . ($conHora ? ', ' . date('H:i', $t) : '');
}

/** Precio de un platillo: si es número, con formato de dinero; si no, tal cual ("Desde 80"). */
function qr_pagina_precio(string $precio): string
{
    $n = str_replace([',', '$', ' '], '', $precio);
    return is_numeric($n) ? dinero((float) $n, floor((float) $n) !== (float) $n) : $precio;
}

function qr_pagina_mapa(string $direccion): string
{
    return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($direccion);
}

/** Color del texto sobre el acento: blanco sobre colores oscuros, tinta sobre claros. */
function qr_pagina_sobre_acento(string $hex): string
{
    [$r, $g, $b] = array_map(fn($x) => hexdec($x) / 255, str_split(ltrim($hex, '#'), 2));
    $lin = fn($c) => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
    return 0.2126 * $lin($r) + 0.7152 * $lin($g) + 0.0722 * $lin($b) > 0.4 ? '#172036' : '#ffffff';
}

/** Título visible de la página (el nombre del QR es privado: nunca se muestra). */
function qr_pagina_titulo(string $tipo, array $c): string
{
    return ($c['titulo'] ?? '') ?: (($c['nombre'] ?? '') ?: match ($tipo) {
        'pdf' => 'Documento PDF',
        'wifi' => 'Red Wi-Fi',
        'email' => 'Escríbenos',
        'imagenes' => 'Galería',
        default => qr_tipo_nombre($tipo),
    });
}

/**
 * Muestra la página completa. $vistaPrevia: la abre su dueño o un admin desde el panel
 * (no cuenta como escaneo y se ve aunque el QR esté pausado).
 */
function qr_pagina_mostrar(array $qr, bool $vistaPrevia = false): void
{
    $tipo = (string) $qr['tipo_contenido'];
    $c = qr_contenido_guardado($qr['contenido'])['campos'];
    $acento = qr_diseno(json_decode((string) $qr['diseno'], true) ?: [])['color'];
    $titulo = qr_pagina_titulo($tipo, $c);
    $base = url('r/' . $qr['codigo_corto']);
    $marca = $qr['marca_agua'] === null || (int) $qr['marca_agua'] === 1;
    $enlace = fn(string $href, string $texto, string $clase = 'micrositio-boton', string $extra = '') =>
        '<a class="' . $clase . '" href="' . e($href) . '" rel="noopener nofollow ugc"' . $extra . '>' . $texto . '</a>';

    pagina_inicio($titulo, 'pagina-qr');
    ?>
<main id="contenido" class="micrositio" style="--acento: <?= e($acento) ?>; --sobre-acento-pagina: <?= qr_pagina_sobre_acento($acento) ?>">
  <?php if ($vistaPrevia): ?>
    <p class="micrositio-aviso"><?= icono('ojo', 16) ?> Vista previa: así la ve quien escanea tu QR. Esta visita no cuenta como escaneo<?= (int) $qr['activo'] !== 1 ? ' y el QR está pausado' : '' ?>.</p>
  <?php endif; ?>
  <article class="micrositio-tarjeta">
    <header class="micrositio-cabeza">
      <?php if (!empty($c['imagen'])): ?>
        <img class="micrositio-imagen <?= in_array($tipo, ['cupon', 'evento', 'mp3'], true) ? 'micrositio-portada' : '' ?>" src="<?= e(url($c['imagen'])) ?>" alt="">
      <?php endif; ?>
      <?php if ($tipo === 'cupon' && ($c['descuento'] ?? '') !== ''): ?><p class="micrositio-descuento"><?= e($c['descuento']) ?></p><?php endif; ?>
      <h1><?= e($titulo) ?></h1>
      <?php if ($tipo === 'vcard' && ($c['cargo'] !== '' || $c['empresa'] !== '')): ?>
        <p class="micrositio-sub"><?= e(implode(' · ', array_filter([$c['cargo'], $c['empresa']]))) ?></p>
      <?php endif; ?>
      <?php if ($tipo === 'evento'): ?><p class="micrositio-sub"><?= e(qr_pagina_fecha_larga($c['inicio'])) ?></p><?php endif; ?>
      <?php if (($c['descripcion'] ?? '') !== ''): ?><p class="micrositio-texto"><?= qr_pagina_parrafo($c['descripcion']) ?></p><?php endif; ?>
    </header>

    <div class="micrositio-cuerpo">
    <?php switch ($tipo):
        case 'pdf': $archivo = url($c['archivo']); ?>
          <iframe class="micrositio-pdf" src="<?= e($archivo) ?>#view=FitH" title="<?= e($titulo) ?>"></iframe>
          <?= $enlace($archivo, icono('archivo', 18) . ' Ver PDF', 'micrositio-boton', ' target="_blank"') ?>
          <?= $enlace($archivo, icono('descargar', 18) . ' Descargar', 'micrositio-boton secundario', ' download') ?>
        <?php break;

        case 'enlaces':
          foreach ($c['enlaces'] as $e) echo $enlace($e['url'], e($e['texto']));
          break;

        case 'vcard': ?>
          <?= $enlace($base . '?descargar=vcf', icono('descargar', 18) . ' Guardar contacto') ?>
          <ul class="micrositio-datos">
            <?php if ($c['telefono'] !== ''): ?><li><span>Teléfono</span><a href="tel:<?= e(preg_replace('/[^\d+]/', '', $c['telefono'])) ?>"><?= e($c['telefono']) ?></a></li><?php endif; ?>
            <?php if ($c['email'] !== ''): ?><li><span>Correo</span><a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a></li><?php endif; ?>
            <?php if ($c['web'] !== ''): ?><li><span>Sitio web</span><a href="<?= e($c['web']) ?>" rel="noopener nofollow ugc"><?= e(qr_pagina_dominio($c['web'])) ?></a></li><?php endif; ?>
            <?php if ($c['direccion'] !== ''): ?><li><span>Dirección</span><a href="<?= e(qr_pagina_mapa($c['direccion'])) ?>" rel="noopener"><?= e($c['direccion']) ?></a></li><?php endif; ?>
            <?php if ($c['nota'] !== ''): ?><li><span>Nota</span><p><?= qr_pagina_parrafo($c['nota']) ?></p></li><?php endif; ?>
          </ul>
        <?php break;

        case 'menu':
          foreach ($c['secciones'] as $s): ?>
            <section class="micrositio-seccion">
              <h2><?= e($s['nombre']) ?></h2>
              <ul class="micrositio-menu">
                <?php foreach ($s['platillos'] as $p): ?>
                  <li>
                    <div><strong><?= e($p['nombre']) ?></strong><?php if ($p['descripcion'] !== ''): ?><p><?= e($p['descripcion']) ?></p><?php endif; ?></div>
                    <?php if ($p['precio'] !== ''): ?><span class="micrositio-precio"><?= e(qr_pagina_precio($p['precio'])) ?></span><?php endif; ?>
                  </li>
                <?php endforeach; ?>
              </ul>
            </section>
          <?php endforeach;
          break;

        case 'cupon':
          $vencido = $c['vigencia'] !== '' && $c['vigencia'] < date('Y-m-d'); ?>
          <?php if ($c['codigo'] !== ''): ?>
            <div class="micrositio-codigo">
              <span>Tu código</span>
              <strong><?= e($c['codigo']) ?></strong>
              <button class="micrositio-boton secundario" type="button" data-copiar="<?= e($c['codigo']) ?>" data-copiar-mensaje="Copiaste el código"><?= icono('copiar', 18) ?> Copiar código</button>
            </div>
          <?php endif; ?>
          <?php if ($c['vigencia'] !== ''): ?>
            <p class="micrositio-vigencia <?= $vencido ? 'vencido' : '' ?>"><?= $vencido ? 'Este cupón venció el ' : 'Válido hasta el ' ?><?= e(qr_pagina_fecha_larga($c['vigencia'], false)) ?></p>
          <?php endif; ?>
          <?php if ($c['boton_url'] !== '' && !$vencido) echo $enlace($c['boton_url'], e($c['boton_texto'] !== '' ? $c['boton_texto'] : 'Usar cupón')); ?>
          <?php if ($c['condiciones'] !== ''): ?><p class="micrositio-letra-chica"><?= qr_pagina_parrafo($c['condiciones']) ?></p><?php endif; ?>
        <?php break;

        case 'wifi': ?>
          <ul class="micrositio-datos">
            <li><span>Red</span><strong><?= e($c['red']) ?></strong></li>
            <?php if ($c['seguridad'] !== 'nopass'): ?><li><span>Contraseña</span><strong class="micrositio-clave"><?= e($c['clave']) ?></strong></li><?php endif; ?>
          </ul>
          <?php if ($c['seguridad'] !== 'nopass'): ?>
            <button class="micrositio-boton" type="button" data-copiar="<?= e($c['clave']) ?>" data-copiar-mensaje="Copiaste la contraseña"><?= icono('copiar', 18) ?> Copiar contraseña</button>
          <?php else: ?>
            <p class="micrositio-texto">Esta red no tiene contraseña.</p>
          <?php endif; ?>
          <p class="micrositio-letra-chica">Abre la configuración de Wi-Fi de tu celular, elige la red «<?= e($c['red']) ?>»<?= $c['seguridad'] !== 'nopass' ? ' y pega la contraseña' : '' ?>.</p>
        <?php break;

        case 'evento': ?>
          <ul class="micrositio-datos">
            <li><span>Empieza</span><strong><?= e(qr_pagina_fecha_larga($c['inicio'])) ?></strong></li>
            <?php if ($c['fin'] !== ''): ?><li><span>Termina</span><strong><?= e(qr_pagina_fecha_larga($c['fin'])) ?></strong></li><?php endif; ?>
            <?php if ($c['lugar'] !== '' || $c['direccion'] !== ''): ?>
              <li><span>Lugar</span><?php if ($c['direccion'] !== ''): ?><a href="<?= e(qr_pagina_mapa(trim($c['lugar'] . ' ' . $c['direccion']))) ?>" rel="noopener"><?= e(implode(', ', array_filter([$c['lugar'], $c['direccion']]))) ?></a><?php else: ?><strong><?= e($c['lugar']) ?></strong><?php endif; ?></li>
            <?php endif; ?>
          </ul>
          <?php if ($c['boton_url'] !== '') echo $enlace($c['boton_url'], e($c['boton_texto'] !== '' ? $c['boton_texto'] : 'Boletos y registro')); ?>
          <?= $enlace($base . '?descargar=ics', icono('descargar', 18) . ' Agregar a mi calendario', $c['boton_url'] !== '' ? 'micrositio-boton secundario' : 'micrositio-boton') ?>
        <?php break;

        case 'empresa': ?>
          <?php if ($c['telefono'] !== '') echo $enlace('tel:' . preg_replace('/[^\d+]/', '', $c['telefono']), icono('telefono', 18) . ' Llamar'); ?>
          <?php if ($c['web'] !== '') echo $enlace($c['web'], icono('externo', 18) . ' Sitio web', $c['telefono'] !== '' ? 'micrositio-boton secundario' : 'micrositio-boton'); ?>
          <ul class="micrositio-datos">
            <?php if ($c['horario'] !== ''): ?><li><span>Horario</span><p><?= qr_pagina_parrafo($c['horario']) ?></p></li><?php endif; ?>
            <?php if ($c['direccion'] !== ''): ?><li><span>Dirección</span><a href="<?= e(qr_pagina_mapa($c['direccion'])) ?>" rel="noopener"><?= e($c['direccion']) ?></a></li><?php endif; ?>
            <?php if ($c['telefono'] !== ''): ?><li><span>Teléfono</span><a href="tel:<?= e(preg_replace('/[^\d+]/', '', $c['telefono'])) ?>"><?= e($c['telefono']) ?></a></li><?php endif; ?>
            <?php if ($c['email'] !== ''): ?><li><span>Correo</span><a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a></li><?php endif; ?>
          </ul>
          <?php if ($c['redes']): ?>
            <div class="micrositio-redes">
              <?php foreach ($c['redes'] as $red => $url) echo $enlace($url, e(QR_PAGINA_REDES[$red] ?? $red), 'micrositio-boton secundario'); ?>
            </div>
          <?php endif; ?>
        <?php break;

        case 'video': ?>
          <div class="micrositio-video">
            <iframe src="<?= e((string) qr_pagina_video_incrustado($c['url'])) ?>" title="<?= e($titulo) ?>" loading="lazy" allowfullscreen
                    allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin"></iframe>
          </div>
        <?php break;

        case 'imagenes': ?>
          <div class="micrositio-galeria">
            <?php foreach ($c['imagenes'] as $i => $img): ?>
              <a href="<?= e(url($img)) ?>" target="_blank" rel="noopener"><img src="<?= e(url($img)) ?>" alt="Imagen <?= $i + 1 ?> de <?= count($c['imagenes']) ?>" loading="lazy"></a>
            <?php endforeach; ?>
          </div>
        <?php break;

        case 'redes':
          foreach ($c['redes'] as $red => $url) echo $enlace($url, e(QR_PAGINA_REDES[$red] ?? $red));
          if ($c['web'] !== '') echo $enlace($c['web'], icono('externo', 18) . ' Sitio web');
          break;

        case 'mp3': $audio = url($c['audio']); ?>
          <audio class="micrositio-audio" controls preload="metadata" src="<?= e($audio) ?>"></audio>
          <?= $enlace($audio, icono('descargar', 18) . ' Descargar audio', 'micrositio-boton secundario', ' download') ?>
        <?php break;

        case 'apps':
          if ($c['ios'] !== '') echo $enlace($c['ios'], 'Descargar en App Store');
          if ($c['android'] !== '') echo $enlace($c['android'], 'Descargar en Google Play', $c['ios'] !== '' ? 'micrositio-boton secundario' : 'micrositio-boton');
          break;

        case 'email':
          $consulta = http_build_query(array_filter(['subject' => $c['asunto'], 'body' => $c['mensaje']]), '', '&', PHP_QUERY_RFC3986); ?>
          <?= $enlace('mailto:' . $c['email'] . ($consulta !== '' ? '?' . $consulta : ''), icono('correo', 18) . ' Escribir correo') ?>
          <p class="micrositio-texto micrositio-centro"><?= e($c['email']) ?></p>
        <?php break;
    endswitch; ?>
    </div>
  </article>
  <?php if ($marca): ?>
    <p class="micrositio-marca">Creado con <a href="<?= e(url('index.php')) ?>"><?= e(nombre_portal()) ?></a></p>
  <?php endif; ?>
</main>
<?php
    pagina_fin();
}
