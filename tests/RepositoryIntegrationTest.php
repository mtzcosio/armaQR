<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/database/Database.php';
require_once dirname(__DIR__) . '/app/Models/DatabaseRepository.php';

use App\Models\DatabaseRepository;

$repository = new DatabaseRepository();

$planes = $repository->planes();
if (count($planes) !== 3) {
    fwrite(STDERR, "Se esperaban 3 planes y se encontraron " . count($planes) . ".\n");
    exit(1);
}

$users = $repository->usuarios();
if ($users === []) {
    fwrite(STDERR, "No se encontraron usuarios para la integración.\n");
    exit(1);
}

$admin = $repository->usuarioPorEmail('admin@armaqr.com');
if ($admin === null || $admin['rol'] !== 'admin') {
    fwrite(STDERR, "No se encontró un administrador válido.\n");
    exit(1);
}

$qrRows = $repository->qrsDeUsuario((int) $admin['id']);
if (!is_array($qrRows)) {
    fwrite(STDERR, "La consulta de QR no devolvió un array.\n");
    exit(1);
}

fwrite(STDOUT, "Repository integration test passed: " . count($planes) . " plans, " . count($users) . " users.\n");
