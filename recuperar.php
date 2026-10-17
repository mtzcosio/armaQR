<?php
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/recuperacion.php';

$mensajes = [
    'invalid'   => ['error', 'Escribe un correo electrónico válido.'],
    'bloqueado' => ['error', 'Pediste varias ligas seguidas. Espera 15 minutos e inténtalo de nuevo.'],
    'csrf'      => ['error', 'La sesión del formulario expiró. Vuelve a intentarlo.'],
];
$aviso = $mensajes[$_GET['error'] ?? ''] ?? null;
$enviado = !$aviso && isset($_GET['enviado']);

pagina_inicio('Recuperar contraseña');
?>
<main id="contenido" class="acceso">
  <div class="acceso-form">
    <?= boton_tema('acceso-tema') ?>
    <div class="acceso-caja">
      <a class="logo" href="<?= url('index.php') ?>"><?= marca(28) ?><?= e(nombre_portal()) ?></a>

      <?php if ($enviado): ?>
        <div>
          <h1>Revisa tu correo</h1>
          <p class="texto-gris mt">
            Si hay una cuenta con ese correo, te enviamos una liga para elegir una contraseña nueva.
            Vence en <?= RECUPERACION_MINUTOS ?> minutos y solo funciona una vez.
          </p>
        </div>
        <?= aviso('info', '¿No llegó? Revisa la carpeta de spam o pide otra liga en unos minutos.') ?>
        <a class="boton boton-tinta boton-ancho" href="<?= url('login.php') ?>">Volver a iniciar sesión</a>
        <p class="texto-gris texto-chico"><a class="enlace" href="<?= url('recuperar.php') ?>">Pedir otra liga</a></p>
      <?php else: ?>
        <div>
          <h1>¿Olvidaste tu contraseña?</h1>
          <p class="texto-gris mt">Escribe el correo de tu cuenta y te enviamos una liga para elegir una nueva.</p>
        </div>

        <?php if ($aviso): ?><?= aviso($aviso[0], $aviso[1]) ?><?php endif; ?>

        <form class="acceso-caja" method="post" action="<?= url('auth/recuperar.php') ?>">
          <?= csrf_campo() ?>
          <div class="campo">
            <label for="email">Correo electrónico</label>
            <input class="entrada" id="email" name="email" type="email" autocomplete="email" required autofocus>
          </div>
          <button class="boton boton-tinta boton-ancho" type="submit">Enviarme la liga</button>
        </form>

        <p class="texto-gris texto-chico">¿Ya la recordaste? <a class="enlace" href="<?= url('login.php') ?>">Inicia sesión</a></p>
      <?php endif; ?>
    </div>
  </div>
  <aside class="acceso-arte" aria-hidden="true">
    <span class="logo"><?= marca(28) ?></span>
    <blockquote>
      Tus códigos QR siguen funcionando mientras recuperas tu acceso.
      <cite>La liga es personal: no la compartas con nadie.</cite>
    </blockquote>
    <p class="texto-chico nota-arte">Si no pediste cambiar tu contraseña, ignora el correo.</p>
  </aside>
</main>
<?php pagina_fin(); ?>
