<?php
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/qr.php';

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

$tipoInicial = $previo['tipo_contenido'] ?? ($editar['tipo_contenido'] ?? 'url');
$esDinamico  = $editar ? $editar['tipo'] === 'dinamico' : (($previo['tipo'] ?? ($esPago ? 'dinamico' : 'estatico')) === 'dinamico');
$estaticoGuardado = $editar && $editar['tipo'] === 'estatico';
$color = $previo['color'] ?? $diseno['color'];
$fondo = $previo['fondo'] ?? $diseno['fondo'];

// Cuánto queda del plan (solo al crear)
$usoEstaticos = qr_estaticos_del_mes((int) $usuario['id']);
$usoDinamicos = qr_dinamicos_activos((int) $usuario['id']);
$restan = fn(?int $limite, int $usado) => $limite === null ? 'sin límite' : max(0, $limite - $usado) . ' de ' . $limite;

pagina_inicio($editar ? 'Editar QR' : 'Crear QR', 'app', 'crear');
?>

<div class="encabezado">
  <div>
    <a class="miga" href="<?= url_app('index.php') ?>">← Mis QR</a>
    <h1><?= $editar ? 'Editar “' . e($editar['nombre']) . '”' : 'Crear QR' ?></h1>
    <p><?= $editar
        ? ($esDinamico ? 'Cambia el destino y el código impreso seguirá funcionando.' : 'Puedes cambiar el nombre y el diseño. El contenido de un QR estático ya no cambia.')
        : 'Elige el contenido y la vista previa se actualiza mientras escribes.' ?></p>
  </div>
  <?php if ($editar && $esDinamico): ?>
    <div class="encabezado-acciones">
      <button class="boton boton-fantasma" type="button" data-copiar="<?= e(qr_liga_corta($editar['codigo_corto'])) ?>" data-copiar-mensaje="Copiaste la liga corta"><?= icono('copiar', 17) ?> <?= e(dominio_portal() . '/r/' . $editar['codigo_corto']) ?></button>
      <a class="boton boton-fantasma" href="<?= url_app('estadisticas.php', ['qr' => $editar['id']]) ?>"><?= icono('grafica', 17) ?> Estadísticas</a>
    </div>
  <?php endif; ?>
</div>

