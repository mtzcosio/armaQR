<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    /**
     * Lee un valor enviado por GET o POST. Si no existe, devuelve el valor por defecto.
     * Útil para formularios, filtros y parámetros de acciones.
     */
    protected function input(string $name, mixed $default = null): mixed
    {
        return $_GET[$name] ?? $_POST[$name] ?? $default;
    }

    /**
     * Redirige al usuario a otra ruta y termina la ejecución actual.
     * Usa este método después de guardar cambios o cerrar una sesión.
     */
    protected function redirect(string $path): never
    {
        header('Location: ' . $path);
        exit;
    }
}
