<?php
/**
 * Redireccionador de los QR dinámicos: /r/{codigo} (regla en .htaccess).
 * Registra el escaneo y manda al destino actual. Sin caché, para que un cambio de
 * destino aplique de inmediato y cada escaneo llegue al servidor.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/qr.php';

$codigo = (string) ($_GET['c'] ?? '');
$qr = qr_por_codigo($codigo);
$motivo = qr_motivo_sin_redireccion($qr);

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex');

if ($motivo === null) {
    if (!qr_es_robot((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''))) {
        try {
            qr_registrar_escaneo((int) $qr['id'], $_SERVER);
        } catch (Throwable $exception) {
            // Si falla el registro, el escaneo igual debe llevar a su destino.
            error_log('No se registró el escaneo del QR ' . $qr['id'] . ': ' . $exception->getMessage());
        }
    }
    header('Location: ' . $qr['url_destino'], true, 302);
    exit;
}

// No redirige: página breve para quien escaneó.
require_once __DIR__ . '/includes/layout.php';
http_response_code($motivo === 'pausado' ? 410 : 404);
pagina_inicio($motivo === 'pausado' ? 'Código no disponible' : 'Código no encontrado');
?>
<main id="contenido" class="acceso">
  <div class="acceso-form">
    <div class="acceso-caja">
      <a class="logo" href="<?= url('index.php') ?>"><?= marca(28) ?><?= e(nombre_portal()) ?></a>
      <?php if ($motivo === 'pausado'): ?>
        <h1>Este código no está disponible</h1>
        <p class="texto-gris mt">Quien lo imprimió lo pausó por ahora. Si lo necesitas, pregúntale al negocio que te lo compartió.</p>
      <?php else: ?>
        <h1>Este código no existe</h1>
        <p class="texto-gris mt">Puede que se haya eliminado o que la liga esté incompleta. Revisa que la dirección sea correcta.</p>
      <?php endif; ?>
      <a class="boton boton-principal boton-ancho" href="<?= url('index.php') ?>">Crear mis propios QR</a>
    </div>
  </div>
</main>
<?php pagina_fin(); ?>
