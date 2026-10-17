<?php
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/pagos.php';

$usuario = datos_usuario_actual();
try {
    pagos_vencer_suscripciones((int) $usuario['id']); // limpia un periodo vencido sin esperar al cron
} catch (Throwable $exception) {
    error_log('No se pudieron vencer suscripciones: ' . $exception->getMessage());
}
$plan    = $usuario['plan_datos'];
$planes  = datos_planes(true);
$esPago  = $plan['codigo'] !== 'gratis';
$qrs     = datos_qrs_de($usuario['id']);
$pagos   = datos_pagos($usuario['id']);

$dinamicos = count(array_filter($qrs, fn($q) => $q['tipo'] === 'dinamico' && $q['activo']));
$estaticos = count(array_filter($qrs, fn($q) => $q['tipo'] === 'estatico' && str_starts_with($q['fecha_creacion'], date('Y-m'))));
$clavesApi = datos_claves_api_activas($usuario['id']);
$finPeriodo = $usuario['periodo_fin'] ?? null;
$tarjeta = null;
foreach ($pagos as $p) {
    if ($p['ultimos_digitos']) { $tarjeta = $p['ultimos_digitos']; break; }
}

// Pasarelas disponibles y cómo paga hoy el usuario.
$stripeOn  = stripe_activo();
$conektaOn = conekta_activo();
$conStripe = $esPago && $usuario['pasarela'] === 'stripe' && !empty($usuario['suscripcion_externa']);
$conConekta = $esPago && $usuario['pasarela'] === 'conekta';
$cancelada = $conStripe && !empty($usuario['cancelar_al_final']);

$avisosPago = [
    'exito'     => ['ok', 'Recibimos tu pago. Tu plan se activa en cuanto la pasarela lo confirme: con tarjeta tarda unos segundos; con OXXO o SPEI, cuando hagas el pago.'],
    'cancelado' => ['info', 'No se completó el pago. Puedes intentarlo de nuevo cuando quieras.'],
];
$avisoPago = $avisosPago[$_GET['pago'] ?? ''] ?? null;

/** Formulario POST a auth/pago.php con los campos ocultos dados. */
$formPago = fn(string $accion, string $contenido, array $campos = []) => '<form method="post" action="' . e(url('auth/pago.php')) . '" class="en-linea">'
    . csrf_campo() . '<input type="hidden" name="accion" value="' . e($accion) . '">'
    . implode('', array_map(fn($k, $v) => '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">', array_keys($campos), $campos))
    . $contenido . '</form>';

function medidor_uso(string $texto, int $usado, ?int $limite): string
{
    if ($limite === null) {
        return '<div class="barra-h"><div class="barra-h-cabeza"><span>' . e($texto) . '</span><span>' . numero($usado) . ' · ilimitados</span></div><div class="medidor"><i style="width:4%"></i></div></div>';
    }
    if ($limite === 0) {
        return '<div class="barra-h"><div class="barra-h-cabeza"><span>' . e($texto) . '</span><span>No incluido</span></div><div class="medidor"><i style="width:0"></i></div></div>';
    }
    $pct = min(100, round($usado / $limite * 100));
    return '<div class="barra-h"><div class="barra-h-cabeza"><span>' . e($texto) . '</span><span>' . numero($usado) . ' de ' . numero($limite) . '</span></div>'
        . '<div class="medidor ' . ($pct >= 90 ? 'lleno' : 'rosa') . '"><i style="width:' . $pct . '%"></i></div></div>';
}

/** Botones para pagar un plan: periodicidad y pasarela. */
$botonesPago = function (array $p, string $textoStripe, bool $soloConekta = false) use ($formPago, $stripeOn, $conektaOn, $conStripe): string {
    $opciones = '<option value="mensual">Mensual · ' . e(dinero($p['precio_mensual'], false)) . ' al mes</option>';
    if ($p['precio_anual'] > 0) {
        $opciones .= '<option value="anual">Anual · ' . e(dinero($p['precio_anual'], false)) . ' al año</option>';
    }
    $html = '<label class="oculto-visual" for="per-' . e($p['codigo']) . '">Periodicidad</label>'
        . '<select class="entrada" id="per-' . e($p['codigo']) . '" name="periodicidad">' . $opciones . '</select>';
    $botones = '';
    if ($stripeOn && !$soloConekta) {
        $botones .= '<button class="boton boton-principal" type="submit" name="pasarela" value="stripe">' . icono('tarjeta', 17) . ' ' . e($textoStripe) . '</button>';
    }
    if ($conektaOn && !$conStripe) {
        $botones .= '<button class="boton boton-fantasma" type="submit" name="pasarela" value="conekta">Pago único · tarjeta, OXXO o SPEI</button>';
    }
    if ($botones === '') {
        return '<p class="texto-gris texto-chico">Los pagos en línea aún no están disponibles.</p>';
    }
    return $formPago('pagar', '<div style="display:grid;gap:8px">' . $html . $botones . '</div>', ['plan' => $p['codigo']]);
};

