<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/database/Database.php';

use App\Core\Database;

try {
    Database::ping();
    echo "Database connection passed.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
