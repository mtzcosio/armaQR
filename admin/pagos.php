<?php

// Carga el layout, los helpers de iconos, dinero y URLs del panel administrativo.
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/pagos.php';
App\Core\Auth::requireAdmin();

// Obtén los cobros y el resumen general. En producción, estos datos provendrán de las tablas de pagos y usuarios.
$pagos = datos_pagos();
$r = datos_resumen_admin();
$usuariosPorId = array_column(datos_usuarios(true), null, 'id');

// Calcula totales, ticket promedio, reembolsos y cantidades por estado.
$pagados = array_filter($pagos, fn($p) => $p['estatus'] === 'pagado');
$totalCobrado = array_sum(array_column($pagados, 'monto'));
$ticket = count($pagados) ? $totalCobrado / count($pagados) : 0;
$reembolsado = array_sum(array_map(fn($p) => $p['estatus'] === 'reembolsado' ? $p['monto'] : 0, $pagos));
$cuenta = fn($est) => count(array_filter($pagos, fn($p) => $p['estatus'] === $est));
$pasarelas = ['stripe' => 'Stripe', 'mercadopago' => 'Mercado Pago', 'conekta' => 'Conekta'];

pagina_inicio('Pagos', 'admin', 'pagos');
?>
<div class="encabezado">
  <div>
    <h1>Pagos</h1>
    <p>Cobros recibidos desde las pasarelas. Cada registro llega por webhook.</p>
  </div>
  <div class="encabezado-acciones">
    <button class="boton boton-tinta" type="button" data-exportar-csv="tabla-pagos" data-nombre="pagos-<?= date('Y-m-d') ?>"><?= icono('descargar', 17) ?> Exportar CSV</button>
  </div>
</div>

<!-- Métricas de cobros, ticket, fallos y reembolsos -->
<section class="cifras">
  <div class="cifra destaca"><span>Cobrado este mes</span><strong><?= dinero($r['ingresos_mes'], false) ?></strong><small>Total histórico <?= dinero($totalCobrado, false) ?></small></div>
  <div class="cifra"><span>Ticket promedio</span><strong><?= dinero($ticket, false) ?></strong><small><?= count($pagados) ?> cobros exitosos</small></div>
  <div class="cifra"><span>Pagos rechazados</span><strong><?= $cuenta('fallido') ?></strong><small class="baja">Requieren seguimiento</small></div>
  <div class="cifra"><span>Reembolsos</span><strong><?= dinero($reembolsado, false) ?></strong><small><?= $cuenta('reembolsado') ?> en total</small></div>
</section>

