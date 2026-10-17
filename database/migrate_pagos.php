<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * Prepara la base para Stripe y Conekta (includes/pagos.php).
 * Es seguro ejecutarla varias veces: solo agrega lo que falta y no toca datos.
 *
 *   php database/migrate_pagos.php
 */

require_once __DIR__ . '/Database.php';

$pdo = App\Core\Database::connection();

// Customer de Conekta (cus_… de Stripe ya va en usuarios.id_cliente_pasarela).
$existe = $pdo->query(
    "SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'id_cliente_conekta'"
)->fetchColumn();
if ((int) $existe === 0) {
    $pdo->exec('ALTER TABLE usuarios ADD COLUMN id_cliente_conekta VARCHAR(100) NULL AFTER id_cliente_pasarela');
    echo "Columna usuarios.id_cliente_conekta agregada.\n";
}

// Secreto de firma del webhook de Stripe (se captura en Administración › Configuración › Pagos).
$pdo->exec(
    "INSERT IGNORE INTO configuracion (clave, valor, descripcion)
     VALUES ('pago_stripe_webhook', '', 'Configuración inicial del portal')"
);

echo "Migración de pagos lista.\n";
