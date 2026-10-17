<?php

declare(strict_types=1);

$title = $title ?? 'Página no encontrada';
$root = dirname(__DIR__, 3);
require_once $root . '/includes/layout.php';
pagina_inicio($title);
?>
<main id="contenido" class="acceso">
    <div class="acceso-form">
        <?= boton_tema('acceso-tema') ?>
        <div class="acceso-caja">
            <a class="logo" href="<?= url('index.php') ?>"><?= marca(28) ?><?= e(nombre_portal()) ?></a>
            <h1>404</h1>
            <p class="texto-gris mt">La página que buscas no existe o se trasladó.</p>
            <a class="boton boton-principal boton-ancho" href="<?= url('index.php') ?>">Volver al inicio</a>
        </div>
    </div>
</main>
<?php pagina_fin(); ?>
