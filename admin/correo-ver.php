<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/correo.php';
App\Core\Auth::requireAdmin();

$id = (int) ($_GET['id'] ?? 0);
$p = correo_tablas_listas() && $id > 0
    ? (datos_consulta('SELECT * FROM plantillas_correo WHERE id = :id', [':id' => $id])[0] ?? null)
    : null;
if (!$p) {
    flash('error', 'La plantilla no existe o ya se eliminó.');
    redirigir('admin/correos.php');
}

// Se muestra como llegaría, con datos de ejemplo (las variables sin dato quedan visibles).
$correo = correo_componer($p, correo_datos_ejemplo(), null, false);
$smtp = correo_config_smtp();
$deNombre = $p['remitente_nombre'] !== '' ? $p['remitente_nombre'] : $smtp['from_nombre'];
$deCorreo = $p['remitente_correo'] !== '' ? $p['remitente_correo'] : ($smtp['from_email'] ?: 'sin configurar');
$sistema = correo_codigos_sistema()[$p['codigo']] ?? null;

pagina_inicio('Ver correo', 'admin', 'correos');
?>
<div class="encabezado">
  <div>
    <a class="miga" href="<?= url('admin/correos.php') ?>">← Correos</a>
    <h1><?= e($p['nombre']) ?></h1>
    <p><?= insignia_estatus($p['estatus']) ?> · <code class="codigo"><?= e($p['codigo']) ?></code> · <?= e($p['tipo']) ?> · Actualizada el <?= e(fecha_corta($p['fecha_modificacion'])) ?></p>
  </div>
  <div class="encabezado-acciones">
    <a class="boton boton-principal" href="<?= url('admin/correo.php?id=' . $id) ?>"><?= icono('editar', 17) ?> Editar</a>
  </div>
</div>

<?php if ($sistema): ?>
  <?= aviso('info', 'Correo del sistema. Se envía ' . mb_strtolower(rtrim($sistema['cuando'], '.')) . '.'
      . ($p['estatus'] === 'activa' ? '' : ' Como no está activa, hoy se envía el correo predeterminado.')) ?>
<?php endif; ?>

<section class="caja">
  <div class="caja-cuerpo">
    <dl class="previa-sobre">
      <dt>De</dt><dd><?= e($deNombre . ' <' . $deCorreo . '>') ?></dd>
      <dt>Asunto</dt><dd><?= e($correo['asunto']) ?></dd>
      <dt>Preheader</dt><dd><?= $p['preheader'] !== '' ? e(html_entity_decode(correo_render($p['preheader'], correo_datos_ejemplo()), ENT_QUOTES | ENT_HTML5, 'UTF-8')) : '<span class="texto-gris">Sin preheader</span>' ?></dd>
    </dl>
    <iframe class="previa-correo" title="Contenido del correo" sandbox="" srcdoc="<?= e($correo['html']) ?>"></iframe>
    <p class="texto-chico texto-gris mt">Con datos de ejemplo.</p>
  </div>
</section>
<?php pagina_fin('admin'); ?>
