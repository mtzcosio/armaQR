<?php
/**
 * Exporta a CSV los escaneos del periodo de app/estadisticas.php (mismos filtros: ?dias= y ?qr=),
 * uno por fila, para abrirlos en Excel. Solo con un plan con analítica y dentro de los días que
 * deja ver el plan. Nunca incluye la IP ni su hash.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/qr_csv.php';
require_once __DIR__ . '/../includes/qr_paginas.php';
require_once __DIR__ . '/../includes/estadisticas_vista.php';

$usuario = datos_usuario_actual();
$plan = $usuario['plan_datos'];
if (!$plan['permite_analitica']) {
    flash('error', 'Exportar estadísticas está disponible desde el plan Pro.');
    redirigir('app/estadisticas.php');
}

// Los mismos periodos y QR que puede ver en Estadísticas.
$retencion = $plan['dias_retencion_escaneos'];
$periodos = array_filter([7, 30, 90], fn($d) => datos_periodo_visible($retencion, $d)) ?: [7];
$dias = in_array((int) ($_GET['dias'] ?? 30), $periodos, true) ? (int) $_GET['dias'] : (in_array(30, $periodos, true) ? 30 : max($periodos));
$qr = null;
if ((int) ($_GET['qr'] ?? 0) > 0) {
    $qr = qr_del_usuario((int) $_GET['qr'], (int) $usuario['id']);
    if ($qr === null || $qr['tipo'] !== 'dinamico') {
        flash('error', 'Ese QR no existe o no es tuyo.');
        redirigir('app/estadisticas.php');
    }
}

$dispositivos = ['movil' => 'Móvil', 'tablet' => 'Tablet', 'escritorio' => 'Escritorio', 'otro' => 'Otro'];
$filas = [['Fecha', 'Hora', 'Día', 'QR', 'Liga corta', 'Tipo', 'País', 'Región', 'Ciudad', 'Dispositivo', 'Sistema operativo', 'Navegador', 'Idioma', 'Página de origen']];
foreach (datos_escaneos_detalle($dias, (int) $usuario['id'], $qr ? (int) $qr['id'] : null) as $e) {
    $momento = strtotime((string) $e['fecha_escaneo']);
    $filas[] = [
        date('Y-m-d', $momento), date('H:i:s', $momento), ESTADISTICAS_DIAS_SEMANA[(int) date('N', $momento) - 1],
        $e['qr'], $e['codigo_corto'] ? qr_liga_corta($e['codigo_corto']) : '', qr_tipo_nombre((string) $e['tipo_contenido']),
        $e['pais'] ? geoip_pais_nombre($e['pais']) : '', $e['region'] ?? '', $e['ciudad'] ?? '',
        $dispositivos[$e['dispositivo'] ?? ''] ?? '', $e['sistema_operativo'] ?? '', $e['navegador'] ?? '', $e['idioma'] ?? '', $e['referer'] ?? '',
    ];
}

$nombre = 'escaneos-' . ($qr ? (qr_pagina_nombre_archivo((string) $qr['nombre']) ?: 'qr') : 'todos') . '-' . $dias . 'dias-' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $nombre . '"');
header('Cache-Control: no-store');
echo qr_csv_escribir($filas);
