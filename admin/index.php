<?php

// Carga el layout común de la aplicación y los helpers de HTML, iconos y URLs.
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/geoip.php';
App\Core\Auth::requireAdmin();

// Datos de negocio desde la base de datos (solo cuentas de clientes).
$r        = datos_resumen_admin();
$usuarios = array_values(array_filter(datos_usuarios(), fn($u) => $u['rol'] === 'cliente'));
$planes   = datos_planes();

// Series de los últimos 30 días.
$registros = datos_serie_registros(30);
$escaneos  = datos_serie_escaneos(30);
$etiq      = array_map(fn($f) => date('j/n', strtotime($f)), array_keys($registros));

// Ingresos cobrados de los últimos seis meses.
$mesesNombres = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
$ingresos = datos_ingresos_mensuales(6);
$meses = [];
foreach ($ingresos as $ym => $monto) {
    $meses[$mesesNombres[(int) substr($ym, 5, 2) - 1]] = $monto;
}
$ingresosValores = array_values($ingresos);
$cambioIngresos  = datos_variacion($ingresosValores[5] ?? 0, $ingresosValores[4] ?? 0);

// Configura las gráficas: barras para registros y líneas para ingresos y escaneos.
$grafRegistros = ['tipo' => 'bar', 'etiquetas' => $etiq, 'series' => [['nombre' => 'Registros', 'datos' => array_values($registros)]]];
$grafIngresos  = ['tipo' => 'line', 'moneda' => true, 'etiquetas' => array_keys($meses), 'series' => [['nombre' => 'Ingresos cobrados', 'datos' => array_values($meses)]]];
$grafEscaneos  = ['tipo' => 'line', 'etiquetas' => $etiq, 'series' => [['nombre' => 'Escaneos', 'datos' => array_values($escaneos)]]];

// Cuenta cómo muchos usuarios activos hay en cada plan y calcula la conversión a plan de pago.
$porPlan = array_fill_keys(array_keys($planes), 0);
foreach ($usuarios as $u) if ($u['estatus'] !== 'pendiente' && isset($porPlan[$u['plan']])) $porPlan[$u['plan']]++;
$activosTotal = array_sum($porPlan);
$conversion = round(($activosTotal - ($porPlan['gratis'] ?? 0)) / max(1, $activosTotal) * 100);

// Ordena los usuarios más recientemente registrados y muestra solo los primeros seis.
$recientes = $usuarios;
usort($recientes, fn($a, $b) => strcmp($b['fecha_registro'], $a['fecha_registro']));
$recientes = array_slice($recientes, 0, 6);

// Abre el encabezado del panel administrativo y marca la sección "Resumen" como activa.
pagina_inicio('Resumen', 'admin', 'resumen');
?>
<!-- Encabezado de la página: título, fecha y acceso a la lista de usuarios -->
<div class="encabezado">
  <div>
    <h1>Resumen</h1>
    <p>Así va <?= e(nombre_portal()) ?> hoy, <?= e(fecha_corta(date('Y-m-d'))) ?>.</p>
  </div>
  <div class="encabezado-acciones">
    <a class="boton boton-fantasma" href="<?= url('admin/usuarios.php') ?>"><?= icono('usuarios', 17) ?> Ver usuarios</a>
  </div>
</div>

<?php if (!geoip_disponible()): ?>
  <?= aviso('info', 'Los escaneos se están guardando sin país ni ciudad: falta la base de ubicaciones en este servidor. Corre «php database/actualizar_geoip.php» (y prográmalo cada mes con cron) o sube database/geoip/ciudades.mmdb.') ?>
<?php endif; ?>

<!-- Tarjetas de resumen: muestran los ingresos, usuarios, conversión y escaneos principales -->
<section class="cifras" aria-label="Indicadores principales">
  <div class="cifra destaca"><span>Ingreso recurrente mensual</span><strong><?= dinero($r['mrr'], false) ?></strong><small><?= $r['de_pago'] ?> suscripciones de pago</small></div>
  <div class="cifra"><span>Cobrado este mes</span><strong><?= dinero($r['ingresos_mes'], false) ?></strong><small class="<?= $cambioIngresos >= 0 ? 'sube' : 'baja' ?>"><?= ($cambioIngresos >= 0 ? '+' : '') . $cambioIngresos ?>% vs. mes anterior</small></div>
  <div class="cifra"><span>Usuarios registrados</span><strong><?= numero($r['total']) ?></strong><small><?= $r['de_pago'] ?> de pago · <?= $r['gratis'] ?> gratis</small></div>
  <div class="cifra"><span>Conversión a pago</span><strong><?= $conversion ?>%</strong><small>de usuarios verificados</small></div>
  <div class="cifra"><span>Escaneos totales</span><strong><?= numero($r['escaneos_total']) ?></strong><small><?= numero($r['qr_total']) ?> QR creados</small></div>
</section>

