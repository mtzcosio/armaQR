<?php
require_once __DIR__ . '/../includes/layout.php';

$usuario   = datos_usuario_actual();
$plan      = $usuario['plan_datos'];
$esPago    = $plan['permite_analitica'];
$dinamicos = array_values(array_filter(datos_qrs_de($usuario['id']), fn($q) => $q['tipo'] === 'dinamico'));

$qrId   = (int) ($_GET['qr'] ?? 0);
$qrSel  = null;
foreach ($dinamicos as $q) if ($q['id'] === $qrId) $qrSel = $q;
$qrFiltro = $qrSel['id'] ?? null; // solo QR del propio usuario

// Periodos que deja ver el plan (dias_retencion_escaneos); el anterior solo si también cabe.
$retencion = $plan['dias_retencion_escaneos'];
$periodos  = array_filter([7 => '7 días', 30 => '30 días', 90 => '90 días'], fn($d) => datos_periodo_visible($retencion, $d), ARRAY_FILTER_USE_KEY) ?: [7 => '7 días'];
$pedido    = (int) ($_GET['dias'] ?? 30);
$dias      = isset($periodos[$pedido]) ? $pedido : (isset($periodos[30]) ? 30 : max(array_keys($periodos)));
$conAnterior = datos_periodo_visible($retencion, $dias * 2);

if ($esPago) {
    $serie    = datos_serie_escaneos($dias, $usuario['id'], $qrFiltro);
    $anterior = $conAnterior ? datos_serie_escaneos($dias, $usuario['id'], $qrFiltro, $dias) : [];
    $unicos   = datos_escaneos_unicos($dias, $usuario['id'], $qrFiltro);
    $des      = datos_desgloses($dias, $usuario['id'], $qrFiltro);
} else {
    // Sin analítica: la vista previa difuminada usa datos inventados, nunca los del usuario.
    $serie = datos_rango($dias)[2];
    $i = 0;
    foreach ($serie as $fecha => $n) {
        $serie[$fecha] = 18 + (int) round(9 * sin($i / 3)) + ($i++ % 7);
    }
    $anterior = array_map(fn($n) => max(0, $n - 4), $serie);
    $unicos   = (int) round(array_sum($serie) * 0.7);
    $des      = [
        'dispositivo' => ['Móvil' => 82, 'Tablet' => 6, 'Escritorio' => 12],
        'sistema'     => ['Android' => 58, 'iOS' => 38, 'Windows' => 4],
        'ciudades'    => ['Ciudad de México' => 120, 'Guadalajara' => 64, 'Monterrey' => 41, 'Puebla' => 18],
        'horas'       => array_map(fn($h) => (int) round(12 * exp(-(($h - 14) ** 2) / 18)), range(0, 23)),
    ];
}
$total  = array_sum($serie);
$cambio = datos_variacion($total, array_sum($anterior));
$mejor  = $total > 0 ? array_search(max($serie), $serie) : null;

$etiquetas = array_map(fn($f) => date('j/n', strtotime($f)), array_keys($serie));
$grafLinea = ['tipo' => 'line', 'etiquetas' => $etiquetas, 'series' => array_values(array_filter([
    ['nombre' => 'Escaneos', 'datos' => array_values($serie)],
    $conAnterior ? ['nombre' => 'Periodo anterior', 'datos' => array_values($anterior)] : null,
]))];
$grafDisp  = ['tipo' => 'doughnut', 'etiquetas' => array_keys($des['dispositivo']), 'series' => [['nombre' => 'Dispositivo', 'datos' => array_values($des['dispositivo'])]]];
$grafHoras = ['tipo' => 'bar', 'etiquetas' => array_map(fn($h) => $h . 'h', range(0, 23)), 'series' => [['nombre' => 'Escaneos', 'datos' => $des['horas']]]];
$maxCiudad = max([1, ...array_values($des['ciudades'])]);

$parametros = fn(array $extra) => array_filter(array_merge(['qr' => $qrId ?: null, 'dias' => $dias], $extra));

