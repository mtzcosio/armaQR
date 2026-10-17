<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/legales.php';
App\Core\Auth::requireAdmin();

$tipo = (string) ($_GET['tipo'] ?? '');
if (!isset(LEGAL_TIPOS[$tipo]) || !legal_tabla_lista()) {
    redirigir('admin/legales.php');
}
$info = LEGAL_TIPOS[$tipo];
$vigente = legal_publicado($tipo);
$mensaje = flash_tomar();

/* ---------- Ver una versión publicada (solo lectura) ---------- */
if (isset($_GET['version'])) {
    $doc = legal_version($tipo, (int) $_GET['version']);
    if (!$doc) {
        flash('error', 'Esa versión no existe.');
        redirigir('admin/legales.php');
    }
    $esVigente = $vigente && (int) $vigente['version'] === (int) $doc['version'];
    pagina_inicio($info['titulo'] . ' · versión ' . $doc['version'], 'admin', 'legales');
    ?>
<div class="encabezado">
  <div>
    <a class="miga" href="<?= url('admin/legales.php') ?>">← Legales</a>
    <h1><?= e($doc['titulo']) ?> · versión <?= (int) $doc['version'] ?></h1>
    <p>Publicada el <?= e(fecha_corta($doc['publicado_en'])) ?><?= $doc['autor'] ? ' por ' . e($doc['autor']) : '' ?>. <?= $esVigente ? insignia_estatus('activa') : '<span class="estatus estatus-neutro">Anterior</span>' ?></p>
  </div>
  <?php if (!$esVigente): ?>
    <form class="encabezado-acciones" method="post" action="<?= url('auth/legales.php') ?>">
      <?= csrf_campo() ?>
      <input type="hidden" name="tipo" value="<?= e($tipo) ?>">
      <input type="hidden" name="version" value="<?= (int) $doc['version'] ?>">
      <button class="boton boton-fantasma" type="submit" name="accion" value="restaurar"
        data-confirmar="¿Restaurar la versión <?= (int) $doc['version'] ?>?" data-accion="Copiar como borrador"
        data-detalle="Se copia como borrador para que la revises; el público sigue viendo la versión vigente hasta que publiques."><?= icono('copiar', 17) ?> Restaurar como borrador</button>
    </form>
  <?php endif; ?>
</div>
<section class="caja caja-cuerpo">
  <article class="legal legal-previa"><div class="legal-contenido"><?= legal_render(legal_sanitizar((string) $doc['contenido_html'])) ?></div></article>
</section>
<?php
    pagina_fin('admin');
    return;
}

/* ---------- Editor ---------- */
$borrador = legal_borrador($tipo);
$previo = $_SESSION['legal_formulario'] ?? null;
unset($_SESSION['legal_formulario']);
if ($previo && $previo['tipo'] !== $tipo) {
    $previo = null;
}
$base = $previo ?? $borrador ?? $vigente;
$siguiente = ($vigente['version'] ?? 0) + 1;

pagina_inicio(($vigente ? 'Actualizar ' : 'Crear ') . mb_strtolower($info['titulo']), 'admin', 'legales', ['https://cdnjs.cloudflare.com/ajax/libs/quill/2.0.2/quill.snow.css']);
?>
<div class="encabezado">
  <div>
    <a class="miga" href="<?= url('admin/legales.php') ?>">← Legales</a>
    <h1><?= e($info['titulo']) ?></h1>
    <p><?= $vigente
        ? 'Vigente: versión ' . (int) $vigente['version'] . ' desde el ' . e(fecha_corta($vigente['publicado_en'])) . '. Al publicar se crea la versión ' . $siguiente . '.'
        : 'Todavía no hay una versión publicada.' ?></p>
  </div>
  <?php if ($vigente): ?>
    <div class="encabezado-acciones"><a class="boton boton-fantasma" href="<?= url($info['ruta']) ?>" target="_blank" rel="noopener"><?= icono('externo', 17) ?> Ver página pública</a></div>
  <?php endif; ?>
</div>

<?php if ($mensaje): ?><?= aviso($mensaje['tipo'], $mensaje['mensaje']) ?><?php endif; ?>
<?php if ($borrador && !$previo): ?>
  <?= aviso('info', 'Estás editando el borrador guardado el ' . fecha_corta($borrador['fecha_modificacion']) . '. El público sigue viendo la versión publicada.') ?>
<?php endif; ?>

<form id="form-legal" class="editor-correo" method="post" action="<?= url('auth/legales.php') ?>" data-editor-legal>
  <?= csrf_campo() ?>
  <input type="hidden" name="tipo" value="<?= e($tipo) ?>">
  <input type="hidden" name="accion" value="guardar">
  <input type="hidden" name="contenido_html" id="contenido_html" value="<?= e((string) ($base['contenido_html'] ?? '')) ?>">

  <div class="editor-correo-principal">
    <section class="caja">
      <div class="caja-cuerpo" style="display:grid;gap:16px">
        <div class="campo">
          <label for="titulo">Título</label>
          <input class="entrada" id="titulo" name="titulo" maxlength="150" value="<?= e((string) ($base['titulo'] ?? $info['titulo'])) ?>">
        </div>
        <div class="campo editor-contenido">
          <span class="etiqueta">Contenido</span>
          <div id="editor-legal" class="editor-visual"></div>
          <small>Usa títulos (Encabezado 2) para cada sección. Las {{variables}} se sustituyen por los datos de Configuración al mostrar la página.</small>
        </div>
      </div>
    </section>
  </div>

  <aside class="caja editor-variables">
    <div class="caja-cabeza"><div><h2>Variables</h2><p>Se actualizan solas si cambias la configuración.</p></div></div>
    <div class="caja-cuerpo">
      <ul class="variables-grupo variables-legales">
        <?php $valores = legal_valores(); foreach (LEGAL_VARIABLES as $nombre => $descripcion): ?>
          <li data-variable="<?= e($nombre) ?>">
            <code>{{<?= e($nombre) ?>}}</code>
            <span class="variables-acciones"><button class="boton-icono" type="button" title="Insertar" aria-label="Insertar {{<?= e($nombre) ?>}}" data-insertar-variable><?= icono('mas', 16) ?></button></span>
            <small><?= e($descripcion) ?>. Hoy: <strong><?= strip_tags($valores[$nombre]) ?></strong></small>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </aside>

  <div class="barra-guardar">
    <span class="ajustes-estado" data-estado-cambios aria-live="polite">Sin cambios</span>
    <span class="barra-guardar-acciones">
      <?php if ($borrador): ?>
        <button class="boton boton-fantasma" type="submit" name="accion" value="descartar" formnovalidate
          data-confirmar="¿Descartar el borrador?" data-detalle="Se pierden los cambios que no publicaste. La versión publicada no cambia." data-accion="Descartar" data-peligro>Descartar borrador</button>
      <?php endif; ?>
      <button class="boton boton-fantasma" type="submit" name="guardar" value="borrador">Guardar borrador</button>
      <button class="boton boton-principal" type="submit" name="guardar" value="publicar"
        data-confirmar="¿Publicar como versión <?= $siguiente ?>?" data-accion="Publicar"
        data-detalle="Será la versión que ve el público desde este momento. <?= $vigente ? 'La versión ' . (int) $vigente['version'] . ' queda en el historial.' : '' ?>"><?= icono('check', 16) ?> Publicar</button>
    </span>
  </div>
</form>
<?php pagina_fin('admin', ['https://cdnjs.cloudflare.com/ajax/libs/quill/2.0.2/quill.js', asset('assets/js/legales.js')]); ?>
