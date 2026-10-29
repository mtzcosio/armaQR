<?php
/**
 * Página explicativa de un tipo de QR dinámico (?t=tipo): qué es, qué incluye, para qué sirve,
 * cómo crearlo y un teléfono con un ejemplo (ejemplo.php). Textos en includes/qr_tipos_info.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/qr_tipos_info.php';

$tipo = (string) ($_GET['t'] ?? '');
$info = qr_tipo_info($tipo);
if ($info === null) {
    redirigir('index.php#tipos', 302);
}
$datos = QR_TIPOS_DINAMICOS[$tipo];
$redirige = qr_tipo_usa_url($tipo);

// Con sesión, el botón abre "Crear QR" con este tipo elegido; sin sesión, el registro.
$conSesion = App\Core\Auth::user() !== null;
$crear = $conSesion ? url_app('crear.php', ['tipo' => $tipo]) : url('registro.php?plan=pro');

pagina_inicio($info['titulo'], 'publico', '', [], $info['intro']);
cabecera_publica();
?>

<main id="contenido">
  <section class="seccion tipo-heroe">
    <div class="envoltura tipo-heroe-rejilla">
      <div class="tipo-heroe-texto">
        <nav class="miga-publica" aria-label="Ruta">
          <a href="<?= e(url()) ?>">Inicio</a> <span aria-hidden="true">›</span>
          <a href="<?= e(url()) ?>#tipos">Tipos de QR</a> <span aria-hidden="true">›</span>
          <span aria-current="page"><?= e($datos['nombre']) ?></span>
        </nav>
        <span class="tipo-icono tipo-icono-grande"><?= icono($datos['icono'], 30) ?></span>
        <h1><?= e($info['titulo']) ?></h1>
        <p class="lead"><?= e($info['intro']) ?></p>
        <div class="cabecera-acciones">
          <a class="boton boton-principal" href="<?= e($crear) ?>">Crear mi QR de <?= e($datos['nombre']) ?></a>
          <a class="boton boton-fantasma" href="#incluye">Qué incluye</a>
        </div>
        <p class="texto-chico texto-gris">Los QR dinámicos están disponibles desde el plan Pro.</p>
      </div>

      <figure class="tipo-ejemplo">
        <div class="telefono">
          <div class="telefono-pantalla">
            <iframe class="telefono-pagina" src="<?= e(url('ejemplo.php?t=' . $tipo)) ?>" title="Ejemplo de la página de un QR de <?= e($datos['nombre']) ?>" loading="lazy"></iframe>
          </div>
        </div>
        <figcaption><?= $redirige ? 'Ejemplo con datos ficticios: al escanear se abre el destino directamente.' : 'Ejemplo con datos ficticios: así lo ve quien escanea tu QR.' ?></figcaption>
      </figure>
    </div>
  </section>

  <section class="seccion seccion-papel" id="incluye">
    <div class="envoltura tipo-columnas">
      <div>
        <h2>Qué incluye</h2>
        <ul class="lista-check">
          <?php foreach ($info['incluye'] as $x): ?><li><?= icono('check') ?> <?= e($x) ?></li><?php endforeach; ?>
        </ul>
      </div>
      <div>
        <h2>Ideal para</h2>
        <ul class="lista-check">
          <?php foreach ($info['usos'] as $x): ?><li><?= icono('check') ?> <?= e($x) ?></li><?php endforeach; ?>
        </ul>
      </div>
    </div>
  </section>

  <section class="seccion">
    <div class="envoltura">
      <div class="seccion-cabeza"><h2>Cómo crearlo</h2></div>
      <div class="pasos">
        <div class="paso"><h3>Elige «<?= e($datos['nombre']) ?>»</h3><p>En Crear QR, marca el tipo dinámico y elige «<?= e($datos['nombre']) ?>».</p></div>
        <div class="paso"><h3><?= $redirige ? 'Escribe el destino' : 'Llena tus datos' ?></h3><p><?= $redirige
            ? 'Captura la dirección a la que debe llevar el código. Puedes cambiarla cuando quieras.'
            : 'Captura la información y elige los colores de tu página. Un teléfono te muestra cómo quedará antes de guardar.' ?></p></div>
        <div class="paso"><h3>Descarga e imprime</h3><p>Descarga el código en PNG, SVG o PDF. Si algo cambia, lo editas y el QR impreso sigue funcionando.</p></div>
      </div>
      <?php if ($info['notas']): ?>
        <div class="tipo-notas">
          <h3>Bueno saber</h3>
          <ul class="lista-check">
            <?php foreach ($info['notas'] as $x): ?><li><?= icono('alerta', 18) ?> <?= e($x) ?></li><?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <section class="seccion seccion-papel">
    <div class="envoltura">
      <div class="seccion-cabeza"><h2>Otros tipos de QR dinámico</h2></div>
      <ul class="tipos-landing">
        <?php foreach (QR_TIPOS_DINAMICOS as $clave => $t): if ($clave === $tipo) continue; ?>
          <li><a href="<?= e(url('tipo.php?t=' . $clave)) ?>"><span class="tipo-icono"><?= icono($t['icono'], 22) ?></span><strong><?= e($t['nombre']) ?></strong><small><?= e($t['descripcion']) ?></small><span class="tipos-landing-ver">Ver ejemplo <?= icono('derecha', 14) ?></span></a></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>

  <section class="seccion">
    <div class="envoltura">
      <div class="llamado">
        <div>
          <h2>Crea tu QR de <?= e($datos['nombre']) ?> hoy</h2>
          <p>Regístrate gratis y pasa a Pro cuando quieras editar tus códigos y darles su propia página.</p>
        </div>
        <a class="boton boton-principal" href="<?= e($crear) ?>"><?= $conSesion ? 'Crear mi QR' : 'Crear cuenta gratis' ?></a>
      </div>
    </div>
  </section>
</main>

<?php pie_publico(); ?>
<?php pagina_fin(); ?>
