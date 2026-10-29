<?php
/**
 * Crear o editar un QR en tres pasos, con un teléfono de vista previa al lado:
 *  1. Tipo de QR: estático o dinámico y qué comparte (Sitio web, PDF, Menú…).
 *  2. Contenido: secciones plegables con los datos, los colores de la página (dinámicos de tipo
 *     página) y el nombre del QR. El teléfono muestra la página en vivo (app/vista-pagina.php).
 *  3. Diseño del QR: colores y logo; aquí se guarda y se descarga.
 */
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/qr_paginas_form.php';

$usuario = datos_usuario_actual();
$plan    = $usuario['plan_datos'];
$esPago  = $plan['max_qr_dinamicos'] !== 0;

// Modo edición (?editar=ID): solo QR propios
$editar = isset($_GET['editar']) ? qr_del_usuario((int) $_GET['editar'], (int) $usuario['id']) : null;
if (isset($_GET['editar']) && $editar === null) {
    flash('error', 'Ese QR no existe o no es tuyo.');
    redirigir('app/index.php');
}
$guardado = $editar ? qr_contenido_guardado($editar['contenido']) : ['campos' => []];
$diseno = $editar ? qr_diseno(json_decode((string) $editar['diseno'], true) ?: []) : qr_diseno([]);

// Lo capturado y no guardado (error de validación) tiene prioridad.
$previo = $_SESSION['qr_formulario'] ?? null;
unset($_SESSION['qr_formulario']);
$campo = fn(string $nombre, string $deGuardado = '') => e((string) ($previo[$nombre] ?? ($guardado['campos'][$deGuardado ?: $nombre] ?? '')));

// ?tipo=: llega desde la página explicativa de un tipo (tipo.php) con ese tipo ya elegido.
$tipoPedido = !$editar && is_string($_GET['tipo'] ?? null) && isset(QR_TIPOS_DINAMICOS[$_GET['tipo']]) ? $_GET['tipo'] : null;
$tipoInicial = $previo['tipo_contenido'] ?? ($editar['tipo_contenido'] ?? ($tipoPedido ?? 'url'));
$esDinamico  = $editar ? $editar['tipo'] === 'dinamico' : (($previo['tipo'] ?? ($esPago ? 'dinamico' : 'estatico')) === 'dinamico');
$estaticoGuardado = $editar && $editar['tipo'] === 'estatico';
$color = $previo['color'] ?? $diseno['color'];
$fondo = $previo['fondo'] ?? $diseno['fondo'];

// Páginas de los dinámicos: lo capturado (tras un error) o lo guardado del tipo que se edita.
$guardadosPagina = $editar && $esDinamico ? [$editar['tipo_contenido'] => $guardado['campos']] : [];
$valoresPagina = [];
foreach (array_keys(QR_TIPOS_DINAMICOS) as $t) {
    $valoresPagina[$t] = $previo['pg'][$t] ?? ($guardadosPagina[$t] ?? []);
}
// Colores de la página: lo capturado, lo guardado o, en páginas de antes de las paletas, el color del QR.
$estiloPagina = is_array($previo['pagina_estilo'] ?? null) ? qr_pagina_estilo($previo)
    : ($guardado['campos']['estilo'] ?? ($editar && $esDinamico && !qr_tipo_usa_url($editar['tipo_contenido']) ? ['primario' => $diseno['color'], 'secundario' => QR_PAGINA_PALETAS[0][1]] : []));

// Tarjetas de tipo de contenido: los dinámicos (con su descripción) y Texto, que solo es estático.
$tarjetasTipo = [];
foreach (QR_TIPOS_DINAMICOS as $t => $info) {
    $tarjetasTipo[$t] = $info + ['modos' => isset(QR_TIPOS[$t]) ? 'estatico dinamico' : 'dinamico'];
}
$tarjetasTipo['texto'] = ['nombre' => 'Texto', 'descripcion' => 'Muestra un texto al escanear', 'icono' => 'texto', 'modos' => 'estatico'];

// Cuánto queda del plan (solo al crear)
$usoEstaticos = qr_estaticos_del_mes((int) $usuario['id']);
$usoDinamicos = qr_dinamicos_activos((int) $usuario['id']);
$restan = fn(?int $limite, int $usado) => $limite === null ? 'sin límite' : max(0, $limite - $usado) . ' de ' . $limite;

// Al editar, al volver de un error o con un tipo pedido se empieza en el contenido; al crear, en el tipo.
$pasoInicial = $editar || $previo || ($tipoPedido && $esPago) ? 2 : 1;
$pasos = [1 => 'Tipo de QR', 2 => 'Contenido', 3 => 'Diseño del QR'];

