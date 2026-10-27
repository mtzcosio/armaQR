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
  <span>Un precio nuevo aplica a las contrataciones nuevas y a los pagos de Conekta (se cobran periodo por periodo). Quien ya está suscrito con Stripe conserva su precio hasta que cambie de plan. Si bajas el límite de QR dinámicos, a los clientes que lo excedan se les pausan los más nuevos.</span>
</div>

<!-- Cada formulario administra los precios, límites y funciones de un plan (auth/planes.php) -->
<div class="planes-admin">
  <?php foreach ($planes as $p):
      $suscritos = count(array_filter($usuarios, fn($u) => $u['plan'] === $p['codigo'] && $u['estatus'] === 'activo'));
      $esGratis = $p['codigo'] === 'gratis';
  ?>
  <form class="caja" method="post" action="<?= e(url('auth/planes.php')) ?>" id="plan-<?= e($p['codigo']) ?>">
    <?= csrf_campo() ?>
    <input type="hidden" name="id" value="<?= $p['id'] ?>">
    <div class="caja-cabeza">
      <div><h2><?= e($p['nombre']) ?> <?= insignia_plan($p['codigo']) ?></h2><p><?= $suscritos ?> clientes activos</p></div>
    </div>
    <div class="caja-cuerpo" style="display:grid;gap:14px">
      <div class="campo"><label for="desc-<?= $p['id'] ?>">Descripción</label><input class="entrada" id="desc-<?= $p['id'] ?>" name="descripcion" maxlength="500" value="<?= e($p['descripcion']) ?>"></div>
      <div class="fila-campos">
        <div class="campo"><label for="pm-<?= $p['id'] ?>">Precio mensual</label><input class="entrada" id="pm-<?= $p['id'] ?>" name="precio_mensual" type="number" min="0" max="999999.99" step="0.01" value="<?= $p['precio_mensual'] ?>" <?= $esGratis ? 'disabled' : '' ?>></div>
        <div class="campo"><label for="pa-<?= $p['id'] ?>">Precio anual</label><input class="entrada" id="pa-<?= $p['id'] ?>" name="precio_anual" type="number" min="0" max="999999.99" step="0.01" value="<?= $p['precio_anual'] ?>" <?= $esGratis ? 'disabled' : '' ?>></div>
      </div>
      <?php if ($esGratis): ?><small class="ayuda">El plan Gratis siempre cuesta $0.</small><?php endif; ?>
      <div class="fila-campos">
        <div class="campo"><label for="qe-<?= $p['id'] ?>">QR estáticos al mes</label><input class="entrada" id="qe-<?= $p['id'] ?>" name="max_qr_estaticos_mes" type="number" min="0" max="<?= PLAN_MAXIMO ?>" step="1" value="<?= $p['max_qr_estaticos_mes'] ?>" placeholder="Ilimitados"></div>
        <div class="campo"><label for="qd-<?= $p['id'] ?>">QR dinámicos activos</label><input class="entrada" id="qd-<?= $p['id'] ?>" name="max_qr_dinamicos" type="number" min="0" max="<?= PLAN_MAXIMO ?>" step="1" value="<?= $p['max_qr_dinamicos'] ?>" placeholder="Ilimitados"></div>
      </div>
      <div class="fila-campos">
        <div class="campo"><label for="ret-<?= $p['id'] ?>">Días de estadísticas</label><input class="entrada" id="ret-<?= $p['id'] ?>" name="dias_retencion_escaneos" type="number" min="7" max="<?= PLAN_MAXIMO ?>" step="1" value="<?= $p['dias_retencion_escaneos'] ?>" placeholder="Sin límite"></div>
        <div class="campo"><label for="api-<?= $p['id'] ?>">Claves API</label><input class="entrada" id="api-<?= $p['id'] ?>" name="max_claves_api" type="number" min="0" max="1000" step="1" value="<?= $p['max_claves_api'] ?>" placeholder="0"><small class="ayuda">La API aún no está disponible; no se anuncia.</small></div>
      </div>
      <small class="ayuda">Deja vacíos los QR o los días para que sean ilimitados. Con 0 QR dinámicos el plan no los incluye. Los días de estadísticas limitan los periodos que el cliente puede consultar (7, 30 o 90).</small>
      <div>
        <?php foreach ($funciones as $campo => $texto): ?>
          <label class="interruptor"><span><?= e($texto) ?></span><input type="checkbox" name="<?= $campo ?>" value="1" <?= $p[$campo] ? 'checked' : '' ?>></label>
        <?php endforeach; ?>
        <label class="interruptor"><span>Visible en la página de precios</span><input type="checkbox" name="es_publico" value="1" <?= $p['es_publico'] ? 'checked' : '' ?>></label>
      </div>
      <button class="boton boton-tinta" type="submit">Guardar cambios</button>
    </div>
  </form>
  <?php endforeach; ?>
</div>
<?php pagina_fin('admin'); ?>
