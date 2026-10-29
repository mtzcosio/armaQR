<?php

declare(strict_types=1);

// Páginas explicativas de los tipos dinámicos (tipo.php) y sus ejemplos (ejemplo.php):
// cada tipo tiene textos y un ejemplo válido que se dibuja sin avisos ni enlaces que salgan del teléfono.
require_once dirname(__DIR__) . '/includes/layout.php';
require_once dirname(__DIR__) . '/includes/qr_tipos_info.php';
require_once dirname(__DIR__) . '/includes/qr_pagina_vista.php';

$fallas = [];
$prueba = function (string $nombre, bool $ok, string $detalle = '') use (&$fallas) {
    if (!$ok) $fallas[] = $nombre . ($detalle !== '' ? "  [{$detalle}]" : '');
};
$raiz = dirname(__DIR__);

// Los avisos de PHP cuentan como falla al dibujar los ejemplos.
set_error_handler(function (int $nivel, string $mensaje, string $archivo, int $linea) {
    throw new ErrorException($mensaje, 0, $nivel, $archivo, $linea);
});

foreach (array_keys(QR_TIPOS_DINAMICOS) as $tipo) {
    $info = qr_tipo_info($tipo);
    $prueba("$tipo: tiene textos", $info !== null && $info['titulo'] !== '' && $info['intro'] !== '' && $info['incluye'] && $info['usos']);

    try {
        $campos = qr_tipo_ejemplo_campos($tipo);
    } catch (Throwable $e) {
        $prueba("$tipo: el ejemplo es válido", false, $e->getMessage());
        continue;
    }
    foreach (qr_pagina_rutas($campos) as $ruta) {
        $prueba("$tipo: existe $ruta", false, 'los ejemplos no usan assets/uploads');
    }
    foreach (QR_PAGINA_CAMPOS_ARCHIVO as $clave) {
        foreach ((array) ($campos[$clave] ?? []) as $ruta) {
            $prueba("$tipo: archivo de ejemplo $ruta", is_string($ruta) && str_starts_with($ruta, QR_EJEMPLOS_CARPETA) && is_file($raiz . '/' . $ruta));
        }
    }

    try {
        ob_start();
        qr_pagina_mostrar(['tipo_contenido' => $tipo, 'campos' => $campos, 'codigo_corto' => '', 'diseno' => '{}', 'marca_agua' => 0, 'activo' => 1], 'telefono');
        $html = ob_get_clean();
    } catch (Throwable $e) {
        ob_end_clean();
        $prueba("$tipo: el ejemplo se dibuja", false, $e->getMessage());
        continue;
    }
    $prueba("$tipo: sin espacios vacíos", !str_contains($html, 'micrositio-vacio'));
    $prueba("$tipo: los botones no salen del teléfono", !preg_match('/class="micrositio-boton[^"]*" href="(?!#)/', $html));
    $prueba("$tipo: frena los demás enlaces", str_contains($html, 'ev.preventDefault()'));
}
restore_error_handler();

$prueba('Tipo inexistente', qr_tipo_info('texto') === null && qr_tipo_info('nada') === null);
$prueba('La página pública no frena enlaces', (function () {
    ob_start();
    qr_pagina_mostrar(['tipo_contenido' => 'enlaces', 'campos' => qr_tipo_ejemplo_campos('enlaces'), 'codigo_corto' => 'abc234', 'diseno' => '{}', 'marca_agua' => 0, 'activo' => 1]);
    $html = ob_get_clean();
    return !str_contains($html, 'ev.preventDefault()') && str_contains($html, 'href="https://www.example.com/pedidos"');
})());

if ($fallas) {
    fwrite(STDERR, "Fallaron:\n  - " . implode("\n  - ", $fallas) . "\n");
    exit(1);
}
echo 'Tipos de QR: textos y ejemplos de los ' . count(QR_TIPOS_DINAMICOS) . " tipos dinámicos: OK\n";
