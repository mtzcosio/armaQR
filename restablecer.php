<?php
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/recuperacion.php';

// El token viaja en la URL: que no se filtre a terceros (fuentes, CDN) por la cabecera Referer.
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');

$token = (string) ($_GET['token'] ?? '');
$usuario = recuperacion_usuario_por_token($token);

$mensajes = [
    'corta'     => 'La contraseña debe tener al menos 8 caracteres.',
    'larga'     => 'La contraseña no puede tener más de 72 caracteres.',
    'distintas' => 'Las dos contraseñas no coinciden.',
    'csrf'      => 'La sesión del formulario expiró. Vuelve a intentarlo.',
];
$error = $mensajes[$_GET['error'] ?? ''] ?? null;

pagina_inicio('Elegir contraseña nueva');
?>
<main id="contenido" class="acceso">
  <div class="acceso-form">
    <?= boton_tema('acceso-tema') ?>
    <div class="acceso-caja">
      <a class="logo" href="<?= url('index.php') ?>"><?= marca(28) ?><?= e(nombre_portal()) ?></a>

      <?php if ($usuario === null): ?>
        <div>
          <h1>La liga ya no sirve</h1>
          <p class="texto-gris mt">Venció, ya se usó o se pidió una más reciente. Cada liga dura <?= RECUPERACION_MINUTOS ?> minutos y funciona una sola vez.</p>
        </div>
        <a class="boton boton-tinta boton-ancho" href="<?= url('recuperar.php') ?>">Pedir una liga nueva</a>
        <p class="texto-gris texto-chico"><a class="enlace" href="<?= url('login.php') ?>">Volver a iniciar sesión</a></p>
      <?php else: ?>
        <div>
          <h1>Elige tu contraseña nueva</h1>
          <p class="texto-gris mt">Para la cuenta <strong><?= e($usuario['email']) ?></strong>.</p>
        </div>

        <?php if ($error): ?><?= aviso('error', $error) ?><?php endif; ?>

        <form class="acceso-caja" method="post" action="<?= url('auth/restablecer.php') ?>">
          <?= csrf_campo() ?>
          <input type="hidden" name="token" value="<?= e($token) ?>">
          <!-- Ayuda a los gestores de contraseñas a guardar la nueva en la cuenta correcta -->
          <input type="email" name="usuario" value="<?= e($usuario['email']) ?>" autocomplete="username" hidden>
          <div class="campo">
            <label for="password">Contraseña nueva</label>
            <input class="entrada" id="password" name="password" type="password" autocomplete="new-password" minlength="8" maxlength="72" required autofocus>
            <small>Mínimo 8 caracteres.</small>
          </div>
          <div class="campo">
            <label for="password_confirmacion">Repite la contraseña</label>
            <input class="entrada" id="password_confirmacion" name="password_confirmacion" type="password" autocomplete="new-password" minlength="8" maxlength="72" required>
          </div>
          <button class="boton boton-principal boton-ancho" type="submit">Guardar contraseña</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
  <aside class="acceso-arte" aria-hidden="true">
    <span class="logo"><?= marca(28) ?></span>
    <blockquote>
      Usa una contraseña que no uses en otros sitios.
      <cite>Un gestor de contraseñas puede crearla y recordarla por ti.</cite>
    </blockquote>
    <p class="texto-chico nota-arte">Al guardarla, la liga deja de funcionar.</p>
  </aside>
</main>
<script>
  // Aviso inmediato si las contraseñas no coinciden (el servidor también lo valida).
  (function () {
    var nueva = document.getElementById('password'), repetida = document.getElementById('password_confirmacion');
    if (!nueva || !repetida) return;
    function revisar() { repetida.setCustomValidity(repetida.value && repetida.value !== nueva.value ? 'Las contraseñas no coinciden.' : ''); }
    nueva.addEventListener('input', revisar);
    repetida.addEventListener('input', revisar);
  })();
</script>
<?php pagina_fin(); ?>
