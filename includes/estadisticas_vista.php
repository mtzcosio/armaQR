<?php
/**
 * Piezas de la pantalla de estadísticas que comparten app/estadisticas.php y el ejemplo de
 * tipo.php: ubicación (países y ciudades) y mapa de calor por día de la semana y hora.
 * Reciben los desgloses con la forma de datos_desgloses().
 */

declare(strict_types=1);

require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/geoip.php';

const ESTADISTICAS_DIAS_SEMANA = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];

/** Barra horizontal con etiqueta y cantidad (el ancho es relativo a $maximo). */
function estadisticas_barra(string $etiqueta, int $n, int $maximo, string $detalle = ''): string
{
    $ancho = $maximo > 0 ? round($n / $maximo * 100) : 0;
    return '<div class="barra-h"><div class="barra-h-cabeza"><span>' . $etiqueta
        . ($detalle !== '' ? ' <small class="texto-gris">' . e($detalle) . '</small>' : '') . '</span><span>' . numero($n) . '</span></div>'
        . '<div class="medidor' . ($n === $maximo ? ' rosa' : '') . '"><i style="width:' . $ancho . '%"></i></div></div>';
}

/** Países y ciudades desde donde escanean. $atribucion: crédito de DB-IP (lo pide su licencia CC BY 4.0). */
function estadisticas_ubicacion(array $des, bool $atribucion = true): string
{
    $paises = $des['paises'] ?? [];
    $ciudades = $des['ciudades'] ?? [];
    $html = '<div class="ubicacion-rejilla"><div><h3 class="ubicacion-titulo">Países</h3><div class="barras-h">';
    if (!$paises) {
        $html .= '<p class="texto-gris texto-chico">Aún no hay escaneos con ubicación en este periodo.</p>';
    }
    $max = $paises ? max($paises) : 0;
    foreach ($paises as $codigo => $n) {
        $nombre = $codigo === 'Otros' ? 'Otros países' : geoip_bandera((string) $codigo) . ' ' . e(geoip_pais_nombre((string) $codigo));
        $html .= estadisticas_barra($nombre, (int) $n, (int) $max);
    }
    $html .= '</div></div><div><h3 class="ubicacion-titulo">Ciudades</h3><div class="barras-h">';
    if (!$ciudades) {
        $html .= '<p class="texto-gris texto-chico">Aún no hay escaneos con ciudad en este periodo.</p>';
    }
    $max = $ciudades ? max(array_column($ciudades, 'n')) : 0;
    foreach ($ciudades as $c) {
        $detalle = implode(', ', array_filter([$c['region'] !== $c['ciudad'] ? $c['region'] : null, $c['pais'] !== 'MX' ? $c['pais'] : null]));
        $html .= estadisticas_barra(e($c['ciudad']), (int) $c['n'], (int) $max, (string) $detalle);
    }
    $html .= '</div></div></div>';

    $notas = [];
    if (($des['sin_ubicacion'] ?? 0) > 0) {
        $notas[] = numero((int) $des['sin_ubicacion']) . ' escaneos sin ubicación (por ejemplo, desde redes privadas o antes de activarla).';
    }
    if ($atribucion) {
        $notas[] = 'Ubicación aproximada por IP, sin guardar la IP. <a href="https://db-ip.com" rel="noopener" target="_blank">IP Geolocation by DB-IP</a>';
    }
    return $html . ($notas ? '<p class="texto-chico texto-gris ubicacion-nota">' . implode('<br>', $notas) . '</p>' : '');
}

/** Mapa de calor [día 0 = lunes][hora]: tabla de 7 × 24 celdas con intensidad según los escaneos. */
function estadisticas_mapa_calor(array $mapa): string
{
    $maximo = 0;
    $pico = [0, 0];
    foreach ($mapa as $d => $horas) {
        foreach ($horas as $h => $n) {
            if ($n > $maximo) {
                $maximo = $n;
                $pico = [$d, $h];
            }
        }
    }
    $html = '<div class="mapa-calor-contenedor"><table class="mapa-calor"><caption class="oculto-visual">Escaneos por día de la semana y hora</caption>'
        . '<thead><tr><td></td>';
    for ($h = 0; $h < 24; $h++) {
        $html .= '<th scope="col"><span' . ($h % 3 !== 0 ? ' class="mapa-calor-tenue"' : '') . '>' . $h . '</span></th>';
    }
    $html .= '</tr></thead><tbody>';
    foreach (ESTADISTICAS_DIAS_SEMANA as $d => $dia) {
        $html .= '<tr><th scope="row"><abbr title="' . $dia . '">' . mb_substr($dia, 0, 3) . '</abbr></th>';
        for ($h = 0; $h < 24; $h++) {
            $n = (int) ($mapa[$d][$h] ?? 0);
            $nivel = $maximo > 0 && $n > 0 ? max(12, (int) round($n / $maximo * 100)) : 0;
            $texto = $dia . ' de ' . sprintf('%02d:00 a %02d:00', $h, ($h + 1) % 24) . ': ' . numero($n) . ($n === 1 ? ' escaneo' : ' escaneos');
            $html .= '<td style="--p:' . $nivel . '%" title="' . e($texto) . '"><span class="oculto-visual">' . e($texto) . '</span></td>';
        }
        $html .= '</tr>';
    }
    $html .= '</tbody></table></div>'
        . '<div class="mapa-calor-pie"><p class="texto-chico texto-gris">'
        . ($maximo > 0
            ? 'Momento con más escaneos: <strong>' . mb_strtolower(ESTADISTICAS_DIAS_SEMANA[$pico[0]]) . ' de ' . sprintf('%02d:00 a %02d:00', $pico[1], ($pico[1] + 1) % 24) . '</strong> (' . numero($maximo) . ').'
            : 'Aún no hay escaneos en este periodo.')
        . '</p><span class="mapa-calor-escala" aria-hidden="true">Menos <i style="--p:0%"></i><i style="--p:25%"></i><i style="--p:50%"></i><i style="--p:75%"></i><i style="--p:100%"></i> Más</span></div>';
    return $html;
}
