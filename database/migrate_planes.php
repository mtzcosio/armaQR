<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * Quita de la tabla planes las columnas id_externo_mensual e id_externo_anual: nunca se usaron,
 * Stripe busca el precio por lookup_key (stripe_precio) y Conekta cobra el precio vigente.
 * Es seguro ejecutarla varias veces: solo quita lo que aún exista.
 *
 *   php database/migrate_planes.php
 */

require_once __DIR__ . '/Database.php';

$pdo = App\Core\Database::connection();
$existe = $pdo->prepare(
    "SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'planes' AND COLUMN_NAME = :c"
);

foreach (['id_externo_mensual', 'id_externo_anual'] as $columna) {
    $existe->execute([':c' => $columna]);
    if ((int) $existe->fetchColumn() > 0) {
        $pdo->exec("ALTER TABLE planes DROP COLUMN {$columna}");
        echo "Columna planes.{$columna} eliminada.\n";
    }
}

echo "Tabla planes al día.\n";
