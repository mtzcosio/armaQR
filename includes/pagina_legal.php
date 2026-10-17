<?php
/**
 * Página pública de un documento legal (terminos.php, privacidad.php).
 * Muestra la última versión publicada; con ?version=N, una versión anterior.
 */

declare(strict_types=1);

require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/legales.php';

function pagina_legal(string $tipo): void
{
    $info = LEGAL_TIPOS[$tipo];
    $versionPedida = isset($_GET['version']) ? (int) $_GET['version'] : null;
    $doc = legal_tabla_lista()
        ? ($versionPedida ? legal_version($tipo, $versionPedida) : legal_publicado($tipo))
        : null;
    $vigente = $doc && $versionPedida ? legal_publicado($tipo) : $doc;
    $esAnterior = $doc && $vigente && (int) $doc['version'] !== (int) $vigente['version'];
    if (!$doc) {
        http_response_code($versionPedida ? 404 : 200);
    }
    $otro = $tipo === 'terminos' ? 'privacidad' : 'terminos';

    pagina_inicio($doc['titulo'] ?? $info['titulo']);
    ?>
<header class="cabecera con-borde">
  <div class="envoltura">
    <a class="logo" href="<?= url('index.php') ?>"><?= marca(28) ?><?= e(nombre_portal()) ?></a>
    <div class="cabecera-acciones">
      <?= boton_tema() ?>
      <a class="boton boton-fantasma boton-chico" href="<?= url('login.php') ?>">Iniciar sesión</a>
    </div>
  </div>
</header>

<main id="contenido" class="seccion">
  <article class="envoltura legal">
    <?php if ($doc): ?>
      <p class="legal-meta">Versión <?= (int) $doc['version'] ?> · vigente desde el <?= e(fecha_corta($doc['publicado_en'])) ?></p>
      <h1><?= e($doc['titulo']) ?></h1>
      <?php if ($esAnterior): ?>
        <?= aviso('info', 'Estás viendo una versión anterior. La vigente es la versión ' . $vigente['version'] . '.') ?>
        <p><a class="enlace" href="<?= url($info['ruta']) ?>">Ver la versión vigente</a></p>
      <?php endif; ?>
      <div class="legal-contenido"><?= legal_render(legal_sanitizar((string) $doc['contenido_html'])) ?></div>
    <?php else: ?>
      <h1><?= e($info['titulo']) ?></h1>
      <p class="texto-gris mt"><?= $versionPedida ? 'No existe esa versión.' : 'Este documento se publicará próximamente.' ?></p>
    <?php endif; ?>
  </article>
</main>

<footer class="pie">
  <div class="envoltura">
    <span>© <?= date('Y') ?> <?= e(nombre_portal()) ?>. Hecho en México.</span>
    <nav aria-label="Legal">
      <a href="<?= url(LEGAL_TIPOS[$otro]['ruta']) ?>"><?= e(LEGAL_TIPOS[$otro]['titulo']) ?></a>
      <a href="<?= url('index.php') ?>">Inicio</a>
    </nav>
  </div>
</footer>
<?php
    pagina_fin();
}
