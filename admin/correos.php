<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/correo.php';
App\Core\Auth::requireAdmin();

$mensaje = flash_tomar();
$tablasListas = correo_tablas_listas();
$sistema = correo_codigos_sistema();

// Filtros
$q = trim((string) ($_GET['q'] ?? ''));
$tipo = in_array($_GET['tipo'] ?? '', CORREO_TIPOS, true) ? $_GET['tipo'] : '';
$estado = isset(CORREO_ESTATUS[$_GET['estado'] ?? '']) ? $_GET['estado'] : '';
$porPagina = 15;
$pagina = max(1, (int) ($_GET['pagina'] ?? 1));

$plantillas = [];
$total = 0;
$porCodigo = [];
if ($tablasListas) {
    $where = ['1 = 1'];
    $params = [];
    if ($q !== '') {
        $where[] = '(nombre LIKE :q OR codigo LIKE :q2)';
        $params[':q'] = $params[':q2'] = '%' . addcslashes($q, '%_\\') . '%';
    }
    if ($tipo !== '') {
        $where[] = 'tipo = :tipo';
        $params[':tipo'] = $tipo;
    }
    if ($estado !== '') {
        $where[] = 'estatus = :estado';
        $params[':estado'] = $estado;
    }
    $sqlWhere = implode(' AND ', $where);
    $total = (int) datos_consulta("SELECT COUNT(*) AS n FROM plantillas_correo WHERE {$sqlWhere}", $params)[0]['n'];
    $pagina = min($pagina, max(1, (int) ceil($total / $porPagina)));
    $plantillas = datos_consulta(
        "SELECT id, nombre, codigo, tipo, estatus, fecha_modificacion FROM plantillas_correo
         WHERE {$sqlWhere} ORDER BY fecha_modificacion DESC LIMIT {$porPagina} OFFSET " . (($pagina - 1) * $porPagina),
        $params
    );
    // Plantillas de los códigos del sistema (para saber qué usa el sitio hoy)
    foreach (datos_consulta('SELECT id, nombre, codigo, estatus FROM plantillas_correo WHERE codigo IN (' .
        implode(',', array_fill(0, count($sistema), '?')) . ')', array_keys($sistema)) as $fila) {
        $porCodigo[$fila['codigo']] = $fila;
    }
}
$paginas = max(1, (int) ceil($total / $porPagina));
$filtrosActivos = $q !== '' || $tipo !== '' || $estado !== '';
$enlacePagina = fn(int $n) => url('admin/correos.php') . '?' . http_build_query(array_filter(['q' => $q, 'tipo' => $tipo, 'estado' => $estado, 'pagina' => $n > 1 ? $n : null]));

/** Formulario POST de una acción sobre una plantilla. */
$accion = fn(string $accion, int $id, string $boton) => '<form method="post" action="' . e(url('auth/correos.php')) . '" class="en-linea">'
    . csrf_campo() . '<input type="hidden" name="accion" value="' . e($accion) . '"><input type="hidden" name="id" value="' . $id . '">' . $boton . '</form>';

pagina_inicio('Correos', 'admin', 'correos');
?>
<div class="encabezado">
  <div>
    <h1>Correos</h1>
    <p>Edita el asunto y el contenido de los correos que envía el portal, sin tocar código.</p>
  </div>
  <div class="encabezado-acciones">
    <?php if ($tablasListas): ?>
      <a class="boton boton-fantasma" href="<?= url('admin/correo-variables.php') ?>"><?= icono('editar', 17) ?> Variables</a>
      <a class="boton boton-principal" href="<?= url('admin/correo.php') ?>"><?= icono('mas', 17) ?> Nueva plantilla</a>
    <?php endif; ?>
  </div>
</div>

<?php if ($mensaje): ?><?= aviso($mensaje['tipo'], $mensaje['mensaje']) ?><?php endif; ?>
<?php if (!$tablasListas): ?>
  <?= aviso('error', 'Faltan las tablas del módulo de correos. Ejecuta en el servidor: php database/migrate_plantillas_correo.php') ?>
<?php endif; ?>

