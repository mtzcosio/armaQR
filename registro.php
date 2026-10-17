<?php
require_once __DIR__ . '/includes/layout.php';
$planes = datos_planes(true);
$planElegido = $planes[$_GET['plan'] ?? ''] ?? null;
$mensajes = [
    'invalid'   => 'Revisa los datos: nombre, un correo válido y una contraseña de al menos 8 caracteres.',
    'duplicate' => 'Ya existe una cuenta con ese correo. Inicia sesión o recupera tu contraseña.',
    'internal'  => 'No pudimos crear tu cuenta en este momento. Inténtalo de nuevo en unos minutos.',
    'bloqueado' => 'Demasiados intentos. Espera 15 minutos e inténtalo de nuevo.',
    'csrf'      => 'La sesión del formulario expiró. Vuelve a intentarlo.',
];
$error = $mensajes[$_GET['error'] ?? ''] ?? null;
pagina_inicio('Crear cuenta');
?>
<main id="contenido" class="acceso">
  <div class="acceso-form">
    <?= boton_tema('acceso-tema') ?>
    <div class="acceso-caja">
      <a class="logo" href="<?= url('index.php') ?>"><?= marca(28) ?><?= e(nombre_portal()) ?></a>
      <div>
        <h1>Crea tu cuenta</h1>
        <p class="texto-gris mt">
          <?php if ($planElegido && $planElegido['codigo'] !== 'gratis'): ?>
            Empiezas con 14 días del plan <?= e($planElegido['nombre']) ?> sin tarjeta. Al terminar eliges si continúas.
          <?php else: ?>
            Gratis para siempre. Pasa a un plan de pago solo si lo necesitas.
          <?php endif; ?>
        </p>
      </div>

      <?php if ($error): ?><?= aviso('error', $error) ?><?php endif; ?>

      <form class="acceso-caja" method="post" action="<?= url('auth/registro.php') ?>">
        <?= csrf_campo() ?>
        <div class="campo">
          <label for="nombre">Nombre completo</label>
          <input class="entrada" id="nombre" name="nombre" autocomplete="name" required>
        </div>
        <div class="campo">
          <label for="email">Correo electrónico</label>
          <input class="entrada" id="email" name="email" type="email" autocomplete="email" required>
        </div>
        <div class="campo">
          <label for="password">Contraseña</label>
          <input class="entrada" id="password" name="password" type="password" autocomplete="new-password" minlength="8" required>
          <small>Mínimo 8 caracteres.</small>
        </div>
        <label class="check">
          <input type="checkbox" name="acepta" required>
          <span>Acepto los <a class="enlace" href="<?= url('terminos.php') ?>" target="_blank" rel="noopener">términos y condiciones</a> y el <a class="enlace" href="<?= url('privacidad.php') ?>" target="_blank" rel="noopener">aviso de privacidad</a>.</span>
        </label>
        <?php if ($planElegido): ?><input type="hidden" name="plan" value="<?= e($planElegido['codigo']) ?>"><?php endif; ?>
        <button class="boton boton-principal boton-ancho" type="submit">Crear cuenta</button>
      </form>

      <p class="texto-gris texto-chico">¿Ya tienes cuenta? <a class="enlace" href="<?= url('login.php') ?>">Inicia sesión</a></p>
    </div>
  </div>
  <aside class="acceso-arte" aria-hidden="true">
    <span class="logo"><?= marca(28) ?></span>
    <blockquote>
      “Cambiamos el menú tres veces este mes y no reimprimimos ni un porta-menú.”
      <cite>Mariana López · Tacos El Güero, CDMX</cite>
    </blockquote>
    <p class="texto-chico nota-arte">Más de 9,000 escaneos en su QR de la sucursal Centro.</p>
  </aside>
</main>
<?php pagina_fin(); ?>
