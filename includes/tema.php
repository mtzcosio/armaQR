<?php
/**
 * TEMA VISUAL
 * --------------------------------------------------------------------
 * Fuente única de los colores de la aplicación. Cada color es una
 * variable CSS (--clave) con un valor para el tema claro y otro para el
 * oscuro. Los administradores pueden cambiarlos en Configuración › Colores;
 * aquí solo viven los valores predeterminados.
 */

declare(strict_types=1);

/** Modos del tema oscuro que puede elegir el administrador. */
const TEMA_MODOS = [
    'desactivado' => 'Desactivado: el portal siempre se ve claro',
    'opcional'    => 'Opcional: cada usuario puede activarlo con el botón de luna',
    'automatico'  => 'Automático: sigue al sistema del usuario y puede cambiarlo',
];
const TEMA_MODO_PREDETERMINADO = 'opcional';

/**
 * Colores agrupados: [variable => [etiqueta, uso, claro, oscuro]].
 */
function tema_colores(): array
{
    return [
        'Marca' => [
            'rosa'         => ['Acento', 'Botones principales, cifras destacadas y marca', '#E4007C', '#E4007C'],
            'rosa-oscuro'  => ['Acento intenso', 'Enlaces, hover de botones y texto sobre acento suave', '#B8006A', '#FF8CC3'],
            'rosa-claro'   => ['Acento suave', 'Fondos de avisos de mejora, avatar y menú activo', '#FDE7F2', '#3A1730'],
            'sobre-acento' => ['Texto sobre acento', 'Texto de los botones principales', '#FFFFFF', '#FFFFFF'],
        ],
        'Base' => [
            'blanco'     => ['Superficie', 'Tarjetas, campos, tablas y fondo de la página pública', '#FFFFFF', '#171E2E'],
            'papel'      => ['Fondo de los paneles', 'Fondo de los paneles y secciones alternas', '#F4F5F9', '#0E1320'],
            'tinta'      => ['Texto principal', 'Títulos, texto y bordes marcados', '#172036', '#E8EBF3'],
            'gris'       => ['Texto secundario', 'Descripciones y etiquetas', '#5B6478', '#A3ABBF'],
            'gris-claro' => ['Texto tenue', 'Ayudas, iconos y elementos inactivos', '#8A92A6', '#7F889F'],
            'linea'      => ['Bordes', 'Líneas, separadores y bordes de campos', '#DDE1EA', '#2C3549'],
        ],
        'Bloques destacados' => [
            'oscuro'       => ['Fondo destacado', 'Menú de administración, botón oscuro, banners y notificaciones', '#172036', '#262F47'],
            'sobre-oscuro' => ['Texto sobre fondo destacado', 'Texto e iconos dentro de esos bloques', '#FFFFFF', '#FFFFFF'],
        ],
        'Estados' => [
            'verde'       => ['Éxito', 'Mensajes y estatus correctos', '#0B8A6D', '#3FCB9F'],
            'verde-claro' => ['Éxito (fondo)', 'Fondo de avisos correctos', '#E2F5EF', '#123629'],
            'ambar'       => ['Advertencia', 'Estatus pendientes', '#9A5B00', '#F2B85B'],
            'ambar-claro' => ['Advertencia (fondo)', 'Fondo de estatus pendientes', '#FFF1D9', '#3A2B12'],
            'rojo'        => ['Error', 'Errores, acciones peligrosas y límites alcanzados', '#C2253A', '#FF6B7D'],
            'rojo-claro'  => ['Error (fondo)', 'Fondo de avisos de error', '#FCE8EB', '#3D1A22'],
        ],
        'Gráficas' => [
            'serie-3' => ['Serie 3', 'Tercera serie de las gráficas (1 y 2 usan texto y acento)', '#7A86A8', '#8C97B8'],
            'serie-4' => ['Serie 4', 'Cuarta serie de las gráficas', '#F48FC4', '#F48FC4'],
        ],
    ];
}

/** Lista plana [variable => [etiqueta, uso, claro, oscuro]]. */
function tema_colores_planos(): array
{
    return array_merge(...array_values(tema_colores()));
}

