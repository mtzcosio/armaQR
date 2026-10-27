<?php
/**
 * Liga corta de los QR dinámicos: /r/{codigo} (regla en .htaccess).
 * Registra el escaneo y manda al destino actual o, en los tipos de página (PDF, menú, cupón…),
 * muestra la página del QR. Sin caché, para que un cambio aplique de inmediato y cada escaneo
 * llegue al servidor.
 *  - ?descargar=vcf|ics: contacto o evento de la página (no cuenta como escaneo).
 *  - ?vista_previa=1: el dueño o un admin ve la página sin contar escaneo, aunque esté pausado.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/qr_paginas.php';

$codigo = (string) ($_GET['c'] ?? '');
$qr = qr_por_codigo($codigo);
$motivo = qr_motivo_sin_redireccion($qr);

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex');

$vistaPrevia = false;
if (isset($_GET['vista_previa']) && $qr !== null && $motivo !== 'no_existe') {
    require_once __DIR__ . '/includes/seguridad.php';
    $sesion = App\Core\Auth::user();
    $vistaPrevia = $sesion !== null && ((int) $sesion['id'] === (int) $qr['usuario_id'] || App\Core\Auth::isAdminRole($sesion));
}

if ($motivo === null || $vistaPrevia) {
    $tipo = (string) $qr['tipo_contenido'];
    $campos = qr_contenido_guardado($qr['contenido'])['campos'];
    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');

    // Descargas de la página: contacto (.vcf) y evento (.ics).
    $descarga = (string) ($_GET['descargar'] ?? '');
    if (($descarga === 'vcf' && $tipo === 'vcard') || ($descarga === 'ics' && $tipo === 'evento')) {
        $nombre = qr_pagina_nombre_archivo((string) ($campos['nombre'] ?? $campos['titulo'] ?? '')) ?: $descarga;
        header('Content-Type: ' . ($descarga === 'vcf' ? 'text/vcard' : 'text/calendar') . '; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nombre . '.' . $descarga . '"');
        echo $descarga === 'vcf' ? qr_pagina_vcard($campos) : qr_pagina_ics($campos, $qr['codigo_corto'] . '@' . dominio_portal());
        exit;
    }

    if (!$vistaPrevia && !qr_es_robot($ua)) {
        try {
            qr_registrar_escaneo((int) $qr['id'], $_SERVER);
        } catch (Throwable $exception) {
            // Si falla el registro, el escaneo igual debe llevar a su destino.
            error_log('No se registró el escaneo del QR ' . $qr['id'] . ': ' . $exception->getMessage());
        }
    }

    $destino = match (true) {
        qr_tipo_usa_url($tipo) => (string) $qr['url_destino'],
        $tipo === 'apps' => qr_pagina_destino_app($campos, $ua),
        default => null,
    };
    if ($destino !== null && $destino !== '') {
        header('Location: ' . $destino, true, 302);
        exit;
    }
    require_once __DIR__ . '/includes/qr_pagina_vista.php';
    qr_pagina_mostrar($qr, $vistaPrevia);
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
