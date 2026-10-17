<?php

declare(strict_types=1);

/**
 * Crear QR desde CSV.
 *  GET  ?plantilla=1  → descarga la plantilla de ejemplo.
 *  GET  ?resultado=1  → descarga un CSV con los QR del último archivo (nombre, liga corta, destino).
 *  POST (archivo)     → valida todo el archivo y crea los QR, o no crea ninguno si hay errores.
 */

require_once dirname(__DIR__) . '/includes/seguridad.php';
require_once dirname(__DIR__) . '/includes/qr_csv.php';
require_once dirname(__DIR__) . '/app/Core/Auth.php';

App\Core\Auth::requireUser();
$usuario = datos_usuario_actual();
$plan = $usuario['plan_datos'];

/** Envía un CSV como descarga y termina. */
$descargar = function (string $nombre, array $filas): never {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $nombre . '"');
    header('Cache-Control: no-store');
    echo qr_csv_escribir($filas);
    exit;
};

if (($_GET['plantilla'] ?? '') === '1') {
    $descargar('plantilla-qr.csv', [
        ['nombre', 'destino', 'tipo', 'color', 'fondo'],
        ['Menú terraza', 'https://tunegocio.mx/menu', 'dinámico', '#172036', '#ffffff'],
        ['Pedidos por WhatsApp', 'https://wa.me/525512345678', 'dinámico', '', ''],
        ['Catálogo 2026', 'tunegocio.mx/catalogo.pdf', 'estático', '', ''],
    ]);
}

if (($_GET['resultado'] ?? '') === '1') {
    $ids = array_map('intval', $_SESSION['csv_ultimo'] ?? []);
    if (!$ids) {
        redirigir('app/csv.php');
    }
    $marcadores = implode(',', array_fill(0, count($ids), '?'));
    $stmt = datos_pdo()->prepare("SELECT nombre, tipo, codigo_corto, url_destino, contenido FROM qr_codigos WHERE usuario_id = ? AND eliminado = 0 AND id IN ({$marcadores}) ORDER BY id");
    $stmt->execute(array_merge([(int) $usuario['id']], $ids));
    $filas = [['nombre', 'tipo', 'liga corta', 'destino']];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $q) {
        $filas[] = [$q['nombre'], $q['tipo'] === 'dinamico' ? 'dinámico' : 'estático',
                    $q['codigo_corto'] ? qr_liga_corta($q['codigo_corto']) : '',
                    $q['url_destino'] ?? qr_contenido_guardado($q['contenido'])['valor']];
    }
    $descargar('qr-creados.csv', $filas);
}

/* ---------- Subir archivo ---------- */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('app/csv.php');
}
if (!csrf_valido($_POST['csrf_token'] ?? null)) {
    flash('error', 'La sesión del formulario expiró. Vuelve a intentarlo.');
    redirigir('app/csv.php');
}
if (!$plan['permite_masivo']) {
    flash('error', 'Crear QR desde CSV está disponible en el plan Negocio.');
    redirigir('app/csv.php');
}

$archivo = $_FILES['archivo'] ?? null;
$nombreArchivo = mb_substr(basename((string) ($archivo['name'] ?? 'archivo.csv')), 0, 120);
$terminar = function (array $resultado) use ($nombreArchivo): never {
    $_SESSION['csv_resultado'] = $resultado + ['archivo' => $nombreArchivo];
    redirigir('app/csv.php');
};

if (!$archivo || ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    $terminar(['errores' => ['Elige un archivo CSV.']]);
}
if ($archivo['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($archivo['tmp_name'])) {
    $terminar(['errores' => ['El archivo no se recibió bien. Vuelve a elegirlo.']]);
}
if ((int) $archivo['size'] > QR_CSV_MAX_BYTES) {
    $terminar(['errores' => ['El archivo pesa más de 1 MB. Divídelo en varios archivos.']]);
}
if (!preg_match('/\.(csv|txt)$/i', $nombreArchivo)) {
    $terminar(['errores' => ['Sube un archivo .csv. En Excel: Archivo › Guardar como › CSV (delimitado por comas).']]);
}

$lectura = qr_csv_leer((string) file_get_contents($archivo['tmp_name']));
if ($lectura['error'] !== null) {
    $terminar(['errores' => [$lectura['error']]]);
}

$permiteDinamicos = $plan['max_qr_dinamicos'] !== 0;
$tipoPredeterminado = ($_POST['tipo'] ?? '') === 'estatico' || !$permiteDinamicos ? 'estatico' : 'dinamico';
$validacion = qr_csv_validar($lectura['filas'], $tipoPredeterminado, $permiteDinamicos);
if ($validacion['errores']) {
    $terminar(['errores' => $validacion['errores'], 'filas' => count($lectura['filas'])]);
}

// Límites del plan con el total del archivo.
$porTipo = array_count_values(array_column($validacion['qr'], 'tipo'));
$nuevosDinamicos = $porTipo['dinamico'] ?? 0;
$nuevosEstaticos = $porTipo['estatico'] ?? 0;
$errores = [];
if ($nuevosDinamicos && $plan['max_qr_dinamicos'] !== null) {
    $libres = $plan['max_qr_dinamicos'] - qr_dinamicos_activos((int) $usuario['id']);
    if ($nuevosDinamicos > $libres) {
        $errores[] = "El archivo tiene {$nuevosDinamicos} QR dinámicos y a tu plan le quedan " . max(0, $libres) . '.';
    }
}
if ($nuevosEstaticos && $plan['max_qr_estaticos_mes'] !== null) {
    $libres = $plan['max_qr_estaticos_mes'] - qr_estaticos_del_mes((int) $usuario['id']);
    if ($nuevosEstaticos > $libres) {
        $errores[] = "El archivo tiene {$nuevosEstaticos} QR estáticos y este mes te quedan " . max(0, $libres) . '.';
    }
}
if ($errores) {
    $terminar(['errores' => $errores, 'filas' => count($lectura['filas'])]);
}

// Todo o nada.
$pdo = datos_pdo();
$creados = [];
try {
    $pdo->beginTransaction();
    foreach ($validacion['qr'] as $q) {
        $creados[] = qr_insertar((int) $usuario['id'], $q['nombre'], $q['tipo'], 'url', $q['contenido'], $q['diseno']);
    }
    if ($nuevosDinamicos) qr_registrar_uso((int) $usuario['id'], 'dinamico', $nuevosDinamicos);
    if ($nuevosEstaticos) qr_registrar_uso((int) $usuario['id'], 'estatico', $nuevosEstaticos);
    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error al crear QR desde CSV: ' . $exception->getMessage());
    $terminar(['errores' => ['No se pudieron crear los QR. No se guardó ninguno; inténtalo de nuevo.']]);
}

$terminar(['creados' => $creados]);
