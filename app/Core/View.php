<?php

declare(strict_types=1);

namespace App\Core;

final class View
{
    /**
     * Carga una vista desde app/Views. El área indica si usa el layout público,
     * de cliente o de administración; los datos se exponen como variables en la vista.
     */
    public static function render(string $name, array $data = [], string $area = 'publico'): void
    {
        // La ruta se calcula desde la carpeta Core, dos niveles arriba hasta la raíz del proyecto.
        $root = dirname(__DIR__, 2);
        $viewPath = $root . '/app/Views/' . $name . '.php';
        if (!is_file($viewPath)) {
            throw new \RuntimeException("Vista no encontrada: {$name}");
        }

        // Conserva el nombre de la vista y el área para que el layout pueda identificarlos.
        $data['view'] = $name;
        $data['area'] = $area;
        extract($data, EXTR_SKIP);
        include $viewPath;
    }
}
