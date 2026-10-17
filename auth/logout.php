<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/seguridad.php';
require_once dirname(__DIR__) . '/app/Core/Auth.php';

App\Core\Auth::logout();
redirigir('login.php');
