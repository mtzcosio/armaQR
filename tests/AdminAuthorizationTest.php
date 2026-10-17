<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Auth.php';

use App\Core\Auth;

if (!Auth::isAdminRole(['rol' => 'admin'])) {
    fwrite(STDERR, "El rol admin no debería ser rechazado.\n");
    exit(1);
}

if (Auth::isAdminRole(['rol' => 'cliente'])) {
    fwrite(STDERR, "El rol cliente debería ser rechazado.\n");
    exit(1);
}

fwrite(STDOUT, "Admin authorization role test passed.\n");
