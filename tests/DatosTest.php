<?php

declare(strict_types=1);

// Prueba de solo lectura contra la base configurada en database/.env.
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/datos.php';

$fallas = [];

$planes = datos_planes();
if (!isset($planes['gratis'])) {
    $fallas[] = "datos_planes() debe incluir el plan 'gratis' indexado por código.";
}
foreach ($planes as $codigo => $p) {
    if ($p['codigo'] !== $codigo || !is_float($p['precio_mensual']) || !is_bool($p['permite_analitica']) || $p['caracteristicas'] === []) {
        $fallas[] = "El plan {$codigo} no tiene la forma esperada.";
    }
}

foreach (datos_usuarios() as $u) {
    foreach (['id', 'nombre', 'email', 'plan', 'estatus', 'fecha_registro', 'total_qr', 'total_escaneos', 'total_pagado', 'periodicidad'] as $campo) {
        if (!array_key_exists($campo, $u)) {
            $fallas[] = "datos_usuarios() no devuelve el campo {$campo}.";
            break 2;
        }
    }
}

$serie = datos_serie_escaneos(30);
if (count($serie) !== 30 || array_key_last($serie) !== date('Y-m-d')) {
    $fallas[] = 'datos_serie_escaneos() debe devolver 30 días terminando hoy.';
}
$anterior = datos_serie_escaneos(7, null, null, 7);
if (count($anterior) !== 7 || array_key_last($anterior) !== date('Y-m-d', strtotime('-7 days'))) {
    $fallas[] = 'El periodo anterior debe terminar 7 días antes de hoy.';
}

// Escaneos por QR: un periodo nunca suma más que el histórico, y cada fila trae total y último.
$duenoQr = (int) (datos_consulta('SELECT usuario_id FROM qr_escaneos e JOIN qr_codigos q ON q.id = e.qr_id LIMIT 1')[0]['usuario_id'] ?? 0);
$historico = datos_escaneos_por_qr(null, $duenoQr);
$semana = datos_escaneos_por_qr(7, $duenoQr);
foreach ($semana as $qrId => $fila) {
    if (!isset($historico[$qrId]) || $fila['total'] > $historico[$qrId]['total'] || $fila['ultimo'] < date('Y-m-d', strtotime('-6 days'))) {
        $fallas[] = 'datos_escaneos_por_qr() debe limitarse al periodo pedido.';
        break;
    }
}

$des = datos_desgloses(30);
if (count($des['horas']) !== 24 || !is_array($des['ciudades'])) {
    $fallas[] = 'datos_desgloses() no tiene la forma esperada.';
}

if (count(datos_ingresos_mensuales(6)) !== 6) {
    $fallas[] = 'datos_ingresos_mensuales(6) debe devolver 6 meses.';
}

$r = datos_resumen_admin();
foreach (['total', 'de_pago', 'gratis', 'sin_verificar', 'suspendidos', 'mrr', 'ingresos_mes', 'qr_total', 'escaneos_total', 'pagos_fallidos'] as $k) {
    if (!array_key_exists($k, $r)) {
        $fallas[] = "datos_resumen_admin() no devuelve {$k}.";
    }
}

if (datos_qr_contenido_texto('"hola"') !== 'hola' || datos_qr_contenido_texto('{"url":"https://x.mx"}') !== 'https://x.mx') {
    $fallas[] = 'datos_qr_contenido_texto() no interpreta el JSON de contenido.';
}

if ($fallas) {
    fwrite(STDERR, implode("\n", $fallas) . "\n");
    exit(1);
}
echo "Datos reales: " . count($planes) . " planes, forma de usuarios, series y resumen OK\n";