<section class="caja">
  <div class="caja-cabeza"><div><h2>Correos automáticos del sistema</h2><p>El sitio los envía solo. Si no hay una plantilla activa con su código, usa el correo predeterminado.</p></div></div>
  <div class="tabla-envoltura">
    <table class="tabla">
      <thead><tr><th>Correo</th><th>Cuándo se envía</th><th>Qué usa hoy</th><th><span class="oculto-visual">Acciones</span></th></tr></thead>
      <tbody>
        <?php foreach ($sistema as $codigo => $info): $p = $porCodigo[$codigo] ?? null; ?>
          <tr>
            <td><strong><?= e($info['nombre']) ?></strong><div><code class="codigo"><?= e($codigo) ?></code></div></td>
            <td class="texto-gris"><?= e($info['cuando']) ?><div class="texto-chico">Variables: <?= e(implode(', ', $info['variables'])) ?></div></td>
            <td>
              <?php if ($p && $p['estatus'] === 'activa'): ?>
                <?= insignia_estatus('activa') ?> <span class="texto-chico">«<?= e($p['nombre']) ?>»</span>
              <?php else: ?>
                <span class="estatus estatus-neutro">Predeterminado</span>
                <?php if ($p): ?><div class="texto-chico texto-gris">La plantilla «<?= e($p['nombre']) ?>» está <?= e(mb_strtolower(CORREO_ESTATUS[$p['estatus']])) ?>.</div><?php endif; ?>
              <?php endif; ?>
            </td>
            <td class="acciones">
              <?php if (!$tablasListas): ?>
              <?php elseif ($p): ?>
                <a class="boton boton-fantasma boton-chico" href="<?= url('admin/correo.php?id=' . (int) $p['id']) ?>"><?= icono('editar', 15) ?> Editar</a>
              <?php else: ?>
                <a class="boton boton-fantasma boton-chico" href="<?= url('admin/correo.php?codigo=' . urlencode($codigo)) ?>"><?= icono('editar', 15) ?> Personalizar</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<?php if ($tablasListas): ?>
