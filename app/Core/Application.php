<?php

declare(strict_types=1);

namespace App\Core;

require_once dirname(__DIR__) . '/Controllers/PageController.php';

use App\Controllers\PageController;
use App\Core\View;

final class Application
{
    private PageController $controller;

    public function __construct()
    {
        $this->controller = new PageController();
    }

    /**
     * Registra las rutas disponibles. Agrega aquí una nueva ruta si quieres
     * conectar una nueva página con un método del controlador PageController.
     */
    public function routes(): array
    {
        return [
            'home' => ['PageController', 'home'],
            'login' => ['PageController', 'login'],
            'registro' => ['PageController', 'registro'],
            'client/dashboard' => ['PageController', 'clientDashboard'],
            'client/crear' => ['PageController', 'crear'],
            'client/estadisticas' => ['PageController', 'estadisticas'],
            'client/plan' => ['PageController', 'plan'],
            'admin/index' => ['PageController', 'adminIndex'],
            'admin/qrs' => ['PageController', 'adminQrs'],
        ];
    }

    /**
     * Resuelve la URL recibida, la convierte en una ruta y ejecuta el controlador.
     * Si no existe la ruta, devuelve el error HTTP 404 y la vista de errores.
     */
    public function run(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        $path = rtrim(str_replace('\\', '/', $path), '/');
        $path = $path === '' ? '/index.php' : $path;
        $projectFolder = basename(dirname(__DIR__, 2));
        $path = preg_replace('#^/' . preg_quote($projectFolder, '#') . '(?=/|$)#i', '', $path);
        $path = $path === '' ? '/index.php' : $path;
        $route = str_replace('.php', '', trim($path, '/'));
        $route = $route === 'index' ? 'home' : $route;
        $route = $route === 'admin' ? 'admin/index' : $route;
        $route = $route === 'app/index' ? 'client/dashboard' : $route;
        $route = $route === 'app/crear' ? 'client/crear' : $route;
        $route = $route === 'app/estadisticas' ? 'client/estadisticas' : $route;
        $route = $route === 'app/plan' ? 'client/plan' : $route;
        $route = $route === 'admin/qrs' ? 'admin/qrs' : $route;

        $action = $this->routes()[$route] ?? null;
        if (!$action) {
            http_response_code(404);
            View::render('errors/404', ['title' => 'Página no encontrada'], 'publico');
            return;
        }

        $this->controller->{$action[1]}();
    }
}
