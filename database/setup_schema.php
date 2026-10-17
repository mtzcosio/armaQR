<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/database/Database.php';

use App\Core\Database;

$pdo = Database::connection();
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

if ($tables !== []) {
    fwrite(
        STDERR,
        "La base de datos no está vacía. Se encontraron " . count($tables) . " tablas. "
        . "No se aplicó el esquema para evitar sobrescribir datos.\n"
    );
    exit(1);
}

$sql = file_get_contents(__DIR__ . '/qr_saas_schema.sql');
if ($sql === false) {
    fwrite(STDERR, "No se pudo leer qr_saas_schema.sql.\n");
    exit(1);
}

try {
    $pdo->exec($sql);
    echo "Schema installed successfully.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
