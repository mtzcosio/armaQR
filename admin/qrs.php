<?php

// Carga el layout y los helpers comunes de la administración.
require_once __DIR__ . '/../includes/layout.php';
App\Core\Auth::requireAdmin();

// Obtén los QR y los ordena por número de escaneos para mostrar los más utilizados primero.
$qrs = datos_qrs();
$usuariosPorId = array_column(datos_usuarios(true), null, 'id');
usort($qrs, fn($a, $b) => $b['total_escaneos'] <=> $a['total_escaneos']);
$cuenta = fn(callable $f) => count(array_filter($qrs, $f));

// Inicia la sección de administración y marca "Códigos QR" como el menú activo.
$mensaje = flash_tomar();
pagina_inicio('Códigos QR', 'admin', 'qrs');
?>
<div class="encabezado">
  <div>
    <h1>Códigos QR</h1>
    <p>Todos los códigos de la plataforma. Pausa los que apunten a sitios engañosos o reportados.</p>
  </div>
  <div class="encabezado-acciones">
    <button class="boton boton-tinta" type="button" data-exportar-csv="tabla-qrs" data-nombre="qr-<?= date('Y-m-d') ?>"><?= icono('descargar', 17) ?> Exportar CSV</button>
  </div>
</div>

<?php if ($mensaje): ?><?= aviso($mensaje['tipo'], $mensaje['mensaje']) ?><?php endif; ?>

<!-- Métricas de cantidad de QR, dinámicos, pausados y escaneos -->
<section class="cifras">
  <div class="cifra"><span>QR en la plataforma</span><strong><?= count($qrs) ?></strong><small>sin contar eliminados</small></div>
  <div class="cifra destaca"><span>Dinámicos</span><strong><?= $cuenta(fn($q) => $q['tipo'] === 'dinamico') ?></strong><small>Pasan por el redireccionador</small></div>
  <div class="cifra"><span>Pausados</span><strong><?= $cuenta(fn($q) => !$q['activo']) ?></strong><small>No redirigen</small></div>
  <div class="cifra"><span>Escaneos</span><strong><?= numero(array_sum(array_column($qrs, 'total_escaneos'))) ?></strong><small>Acumulados</small></div>
</section>

