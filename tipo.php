<?php
/**
 * Página explicativa de un tipo de QR dinámico (?t=tipo): qué es, qué incluye, para qué sirve,
 * cómo crearlo y un teléfono con un ejemplo (ejemplo.php). Textos en includes/qr_tipos_info.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/qr_tipos_info.php';
require_once __DIR__ . '/includes/estadisticas_vista.php';

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

// Estadísticas de ejemplo (datos ficticios con la forma típica de este tipo) y sus gráficas.
$est = qr_tipo_ejemplo_estadisticas($tipo);
$grafDias = ['tipo' => 'line', 'etiquetas' => array_map(fn($f) => date('j/n', strtotime($f)), array_keys($est['serie'])),
             'series' => [['nombre' => 'Escaneos', 'datos' => array_values($est['serie'])]]];
$grafDisp = ['tipo' => 'doughnut', 'etiquetas' => array_keys($est['dispositivo']), 'series' => [['nombre' => 'Dispositivo', 'datos' => array_values($est['dispositivo'])]]];

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

  <section class="seccion" id="estadisticas">
    <div class="envoltura">
      <div class="seccion-cabeza">
        <h2>Así se ven tus estadísticas</h2>
        <p>Un QR de <?= e($datos['nombre']) ?> durante 30 días, con datos de ejemplo. Con un QR dinámico ves esto de cada código desde tu cuenta.</p>
      </div>
      <div class="estadisticas-ejemplo">
        <span class="estadisticas-ejemplo-sello"><?= icono('grafica', 15) ?> Datos de ejemplo</span>
        <section class="cifras" aria-label="Resumen de ejemplo">
          <div class="cifra destaca"><span>Escaneos</span><strong><?= numero($est['total']) ?></strong><small>Últimos 30 días</small></div>
          <div class="cifra"><span>Personas distintas</span><strong><?= numero($est['unicos']) ?></strong><small>Aproximado por IP anónima</small></div>
          <div class="cifra"><span>Promedio diario</span><strong><?= numero($est['promedio']) ?></strong><small>Escaneos por día</small></div>
          <div class="cifra"><span>Mejor día</span><strong><?= e(fecha_corta($est['mejor'])) ?></strong><small><?= numero($est['mejor_n']) ?> escaneos</small></div>
        </section>

        <section class="caja">
          <div class="caja-cabeza"><div><h3>Escaneos por día</h3><p>Últimos 30 días</p></div></div>
          <div class="caja-cuerpo"><div class="grafica"><canvas data-grafica='<?= e(json_encode($grafDias)) ?>' aria-label="Gráfica de ejemplo: escaneos por día" role="img"></canvas></div></div>
        </section>

        <section class="caja">
          <div class="caja-cabeza"><div><h3>Día y hora</h3><p>Cuándo lo escanean durante la semana</p></div></div>
          <div class="caja-cuerpo"><?= estadisticas_mapa_calor($est['mapa']) ?></div>
        </section>

        <div class="rejilla-mitad">
          <section class="caja">
            <div class="caja-cabeza"><div><h3>¿Desde dónde lo escanean?</h3><p>País y ciudad aproximados</p></div></div>
            <div class="caja-cuerpo"><?= estadisticas_ubicacion($est, false) ?></div>
          </section>
          <section class="caja">
            <div class="caja-cabeza"><h3>Dispositivo</h3></div>
            <div class="caja-cuerpo">
              <div class="grafica grafica-chica"><canvas data-grafica='<?= e(json_encode($grafDisp)) ?>' aria-label="Gráfica de ejemplo: escaneos por dispositivo" role="img"></canvas></div>
              <p class="texto-chico texto-gris mt"><?= e(implode(' · ', array_map(fn($so, $pct) => "$so $pct%", array_keys($est['sistema']), $est['sistema']))) ?></p>
            </div>
          </section>
        </div>

        <p class="texto-chico texto-gris">Además, puedes exportar cada escaneo a CSV para abrirlo en Excel.</p>
        <p class="estadisticas-ejemplo-lectura"><?= icono('rayo', 18) ?> <span><?= e($est['lectura']) ?></span></p>
      </div>
    </div>
  </section>

  <section class="seccion seccion-papel">
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

  <section class="seccion">
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
<?php pagina_fin('publico', [CDN_CHART]); ?>