pagina_inicio('Estadísticas', 'app', 'estadisticas');
?>
<div class="encabezado">
  <div>
    <h1>Estadísticas</h1>
    <p><?= $qrSel ? 'Escaneos de “' . e($qrSel['nombre']) . '”' : 'Escaneos de todos tus QR dinámicos' ?></p>
  </div>
  <div class="encabezado-acciones">
    <form method="get" style="display:flex;gap:8px">
      <input type="hidden" name="dias" value="<?= $dias ?>">
      <label class="oculto-visual" for="qr-sel">Código QR</label>
      <select class="entrada" id="qr-sel" name="qr" onchange="this.form.submit()" style="min-width:220px">
        <option value="">Todos mis QR dinámicos</option>
        <?php foreach ($dinamicos as $q): ?>
          <option value="<?= $q['id'] ?>" <?= $q['id'] === $qrId ? 'selected' : '' ?>><?= e($q['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
    <nav class="segmentado" aria-label="Periodo">
      <?php foreach ($periodos as $d => $txt): ?>
        <a href="<?= url_app('estadisticas.php', $parametros(['dias' => $d])) ?>" class="<?= $d === $dias ? 'activo' : '' ?>"><?= $txt ?></a>
      <?php endforeach; ?>
    </nav>
  </div>
</div>

<div class="<?= $esPago ? '' : 'bloqueado' ?>">
  <?php if (!$esPago): ?>
    <div class="capa-mejora">
      <div class="caja">
        <?= icono('grafica', 28) ?>
        <h2>Descubre quién escanea tus QR</h2>
        <p>Con el plan Pro ves escaneos por día, ciudad, hora y tipo de celular de cada código dinámico.</p>
        <a class="boton boton-principal" href="<?= url_app('plan.php') ?>">Pasar a Pro</a>
      </div>
    </div>
  <?php endif; ?>

  <div class="<?= $esPago ? '' : 'contenido-bloqueado' ?>" style="display:grid;gap:22px" <?= $esPago ? '' : 'aria-hidden="true"' ?>>
    <section class="cifras" aria-label="Resumen del periodo">
      <div class="cifra destaca"><span>Escaneos</span><strong><?= numero($total) ?></strong><?php if ($conAnterior): ?><small class="<?= $cambio >= 0 ? 'sube' : 'baja' ?>"><?= ($cambio >= 0 ? '+' : '') . $cambio ?>% vs. periodo anterior</small><?php else: ?><small>Últimos <?= $dias ?> días</small><?php endif; ?></div>
      <div class="cifra"><span>Personas distintas</span><strong><?= numero($unicos) ?></strong><small>Aproximado por IP anónima</small></div>
      <div class="cifra"><span>Promedio diario</span><strong><?= numero((int) round($total / $dias)) ?></strong><small>Últimos <?= $dias ?> días</small></div>
      <div class="cifra"><span>Mejor día</span><strong><?= $mejor ? e(fecha_corta($mejor)) : '—' ?></strong><small><?= $mejor ? numero($serie[$mejor]) . ' escaneos' : 'Sin escaneos en el periodo' ?></small></div>
    </section>

    <section class="caja">
      <div class="caja-cabeza"><div><h2>Escaneos por día</h2><p><?= $conAnterior ? 'Comparado con los ' . $dias . ' días anteriores' : 'Últimos ' . $dias . ' días' ?></p></div></div>
      <div class="caja-cuerpo"><div class="grafica"><canvas data-grafica='<?= e(json_encode($grafLinea)) ?>' aria-label="Gráfica de escaneos por día" role="img"></canvas></div></div>
    </section>

    <div class="rejilla-mitad">
      <section class="caja">
        <div class="caja-cabeza"><h2>¿Desde dónde lo escanean?</h2></div>
        <div class="caja-cuerpo barras-h">
          <?php if (!$des['ciudades']): ?><p class="texto-gris">Aún no hay escaneos en este periodo.</p><?php endif; ?>
          <?php foreach ($des['ciudades'] as $ciudad => $n): ?>
            <div class="barra-h">
              <div class="barra-h-cabeza"><span><?= e($ciudad) ?></span><span><?= numero($n) ?></span></div>
              <div class="medidor <?= $n === $maxCiudad ? 'rosa' : '' ?>"><i style="width:<?= round($n / $maxCiudad * 100) ?>%"></i></div>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
      <section class="caja">
        <div class="caja-cabeza"><h2>Dispositivo</h2></div>
        <div class="caja-cuerpo">
          <div class="grafica grafica-chica"><canvas data-grafica='<?= e(json_encode($grafDisp)) ?>' aria-label="Escaneos por dispositivo" role="img"></canvas></div>
          <?php if ($des['sistema']): ?><p class="texto-chico texto-gris mt"><?= e(implode(' · ', array_map(fn($so, $pct) => "$so $pct%", array_keys($des['sistema']), $des['sistema']))) ?></p><?php endif; ?>
        </div>
      </section>
    </div>

    <section class="caja">
      <div class="caja-cabeza"><div><h2>Hora del día</h2><p>Úsalo para saber cuándo lanzar una promoción</p></div></div>
      <div class="caja-cuerpo"><div class="grafica grafica-chica"><canvas data-grafica='<?= e(json_encode($grafHoras)) ?>' aria-label="Escaneos por hora" role="img"></canvas></div></div>
    </section>

    <?php if ($esPago && !$qrSel && $dinamicos): ?>
    <section class="caja">
      <div class="caja-cabeza"><h2>Tus QR con más escaneos</h2></div>
      <div class="tabla-envoltura">
        <table class="tabla">
          <thead><tr><th>Código</th><th>Destino</th><th class="num">Escaneos</th><th>Último escaneo</th></tr></thead>
          <tbody>
            <?php usort($dinamicos, fn($a, $b) => $b['total_escaneos'] <=> $a['total_escaneos']);
            foreach ($dinamicos as $q): ?>
            <tr>
              <td><a class="enlace" href="<?= url_app('estadisticas.php', ['qr' => $q['id'], 'dias' => $dias]) ?>"><?= e($q['nombre']) ?></a></td>
              <td class="texto-gris"><?= e($q['destino']) ?></td>
              <td class="num"><?= numero($q['total_escaneos']) ?></td>
              <td class="texto-gris"><?= $q['ultimo_escaneo'] ? e(fecha_corta($q['ultimo_escaneo'])) : '—' ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
    <?php endif; ?>
  </div>
</div>
<?php pagina_fin('app', [CDN_CHART]); ?>
