<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$admin = file_get_contents($root . '/admin/configuracion.php') ?: '';
$migration = file_get_contents($root . '/database/migrate_configuracion.php') ?: '';
$endpoint = file_get_contents($root . '/auth/configuracion.php') ?: '';

$requiredSections = [
    'Identidad',
    'Colores',
    'Contacto',
    'Ventas',
    'Correo',
    'Pagos',
];
$requiredKeys = [
    'portal_descripcion', 'portal_logo_url', 'portal_favicon_url',
    'tema_oscuro', 'tema_colores()',
    'contacto_correo', 'contacto_telefono', 'contacto_whatsapp',
    'contacto_horario', 'contacto_direccion', 'contacto_instagram',
    'contacto_facebook', 'contacto_tiktok', 'ventas_moneda',
    'ventas_impuesto', 'ventas_duracion_qr', 'ventas_escaneos_qr',
    'smtp_enabled', 'smtp_host', 'smtp_test', 'pago_conekta',
    'pago_stripe', 'pago_tarjeta', 'pago_oxxo', 'pago_spei',
];

foreach ($requiredSections as $section) {
    if (!str_contains($admin, $section)) {
        fwrite(STDERR, "Falta sección: {$section}\n");
        exit(1);
    }
}

foreach ($requiredKeys as $key) {
    if (!str_contains($admin, $key) && !str_contains($migration, $key)) {
        fwrite(STDERR, "Falta clave de configuración: {$key}\n");
        exit(1);
    }
}

if (!str_contains($endpoint, 'probar-correo') || !str_contains($endpoint, 'filter_var')) {
    fwrite(STDERR, "Falta endpoint seguro de prueba SMTP.\n");
    exit(1);
}

echo "Configuración por secciones: OK\n";
