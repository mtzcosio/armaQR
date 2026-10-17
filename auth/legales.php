<?php

declare(strict_types=1);

/**
 * Documentos legales (POST con CSRF, solo administradores):
 *  - guardar con guardar=borrador | publicar
 *  - restaurar: copia una versión anterior como borrador para revisarla y publicarla
 *  - descartar: borra el borrador
 */

require_once dirname(__DIR__) . '/includes/seguridad.php';
require_once dirname(__DIR__) . '/includes/legales.php';
require_once dirname(__DIR__) . '/app/Core/Auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('admin/legales.php');
}
$admin = App\Core\Auth::requireAdmin();

$tipo = (string) ($_POST['tipo'] ?? '');
if (!isset(LEGAL_TIPOS[$tipo])) {
    flash('error', 'Elige un documento válido.');
    redirigir('admin/legales.php');
}
$volver = 'admin/legal.php?tipo=' . $tipo;
if (!csrf_valido($_POST['csrf_token'] ?? null)) {
    flash('error', 'La sesión del formulario expiró. Vuelve a intentarlo.');
    redirigir($volver);
}

$accion = (string) ($_POST['accion'] ?? 'guardar');

if ($accion === 'restaurar') {
    $version = legal_version($tipo, (int) ($_POST['version'] ?? 0));
    if (!$version) {
        flash('error', 'Esa versión no existe.');
        redirigir($volver);
    }
    legal_guardar_borrador($tipo, $version['titulo'], $version['contenido_html'], (int) $admin['id']);
    flash('ok', "Copiaste la versión {$version['version']} como borrador. Revísala y pulsa Publicar para que vuelva a estar vigente.");
    redirigir($volver);
}

if ($accion === 'descartar') {
    legal_descartar_borrador($tipo);
    flash('ok', 'Descartaste el borrador. El público sigue viendo la versión publicada.');
    redirigir($volver);
}

// Guardar
$titulo = mb_substr(trim((string) ($_POST['titulo'] ?? '')), 0, 150);
$html = legal_sanitizar((string) ($_POST['contenido_html'] ?? ''));
if ($titulo === '') {
    $titulo = LEGAL_TIPOS[$tipo]['titulo'];
}
$publicar = ($_POST['guardar'] ?? '') === 'publicar';

if (legal_vacio($html)) {
    // Se conserva lo escrito para no perderlo.
    $_SESSION['legal_formulario'] = ['tipo' => $tipo, 'titulo' => $titulo, 'contenido_html' => $html];
    flash('error', 'El documento está vacío. Escribe su contenido antes de guardarlo.');
    redirigir($volver);
}

if ($publicar) {
    try {
        $version = legal_publicar($tipo, $titulo, $html, (int) $admin['id']);
    } catch (Throwable $exception) {
        error_log('Error al publicar documento legal: ' . $exception->getMessage());
        $_SESSION['legal_formulario'] = ['tipo' => $tipo, 'titulo' => $titulo, 'contenido_html' => $html];
        flash('error', 'No se pudo publicar. Inténtalo de nuevo.');
        redirigir($volver);
    }
    flash('ok', "Publicaste la versión {$version}. Ya es la que ve el público; la anterior quedó en el historial.");
} else {
    legal_guardar_borrador($tipo, $titulo, $html, (int) $admin['id']);
    flash('ok', 'Guardaste el borrador. El público sigue viendo la versión publicada hasta que la publiques.');
}
redirigir($volver);
