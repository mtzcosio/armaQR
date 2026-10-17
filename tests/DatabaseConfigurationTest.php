<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/database/Database.php';

use App\Core\Database;

$config = Database::config();

if ($config['host'] === '') {
    fwrite(STDERR, "Configura DB_HOST antes de ejecutar la prueba de conexión.\n");
    exit(2);
}

if ($config['name'] === '') {
    fwrite(STDERR, "Configura DB_NAME antes de ejecutar la prueba de conexión.\n");
    exit(2);
}

if (!is_int($config['port']) || $config['port'] <= 0) {
    fwrite(STDERR, "DB_PORT debe ser un puerto numérico válido.\n");
    exit(2);
}

echo "Database configuration is valid.\n";