pagina_inicio($editar ? 'Editar QR' : 'Crear QR', 'app', 'crear');
?>

<div class="encabezado">
  <div>
    <a class="miga" href="<?= url_app('index.php') ?>">← Mis QR</a>
    <h1><?= $editar ? 'Editar “' . e($editar['nombre']) . '”' : 'Crear QR' ?></h1>
  </div>
  <?php if ($editar && $esDinamico): ?>
    <div class="encabezado-acciones">
      <button class="boton boton-fantasma" type="button" data-copiar="<?= e(qr_liga_corta($editar['codigo_corto'])) ?>" data-copiar-mensaje="Copiaste la liga corta"><?= icono('copiar', 17) ?> <?= e(dominio_portal() . '/r/' . $editar['codigo_corto']) ?></button>
      <?php if (!qr_tipo_usa_url($editar['tipo_contenido'])): ?>
        <a class="boton boton-fantasma" href="<?= e(url('r/' . $editar['codigo_corto']) . '?vista_previa=1') ?>" target="_blank" rel="noopener"><?= icono('ojo', 17) ?> Ver página</a>
      <?php endif; ?>
      <a class="boton boton-fantasma" href="<?= url_app('estadisticas.php', ['qr' => $editar['id']]) ?>"><?= icono('grafica', 17) ?> Estadísticas</a>
    </div>
  <?php endif; ?>
</div>

