<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * Páginas propias de los QR dinámicos (PDF, menú, cupón, evento…): qr_codigos.tipo_contenido
 * deja de ser ENUM y pasa a VARCHAR(30); los tipos válidos los define includes/qr_tipos.php.
 * Es seguro ejecutarla varias veces: solo cambia la columna si aún es ENUM.
 *
 *   php database/migrate_paginas_qr.php
 */

require_once __DIR__ . '/Database.php';

$pdo = App\Core\Database::connection();
$tipo = $pdo->query(
    "SELECT DATA_TYPE FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'qr_codigos' AND COLUMN_NAME = 'tipo_contenido'"
)->fetchColumn();

if ($tipo === 'enum') {
    $pdo->exec('ALTER TABLE qr_codigos MODIFY tipo_contenido VARCHAR(30) NOT NULL');
    echo "Columna qr_codigos.tipo_contenido cambiada a VARCHAR(30).\n";
}

echo "Tabla qr_codigos lista para las páginas de los QR.\n";
