<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/database/Database.php';

use App\Core\Database;

$config = Database::config();

if (!class_exists('PDO')) {
    fwrite(STDERR, "PDO no está disponible.\n");
    exit(1);
}

if (!is_array($config) || !isset($config['host'], $config['port'], $config['name'])) {
    fwrite(STDERR, "La configuración de base de datos no está completa.\n");
    exit(1);
}

echo "Database class syntax and configuration shape passed.\n";
