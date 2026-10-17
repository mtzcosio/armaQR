<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Application.php';

$_SERVER['REQUEST_URI'] = '/ruta-no-registrada';

ob_start();
$app = new App\Core\Application();
$app->run();
$output = ob_get_clean();

if (http_response_code() !== 404) {
    fwrite(STDERR, "Expected HTTP status 404, got " . http_response_code() . "\n");
    exit(1);
}

if (!str_contains($output, 'Página no encontrada') && !str_contains($output, '404')) {
    fwrite(STDERR, "La vista 404 no se renderizó correctamente.\n");
    exit(1);
}

fwrite(STDOUT, "Route 404 test passed: HTTP 404 and error view rendered.\n");
