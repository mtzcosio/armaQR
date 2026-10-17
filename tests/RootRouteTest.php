<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$_SERVER['DOCUMENT_ROOT'] = dirname($root);
$_SERVER['REQUEST_URI'] = '/QR/';

require_once $root . '/app/Core/Application.php';

ob_start();
$app = new App\Core\Application();
$app->run();
$output = ob_get_clean();

if (http_response_code() === 404) {
    fwrite(STDERR, "La raíz de la aplicación fue resuelta como 404.\n");
    exit(1);
}

if (!str_contains($output, 'Tu código QR, listo mientras escribes.')) {
    fwrite(STDERR, "La página principal no se renderizó.\n");
    exit(1);
}

fwrite(STDOUT, "Root route test passed: /QR/ renders the home page.\n");
