<?php

declare(strict_types=1);

/**
 * Acciones sobre los QR (POST con CSRF):
 *  - guardar:  crea un QR o edita uno propio. Un estático solo cambia nombre, diseño y logo;
 *              un dinámico además su contenido: una URL de destino (queda en qr_historial_destinos)
 *              o su página propia con archivos (includes/qr_paginas.php).
 *  - pausar / reanudar: un dinámico deja de redirigir o vuelve a hacerlo. El dueño, o un
 *              administrador desde Administración › Códigos QR (moderación).
 *  - eliminar: borrado lógico; un dinámico deja de redirigir.
 */

require_once dirname(__DIR__) . '/includes/seguridad.php';
require_once dirname(__DIR__) . '/includes/qr_paginas.php';
require_once dirname(__DIR__) . '/app/Core/Auth.php';

use App\Core\Auth;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('app/index.php');
}

$sesion = Auth::requireUser();
// Si los archivos rebasan post_max_size, PHP descarta todo el formulario (incluido el CSRF).
if (!$_POST && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    flash('error', 'Los archivos que subiste pesan demasiado juntos. Súbelos en menos cantidad o más ligeros.');
    redirigir('app/crear.php');
}
if (!csrf_valido($_POST['csrf_token'] ?? null)) {
    flash('error', 'La sesión del formulario expiró. Vuelve a intentarlo.');
    redirigir('app/index.php');
}

$pdo = datos_pdo();
$accion = (string) ($_POST['accion'] ?? 'guardar');
$id = (int) ($_POST['id'] ?? 0);
$usuario = datos_usuario_actual();
$plan = $usuario['plan_datos'];

/* ---------- Pausar / reanudar / eliminar ---------- */
if (in_array($accion, ['pausar', 'reanudar', 'eliminar'], true)) {
    $desdeAdmin = ($_POST['origen'] ?? '') === 'admin' && Auth::isAdminRole($sesion);
    $volver = $desdeAdmin ? 'admin/qrs.php' : 'app/index.php';
    $qr = $desdeAdmin
        ? (datos_consulta('SELECT * FROM qr_codigos WHERE id = :id AND eliminado = 0', [':id' => $id])[0] ?? null)
        : qr_del_usuario($id, (int) $usuario['id']);
    if ($qr === null || ($desdeAdmin && $accion === 'eliminar')) {
        flash('error', 'Ese QR no existe o no es tuyo.');
        redirigir($volver);
    }
    if ($accion !== 'eliminar' && $qr['tipo'] !== 'dinamico') {
        flash('error', 'Solo los QR dinámicos se pueden pausar.');
        redirigir($volver);
    }

    if ($accion === 'reanudar') {
        // Al reanudar vuelve a contar como activo: respeta el límite del plan del dueño.
        if (!$desdeAdmin && ($mensaje = qr_limite_alcanzado((int) $usuario['id'], $plan, 'dinamico')) !== null) {
            flash('error', $mensaje);
            redirigir($volver);
        }
        $pdo->prepare('UPDATE qr_codigos SET activo = 1, usuario_modificacion = :u WHERE id = :id')->execute([':u' => $sesion['id'], ':id' => $id]);
        flash('ok', "«{$qr['nombre']}» vuelve a redirigir.");
    } elseif ($accion === 'pausar') {
        $pdo->prepare('UPDATE qr_codigos SET activo = 0, usuario_modificacion = :u WHERE id = :id')->execute([':u' => $sesion['id'], ':id' => $id]);
        flash('ok', "Pausaste «{$qr['nombre']}». Quien lo escanee verá que no está disponible.");
    } else {
        $pdo->prepare('UPDATE qr_codigos SET eliminado = 1, activo = 0, usuario_modificacion = :u WHERE id = :id')->execute([':u' => $sesion['id'], ':id' => $id]);
        flash('ok', "Eliminaste «{$qr['nombre']}»" . ($qr['tipo'] === 'dinamico' ? '. Su liga corta dejó de funcionar.' : '.'));
    }
    redirigir($volver);
}

/* ---------- Guardar (crear o editar) ---------- */
$existente = $id > 0 ? qr_del_usuario($id, (int) $usuario['id']) : null;
if ($id > 0 && $existente === null) {
    flash('error', 'Ese QR no existe o no es tuyo.');
    redirigir('app/index.php');
}
$volver = 'app/crear.php' . ($existente ? '?editar=' . $id : '');

/** Vuelve al formulario con el error y lo capturado (para no perderlo). */
$error = function (string $mensaje) use ($volver): never {
    $_SESSION['qr_formulario'] = array_diff_key($_POST, ['csrf_token' => 1]);
    flash('error', $mensaje);
    redirigir($volver);
};

$nombre = mb_substr(trim((string) ($_POST['nombre'] ?? '')), 0, 120);
if ($nombre === '') {
    $error('Escribe un nombre para tu QR.');
}
$tipo = $existente['tipo'] ?? (($_POST['tipo'] ?? '') === 'dinamico' ? 'dinamico' : 'estatico');
$tipoContenido = (string) ($_POST['tipo_contenido'] ?? ($existente['tipo_contenido'] ?? 'url'));
$diseno = qr_diseno($_POST);

