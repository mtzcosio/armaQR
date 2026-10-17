<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/database/Database.php';

use App\Core\Database;

$database = Database::connection();
$keys = ['portal_nombre', 'portal_descripcion', 'smtp_enabled', 'pago_stripe'];
foreach ($keys as $key) {
    $statement = $database->prepare('SELECT valor FROM configuracion WHERE clave = :clave');
    $statement->execute([':clave' => $key]);
    $value = $statement->fetchColumn();
    if ($value === false || $value === null) {
        fwrite(STDERR, "No existe la clave persistida: {$key}\n");
        exit(1);
    }
}

$admin = file_get_contents(dirname(__DIR__) . '/admin/configuracion.php') ?: '';
foreach (['Identidad', 'Colores', 'Contacto', 'Ventas', 'Correo', 'Pagos', 'Probar envío'] as $label) {
    if (!str_contains($admin, $label)) {
        fwrite(STDERR, "Falta dato de interfaz: {$label}\n");
        exit(1);
    }
}

echo "Persistencia y contenido de configuración: OK\n";