<form class="editor" method="post" action="<?= url('auth/qr.php') ?>" enctype="multipart/form-data"
      data-generador="vista-crear" data-dominio="<?= e(dominio_portal()) ?>"
      <?= !empty($editar['codigo_corto']) ? 'data-codigo="' . e($editar['codigo_corto']) . '"' : '' ?>
      <?= !empty($editar['logo_ruta']) ? 'data-logo-url="' . e(url($editar['logo_ruta'])) . '"' : '' ?>
      <?= $plan['marca_agua'] ? 'data-marca="' . e('Creado con ' . nombre_portal()) . '"' : '' ?>>
  <?= csrf_campo() ?>
  <input type="hidden" name="accion" value="guardar">
  <?php if ($editar): ?><input type="hidden" name="id" value="<?= (int) $editar['id'] ?>"><?php endif; ?>

  <div class="caja caja-cuerpo">
    <div class="bloque">
      <div class="campo">
        <label for="nombre">Nombre del QR</label>
        <input class="entrada" id="nombre" name="nombre" required maxlength="120" value="<?= e((string) ($previo['nombre'] ?? ($editar['nombre'] ?? ''))) ?>" placeholder="Ej. Menú de la terraza">
        <small>Solo tú lo ves. Te ayuda a encontrarlo en tu lista y nombra los archivos que descargas.</small>
      </div>
    </div>

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
            <small><?= $esPago ? 'Cambias el destino cuando quieras y ves sus escaneos.' : 'Disponible en el plan Pro.' ?>
              <?php if ($esPago && !$editar): ?><br>Activos: <?= $usoDinamicos ?> · disponibles: <?= $restan($plan['max_qr_dinamicos'], $usoDinamicos) ?>.<?php endif; ?></small>
          </span>
        </label>
      </div>
      <?php if (!$esPago): ?>
        <p class="texto-chico"><a class="enlace" href="<?= url_app('plan.php') ?>">Pasa a Pro</a> para crear QR que puedes editar después de imprimirlos.</p>
      <?php endif; ?>
    </div>

    <div class="bloque">
      <h3>Contenido</h3>
      <?php if ($estaticoGuardado): ?>
        <p class="aviso aviso-info aviso-compacto"><?= icono('candado', 16) ?><span>El contenido de un QR estático no se puede cambiar porque ya puede estar impreso. Si necesitas otro, crea uno nuevo.</span></p>
      <?php endif; ?>
      <fieldset class="sin-borde" <?= $estaticoGuardado ? 'disabled' : '' ?>>
        <legend class="oculto-visual">Tipo de contenido y datos</legend>
        <div class="tipo-contenido" role="radiogroup" aria-label="Tipo de contenido">
          <?php foreach (QR_TIPOS as $valor => $texto): ?>
            <label <?= in_array($valor, QR_TIPOS_DINAMICOS, true) ? '' : 'data-solo-estatico' ?>><input type="radio" name="tipo_contenido" value="<?= $valor ?>" <?= $valor === $tipoInicial ? 'checked' : '' ?>><span><?= e($texto) ?></span></label>
          <?php endforeach; ?>
        </div>
        <p class="texto-chico texto-gris" data-nota-dinamico hidden>Un QR dinámico lleva a una página: elige Liga o WhatsApp.</p>

        <div data-panel-tipo="url" class="campo">
          <label for="c-url">Dirección web</label>
          <input class="entrada" id="c-url" name="url" inputmode="url" data-campo="url" placeholder="https://tunegocio.mx/menu" value="<?= $campo('url') ?>">
        </div>
        <div data-panel-tipo="whatsapp" class="fila-campos" hidden>
          <div class="campo"><label for="c-tel">Número a 10 dígitos</label><input class="entrada" id="c-tel" name="telefono" inputmode="tel" data-campo="telefono" placeholder="55 1234 5678" value="<?= $campo('telefono') ?>"></div>
          <div class="campo"><label for="c-msg">Mensaje inicial (opcional)</label><input class="entrada" id="c-msg" name="mensaje" maxlength="500" data-campo="mensaje" placeholder="Hola, quiero hacer un pedido" value="<?= $campo('mensaje') ?>"></div>
        </div>
        <div data-panel-tipo="wifi" class="fila-campos" hidden>
          <div class="campo"><label for="c-red">Nombre de la red</label><input class="entrada" id="c-red" name="red" maxlength="32" data-campo="red" value="<?= $campo('red') ?>"></div>
          <div class="campo"><label for="c-clave">Contraseña</label><input class="entrada" id="c-clave" name="clave" maxlength="63" data-campo="clave" value="<?= $campo('clave') ?>"></div>
          <div class="campo"><label for="c-seg">Seguridad</label>
            <?php $seg = (string) ($previo['seguridad'] ?? ($guardado['campos']['seguridad'] ?? 'WPA')); ?>
            <select class="entrada" id="c-seg" name="seguridad" data-campo="seguridad">
              <?php foreach (['WPA' => 'WPA / WPA2', 'WEP' => 'WEP', 'nopass' => 'Sin contraseña'] as $v => $t): ?><option value="<?= $v ?>" <?= $seg === $v ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <div data-panel-tipo="vcard" class="fila-campos" hidden>
          <div class="campo"><label for="c-nombre">Nombre</label><input class="entrada" id="c-nombre" name="vc_nombre" maxlength="120" data-campo="nombre" value="<?= $campo('vc_nombre', 'nombre') ?>"></div>
          <div class="campo"><label for="c-empresa">Empresa</label><input class="entrada" id="c-empresa" name="vc_empresa" maxlength="120" data-campo="empresa" value="<?= $campo('vc_empresa', 'empresa') ?>"></div>
          <div class="campo"><label for="c-vtel">Teléfono</label><input class="entrada" id="c-vtel" name="vc_telefono" inputmode="tel" data-campo="telefono" value="<?= $campo('vc_telefono', 'telefono') ?>"></div>
          <div class="campo"><label for="c-vmail">Correo</label><input class="entrada" id="c-vmail" name="vc_email" type="email" data-campo="email" value="<?= $campo('vc_email', 'email') ?>"></div>
        </div>
        <div data-panel-tipo="email" class="fila-campos" hidden>
          <div class="campo"><label for="c-mail">Correo de destino</label><input class="entrada" id="c-mail" name="email" type="email" data-campo="email" value="<?= $campo('email') ?>"></div>
          <div class="campo"><label for="c-asunto">Asunto</label><input class="entrada" id="c-asunto" name="asunto" maxlength="200" data-campo="asunto" value="<?= $campo('asunto') ?>"></div>
        </div>
        <div data-panel-tipo="texto" class="campo" hidden>
          <label for="c-texto">Texto</label>
          <textarea class="entrada" id="c-texto" name="texto" data-campo="texto" maxlength="500"><?= $campo('texto') ?></textarea>
        </div>
      </fieldset>
    </div>

    <div class="bloque">
      <h3>Diseño</h3>
      <div class="fila-campos">
        <div class="campo"><label for="color-qr">Color del código</label><input class="entrada-color" id="color-qr" name="color" type="color" value="<?= e($color) ?>" data-color-qr></div>
        <div class="campo"><label for="color-fondo">Color de fondo</label><input class="entrada-color" id="color-fondo" name="fondo" type="color" value="<?= e($fondo) ?>" data-fondo-qr></div>
      </div>
      <p class="ayuda">Usa un color oscuro sobre fondo claro para que cualquier celular lo lea.</p>
      <p class="aviso aviso-advertencia aviso-compacto" data-aviso-contraste hidden role="status"><?= icono('alerta', 16) ?><span></span></p>
      <div class="campo">
        <label for="logo">Logo al centro <?= !$plan['permite_logo'] ? '<span class="plan plan-pro">Pro</span>' : '' ?></label>
        <input class="entrada" id="logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp,image/gif" data-archivo-logo <?= !$plan['permite_logo'] ? 'disabled' : '' ?>>
        <small>PNG, JPG, WebP o GIF cuadrado, de preferencia con fondo transparente. Máximo 1 MB.</small>
        <?php if (!empty($editar['logo_ruta'])): ?>
          <label class="check texto-chico"><input type="checkbox" name="quitar_logo" value="1" data-quitar-logo> Quitar el logo actual</label>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <aside class="editor-vista caja caja-cuerpo" id="vista-crear">
    <div class="bloque">
      <h3>Vista previa</h3>
      <div class="vista-previa">
        <div class="qr-vivo" data-qr-vivo data-qr-tam="240" role="img" aria-label="Vista previa del QR"></div>
        <?php if ($plan['marca_agua']): ?><p class="marca-agua">Creado con <?= e(nombre_portal()) ?></p><?php endif; ?>
      </div>
      <p class="texto-chico texto-gris" style="word-break:break-all"><strong style="color:var(--tinta)">Contenido:</strong> <span data-contenido-salida></span></p>
    </div>
    <div class="bloque">
      <button class="boton boton-principal boton-ancho" type="submit"><?= icono('check', 16) ?> <?= $editar ? 'Guardar cambios' : 'Guardar QR' ?></button>
      <div class="fila-campos">
        <button class="boton boton-fantasma" type="button" data-descargar-png="<?= e($editar['nombre'] ?? 'mi-qr') ?>"><?= icono('descargar', 16) ?> PNG</button>
        <button class="boton boton-fantasma" type="button" <?= $plan['permite_svg_pdf'] ? 'data-descargar-svg' : 'disabled title="Disponible en Pro"' ?>><?= !$plan['permite_svg_pdf'] ? icono('candado', 15) : icono('descargar', 16) ?> SVG</button>
        <button class="boton boton-fantasma" type="button" <?= $plan['permite_svg_pdf'] ? 'data-descargar-pdf' : 'disabled title="Disponible en Pro"' ?>><?= !$plan['permite_svg_pdf'] ? icono('candado', 15) : icono('descargar', 16) ?> PDF</button>
      </div>
      <?php if ($editar && $esDinamico && !$editar['activo']): ?>
        <p class="texto-chico texto-gris">Este QR está pausado: quien lo escanee verá que no está disponible. Reanúdalo desde Mis QR.</p>
      <?php endif; ?>
    </div>
  </aside>
</form>
<?php pagina_fin('app', [CDN_QR]); ?>