<!-- Filtros y búsqueda para localizar códigos QR por tipo, estado o propietario -->
<section class="caja" data-tabla-filtrable>
  <div class="herramientas">
    <div class="segmentado" role="tablist" aria-label="Filtrar QR">
      <button type="button" role="tab" data-filtro="" aria-selected="true">Todos</button>
      <button type="button" role="tab" data-filtro="dinamico" aria-selected="false">Dinámicos</button>
      <button type="button" role="tab" data-filtro="estatico" aria-selected="false">Estáticos</button>
      <button type="button" role="tab" data-filtro="pausado" aria-selected="false">Pausados</button>
    </div>
    <label class="buscador"><span class="oculto-visual">Buscar</span><?= icono('buscar', 16) ?><input class="entrada" type="search" placeholder="Nombre, dueño, código o destino" data-buscar></label>
    <span class="texto-gris texto-chico" data-contador></span>
  </div>
  <!-- Tabla de QR con el propietario, destino, escaneos y acciones de estado -->
  <div class="tabla-envoltura">
    <table class="tabla" id="tabla-qrs">
      <thead>
        <tr>
          <th data-csv="nombre">Código</th>
          <th data-csv="dueno">Dueño</th>
          <th data-csv="tipo">Tipo</th>
          <th data-csv="destino">Destino</th>
          <th class="num" data-csv="escaneos">Escaneos</th>
          <th data-csv="ultimo">Último escaneo</th>
          <th data-csv="codigo"><span class="oculto-visual">Acciones</span></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($qrs as $q): $u = $usuariosPorId[$q['usuario_id']] ?? ['id' => $q['usuario_id'], 'nombre' => 'Usuario #' . $q['usuario_id'], 'email' => '', 'plan' => 'gratis']; ?>
        <tr data-fila data-etiquetas="<?= e($q['tipo'] . ($q['activo'] ? '' : ' pausado')) ?>"
            data-csv-nombre="<?= e($q['nombre']) ?>" data-csv-dueno="<?= e($u['email']) ?>" data-csv-tipo="<?= e($q['tipo']) ?>"
            data-csv-destino="<?= e($q['destino']) ?>" data-csv-escaneos="<?= $q['total_escaneos'] ?>"
            data-csv-ultimo="<?= e($q['ultimo_escaneo'] ?? '') ?>" data-csv-codigo="<?= e($q['codigo_corto'] ?? '') ?>">
          <td>
            <strong style="font-weight:600"><?= e($q['nombre']) ?></strong>
            <?php if ($q['codigo_corto']): ?><div class="texto-chico texto-gris">/r/<?= e($q['codigo_corto']) ?></div><?php endif; ?>
          </td>
          <td><a class="enlace" href="<?= url('admin/usuario.php?id=' . $u['id']) ?>"><?= e($u['nombre']) ?></a> <?= insignia_plan($u['plan']) ?></td>
          <td><span class="tipo-qr <?= $q['tipo'] === 'dinamico' ? 'dinamico' : '' ?>"><?= $q['tipo'] === 'dinamico' ? 'Dinámico' : 'Estático' ?></span><?= !$q['activo'] ? ' ' . insignia_estatus('pausado') : '' ?></td>
          <td class="texto-gris" style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= e($q['destino']) ?>"><?= e($q['destino']) ?></td>
          <td class="num"><?= $q['tipo'] === 'dinamico' ? numero($q['total_escaneos']) : '—' ?></td>
          <td class="sin-salto texto-gris"><?= $q['ultimo_escaneo'] ? e(fecha_corta($q['ultimo_escaneo'])) : '—' ?></td>
          <td class="acciones">
            <?php if ($q['tipo'] === 'dinamico'): ?>
              <form method="post" action="<?= url('auth/qr.php') ?>" class="en-linea">
                <?= csrf_campo() ?>
                <input type="hidden" name="origen" value="admin">
                <input type="hidden" name="id" value="<?= (int) $q['id'] ?>">
                <?php if ($q['activo']): ?>
                  <button class="boton-icono" type="submit" name="accion" value="pausar" title="Pausar" aria-label="Pausar <?= e($q['nombre']) ?>"
                    data-confirmar="¿Pausar “<?= e($q['nombre']) ?>”?" data-detalle="Dejará de redirigir de inmediato. Avísale al dueño el motivo." data-accion="Pausar QR"><?= icono('pausa') ?></button>
                <?php else: ?>
                  <button class="boton-icono" type="submit" name="accion" value="reanudar" title="Reanudar" aria-label="Reanudar <?= e($q['nombre']) ?>"><?= icono('play') ?></button>
                <?php endif; ?>
              </form>
              <button class="boton-icono" type="button" title="Copiar liga corta" aria-label="Copiar liga corta" data-copiar="https://<?= e(dominio_portal()) ?>/r/<?= e($q['codigo_corto']) ?>" data-copiar-mensaje="Copiaste la liga corta"><?= icono('copiar') ?></button>
              <?php if (!qr_tipo_usa_url($q['tipo_contenido'])): ?>
                <a class="boton-icono" href="<?= e(url('r/' . $q['codigo_corto']) . '?vista_previa=1') ?>" target="_blank" rel="noopener" title="Ver página (no cuenta como escaneo)" aria-label="Ver la página de <?= e($q['nombre']) ?>"><?= icono('ojo') ?></a>
              <?php endif; ?>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <div class="vacio" data-sin-resultados hidden><strong>Ningún QR coincide</strong><span>Revisa la búsqueda o cambia el filtro.</span></div>
  </div>
</section>
<?php pagina_fin('admin'); ?>