<section class="caja">
  <form class="herramientas" method="get" action="<?= url('admin/correos.php') ?>">
    <label class="buscador"><span class="oculto-visual">Buscar</span><?= icono('buscar', 16) ?><input class="entrada" type="search" name="q" value="<?= e($q) ?>" placeholder="Nombre o código"></label>
    <label class="oculto-visual" for="f-tipo">Tipo</label>
    <select class="entrada entrada-compacta" id="f-tipo" name="tipo" onchange="this.form.submit()">
      <option value="">Todos los tipos</option>
      <?php foreach (CORREO_TIPOS as $t): ?><option <?= $t === $tipo ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
    </select>
    <label class="oculto-visual" for="f-estado">Estado</label>
    <select class="entrada entrada-compacta" id="f-estado" name="estado" onchange="this.form.submit()">
      <option value="">Todos los estados</option>
      <?php foreach (CORREO_ESTATUS as $valor => $texto): ?><option value="<?= e($valor) ?>" <?= $valor === $estado ? 'selected' : '' ?>><?= e($texto) ?></option><?php endforeach; ?>
    </select>
    <button class="boton boton-fantasma boton-chico" type="submit">Filtrar</button>
    <?php if ($filtrosActivos): ?><a class="enlace texto-chico" href="<?= url('admin/correos.php') ?>">Quitar filtros</a><?php endif; ?>
  </form>

  <?php if ($plantillas): ?>
  <div class="tabla-envoltura">
    <table class="tabla">
      <thead><tr><th>Plantilla</th><th>Tipo</th><th>Estado</th><th>Actualizada</th><th><span class="oculto-visual">Acciones</span></th></tr></thead>
      <tbody>
        <?php foreach ($plantillas as $p):
            $id = (int) $p['id'];
            $delSistema = isset($sistema[$p['codigo']]);
        ?>
        <tr>
          <td>
            <a class="enlace-fila" href="<?= url('admin/correo.php?id=' . $id) ?>"><strong><?= e($p['nombre']) ?></strong></a>
            <div><code class="codigo"><?= e($p['codigo']) ?></code><?= $delSistema ? ' <span class="texto-chico texto-gris">· del sistema</span>' : '' ?></div>
          </td>
          <td class="texto-gris"><?= e($p['tipo']) ?></td>
          <td><?= insignia_estatus($p['estatus']) ?></td>
          <td class="sin-salto texto-gris"><?= e(fecha_corta($p['fecha_modificacion'])) ?></td>
          <td class="acciones">
            <a class="boton-icono" href="<?= url('admin/correo-ver.php?id=' . $id) ?>" title="Ver" aria-label="Ver «<?= e($p['nombre']) ?>»"><?= icono('ojo') ?></a>
            <a class="boton-icono" href="<?= url('admin/correo.php?id=' . $id) ?>" title="Editar" aria-label="Editar «<?= e($p['nombre']) ?>»"><?= icono('editar') ?></a>
            <?= $accion('duplicar', $id, '<button class="boton-icono" type="submit" title="Duplicar" aria-label="Duplicar «' . e($p['nombre']) . '»">' . icono('copiar') . '</button>') ?>
            <?= $accion('alternar', $id, $p['estatus'] === 'activa'
                ? '<button class="boton-icono" type="submit" title="Desactivar" aria-label="Desactivar «' . e($p['nombre']) . '»">' . icono('pausa') . '</button>'
                : '<button class="boton-icono" type="submit" title="Activar" aria-label="Activar «' . e($p['nombre']) . '»">' . icono('play') . '</button>') ?>
            <button class="boton-icono" type="button" title="Enviar prueba" aria-label="Enviar prueba de «<?= e($p['nombre']) ?>»" data-abrir-prueba="<?= $id ?>" data-nombre="<?= e($p['nombre']) ?>"><?= icono('correo') ?></button>
            <?= $accion('eliminar', $id, '<button class="boton-icono boton-icono-peligro" type="submit" title="Eliminar" aria-label="Eliminar «' . e($p['nombre']) . '»"'
                . ' data-confirmar="¿Eliminar «' . e($p['nombre']) . '»?" data-accion="Eliminar" data-peligro'
                . ' data-detalle="' . e($delSistema
                    ? 'Es la plantilla de un correo del sistema (' . $p['codigo'] . '). Al eliminarla, el sitio volverá a usar el correo predeterminado.'
                    : 'Esta acción no se puede deshacer.') . '">' . icono('x') . '</button>') ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($paginas > 1): ?>
    <nav class="paginacion" aria-label="Páginas">
      <span><?= numero($total) ?> plantillas · página <?= $pagina ?> de <?= $paginas ?></span>
      <span class="paginacion-enlaces">
        <?php if ($pagina > 1): ?><a class="boton boton-fantasma boton-chico" href="<?= e($enlacePagina($pagina - 1)) ?>">← Anterior</a><?php endif; ?>
        <?php if ($pagina < $paginas): ?><a class="boton boton-fantasma boton-chico" href="<?= e($enlacePagina($pagina + 1)) ?>">Siguiente →</a><?php endif; ?>
      </span>
    </nav>
  <?php endif; ?>
  <?php elseif ($filtrosActivos): ?>
    <div class="vacio"><strong>Ninguna plantilla coincide</strong><span>Cambia la búsqueda o quita los filtros.</span></div>
  <?php else: ?>
    <div class="vacio">
      <strong>Aún no hay plantillas</strong>
      <span>Personaliza un correo del sistema o crea una plantilla nueva.</span>
      <a class="boton boton-principal" href="<?= url('admin/correo.php') ?>"><?= icono('mas', 17) ?> Nueva plantilla</a>
    </div>
  <?php endif; ?>
</section>

<dialog id="dialogo-prueba" aria-labelledby="prueba-titulo">
  <form class="dialogo" method="post" action="<?= url('auth/correos.php') ?>">
    <?= csrf_campo() ?>
    <input type="hidden" name="accion" value="probar">
    <input type="hidden" name="id" value="" data-prueba-id>
    <h2 id="prueba-titulo">Enviar prueba</h2>
    <p data-prueba-nombre></p>
    <div class="campo">
      <label for="correo_prueba">Enviar a</label>
      <input class="entrada" id="correo_prueba" name="correo_prueba" type="email" required value="<?= e($_SESSION['usuario_email'] ?? '') ?>">
      <small>Se envía con datos de ejemplo y el asunto empieza con [PRUEBA].</small>
    </div>
    <div class="dialogo-acciones">
      <button type="button" class="boton boton-fantasma" data-dialogo-cancelar>Cancelar</button>
      <button type="submit" class="boton boton-tinta"><?= icono('correo', 16) ?> Enviar prueba</button>
    </div>
  </form>
</dialog>
<?php endif; ?>
<?php pagina_fin('admin', [asset('assets/js/correos.js')]); ?>
