<?php
require_once __DIR__ . '/includes/layout.php';

$mensajes = [
    'invalid'   => ['error', 'El correo o la contraseña no son correctos.'],
    'pendiente' => ['error', 'Tu cuenta aún no está activa. Revisa tu correo o contacta a soporte.'],
    'suspended' => ['error', 'Tu cuenta está suspendida. Contacta a soporte para reactivarla.'],
    'bloqueado' => ['error', 'Demasiados intentos fallidos. Espera 15 minutos e inténtalo de nuevo.'],
    'csrf'      => ['error', 'La sesión del formulario expiró. Vuelve a intentarlo.'],
];
$aviso = $mensajes[$_GET['error'] ?? ''] ?? null;
if (!$aviso && isset($_GET['registered'])) {
    $aviso = ['ok', 'Tu cuenta está lista. Ya puedes iniciar sesión. Te enviamos un correo para confirmar tu dirección.'];
} elseif (!$aviso && isset($_GET['restablecida'])) {
    $aviso = ['ok', 'Guardamos tu contraseña nueva. Ya puedes iniciar sesión con ella.'];
} elseif (!$aviso && isset($_GET['admin_required'])) {
    $aviso = ['info', 'Inicia sesión con una cuenta de administrador para continuar.'];
}

pagina_inicio('Iniciar sesión');
?>
<main id="contenido" class="acceso">
  <div class="acceso-form">
    <?= boton_tema('acceso-tema') ?>
    <div class="acceso-caja">
      <a class="logo" href="<?= url('index.php') ?>"><?= marca(28) ?><?= e(nombre_portal()) ?></a>
      <div>
        <h1>Inicia sesión</h1>
        <p class="texto-gris mt">Entra para ver tus códigos y sus escaneos.</p>
      </div>

      <?php if ($aviso): ?><?= aviso($aviso[0], $aviso[1]) ?><?php endif; ?>

      <form class="acceso-caja" method="post" action="<?= url('auth/login.php') ?>">
        <?= csrf_campo() ?>
        <div class="campo">
          <label for="email">Correo electrónico</label>
          <input class="entrada" id="email" name="email" type="email" autocomplete="email" required>
        </div>
        <div class="campo">
          <div style="display:flex;justify-content:space-between;align-items:baseline">
            <label for="password" class="etiqueta">Contraseña</label>
            <a class="enlace texto-chico" href="<?= url('recuperar.php') ?>">¿La olvidaste?</a>
          </div>
          <input class="entrada" id="password" name="password" type="password" autocomplete="current-password" required>
        </div>
        <button class="boton boton-tinta boton-ancho" type="submit">Iniciar sesión</button>
      </form>

      <p class="texto-gris texto-chico">¿No tienes cuenta? <a class="enlace" href="<?= url('registro.php') ?>">Créala gratis</a></p>
    </div>
  </div>
  <aside class="acceso-arte" aria-hidden="true">
    <span class="logo"><?= marca(28) ?></span>
    <blockquote>
      “Pusimos un QR en cada letrero y ahora sabemos qué casa genera más visitas.”
      <cite>Ricardo Soto · Inmobiliaria Soto</cite>
    </blockquote>
    <p class="texto-chico nota-arte">1,733 escaneos en el letrero de Coyoacán.</p>
  </aside>
</main>
<?php pagina_fin(); ?>
