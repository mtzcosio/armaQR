<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/Database.php';

use App\Core\Database;

$pdo = Database::connection();
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS configuracion (
        clave VARCHAR(120) NOT NULL PRIMARY KEY,
        valor TEXT NOT NULL,
        descripcion VARCHAR(255) NULL,
        actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB'
);

$defaults = [
    'portal_nombre' => 'Arma-QR',
    'portal_dominio' => 'armaqr.com',
    'portal_descripcion' => 'Genera QR y comparte información de forma rápida.',
    'portal_logo_url' => '',
    'portal_favicon_url' => '',
    // Los colores no necesitan fila: sus predeterminados viven en includes/tema.php.
    'tema_oscuro' => 'opcional',
    'contacto_correo' => 'hola@armaqr.com',
    'contacto_telefono' => '',
    'contacto_whatsapp' => '',
    'contacto_horario' => 'Lunes a viernes, 9:00 a 18:00',
    'contacto_direccion' => '',
    'contacto_instagram' => '',
    'contacto_facebook' => '',
    'contacto_tiktok' => '',
    'ventas_moneda' => 'MXN',
    'ventas_impuesto' => '16',
    'ventas_duracion_qr' => '365',
    'ventas_escaneos_qr' => '',
    'smtp_enabled' => '0',
    'smtp_host' => '',
    'smtp_port' => '587',
    'smtp_encryption' => 'tls',
    'smtp_usuario' => '',
    'smtp_password' => '',
    'smtp_from_email' => '',
    'smtp_from_nombre' => 'Arma-QR',
    'pago_conekta' => '',
    'pago_stripe' => '',
    'pago_stripe_webhook' => '',
    'pago_stripe_habilitado' => '1',
    'pago_conekta_habilitado' => '1',
    'pago_tarjeta' => '',
    'pago_oxxo' => '',
    'pago_spei' => '',
];

// INSERT IGNORE: solo agrega las claves que falten; nunca sobrescribe valores ya guardados.
$insert = $pdo->prepare(
    'INSERT IGNORE INTO configuracion (clave, valor, descripcion)
     VALUES (:clave, :valor, :descripcion)'
);
foreach ($defaults as $clave => $valor) {
    $insert->execute([
        ':clave' => $clave,
        ':valor' => $valor,
        ':descripcion' => 'Configuración inicial del portal',
    ]);
}

// Claves de colores antiguas que nunca se aplicaron al portal.
$pdo->exec("DELETE FROM configuracion WHERE clave IN ('color_primario', 'color_secundario', 'color_accento', 'color_botones')");

echo "Migración de configuración ampliada ejecutada correctamente.\n";
