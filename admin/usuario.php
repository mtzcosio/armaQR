<?php

// Carga el layout y los helpers de la administración.
require_once __DIR__ . '/../includes/layout.php';
$admin = App\Core\Auth::requireAdmin();

// Lee el usuario desde el parámetro id de la URL; si no existe, muestra la vista de error.
$u = datos_usuario((int) ($_GET['id'] ?? 0));
$planes = datos_planes();

$mensaje = flash_tomar();

// Configura el encabezado con el nombre del usuario y el menú activo.
pagina_inicio($u ? $u['nombre'] : 'Usuario no encontrado', 'admin', 'usuarios');

// Si el usuario no existe, evita continuar con datos vacíos.
if (!$u): ?>
  <div class="caja vacio">
    <strong>No existe un usuario con ese número</strong>
    <span>Puede que se haya eliminado. Búscalo en la lista.</span>
    <a class="boton boton-tinta" href="<?= url('admin/usuarios.php') ?>">Ir a usuarios</a>
  </div>
<?php pagina_fin('admin'); return; endif;

// Obtén los QR y pagos asociados al usuario seleccionado.
$qrs   = datos_qrs_de($u['id']);
$pagos = datos_pagos($u['id']);
$plan  = $planes[$u['plan']] ?? $planes['gratis'] ?? reset($planes);
$esAdmin = $u['rol'] === 'admin';
$notas = datos_consulta(
    'SELECT n.id, n.nota, n.fecha_creacion, a.nombre AS autor
     FROM notas_usuario n LEFT JOIN usuarios a ON a.id = n.autor_id
     WHERE n.usuario_id = :u ORDER BY n.fecha_creacion DESC, n.id DESC',
    [':u' => $u['id']]
);
$esUnoMismo = (int) $u['id'] === (int) $admin['id'];

/** Formulario POST de una acción sobre esta cuenta (auth/usuarios.php). */
$formAccion = fn(string $accion, string $contenido, string $clase = '') => '<form method="post" action="' . e(url('auth/usuarios.php')) . '"'
    . ($clase ? ' class="' . $clase . '"' : '') . '>' . csrf_campo()
    . '<input type="hidden" name="accion" value="' . e($accion) . '"><input type="hidden" name="usuario_id" value="' . (int) $u['id'] . '">'
    . $contenido . '</form>';
?>
<!-- Encabezado del perfil: datos del cliente, plan y acciones disponibles -->
<div class="encabezado">
  <div>
    <a class="miga" href="<?= url('admin/usuarios.php') ?>">← Usuarios</a>
    <div class="ficha">
      <span class="avatar avatar-grande"><?= e(iniciales($u['nombre'])) ?></span>
      <div>
        <h1><?= e($u['nombre']) ?></h1>
        <p class="texto-gris"><?= e($u['email']) ?> · <?= $esAdmin ? 'Administrador' : 'Cliente' ?> #<?= $u['id'] ?><?= $esUnoMismo ? ' · Eres tú' : '' ?></p>
      </div>
      <?= $esAdmin ? '<span class="plan plan-negocio">Administrador</span>' : insignia_plan($u['plan']) ?> <?= insignia_estatus($u['estatus']) ?>
    </div>
  </div>
  <div class="encabezado-acciones">
    <a class="boton boton-fantasma" href="mailto:<?= e($u['email']) ?>"><?= icono('correo', 17) ?> Escribir correo</a>
  </div>
</div>

<?php if ($mensaje): ?><?= aviso($mensaje['tipo'], $mensaje['mensaje']) ?><?php endif; ?>

