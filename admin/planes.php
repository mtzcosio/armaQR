<?php

// Carga el layout y los helpers comunes del panel administrativo.
require_once __DIR__ . '/../includes/layout.php';
App\Core\Auth::requireAdmin();

// Obtén planes y usuarios para calcular el número de clientes activos y mostrar sus límites.
$planes = datos_planes();
$usuarios = datos_usuarios();

// Lista las funciones disponibles que se pueden habilitar en cada plan.
$funciones = [
    'permite_logo'      => 'Logo al centro del QR',
    'permite_svg_pdf'   => 'Descarga en SVG y PDF',
    'permite_analitica' => 'Estadísticas de escaneo',
    'permite_masivo'    => 'Generación masiva por CSV',
    'marca_agua'        => 'Marca de agua en descargas',
];

// Muestra el título, la descripción y marca el menú "Planes" como activo.
pagina_inicio('Planes', 'admin', 'planes');
?>
<div class="encabezado">
  <div>
    <h1>Planes</h1>
    <p>Precios y límites. Los cambios se reflejan en la página de precios y en el panel de cada cliente.</p>
  </div>
</div>

<div class="aviso aviso-info">
  <?= icono('alerta', 18) ?>
  <span>Si cambias un precio, actualízalo también en la pasarela de pago. Los clientes actuales conservan su precio hasta que renueven.</span>
</div>

<!-- Cada formulario administra los precios, límites y funciones de un plan -->
<div class="planes-admin">
  <?php foreach ($planes as $p):
      $suscritos = count(array_filter($usuarios, fn($u) => $u['plan'] === $p['codigo'] && $u['estatus'] !== 'pendiente'));
  ?>
  <!-- POST real: UPDATE planes SET ... WHERE id = :id (con usuario_modificacion) -->
  <form class="caja" data-simular="Guardaste el plan <?= e($p['nombre']) ?>">
    <div class="caja-cabeza">
      <div><h2><?= e($p['nombre']) ?> <?= insignia_plan($p['codigo']) ?></h2><p><?= $suscritos ?> clientes activos</p></div>
    </div>
    <div class="caja-cuerpo" style="display:grid;gap:14px">
      <div class="campo"><label for="desc-<?= $p['id'] ?>">Descripción</label><input class="entrada" id="desc-<?= $p['id'] ?>" name="descripcion" value="<?= e($p['descripcion']) ?>"></div>
      <div class="fila-campos">
        <div class="campo"><label for="pm-<?= $p['id'] ?>">Precio mensual</label><input class="entrada" id="pm-<?= $p['id'] ?>" name="precio_mensual" type="number" min="0" step="1" value="<?= $p['precio_mensual'] ?>"></div>
        <div class="campo"><label for="pa-<?= $p['id'] ?>">Precio anual</label><input class="entrada" id="pa-<?= $p['id'] ?>" name="precio_anual" type="number" min="0" step="1" value="<?= $p['precio_anual'] ?>"></div>
      </div>
      <div class="fila-campos">
        <div class="campo"><label for="qe-<?= $p['id'] ?>">QR estáticos al mes</label><input class="entrada" id="qe-<?= $p['id'] ?>" name="max_qr_estaticos_mes" type="number" min="0" value="<?= $p['max_qr_estaticos_mes'] ?>" placeholder="Ilimitados"></div>
        <div class="campo"><label for="qd-<?= $p['id'] ?>">QR dinámicos</label><input class="entrada" id="qd-<?= $p['id'] ?>" name="max_qr_dinamicos" type="number" min="0" value="<?= $p['max_qr_dinamicos'] ?>" placeholder="Ilimitados"></div>
      </div>
      <div class="fila-campos">
        <div class="campo"><label for="ret-<?= $p['id'] ?>">Días de estadísticas</label><input class="entrada" id="ret-<?= $p['id'] ?>" name="dias_retencion_escaneos" type="number" min="0" value="<?= $p['dias_retencion_escaneos'] ?>" placeholder="Sin límite"></div>
        <div class="campo"><label for="api-<?= $p['id'] ?>">Claves API</label><input class="entrada" id="api-<?= $p['id'] ?>" name="max_claves_api" type="number" min="0" value="<?= $p['max_claves_api'] ?>"></div>
      </div>
      <small class="ayuda">Deja un límite vacío para que sea ilimitado.</small>
      <div>
        <?php foreach ($funciones as $campo => $texto): ?>
          <label class="interruptor"><span><?= e($texto) ?></span><input type="checkbox" name="<?= $campo ?>" <?= $p[$campo] ? 'checked' : '' ?>></label>
        <?php endforeach; ?>
        <label class="interruptor"><span>Visible en la página de precios</span><input type="checkbox" name="es_publico" <?= $p['es_publico'] ? 'checked' : '' ?>></label>
      </div>
      <button class="boton boton-tinta" type="submit">Guardar cambios</button>
    </div>
  </form>
  <?php endforeach; ?>
</div>
<?php pagina_fin('admin'); ?>
