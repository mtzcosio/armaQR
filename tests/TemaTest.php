<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/tema.php';

$fallas = [];

// Registro: valores predeterminados válidos y claves únicas.
$colores = tema_colores_planos();
foreach ($colores as $variable => [$etiqueta, $uso, $claro, $oscuro]) {
    if (!tema_color_valido($claro) || !tema_color_valido($oscuro)) {
        $fallas[] = "Predeterminado inválido en --{$variable}.";
    }
}
if (count(array_unique(tema_claves())) !== count($colores) * 2) {
    $fallas[] = 'tema_claves() debe tener una clave clara y una oscura por color.';
}

// Valores efectivos: guardado válido > predeterminado; inválido se ignora.
$config = [tema_clave('rosa', false) => '#123abc', tema_clave('rosa', true) => 'red; } body {'];
$claro = tema_valores($config, false);
$oscuro = tema_valores($config, true);
if ($claro['rosa'] !== '#123ABC' || $oscuro['rosa'] !== $colores['rosa'][3]) {
    $fallas[] = 'tema_valores() no combina bien lo guardado con los predeterminados.';
}

// CSS generado: el bloque oscuro solo existe si el modo no está desactivado.
$cssOpcional = tema_css(['tema_oscuro' => 'opcional']);
$cssApagado = tema_css(['tema_oscuro' => 'desactivado']);
if (!str_contains($cssOpcional, '[data-tema="oscuro"]') || str_contains($cssApagado, '[data-tema="oscuro"]')) {
    $fallas[] = 'tema_css() debe incluir el tema oscuro solo cuando está permitido.';
}
if (str_contains(tema_css($config), 'body {')) {
    $fallas[] = 'Un valor de color inválido no debe llegar al CSS.';
}
if (tema_modo(['tema_oscuro' => 'otro']) !== TEMA_MODO_PREDETERMINADO) {
    $fallas[] = 'Un modo desconocido debe usar el predeterminado.';
}
if (!str_contains(tema_script(['tema_oscuro' => 'automatico']), 'true && matchMedia')
    || !str_contains(tema_script(['tema_oscuro' => 'opcional']), 'false && matchMedia')) {
    $fallas[] = 'Solo el modo automático debe seguir la preferencia del sistema.';
}
// El botón debe funcionar desde el script de <head>, sin depender de app.js,
// y app.js no debe registrar un segundo clic (lo anularía).
$script = tema_script(['tema_oscuro' => 'opcional']);
if (!str_contains($script, '[data-cambiar-tema]') || !str_contains($script, 'localStorage.setItem')) {
    $fallas[] = 'El script de <head> debe atender el botón de tema y guardar la elección.';
}
if (str_contains((string) file_get_contents(dirname(__DIR__) . '/assets/js/app.js'), "localStorage.setItem('tema'")) {
    $fallas[] = 'app.js no debe cambiar el tema por su cuenta: lo hace el script de <head>.';
}

// Hoja de estilos: toda variable usada está definida y no hay colores fijos nuevos.
$css = (string) file_get_contents(dirname(__DIR__) . '/assets/css/app.css');
preg_match_all('/--([a-z0-9-]+)\s*:/', $css, $definidas);
preg_match_all('/var\(--([a-z0-9-]+)\)/', $css, $usadas);
$faltantes = array_diff(array_unique($usadas[1]), $definidas[1], array_keys($colores));
if ($faltantes) {
    $fallas[] = 'Variables CSS usadas sin definir: ' . implode(', ', $faltantes);
}
foreach (array_keys($colores) as $variable) {
    if (!in_array($variable, $definidas[1], true)) {
        $fallas[] = "La hoja de estilos no define el predeterminado de --{$variable}.";
    }
}
// Fuera de :root solo se permiten el blanco de fondo de los QR, la perilla y la máscara.
$cuerpo = substr($css, strpos($css, '*, *::before'));
preg_match_all('/#[0-9a-fA-F]{3,8}\b|rgba\([^)]*\)/', $cuerpo, $literales);
$permitidos = ['#fff', '#000', 'rgba(0,0,0,.2)'];
$extra = array_diff(array_unique($literales[0]), $permitidos);
if ($extra) {
    $fallas[] = 'Colores fijos en app.css (usa variables del tema): ' . implode(', ', $extra);
}

if ($fallas) {
    fwrite(STDERR, implode("\n", $fallas) . "\n");
    exit(1);
}
echo 'Tema: ' . count($colores) . " colores configurables, claro/oscuro y CSS sin colores fijos: OK\n";
