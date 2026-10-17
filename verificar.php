<?php
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/verificacion.php';

// El token viaja en la URL: que no se filtre a terceros (fuentes, CDN) por la cabecera Referer.
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');

$listo = isset($_GET['listo']);
$token = (string) ($_GET['token'] ?? '');
$usuario = $listo ? null : verificacion_usuario_por_token($token);
$sesion = App\Core\Auth::user();
$error = ($_GET['error'] ?? '') === 'csrf' ? 'La sesión del formulario expiró. Vuelve a intentarlo.' : null;

pagina_inicio('Confirmar correo');
?>
<main id="contenido" class="acceso">
  <div class="acceso-form">
    <?= boton_tema('acceso-tema') ?>
    <div class="acceso-caja">
      <a class="logo" href="<?= url('index.php') ?>"><?= marca(28) ?><?= e(nombre_portal()) ?></a>

      <?php if ($listo): ?>
        <div>
          <h1>¡Correo confirmado!</h1>
          <p class="texto-gris mt">Gracias. Ya podemos avisarte de tus pagos y ayudarte a recuperar tu acceso si lo necesitas.</p>
        </div>
        <a class="boton boton-principal boton-ancho" href="<?= url($sesion ? 'app/index.php' : 'login.php') ?>"><?= $sesion ? 'Ir a mis QR' : 'Iniciar sesión' ?></a>

      <?php elseif ($usuario === null): ?>
        <div>
          <h1>La liga ya no sirve</h1>
          <p class="texto-gris mt">Venció, ya se usó o se pidió una más reciente. Cada liga dura <?= VERIFICACION_HORAS ?> horas y funciona una sola vez.</p>
        </div>
        <?= aviso('info', $sesion ? 'Puedes pedir otro correo desde tu panel.' : 'Inicia sesión y pide otro correo desde tu panel.') ?>
        <a class="boton boton-tinta boton-ancho" href="<?= url($sesion ? 'app/index.php' : 'login.php') ?>"><?= $sesion ? 'Ir a mi panel' : 'Iniciar sesión' ?></a>

      <?php else: ?>
        <div>
          <h1>Confirma tu correo</h1>
          <p class="texto-gris mt">Confirma que <strong><?= e($usuario['email']) ?></strong> es tu correo.</p>
        </div>
        <?php if ($error): ?><?= aviso('error', $error) ?><?php endif; ?>
        <form class="acceso-caja" method="post" action="<?= url('auth/verificar.php') ?>">
          <?= csrf_campo() ?>
          <input type="hidden" name="accion" value="confirmar">
          <input type="hidden" name="token" value="<?= e($token) ?>">
          <button class="boton boton-principal boton-ancho" type="submit"><?= icono('check', 17) ?> Confirmar mi correo</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
  <aside class="acceso-arte" aria-hidden="true">
    <span class="logo"><?= marca(28) ?></span>
    <blockquote>
      Un correo confirmado es tu llave para recuperar tu cuenta.
      <cite>Lo usamos para avisos de pagos y de seguridad.</cite>
    </blockquote>
    <p class="texto-chico nota-arte">Nunca te pediremos tu contraseña por correo.</p>
  </aside>
</main>
<?php pagina_fin(); ?>