<!-- Alertas de negocio: enlaza con pagos y usuarios que necesitan revisión -->
<?php if ($r['pagos_fallidos'] || $r['sin_verificar'] || $r['suspendidos']): ?>
<section class="caja">
  <div class="caja-cabeza"><div><h2>Requiere atención</h2><p>Casos que pueden convertirse en ingresos perdidos</p></div></div>
  <div class="caja-cuerpo acciones-lista">
    <?php if ($r['pagos_fallidos']): ?>
      <a class="boton boton-fantasma" href="<?= url('admin/pagos.php?filtro=fallido') ?>"><?= icono('alerta', 17) ?> <?= $r['pagos_fallidos'] ?> pagos rechazados este mes. Revisa y contacta al cliente.</a>
    <?php endif; ?>
    <?php if ($r['sin_verificar']): ?>
      <a class="boton boton-fantasma" href="<?= url('admin/usuarios.php?filtro=sin-verificar') ?>"><?= icono('correo', 17) ?> <?= $r['sin_verificar'] ?> <?= $r['sin_verificar'] === 1 ? 'cuenta no ha' : 'cuentas no han' ?> confirmado su correo.</a>
    <?php endif; ?>
    <?php if ($r['suspendidos']): ?>
      <a class="boton boton-fantasma" href="<?= url('admin/usuarios.php?filtro=suspendido') ?>"><?= icono('bloquear', 17) ?> <?= $r['suspendidos'] ?> <?= $r['suspendidos'] === 1 ? 'cuenta suspendida' : 'cuentas suspendidas' ?>.</a>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<!-- Primera fila de gráficas: ingresos y nuevos registros -->
<div class="rejilla-mitad">
  <section class="caja">
    <div class="caja-cabeza"><div><h2>Ingresos cobrados</h2><p>Últimos 6 meses</p></div></div>
    <div class="caja-cuerpo"><div class="grafica grafica-chica"><canvas data-grafica='<?= e(json_encode($grafIngresos)) ?>' role="img" aria-label="Ingresos cobrados por mes"></canvas></div></div>
  </section>
  <section class="caja">
    <div class="caja-cabeza"><div><h2>Registros nuevos</h2><p>Últimos 30 días · <?= array_sum($registros) ?> en total</p></div></div>
    <div class="caja-cuerpo"><div class="grafica grafica-chica"><canvas data-grafica='<?= e(json_encode($grafRegistros)) ?>' role="img" aria-label="Registros por día"></canvas></div></div>
  </section>
</div>

<!-- Segunda fila: tabla de usuarios y distribución de planes -->
<div class="rejilla-2">
  <section class="caja">
    <div class="caja-cabeza">
      <h2>Últimos registros</h2>
      <a class="enlace texto-chico" href="<?= url('admin/usuarios.php') ?>">Ver todos</a>
    </div>
    <div class="tabla-envoltura">
      <table class="tabla">
        <thead><tr><th>Usuario</th><th>Plan</th><th>Estatus</th><th>Registro</th></tr></thead>
        <tbody>
          <?php foreach ($recientes as $u): ?>
            <tr>
              <td><div class="persona"><span class="avatar"><?= e(iniciales($u['nombre'])) ?></span><a href="<?= url('admin/usuario.php?id=' . $u['id']) ?>"><strong><?= e($u['nombre']) ?></strong><small><?= e($u['email']) ?></small></a></div></td>
              <td><?= insignia_plan($u['plan']) ?></td>
              <td><?= insignia_estatus($u['estatus']) ?></td>
              <td class="sin-salto texto-gris"><?= e(fecha_corta($u['fecha_registro'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="caja">
    <div class="caja-cabeza"><h2>Usuarios por plan</h2></div>
    <div class="caja-cuerpo barras-h">
      <?php foreach ($porPlan as $codigo => $n): ?>
        <div class="barra-h">
          <div class="barra-h-cabeza"><span><?= e($planes[$codigo]['nombre']) ?></span><span><?= $n ?> · <?= round($n / max(1, $activosTotal) * 100) ?>%</span></div>
          <div class="medidor <?= $codigo === 'gratis' ? '' : 'rosa' ?>"><i style="width:<?= round($n / max(1, $activosTotal) * 100) ?>%"></i></div>
        </div>
      <?php endforeach; ?>
      <p class="texto-chico texto-gris">Solo cuentas de clientes no pendientes.</p>
    </div>
  </section>
</div>

<!-- Gráfica final: muestra el volumen de escaneos durante los últimos 30 días -->
<section class="caja">
  <div class="caja-cabeza"><div><h2>Escaneos en toda la plataforma</h2><p>Últimos 30 días · mide el tráfico del redireccionador</p></div></div>
  <div class="caja-cuerpo"><div class="grafica grafica-chica"><canvas data-grafica='<?= e(json_encode($grafEscaneos)) ?>' role="img" aria-label="Escaneos por día"></canvas></div></div>
</section>

<?php // Cierra el layout del panel y carga la biblioteca de gráficos y el JavaScript de la aplicación.
pagina_fin('admin', [CDN_CHART]); ?>
