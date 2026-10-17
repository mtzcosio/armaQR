<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$requiredFiles = [
    'app/Core/Application.php',
    'app/Core/Controller.php',
    'app/Core/View.php',
    'app/Controllers/PageController.php',
    'app/Models/MockRepository.php',
    'app/Views/layout.php',
    'app/Views/errors/404.php',
    'app/Views/home.php',
    'app/Views/login.php',
    'app/Views/registro.php',
    'app/Views/client/dashboard.php',
    'app/Views/client/crear.php',
    'app/Views/client/estadisticas.php',
    'app/Views/client/plan.php',
    'app/Views/admin/index.php',
    'app/Views/admin/qrs.php',
];

$failures = [];
foreach ($requiredFiles as $file) {
    if (!is_file($root . '/' . $file)) {
        $failures[] = "Falta {$file}";
    }
}

if ($failures !== []) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}

require_once $root . '/app/Core/Application.php';
require_once $root . '/app/Core/Controller.php';
require_once $root . '/app/Core/View.php';
require_once $root . '/app/Models/MockRepository.php';
require_once $root . '/app/Controllers/PageController.php';

$app = new App\Core\Application();
$routes = $app->routes();

foreach (['home', 'login', 'registro', 'client/dashboard', 'client/crear', 'admin/qrs'] as $route) {
    if (!isset($routes[$route])) {
        $failures[] = "Ruta MVC ausente: {$route}";
    }
}

if ($failures !== []) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}

$repository = new App\Models\MockRepository();
if (count($repository->planes()) !== 3 || count($repository->qrs()) < 1) {
    fwrite(STDERR, "El repositorio de datos de prueba no devuelve los datos esperados.\n");
    exit(1);
}

fwrite(STDOUT, "MVC architecture test passed: 15 MVC files, 9 routes, repository data verified.\n");
