<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/database/Database.php';

use App\Core\Database;

$pdo = Database::connection();
$rows = $pdo->query(
    'SELECT TABLE_SCHEMA, TABLE_NAME FROM information_schema.TABLES '
    . 'WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME'
)->fetchAll(PDO::FETCH_ASSOC);

if ($rows === []) {
    echo "La base activa no contiene tablas.\n";
    exit(0);
}

foreach ($rows as $row) {
    echo $row['TABLE_SCHEMA'] . '.' . $row['TABLE_NAME'] . PHP_EOL;
}

$planes = $pdo->query('SELECT COUNT(*) FROM planes')->fetchColumn();
echo "Planes inserted: " . $planes . PHP_EOL;