<!-- Filtros y búsqueda de la tabla de cobros -->
<section class="caja" data-tabla-filtrable>
  <div class="herramientas">
    <div class="segmentado" role="tablist" aria-label="Filtrar pagos">
      <button type="button" role="tab" data-filtro="" aria-selected="true">Todos <span class="cuenta"><?= count($pagos) ?></span></button>
      <button type="button" role="tab" data-filtro="pagado" aria-selected="false">Pagados <span class="cuenta"><?= $cuenta('pagado') ?></span></button>
      <button type="button" role="tab" data-filtro="fallido" aria-selected="false">Rechazados <span class="cuenta"><?= $cuenta('fallido') ?></span></button>
      <button type="button" role="tab" data-filtro="reembolsado" aria-selected="false">Reembolsados <span class="cuenta"><?= $cuenta('reembolsado') ?></span></button>
    </div>
    <label class="buscador"><span class="oculto-visual">Buscar</span><?= icono('buscar', 16) ?><input class="entrada" type="search" placeholder="Cliente, folio o concepto" data-buscar></label>
    <span class="texto-gris texto-chico" data-contador></span>
  </div>
  <!-- Tabla de pagos con datos para exportar a CSV y acciones de reembolso o aviso -->
  <div class="tabla-envoltura">
    <table class="tabla" id="tabla-pagos">
      <thead>
        <tr>
          <th data-csv="folio">Folio</th>
          <th data-csv="cliente">Cliente</th>
          <th data-csv="concepto">Concepto</th>
          <th data-csv="metodo">Método</th>
          <th data-csv="pasarela">Pasarela</th>
          <th class="num" data-csv="monto">Monto</th>
          <th data-csv="fecha">Fecha</th>
          <th data-csv="estatus">Estatus</th>
          <th data-csv="correo"><span class="oculto-visual">Acciones</span></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($pagos as $p): $u = $usuariosPorId[$p['usuario_id']] ?? ['id' => $p['usuario_id'], 'nombre' => 'Usuario #' . $p['usuario_id'], 'email' => '']; ?>
        <tr data-fila data-etiquetas="<?= e($p['estatus']) ?>"
            data-csv-folio="<?= $p['id'] ?>" data-csv-cliente="<?= e($u['nombre']) ?>" data-csv-correo="<?= e($u['email']) ?>"
            data-csv-concepto="<?= e($p['concepto']) ?>" data-csv-metodo="<?= e($p['metodo_pago']) ?>" data-csv-pasarela="<?= e($p['pasarela']) ?>"
            data-csv-monto="<?= $p['monto'] ?>" data-csv-fecha="<?= e($p['fecha_pago']) ?>" data-csv-estatus="<?= e($p['estatus']) ?>">
          <td class="texto-gris">#<?= $p['id'] ?></td>
          <td><div class="persona" style="min-width:180px"><a href="<?= url('admin/usuario.php?id=' . $u['id']) ?>"><strong><?= e($u['nombre']) ?></strong><small><?= e($u['email']) ?></small></a></div></td>
          <td><?= e($p['concepto']) ?></td>
          <td class="sin-salto"><?= e(ucfirst($p['metodo_pago'])) ?><?= $p['ultimos_digitos'] ? ' <span class="texto-gris">•••• ' . e($p['ultimos_digitos']) . '</span>' : '' ?></td>
          <td class="texto-gris"><?= e($pasarelas[$p['pasarela']] ?? $p['pasarela']) ?></td>
          <td class="num"><?= dinero($p['monto']) ?></td>
          <td class="sin-salto texto-gris"><?= e(fecha_corta($p['fecha_pago'])) ?></td>
          <td><?= insignia_estatus($p['estatus'] === 'pendiente' ? 'por_pagar' : $p['estatus']) ?></td>
          <td class="acciones">
            <?php if ($p['pasarela'] === 'stripe'): ?>
              <a class="boton-icono" href="<?= e(stripe_url_panel((string) $p['id_externo'])) ?>" target="_blank" rel="noopener" title="Ver en Stripe" aria-label="Ver en Stripe"><?= icono('externo') ?></a>
            <?php endif; ?>
            <?php if ($p['estatus'] === 'pagado'): ?>
              <form method="post" action="<?= url('auth/pago.php') ?>" class="en-linea">
                <?= csrf_campo() ?><input type="hidden" name="accion" value="reembolsar"><input type="hidden" name="id" value="<?= $p['id'] ?>">
                <button class="boton-icono" type="submit" title="Reembolsar" aria-label="Reembolsar pago #<?= $p['id'] ?>"
                  data-confirmar="¿Reembolsar <?= e(dinero($p['monto'])) ?> a <?= e($u['nombre']) ?>?" data-detalle="El reembolso total se procesa en <?= e($pasarelas[$p['pasarela']] ?? $p['pasarela']) ?> y puede tardar de 5 a 10 días hábiles en reflejarse. El plan del cliente no cambia."
                  data-accion="Reembolsar" data-peligro><?= icono('dinero') ?></button>
              </form>
            <?php elseif ($p['estatus'] === 'fallido'): ?>
              <a class="boton-icono" href="mailto:<?= e($u['email']) ?>?subject=<?= rawurlencode('Tu pago no se pudo procesar') ?>" title="Avisar al cliente" aria-label="Avisar al cliente"><?= icono('correo') ?></a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <div class="vacio" data-sin-resultados hidden><strong>Ningún pago coincide</strong><span>Revisa la búsqueda o cambia el filtro.</span></div>
  </div>
</section>
<?php pagina_fin('admin'); ?>
