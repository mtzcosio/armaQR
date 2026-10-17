<?php

// Carga el layout y los helpers comunes del panel administrativo.
require_once __DIR__ . '/../includes/layout.php';
App\Core\Auth::requireAdmin();

// Obtén los usuarios y los ordena por fecha de registro más reciente.
$usuarios = datos_usuarios();
usort($usuarios, fn($a, $b) => strcmp($b['fecha_registro'], $a['fecha_registro']));

// Calcula la cantidad de usuarios en cada filtro para mostrar contadores.
$cuenta = fn(callable $f) => count(array_filter($usuarios, $f));
$filtros = [
    ''           => ['Todos',         count($usuarios)],
    'de-pago'    => ['De pago',       $cuenta(fn($u) => $u['plan'] !== 'gratis' && $u['estatus'] === 'activo')],
    'gratis'     => ['Gratis',        $cuenta(fn($u) => $u['plan'] === 'gratis' && $u['estatus'] === 'activo')],
    'sin-verificar' => ['Sin verificar', $cuenta(fn($u) => $u['email_verificado_en'] === null)],
    'suspendido' => ['Suspendidos',   $cuenta(fn($u) => $u['estatus'] === 'suspendido')],
];
$todosLosCorreos = implode(', ', array_column($usuarios, 'email'));

// Abre el encabezado de la lista y marca la sección "Usuarios" como activa.
$mensaje = flash_tomar();
pagina_inicio('Usuarios', 'admin', 'usuarios');
?>
<!-- Cabecera de la página: resumen y acciones de exportación -->
<div class="encabezado">
  <div>
    <h1>Usuarios</h1>
    <p><?= count($usuarios) ?> cuentas registradas. Filtra por plan o estatus y exporta los correos.</p>
  </div>
  <div class="encabezado-acciones">
    <button class="boton boton-fantasma" type="button" data-copiar="<?= e($todosLosCorreos) ?>" data-copiar-mensaje="Copiaste <?= count($usuarios) ?> correos"><?= icono('copiar', 17) ?> Copiar todos los correos</button>
    <button class="boton boton-tinta" type="button" data-exportar-csv="tabla-usuarios" data-nombre="usuarios-<?= date('Y-m-d') ?>"><?= icono('descargar', 17) ?> Exportar CSV</button>
  </div>
</div>

<?php if ($mensaje): ?><?= aviso($mensaje['tipo'], $mensaje['mensaje']) ?><?php endif; ?>

<!-- Filtros, búsqueda y tabla de usuarios -->
<section class="caja" data-tabla-filtrable>
  <div class="herramientas">
    <div class="segmentado" role="tablist" aria-label="Filtrar usuarios">
      <?php foreach ($filtros as $clave => [$texto, $n]): ?>
        <button type="button" role="tab" data-filtro="<?= e($clave) ?>" aria-selected="<?= $clave === '' ? 'true' : 'false' ?>"><?= e($texto) ?> <span class="cuenta"><?= $n ?></span></button>
      <?php endforeach; ?>
    </div>
    <label class="buscador"><span class="oculto-visual">Buscar</span><?= icono('buscar', 16) ?><input class="entrada" type="search" placeholder="Nombre o correo" data-buscar></label>
    <span class="texto-gris texto-chico" data-contador></span>
  </div>

  <!-- Tabla de usuarios con plan, estatus, QR, pagos y enlaces al detalle -->
  <div class="tabla-envoltura">
    <table class="tabla" id="tabla-usuarios">
      <thead>
        <tr>
          <th data-csv="nombre">Usuario</th>
          <th data-csv="plan">Plan</th>
          <th data-csv="estatus">Estatus</th>
          <th class="num" data-csv="qr">QR</th>
          <th class="num" data-csv="escaneos">Escaneos</th>
          <th class="num" data-csv="pagado">Total pagado</th>
          <th data-csv="registro">Registro</th>
          <th data-csv="acceso">Último acceso</th>
          <th data-csv="correo"><span class="oculto-visual">Acciones</span></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($usuarios as $u):
            $etiquetas = $u['estatus'] === 'activo'
                ? ($u['plan'] === 'gratis' ? 'gratis' : 'de-pago')
                : $u['estatus'];
        ?>
        <tr data-fila data-etiquetas="<?= e($etiquetas . ' ' . $u['plan'] . ($u['email_verificado_en'] === null ? ' sin-verificar' : '')) ?>"
            data-csv-nombre="<?= e($u['nombre']) ?>" data-csv-correo="<?= e($u['email']) ?>"
            data-csv-plan="<?= e($u['plan']) ?>" data-csv-estatus="<?= e($u['estatus']) ?>"
            data-csv-qr="<?= $u['total_qr'] ?>" data-csv-escaneos="<?= $u['total_escaneos'] ?>"
            data-csv-pagado="<?= $u['total_pagado'] ?>" data-csv-registro="<?= e($u['fecha_registro']) ?>"
            data-csv-acceso="<?= e($u['ultimo_acceso'] ?? '') ?>">
          <td><div class="persona"><span class="avatar"><?= e(iniciales($u['nombre'])) ?></span><a href="<?= url('admin/usuario.php?id=' . $u['id']) ?>"><strong><?= e($u['nombre']) ?></strong><small><?= e($u['email']) ?></small></a></div></td>
          <td><?= insignia_plan($u['plan']) ?><?php if ($u['periodicidad']): ?> <span class="texto-chico texto-gris"><?= e($u['periodicidad']) ?></span><?php endif; ?></td>
          <td><?= insignia_estatus($u['estatus']) ?><?= $u['rol'] === 'admin' ? ' <span class="texto-chico texto-gris">admin</span>' : '' ?></td>
          <td class="num"><?= numero($u['total_qr']) ?></td>
          <td class="num"><?= numero($u['total_escaneos']) ?></td>
          <td class="num"><?= $u['total_pagado'] ? dinero($u['total_pagado'], false) : '—' ?></td>
          <td class="sin-salto texto-gris"><?= e(fecha_corta($u['fecha_registro'])) ?></td>
          <td class="sin-salto texto-gris"><?= $u['ultimo_acceso'] ? e(fecha_corta($u['ultimo_acceso'])) : 'Nunca' ?></td>
          <td class="acciones">
            <a class="boton-icono" href="<?= url('admin/usuario.php?id=' . $u['id']) ?>" title="Ver detalle" aria-label="Ver detalle de <?= e($u['nombre']) ?>"><?= icono('ojo') ?></a>
            <a class="boton-icono" href="mailto:<?= e($u['email']) ?>" title="Escribir correo" aria-label="Escribir a <?= e($u['nombre']) ?>"><?= icono('correo') ?></a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <div class="vacio" data-sin-resultados hidden><strong>Ningún usuario coincide</strong><span>Revisa la búsqueda o cambia el filtro.</span></div>
  </div>
  <div class="paginacion"><span>Mostrando <?= count($usuarios) ?> de <?= count($usuarios) ?></span></div>
</section>
<?php pagina_fin('admin'); ?>