pagina_inicio('Plan y pagos', 'app', 'plan');
?>
<div class="encabezado">
  <div>
    <h1>Plan y pagos</h1>
    <p>Tu suscripción, lo que llevas usado este mes y tus recibos.</p>
  </div>
</div>

<?php if ($avisoPago): ?><?= aviso($avisoPago[0], $avisoPago[1]) ?><?php endif; ?>
<?php if ($conStripe && $usuario['estatus_suscripcion'] === 'pago_pendiente'): ?>
  <?= aviso('error', 'No pudimos cobrar tu última renovación. Actualiza tu tarjeta para no perder tu plan.') ?>
<?php endif; ?>

<div class="rejilla-2">
  <section class="caja">
    <div class="caja-cabeza">
      <div>
        <h2>Plan <?= e($plan['nombre']) ?> <?= insignia_plan($plan['codigo']) ?></h2>
        <p>
          <?php if ($esPago): ?>
            <?= $usuario['periodicidad'] === 'anual' ? dinero($plan['precio_anual'], false) . ' al año' : dinero($plan['precio_mensual'], false) . ' al mes' ?>
            <?php if ($finPeriodo): ?>· <?= $conStripe && !$cancelada ? 'Próximo cobro' : 'Termina' ?> el <?= e(fecha_corta($finPeriodo)) ?><?php endif; ?>
          <?php else: ?>
            Gratis para siempre
          <?php endif; ?>
        </p>
      </div>
      <?php if ($conStripe && !$cancelada): ?>
        <?= $formPago('cancelar', '<button class="boton boton-peligro boton-chico" type="submit"
          data-confirmar="¿Cancelar tu suscripción?" data-detalle="Conservas el plan ' . e($plan['nombre']) . ' hasta el fin del periodo pagado' . ($finPeriodo ? ' (' . e(fecha_corta($finPeriodo)) . ')' : '') . '. Después tus QR dinámicos se pausan; los estáticos siguen funcionando."
          data-accion="Cancelar suscripción" data-peligro>Cancelar suscripción</button>') ?>
      <?php elseif ($cancelada): ?>
        <?= $formPago('reanudar', '<button class="boton boton-fantasma boton-chico" type="submit">Reanudar suscripción</button>') ?>
      <?php endif; ?>
    </div>
    <div class="caja-cuerpo uso-plan">
      <?= medidor_uso('QR dinámicos activos', $dinamicos, $plan['max_qr_dinamicos']) ?>
      <?= medidor_uso('QR estáticos este mes', $estaticos, $plan['max_qr_estaticos_mes']) ?>
      <?= medidor_uso('Claves API', $clavesApi, $plan['max_claves_api']) ?>
    </div>
  </section>

  <section class="caja">
    <div class="caja-cabeza"><h2>Forma de pago</h2></div>
    <div class="caja-cuerpo" style="display:grid;gap:14px">
      <?php if ($conStripe): ?>
        <div class="ficha"><?= icono('tarjeta', 28) ?><div><strong><?= $tarjeta ? 'Tarjeta terminación ' . e($tarjeta) : 'Tarjeta registrada en Stripe' ?></strong><p class="texto-gris texto-chico">Cobro automático cada <?= $usuario['periodicidad'] === 'anual' ? 'año' : 'mes' ?></p></div></div>
        <?= $formPago('portal', '<button class="boton boton-fantasma" type="submit">' . icono('externo', 17) . ' Cambiar tarjeta o ver facturas</button>') ?>
      <?php elseif ($conConekta): ?>
        <div class="ficha"><?= icono('tarjeta', 28) ?><div><strong>Pago único con Conekta</strong><p class="texto-gris texto-chico">No se renueva solo<?= $finPeriodo ? '. Para seguir después del ' . e(fecha_corta($finPeriodo)) . ', paga otro periodo' : '' ?>.</p></div></div>
      <?php elseif ($esPago): ?>
        <p class="texto-gris">Tu plan lo asignó el equipo de soporte; no tiene cobros automáticos.</p>
      <?php else: ?>
        <p class="texto-gris">Aún no tienes forma de pago. La agregas al elegir un plan: tarjeta con cobro automático, o pago único con tarjeta, OXXO o SPEI.</p>
      <?php endif; ?>
    </div>
  </section>
</div>

<section class="caja">
  <div class="caja-cabeza">
    <div><h2><?= $esPago ? 'Cambiar de plan' : 'Elige un plan' ?></h2>
      <p><?= $conStripe
          ? 'Al cambiar de plan pagas solo la diferencia proporcional del periodo.'
          : 'Con tarjeta y cobro automático, después pagas solo la diferencia al cambiar de plan. Con pago único pagas un periodo completo y no se renueva solo.' ?></p></div>
  </div>
  <div class="caja-cuerpo planes-cambio">
    <?php foreach ($planes as $p): $actual = $p['codigo'] === $plan['codigo']; ?>
      <div class="plan-opcion <?= $actual ? 'actual' : '' ?>">
        <h3><?= e($p['nombre']) ?> <?= $actual ? '<span class="estatus estatus-ok">Tu plan</span>' : '' ?></h3>
        <p class="precio-monto"><?= dinero($p['precio_mensual'], false) ?> <small>al mes</small></p>
        <ul class="lista-check"><?php foreach ($p['caracteristicas'] as $c): ?><li><?= icono('check', 16) ?> <?= e($c) ?></li><?php endforeach; ?></ul>
        <?php if ($p['codigo'] === 'gratis'): ?>
          <?php if (!$actual && $conStripe && !$cancelada): ?>
            <?= $formPago('cancelar', '<button class="boton boton-fantasma" type="submit" data-confirmar="¿Bajar al plan Gratis?"
              data-detalle="Se cancela tu suscripción y conservas tu plan actual hasta el fin del periodo pagado. Después tus QR dinámicos se pausan." data-accion="Bajar a Gratis" data-peligro>Bajar a Gratis</button>') ?>
          <?php elseif (!$actual): ?>
            <p class="texto-gris texto-chico">Al terminar tu periodo pagado regresas a este plan.</p>
          <?php endif; ?>
        <?php elseif ($actual && $conConekta): ?>
          <?= $botonesPago($p, '', true) ?>
          <p class="texto-gris texto-chico">Pagar otro periodo lo suma al final del actual.</p>
        <?php elseif (!$actual): ?>
          <?= $botonesPago($p, $conStripe ? 'Cambiar a ' . $p['nombre'] : 'Tarjeta · cobro automático') ?>
          <?php if ($conConekta): ?><p class="texto-gris texto-chico">El plan nuevo empieza al pagar y reemplaza al actual.</p><?php endif; ?>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<div class="rejilla-2">
  <section class="caja">
    <div class="caja-cabeza"><h2>Recibos</h2></div>
    <?php if ($pagos): ?>
      <div class="tabla-envoltura">
        <table class="tabla">
          <thead><tr><th>Fecha</th><th>Concepto</th><th class="num">Monto</th><th>Estatus</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($pagos as $p): ?>
              <tr>
                <td class="sin-salto"><?= e(fecha_corta($p['fecha_pago'])) ?></td>
                <td><?= e($p['concepto']) ?></td>
                <td class="num"><?= dinero($p['monto']) ?></td>
                <td><?= insignia_estatus($p['estatus'] === 'pendiente' ? 'por_pagar' : $p['estatus']) ?></td>
                <td class="acciones"><?php if ($p['estatus'] === 'pagado'): ?><button class="boton-icono" type="button" title="Descargar factura" aria-label="Descargar factura" data-simular-accion="Descargaste la factura <?= $p['id'] ?>"><?= icono('descargar') ?></button><?php endif; ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="vacio"><strong>Sin recibos todavía</strong><span>Aquí aparecerán tus pagos y facturas.</span></div>
    <?php endif; ?>
  </section>

  <section class="caja">
    <div class="caja-cabeza"><div><h2>Datos de facturación</h2><p>Se usan en cada factura CFDI</p></div></div>
    <form class="caja-cuerpo" style="display:grid;gap:14px" data-simular="Guardaste tus datos fiscales">
      <div class="campo"><label for="rfc">RFC</label><input class="entrada" id="rfc" name="rfc" maxlength="13" style="text-transform:uppercase"></div>
      <div class="campo"><label for="razon">Razón social</label><input class="entrada" id="razon" name="razon_social"></div>
      <div class="fila-campos">
        <div class="campo"><label for="cp">Código postal</label><input class="entrada" id="cp" name="cp" inputmode="numeric" maxlength="5"></div>
        <div class="campo"><label for="regimen">Régimen fiscal</label>
          <select class="entrada" id="regimen" name="regimen"><option>601 · General de Ley</option><option>612 · Personas Físicas con Actividad Empresarial</option><option>626 · RESICO</option></select>
        </div>
      </div>
      <button class="boton boton-tinta" type="submit">Guardar datos fiscales</button>
    </form>
  </section>
</div>
<?php pagina_fin('app'); ?>