/** Clave de configuración de un color: color_<variable> o color_oscuro_<variable>. */
function tema_clave(string $variable, bool $oscuro): string
{
    return 'color_' . ($oscuro ? 'oscuro_' : '') . str_replace('-', '_', $variable);
}

/** Todas las claves de configuración de colores. */
function tema_claves(): array
{
    $claves = [];
    foreach (array_keys(tema_colores_planos()) as $variable) {
        $claves[] = tema_clave($variable, false);
        $claves[] = tema_clave($variable, true);
    }
    return $claves;
}

function tema_color_valido(string $valor): bool
{
    return (bool) preg_match('/^#[0-9a-fA-F]{6}$/', $valor);
}

/** Valores efectivos [variable => color] de un tema, combinando configuración y predeterminados. */
function tema_valores(array $config, bool $oscuro): array
{
    $valores = [];
    foreach (tema_colores_planos() as $variable => [, , $claro, $oscuroDef]) {
        $guardado = (string) ($config[tema_clave($variable, $oscuro)] ?? '');
        $valores[$variable] = tema_color_valido($guardado) ? strtoupper($guardado) : ($oscuro ? $oscuroDef : $claro);
    }
    return $valores;
}

function tema_modo(array $config): string
{
    $modo = (string) ($config['tema_oscuro'] ?? '');
    return isset(TEMA_MODOS[$modo]) ? $modo : TEMA_MODO_PREDETERMINADO;
}

/** Bloque <style> con las variables de ambos temas. */
function tema_css(array $config): string
{
    $bloque = fn(array $valores) => implode(' ', array_map(
        fn($variable, $color) => "--{$variable}: {$color};",
        array_keys($valores),
        $valores
    ));

    $css = ':root { ' . $bloque(tema_valores($config, false)) . ' }';
    if (tema_modo($config) !== 'desactivado') {
        $css .= "\n:root[data-tema=\"oscuro\"] { " . $bloque(tema_valores($config, true)) . ' }';
    }
    return '<style id="tema-colores">' . $css . '</style>';
}

/**
 * Script en <head> que controla todo el tema oscuro:
 *  - fija el tema antes de pintar la página (evita el destello claro).
 *    Prioridad: elección guardada del usuario > sistema (solo en modo automático) > claro;
 *  - atiende los botones [data-cambiar-tema] desde el primer momento, sin esperar a
 *    app.js (que va al final y puede tardar si un CDN es lento);
 *  - avisa con el evento "tema-cambiado" para que app.js repinte las gráficas.
 */
function tema_script(array $config): string
{
    $modo = tema_modo($config);
    if ($modo === 'desactivado') {
        return '<script>try{localStorage.removeItem("tema")}catch(e){}</script>';
    }
    $automatico = $modo === 'automatico' ? 'true' : 'false';
    $js = <<<JS
(function () {
  var raiz = document.documentElement, tema = null;
  try { tema = localStorage.getItem("tema"); } catch (e) {}
  if (tema !== "oscuro" && tema !== "claro") {
    tema = {$automatico} && matchMedia("(prefers-color-scheme: dark)").matches ? "oscuro" : "claro";
  }
  raiz.dataset.tema = tema;

  function sincronizar() {
    var oscuro = raiz.dataset.tema === "oscuro";
    document.querySelectorAll("[data-cambiar-tema]").forEach(function (b) {
      b.setAttribute("aria-pressed", String(oscuro));
      b.setAttribute("aria-label", oscuro ? "Desactivar modo oscuro" : "Activar modo oscuro");
    });
  }
  document.addEventListener("DOMContentLoaded", sincronizar);
  document.addEventListener("click", function (evento) {
    var boton = evento.target.closest && evento.target.closest("[data-cambiar-tema]");
    if (!boton) return;
    var nuevo = raiz.dataset.tema === "oscuro" ? "claro" : "oscuro";
    raiz.dataset.tema = nuevo;
    try { localStorage.setItem("tema", nuevo); } catch (e) {}
    sincronizar();
    document.dispatchEvent(new CustomEvent("tema-cambiado", { detail: nuevo }));
  });
})();
JS;
    return '<script>' . $js . '</script>';
}