if (!$existente && ($mensaje = qr_limite_alcanzado((int) $usuario['id'], $plan, $tipo)) !== null) {
    $error($mensaje);
}

// Contenido: un estático guardado no cambia (ya puede estar impreso). Un dinámico puede ser una
// redirección o una página propia (PDF, menú…), cuyos archivos se guardan aquí mismo.
$contenido = null;
$camposAnteriores = $existente ? qr_contenido_guardado($existente['contenido'])['campos'] : [];
if (!$existente || $tipo === 'dinamico') {
    if (!isset(($tipo === 'dinamico' ? QR_TIPOS_DINAMICOS : QR_TIPOS)[$tipoContenido])) {
        $error('Elige un tipo de contenido válido.');
    }
    try {
        $contenido = $tipo === 'dinamico'
            ? qr_pagina_contenido($tipoContenido, $_POST, $_FILES, $existente && $existente['tipo_contenido'] === $tipoContenido ? $camposAnteriores : [])
            : qr_contenido($tipoContenido, $_POST);
    } catch (QrInvalido $exception) {
        $error($exception->getMessage());
    }
}
$archivosNuevos = $contenido ? array_diff(qr_pagina_rutas($contenido['campos']), qr_pagina_rutas($camposAnteriores)) : [];

// Logo: solo en planes que lo incluyen.
$logo = $existente['logo_ruta'] ?? null;
$logoNuevo = null;
$subioLogo = ($_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
if ($subioLogo && !$plan['permite_logo']) {
    array_map('archivo_borrar', $archivosNuevos);
    $error('El logo al centro está disponible desde el plan Pro.');
}
try {
    $logoNuevo = $subioLogo ? imagen_guardar_subida($_FILES['logo'], 'qr', 'El logo', QR_LOGO_MAX_BYTES, 2048) : null;
} catch (RuntimeException $exception) {
    array_map('archivo_borrar', $archivosNuevos);
    $error($exception->getMessage());
}
if ($logoNuevo !== null || !empty($_POST['quitar_logo'])) {
    $logo = $logoNuevo;
}

$jsonContenido = $contenido ? json_encode(['tipo' => $tipoContenido, 'campos' => $contenido['campos'], 'valor' => $contenido['texto']], JSON_UNESCAPED_UNICODE) : null;

try {
    $pdo->beginTransaction();
    if ($existente) {
        $sets = 'nombre = :nombre, diseno = :diseno, logo_ruta = :logo, usuario_modificacion = :u';
        $params = [':nombre' => $nombre, ':diseno' => json_encode($diseno), ':logo' => $logo, ':u' => $usuario['id'], ':id' => $id];
        if ($tipo === 'dinamico') {
            $sets .= ', tipo_contenido = :tc, contenido = :contenido, url_destino = :destino';
            $params += [':tc' => $tipoContenido, ':contenido' => $jsonContenido, ':destino' => $contenido['texto']];
            if ($contenido['texto'] !== '' && $contenido['texto'] !== $existente['url_destino']) {
                $pdo->prepare('INSERT INTO qr_historial_destinos (qr_id, url_anterior, url_nueva, usuario_creacion) VALUES (:qr, :antes, :nueva, :u)')
                    ->execute([':qr' => $id, ':antes' => $existente['url_destino'], ':nueva' => $contenido['texto'], ':u' => $usuario['id']]);
            }
        }
        $pdo->prepare("UPDATE qr_codigos SET {$sets} WHERE id = :id")->execute($params);
    } else {
        $id = qr_insertar((int) $usuario['id'], $nombre, $tipo, $tipoContenido, $contenido, $diseno, $logo);
        qr_registrar_uso((int) $usuario['id'], $tipo);
    }
    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    imagen_borrar($logoNuevo);
    array_map('archivo_borrar', $archivosNuevos);
    error_log('Error al guardar QR: ' . $exception->getMessage());
    $error('No se pudo guardar el QR. Inténtalo de nuevo.');
}

// El logo y los archivos de la página que ya no se usan.
if ($existente && $existente['logo_ruta'] !== $logo) {
    imagen_borrar($existente['logo_ruta']);
}
if ($contenido) {
    array_map('archivo_borrar', array_diff(qr_pagina_rutas($camposAnteriores), qr_pagina_rutas($contenido['campos'])));
}

unset($_SESSION['qr_formulario']);
flash('ok', $existente
    ? 'Guardaste los cambios' . ($tipo === 'dinamico' ? '. El código impreso ya lleva al destino nuevo.' : '.')
    : ($tipo === 'dinamico' ? 'Creaste tu QR dinámico. Ya puedes descargarlo e imprimirlo.' : 'Guardaste tu QR. Ya puedes descargarlo.'));
redirigir('app/crear.php?editar=' . $id);