<!-- Métricas del cliente: pagos, QR, escaneos y último acceso -->
<section class="cifras">
  <div class="cifra"><span>Total pagado</span><strong><?= dinero($u['total_pagado'], false) ?></strong><small><?= count(array_filter($pagos, fn($p) => $p['estatus'] === 'pagado')) ?> cobros</small></div>
  <div class="cifra"><span>Códigos QR</span><strong><?= numero($u['total_qr']) ?></strong><small><?= count(array_filter($qrs, fn($q) => $q['tipo'] === 'dinamico')) ?> dinámicos</small></div>
  <div class="cifra"><span>Escaneos</span><strong><?= numero($u['total_escaneos']) ?></strong><small>Desde el registro</small></div>
  <div class="cifra"><span>Último acceso</span><strong style="font-size:22px"><?= $u['ultimo_acceso'] ? e(fecha_corta($u['ultimo_acceso'])) : 'Nunca' ?></strong><small>Registro: <?= e(fecha_corta($u['fecha_registro'])) ?></small></div>
</section>

<!-- Perfil del cliente: suscripción, QR, pagos y acciones administrativas -->
<div class="rejilla-2">
  <div style="display:grid;gap:22px;align-content:start">
    <section class="caja">
      <div class="caja-cabeza"><h2><?= $esAdmin ? 'Cuenta' : 'Suscripción' ?></h2></div>
      <div class="caja-cuerpo">
        <dl class="datos">
          <?php if ($esAdmin): ?>
          <dt>Rol</dt><dd>Administrador · sin plan ni cobros</dd>
          <?php else: ?>
          <dt>Plan</dt><dd><?= e($plan['nombre']) ?><?= $u['periodicidad'] ? ' · ' . e($u['periodicidad']) : '' ?></dd>
          <dt>Precio</dt><dd><?= $u['periodicidad'] === 'anual' ? dinero($plan['precio_anual'], false) . ' al año' : dinero($plan['precio_mensual'], false) . ' al mes' ?></dd>
          <dt>Pasarela</dt><dd><?= $u['pasarela'] || $u['id_cliente_pasarela'] ? e(trim(ucfirst((string) $u['pasarela']) . ' · ' . $u['id_cliente_pasarela'], ' ·')) : '—' ?></dd>
          <?php endif; ?>
          <dt>Correo verificado</dt><dd><?= $u['email_verificado_en'] ? 'Sí, el ' . e(fecha_corta($u['email_verificado_en'])) : 'No' ?></dd>
          <dt>Zona horaria</dt><dd><?= e($u['zona_horaria']) ?></dd>
        </dl>
      </div>
    </section>

    <section class="caja">
      <div class="caja-cabeza"><h2>Códigos QR</h2><a class="enlace texto-chico" href="<?= url('admin/qrs.php') ?>">Ver todos los QR</a></div>
      <?php if ($qrs): ?>
      <div class="tabla-envoltura">
        <table class="tabla">
          <thead><tr><th>Nombre</th><th>Tipo</th><th>Destino</th><th class="num">Escaneos</th></tr></thead>
          <tbody>
            <?php foreach ($qrs as $q): ?>
              <tr>
                <td><?= e($q['nombre']) ?><?= !$q['activo'] ? ' ' . insignia_estatus('pausado') : '' ?></td>
                <td><span class="tipo-qr <?= $q['tipo'] === 'dinamico' ? 'dinamico' : '' ?>"><?= $q['tipo'] === 'dinamico' ? 'Dinámico' : 'Estático' ?></span></td>
                <td class="texto-gris" style="max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($q['destino']) ?></td>
                <td class="num"><?= $q['tipo'] === 'dinamico' ? numero($q['total_escaneos']) : '—' ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
        <div class="vacio"><strong>Sin códigos</strong><span>Este usuario aún no guarda QR.</span></div>
      <?php endif; ?>
    </section>

    <section class="caja">
      <div class="caja-cabeza"><h2>Pagos</h2></div>
      <?php if ($pagos): ?>
      <div class="tabla-envoltura">
        <table class="tabla">
          <thead><tr><th>Folio</th><th>Fecha</th><th>Concepto</th><th>Método</th><th class="num">Monto</th><th>Estatus</th></tr></thead>
          <tbody>
            <?php foreach ($pagos as $p): ?>
              <tr>
                <td class="texto-gris">#<?= $p['id'] ?></td>
                <td class="sin-salto"><?= e(fecha_corta($p['fecha_pago'])) ?></td>
                <td><?= e($p['concepto']) ?></td>
                <td class="texto-gris"><?= e(ucfirst($p['metodo_pago'])) ?><?= $p['ultimos_digitos'] ? ' •••• ' . e($p['ultimos_digitos']) : '' ?></td>
                <td class="num"><?= dinero($p['monto']) ?></td>
                <td><?= insignia_estatus($p['estatus']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
        <div class="vacio"><strong>Sin pagos registrados</strong><span><?= $esAdmin ? 'Las cuentas de administrador no tienen cobros.' : 'Aquí aparecerán los cobros de las pasarelas.' ?></span></div>
      <?php endif; ?>
    </section>
  </div>

  <aside style="display:grid;gap:22px;align-content:start">
    <section class="caja">
      <div class="caja-cabeza"><h2>Acciones</h2></div>
      <div class="caja-cuerpo acciones-lista">
        <?php if (!$esAdmin): ?>
          <?= $formAccion('asignar_plan', '
            <label class="etiqueta" for="cambiar-plan">Asignar plan manualmente</label>
            <div class="fila-accion">
              <select class="entrada" id="cambiar-plan" name="plan">'
                . implode('', array_map(fn($p) => '<option value="' . e($p['codigo']) . '"' . ($p['codigo'] === $u['plan'] ? ' selected' : '') . '>' . e($p['nombre']) . '</option>', $planes)) . '
              </select>
              <button class="boton boton-tinta" type="submit" data-confirmar="¿Cambiar el plan de ' . e($u['nombre']) . '?"
                data-detalle="Se aplica de inmediato y no genera cobro. Si paga por una pasarela, ajusta también el cobro allá." data-accion="Cambiar plan">Aplicar</button>
            </div>
            <small class="ayuda">Úsalo para cortesías o soporte. No genera cobro.</small>', 'form-accion') ?>
        <?php endif; ?>

        <?php if ($u['email_verificado_en'] === null): ?>
          <?= $formAccion('reenviar_verificacion', '<button class="boton boton-fantasma boton-ancho" type="submit"' . ($u['estatus'] !== 'activo' ? ' disabled title="La cuenta no está activa"' : '')
              . ' data-confirmar="¿Reenviar el correo de verificación?" data-accion="Reenviar"'
              . ' data-detalle="Le llegará a ' . e($u['email']) . ' una liga nueva para confirmar su correo; las anteriores dejan de servir.">'
              . icono('correo', 17) . ' Reenviar correo de verificación</button>') ?>
          <?= $formAccion('verificar_manual', '<button class="boton boton-fantasma boton-ancho" type="submit" data-confirmar="¿Marcar el correo como confirmado?"'
              . ' data-detalle="Úsalo solo si comprobaste por otro medio que ' . e($u['email']) . ' es de esta persona." data-accion="Marcar como confirmado">'
              . icono('check', 17) . ' Marcar correo como confirmado</button>') ?>
        <?php endif; ?>

        <form method="post" action="<?= url('auth/recuperar.php') ?>">
          <?= csrf_campo() ?>
          <input type="hidden" name="accion" value="admin">
          <input type="hidden" name="usuario_id" value="<?= (int) $u['id'] ?>">
          <button class="boton boton-fantasma boton-ancho" type="submit" <?= $u['estatus'] !== 'activo' ? 'disabled title="La cuenta no está activa"' : '' ?>
            data-confirmar="¿Enviar liga para nueva contraseña?" data-accion="Enviar liga"
            data-detalle="Le llegará a <?= e($u['email']) ?> un correo con una liga de un solo uso. Su contraseña actual sigue funcionando hasta que elija otra."><?= icono('candado', 17) ?> Enviar liga para nueva contraseña</button>
        </form>

        <?php if ($esUnoMismo): ?>
          <p class="texto-chico texto-gris">Es tu cuenta: no puedes suspenderla ni eliminarla desde aquí.</p>
        <?php else: ?>
          <?php if ($u['estatus'] !== 'activo'): ?>
            <?= $formAccion('activar', '<button class="boton boton-fantasma boton-ancho" type="submit" data-confirmar="¿Activar la cuenta de ' . e($u['nombre']) . '?"'
                . ' data-detalle="Podrá volver a iniciar sesión de inmediato." data-accion="Activar cuenta">' . icono('play', 17)
                . ($u['estatus'] === 'suspendido' ? ' Reactivar cuenta' : ' Activar cuenta') . '</button>') ?>
          <?php else: ?>
            <?= $formAccion('suspender', '<button class="boton boton-peligro boton-ancho" type="submit" data-confirmar="¿Suspender a ' . e($u['nombre']) . '?"'
                . ' data-detalle="No podrá iniciar sesión y, si está dentro, saldrá del panel en su siguiente clic. Sus datos, QR y pagos se conservan. Puedes reactivarla cuando quieras."'
                . ' data-accion="Suspender cuenta" data-peligro>' . icono('bloquear', 17) . ' Suspender cuenta</button>') ?>
          <?php endif; ?>
          <?= $formAccion('eliminar', '<button class="boton boton-peligro boton-ancho" type="submit" data-confirmar="¿Eliminar la cuenta de ' . e($u['nombre']) . '?"'
              . ' data-detalle="Borrado lógico: deja de aparecer en el panel, no puede entrar, se cancela su suscripción y su correo queda libre para registrarse de nuevo. Los pagos se conservan para contabilidad. No se puede deshacer desde el panel."'
              . ' data-accion="Eliminar cuenta" data-peligro>' . icono('x', 17) . ' Eliminar cuenta</button>') ?>
        <?php endif; ?>
      </div>
    </section>

    <section class="caja" id="notas">
      <div class="caja-cabeza"><div><h2>Notas internas</h2><p>Solo las ve el equipo de administración.</p></div></div>
      <div class="caja-cuerpo notas">
        <?= $formAccion('nota_agregar', '
          <label class="oculto-visual" for="nota">Nota nueva</label>
          <textarea class="entrada" id="nota" name="nota" rows="3" maxlength="2000" required placeholder="Ej. Pidió factura de octubre; se la enviamos el lunes."></textarea>
          <button class="boton boton-fantasma" type="submit">Guardar nota</button>', 'form-accion') ?>
        <?php if ($notas): ?>
          <ol class="notas-lista">
            <?php foreach ($notas as $n): ?>
              <li>
                <p><?= nl2br(e($n['nota'])) ?></p>
                <div class="notas-pie">
                  <small><?= e($n['autor'] ?? 'Administrador') ?> · <?= e(fecha_corta($n['fecha_creacion'])) ?> <?= e(date('H:i', strtotime($n['fecha_creacion']))) ?></small>
                  <?= $formAccion('nota_eliminar', '<input type="hidden" name="nota_id" value="' . (int) $n['id'] . '">'
                      . '<button class="boton-icono boton-icono-peligro" type="submit" title="Eliminar nota" aria-label="Eliminar nota"'
                      . ' data-confirmar="¿Eliminar esta nota?" data-detalle="No se puede deshacer." data-accion="Eliminar" data-peligro>' . icono('x', 15) . '</button>', 'en-linea') ?>
                </div>
              </li>
            <?php endforeach; ?>
          </ol>
        <?php else: ?>
          <p class="texto-gris texto-chico">Aún no hay notas.</p>
        <?php endif; ?>
      </div>
    </section>
  </aside>
</div>
<?php pagina_fin('admin'); ?>
