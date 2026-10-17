<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/legales.php';
App\Core\Auth::requireAdmin();

$mensaje = flash_tomar();
$listo = legal_tabla_lista();

pagina_inicio('Legales', 'admin', 'legales');
?>
<div class="encabezado">
  <div>
    <h1>Legales</h1>
    <p>Términos y condiciones y aviso de privacidad. Cada publicación crea una versión nueva y las anteriores quedan en el historial.</p>
  </div>
</div>

<?php if ($mensaje): ?><?= aviso($mensaje['tipo'], $mensaje['mensaje']) ?><?php endif; ?>
<?php if (!$listo): ?>
  <?= aviso('error', 'Falta la tabla de documentos legales. Ejecuta en el servidor: php database/migrate_documentos_legales.php') ?>
<?php else: ?>
  <?= aviso('info', 'El texto inicial es una propuesta redactada a partir de cómo funciona el portal. Pide a un abogado que lo revise antes de usarlo con clientes reales.') ?>

  <div class="rejilla-mitad">
    <?php foreach (LEGAL_TIPOS as $tipo => $info):
        $vigente = legal_publicado($tipo);
        $borrador = legal_borrador($tipo);
        $historial = legal_historial($tipo);
    ?>
      <section class="caja">
        <div class="caja-cabeza">
          <div>
            <h2><?= e($info['titulo']) ?></h2>
            <p><?= $vigente
                ? 'Versión ' . (int) $vigente['version'] . ' vigente desde el ' . e(fecha_corta($vigente['publicado_en']))
                : 'Sin publicar: la página pública dice que se publicará próximamente.' ?></p>
          </div>
          <div class="encabezado-acciones">
            <?php if ($vigente): ?><a class="boton boton-fantasma boton-chico" href="<?= url($info['ruta']) ?>" target="_blank" rel="noopener"><?= icono('externo', 15) ?> Ver página</a><?php endif; ?>
            <a class="boton boton-principal boton-chico" href="<?= url('admin/legal.php?tipo=' . $tipo) ?>"><?= icono('editar', 15) ?> <?= $vigente ? 'Actualizar' : 'Crear' ?></a>
          </div>
        </div>
        <?php if ($borrador): ?>
          <div class="caja-cuerpo"><?= aviso('info', 'Hay un borrador sin publicar, guardado el ' . fecha_corta($borrador['fecha_modificacion']) . ($borrador['autor'] ? ' por ' . $borrador['autor'] : '') . '. El público sigue viendo la versión publicada.') ?></div>
        <?php endif; ?>
        <?php if ($historial): ?>
          <div class="tabla-envoltura">
            <table class="tabla">
              <thead><tr><th>Versión</th><th>Publicada</th><th>Por</th><th><span class="oculto-visual">Acciones</span></th></tr></thead>
              <tbody>
                <?php foreach ($historial as $h): ?>
                  <tr>
                    <td><strong><?= (int) $h['version'] ?></strong><?= $vigente && (int) $h['version'] === (int) $vigente['version'] ? ' ' . insignia_estatus('activa') : '' ?></td>
                    <td class="sin-salto texto-gris"><?= e(fecha_corta($h['publicado_en'])) ?> · <?= e(date('H:i', strtotime($h['publicado_en']))) ?></td>
                    <td class="texto-gris"><?= e($h['autor'] ?? '—') ?></td>
                    <td class="acciones"><a class="boton-icono" href="<?= url('admin/legal.php?tipo=' . $tipo . '&version=' . (int) $h['version']) ?>" title="Ver versión <?= (int) $h['version'] ?>" aria-label="Ver versión <?= (int) $h['version'] ?>"><?= icono('ojo') ?></a></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php pagina_fin('admin'); ?>
