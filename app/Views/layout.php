<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$area = $area ?? 'publico';
$view = $view ?? '';

if ($area === 'admin') {
    include $root . '/includes/layout.php';
    pagina_inicio($title ?? '', 'admin');
} elseif ($area === 'app') {
    include $root . '/includes/layout.php';
    pagina_inicio($title ?? '', 'app');
} else {
    include $root . '/includes/layout.php';
}