<form class="editor" method="post" action="<?= url('auth/qr.php') ?>" enctype="multipart/form-data"
      data-generador="vista-crear" data-dominio="<?= e(dominio_portal()) ?>" data-pasos data-paso-inicial="<?= $pasoInicial ?>"
      data-vista-url="<?= e(url_app('vista-pagina.php')) ?>"
      <?= !empty($editar['codigo_corto']) ? 'data-codigo="' . e($editar['codigo_corto']) . '"' : '' ?>
      <?= !empty($editar['logo_ruta']) ? 'data-logo-url="' . e(url($editar['logo_ruta'])) . '"' : '' ?>
      <?= $plan['marca_agua'] ? 'data-marca="' . e('Creado con ' . nombre_portal()) . '"' : '' ?>>
  <?= csrf_campo() ?>
  <input type="hidden" name="accion" value="guardar">
  <?php if ($editar): ?><input type="hidden" name="id" value="<?= (int) $editar['id'] ?>"><?php endif; ?>

  <div class="editor-principal">
    <ol class="asistente-pasos">
      <?php foreach ($pasos as $n => $texto): ?>
        <li><button type="button" data-ir-paso="<?= $n ?>"><span class="pasos-numero"><?= $n ?></span> <?= $texto ?></button></li>
      <?php endforeach; ?>
    </ol>

    <!-- Paso 1: tipo de QR -->
    <section class="asistente-paso" data-paso="1" aria-label="Tipo de QR">
      <div class="caja caja-cuerpo">
        <div class="bloque">
          <h3>Tipo de código</h3>
          <div class="opcion-tipo">
            <label>
              <input type="radio" name="tipo" value="estatico" <?= !$esDinamico ? 'checked' : '' ?> <?= $editar ? 'disabled' : '' ?>>
              <span class="opcion"><strong>Estático</strong><small>El contenido va dentro del código. No se puede editar.
                <?php if (!$editar): ?><br>Te quedan <?= $restan($plan['max_qr_estaticos_mes'], $usoEstaticos) ?> este mes.<?php endif; ?></small></span>
            </label>
            <label>
              <input type="radio" name="tipo" value="dinamico" <?= $esDinamico ? 'checked' : '' ?> <?= !$esPago || $editar ? 'disabled' : '' ?>>
              <span class="opcion">
                <strong>Dinámico <?= !$esPago ? icono('candado', 15) : '' ?></strong>
                <small><?= $esPago ? 'Cambias el contenido cuando quieras y ves sus escaneos.' : 'Disponible en el plan Pro.' ?>
                  <?php if ($esPago && !$editar): ?><br>Activos: <?= $usoDinamicos ?> · disponibles: <?= $restan($plan['max_qr_dinamicos'], $usoDinamicos) ?>.<?php endif; ?></small>
              </span>
            </label>
          </div>
          <?php if (!$esPago): ?>
            <p class="texto-chico"><a class="enlace" href="<?= url_app('plan.php') ?>">Pasa a Pro</a> para crear QR que puedes editar después de imprimirlos, con su propia página (PDF, menú, cupón…).</p>
          <?php endif; ?>
        </div>

        <div class="bloque">
          <h3>¿Qué quieres compartir?</h3>
          <?php if ($estaticoGuardado): ?>
            <p class="aviso aviso-info aviso-compacto"><?= icono('candado', 16) ?><span>El contenido de un QR estático no se puede cambiar porque ya puede estar impreso. Si necesitas otro, crea uno nuevo.</span></p>
          <?php endif; ?>
          <fieldset class="sin-borde" <?= $estaticoGuardado ? 'disabled' : '' ?>>
            <legend class="oculto-visual">Tipo de contenido</legend>
            <div class="tipo-contenido tipo-contenido-tarjetas" role="radiogroup" aria-label="Tipo de contenido">
              <?php foreach ($tarjetasTipo as $valor => $t): ?>
                <label data-modos="<?= $t['modos'] ?>" <?= !qr_tipo_usa_url($valor) && $valor !== 'texto' ? 'data-con-pagina' : '' ?>>
                  <input type="radio" name="tipo_contenido" value="<?= $valor ?>" <?= $valor === $tipoInicial ? 'checked' : '' ?>>
                  <span><span class="tipo-icono"><?= icono($t['icono'], 22) ?></span><strong><?= e($t['nombre']) ?></strong><small><?= e($t['descripcion']) ?></small></span>
                </label>
              <?php endforeach; ?>
            </div>
          </fieldset>
        </div>
      </div>
    </section>

    <!-- Paso 2: contenido -->
    <section class="asistente-paso" data-paso="2" aria-label="Contenido" hidden>
      <?php if ($estaticoGuardado): ?>
        <p class="aviso aviso-info aviso-compacto"><?= icono('candado', 16) ?><span>El contenido de un QR estático no se puede cambiar. Solo puedes cambiar su nombre y su diseño.</span></p>
      <?php endif; ?>

      <div data-solo-pagina hidden><?= qr_pagina_grupo_diseno($estiloPagina) ?></div>

      <fieldset class="sin-borde" <?= $estaticoGuardado ? 'disabled' : '' ?>>
        <legend class="oculto-visual">Datos del contenido</legend>
        <details class="acordeon" open data-panel-tipo="url">
          <summary><span class="acordeon-icono"><?= icono('web', 20) ?></span><span class="acordeon-titulo"><strong>Sitio web</strong><small>La dirección que abre el QR</small></span><span class="acordeon-flecha"><?= icono('derecha', 18) ?></span></summary>
          <div class="acordeon-cuerpo">
            <div class="campo">
              <label for="c-url">Dirección web</label>
              <input class="entrada" id="c-url" name="url" inputmode="url" data-campo="url" placeholder="https://tunegocio.mx/menu" value="<?= $campo('url') ?>">
            </div>
          </div>
        </details>
        <details class="acordeon" open data-panel-tipo="whatsapp" hidden>
          <summary><span class="acordeon-icono"><?= icono('mensaje', 20) ?></span><span class="acordeon-titulo"><strong>WhatsApp</strong><small>Al escanear se abre un chat contigo</small></span><span class="acordeon-flecha"><?= icono('derecha', 18) ?></span></summary>
          <div class="acordeon-cuerpo fila-campos">
            <div class="campo"><label for="c-tel">Número a 10 dígitos</label><input class="entrada" id="c-tel" name="telefono" inputmode="tel" data-campo="telefono" placeholder="55 1234 5678" value="<?= $campo('telefono') ?>"></div>
            <div class="campo"><label for="c-msg">Mensaje inicial <span class="opcional">(opcional)</span></label><input class="entrada" id="c-msg" name="mensaje" maxlength="500" data-campo="mensaje" placeholder="Hola, quiero hacer un pedido" value="<?= $campo('mensaje') ?>"></div>
          </div>
        </details>
        <details class="acordeon" open data-panel-tipo="wifi" data-panel-modo="estatico" hidden>
          <summary><span class="acordeon-icono"><?= icono('wifi', 20) ?></span><span class="acordeon-titulo"><strong>Red Wi-Fi</strong><small>Al escanear, el celular se conecta</small></span><span class="acordeon-flecha"><?= icono('derecha', 18) ?></span></summary>
          <div class="acordeon-cuerpo fila-campos">
            <div class="campo"><label for="c-red">Nombre de la red</label><input class="entrada" id="c-red" name="red" maxlength="32" data-campo="red" value="<?= $campo('red') ?>"></div>
            <div class="campo"><label for="c-clave">Contraseña</label><input class="entrada" id="c-clave" name="clave" maxlength="63" data-campo="clave" value="<?= $campo('clave') ?>"></div>
            <div class="campo"><label for="c-seg">Seguridad</label>
              <?php $seg = (string) ($previo['seguridad'] ?? ($guardado['campos']['seguridad'] ?? 'WPA')); ?>
              <select class="entrada" id="c-seg" name="seguridad" data-campo="seguridad">
                <?php foreach (['WPA' => 'WPA / WPA2', 'WEP' => 'WEP', 'nopass' => 'Sin contraseña'] as $v => $t): ?><option value="<?= $v ?>" <?= $seg === $v ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?>
              </select>
            </div>
          </div>
        </details>
        <details class="acordeon" open data-panel-tipo="vcard" data-panel-modo="estatico" hidden>
          <summary><span class="acordeon-icono"><?= icono('contacto', 20) ?></span><span class="acordeon-titulo"><strong>Contacto</strong><small>Al escanear se guarda en el celular</small></span><span class="acordeon-flecha"><?= icono('derecha', 18) ?></span></summary>
          <div class="acordeon-cuerpo fila-campos">
            <div class="campo"><label for="c-nombre">Nombre</label><input class="entrada" id="c-nombre" name="vc_nombre" maxlength="120" data-campo="nombre" value="<?= $campo('vc_nombre', 'nombre') ?>"></div>
            <div class="campo"><label for="c-empresa">Empresa</label><input class="entrada" id="c-empresa" name="vc_empresa" maxlength="120" data-campo="empresa" value="<?= $campo('vc_empresa', 'empresa') ?>"></div>
            <div class="campo"><label for="c-vtel">Teléfono</label><input class="entrada" id="c-vtel" name="vc_telefono" inputmode="tel" data-campo="telefono" value="<?= $campo('vc_telefono', 'telefono') ?>"></div>
            <div class="campo"><label for="c-vmail">Correo</label><input class="entrada" id="c-vmail" name="vc_email" type="email" data-campo="email" value="<?= $campo('vc_email', 'email') ?>"></div>
          </div>
        </details>
        <details class="acordeon" open data-panel-tipo="email" data-panel-modo="estatico" hidden>
          <summary><span class="acordeon-icono"><?= icono('correo', 20) ?></span><span class="acordeon-titulo"><strong>Correo</strong><small>Al escanear se abre un correo nuevo</small></span><span class="acordeon-flecha"><?= icono('derecha', 18) ?></span></summary>
          <div class="acordeon-cuerpo fila-campos">
            <div class="campo"><label for="c-mail">Correo de destino</label><input class="entrada" id="c-mail" name="email" type="email" data-campo="email" value="<?= $campo('email') ?>"></div>
            <div class="campo"><label for="c-asunto">Asunto</label><input class="entrada" id="c-asunto" name="asunto" maxlength="200" data-campo="asunto" value="<?= $campo('asunto') ?>"></div>
          </div>
        </details>
        <details class="acordeon" open data-panel-tipo="texto" data-panel-modo="estatico" hidden>
          <summary><span class="acordeon-icono"><?= icono('texto', 20) ?></span><span class="acordeon-titulo"><strong>Texto</strong><small>Se muestra al escanear, sin internet</small></span><span class="acordeon-flecha"><?= icono('derecha', 18) ?></span></summary>
          <div class="acordeon-cuerpo">
            <div class="campo">
              <label for="c-texto">Texto</label>
              <textarea class="entrada" id="c-texto" name="texto" data-campo="texto" maxlength="500"><?= $campo('texto') ?></textarea>
            </div>
          </div>
        </details>

        <?php qr_pagina_formularios($valoresPagina, $guardadosPagina); ?>
      </fieldset>

      <?= qr_pagina_grupo('Nombre del código QR', 'Solo tú lo ves; te ayuda a encontrarlo en tu lista', 'qr',
          '<div class="campo campo-completo"><label for="nombre">Nombre</label><input class="entrada" id="nombre" name="nombre" required maxlength="120" value="'
          . e((string) ($previo['nombre'] ?? ($editar['nombre'] ?? ''))) . '" placeholder="Ej. Menú de la terraza"><small>También nombra los archivos que descargas.</small></div>') ?>
    </section>

    <!-- Paso 3: diseño del QR -->
    <section class="asistente-paso" data-paso="3" aria-label="Diseño del QR" hidden>
      <?= qr_pagina_grupo('Colores del código', 'Un color oscuro sobre fondo claro se lee en cualquier celular', 'paleta',
          '<div class="campo"><label for="color-qr">Color del código</label><input class="entrada-color" id="color-qr" name="color" type="color" value="' . e($color) . '" data-color-qr></div>'
          . '<div class="campo"><label for="color-fondo">Color de fondo</label><input class="entrada-color" id="color-fondo" name="fondo" type="color" value="' . e($fondo) . '" data-fondo-qr></div>'
          . '<p class="aviso aviso-advertencia aviso-compacto campo-completo" data-aviso-contraste hidden role="status">' . icono('alerta', 16) . '<span></span></p>') ?>

      <?= qr_pagina_grupo('Logo al centro', $plan['permite_logo'] ? 'Tu marca dentro del código' : 'Disponible desde el plan Pro', 'imagen',
          '<div class="campo campo-completo"><label for="logo">Logo ' . (!$plan['permite_logo'] ? '<span class="plan plan-pro">Pro</span>' : '') . '</label>'
          . '<input class="entrada" id="logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp,image/gif" data-archivo-logo ' . (!$plan['permite_logo'] ? 'disabled' : '') . '>'
          . '<small>PNG, JPG, WebP o GIF cuadrado, de preferencia con fondo transparente. Máximo 1 MB.</small>'
          . (!empty($editar['logo_ruta']) ? '<label class="check texto-chico"><input type="checkbox" name="quitar_logo" value="1" data-quitar-logo> Quitar el logo actual</label>' : '')
          . '</div>') ?>

      <div class="caja caja-cuerpo descargas">
        <h3>Descargar</h3>
        <div class="fila-campos">
          <button class="boton boton-fantasma" type="button" data-descargar-png="<?= e($editar['nombre'] ?? 'mi-qr') ?>"><?= icono('descargar', 16) ?> PNG</button>
          <button class="boton boton-fantasma" type="button" <?= $plan['permite_svg_pdf'] ? 'data-descargar-svg' : 'disabled title="Disponible en Pro"' ?>><?= !$plan['permite_svg_pdf'] ? icono('candado', 15) : icono('descargar', 16) ?> SVG</button>
          <button class="boton boton-fantasma" type="button" <?= $plan['permite_svg_pdf'] ? 'data-descargar-pdf' : 'disabled title="Disponible en Pro"' ?>><?= !$plan['permite_svg_pdf'] ? icono('candado', 15) : icono('descargar', 16) ?> PDF</button>
        </div>
        <?php if (!$editar): ?><p class="texto-chico texto-gris" data-solo-dinamico>Un QR dinámico se descarga después de guardarlo: así obtiene su liga corta.</p><?php endif; ?>
        <?php if ($editar && $esDinamico && !$editar['activo']): ?>
          <p class="texto-chico texto-gris">Este QR está pausado: quien lo escanee verá que no está disponible. Reanúdalo desde Mis QR.</p>
        <?php endif; ?>
      </div>
    </section>

    <div class="pasos-acciones">
      <button class="boton boton-fantasma" type="button" data-paso-atras><?= icono('izquierda', 16) ?> Atrás</button>
      <span class="pasos-espacio"></span>
      <?php if ($editar): ?>
        <button class="boton boton-fantasma" type="submit" data-guardar-siempre><?= icono('check', 16) ?> Guardar cambios</button>
      <?php endif; ?>
      <button class="boton boton-principal" type="button" data-paso-siguiente>Siguiente <?= icono('derecha', 16) ?></button>
      <button class="boton boton-principal" type="submit" data-paso-guardar><?= icono('check', 16) ?> <?= $editar ? 'Guardar cambios' : 'Guardar QR' ?></button>
    </div>
  </div>

  <aside class="editor-vista" id="vista-crear">
    <div class="segmentado vista-pestanas" role="tablist" aria-label="Vista previa" data-vista-pestanas>
      <button type="button" role="tab" data-vista-pestana="pagina" aria-selected="true">Vista previa</button>
      <button type="button" role="tab" data-vista-pestana="qr" aria-selected="false">Código QR</button>
    </div>
    <div class="telefono">
      <div class="telefono-pantalla">
        <iframe class="telefono-pagina" data-vista-pagina title="Vista previa de la página del QR" hidden></iframe>
        <div class="telefono-qr" data-telefono-qr>
          <div class="vista-previa">
            <div class="qr-vivo" data-qr-vivo data-qr-tam="220" role="img" aria-label="Vista previa del QR"></div>
            <?php if ($plan['marca_agua']): ?><p class="marca-agua">Creado con <?= e(nombre_portal()) ?></p><?php endif; ?>
          </div>
          <p class="texto-chico texto-gris telefono-contenido"><strong>Contenido:</strong> <span data-contenido-salida></span></p>
        </div>
      </div>
    </div>
  </aside>
</form>
<?php pagina_fin('app', [CDN_QR]); ?>
