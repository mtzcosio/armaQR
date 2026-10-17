<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/pagos.php';
App\Core\Auth::requireAdmin();

$pdo = App\Core\Database::connection();
$rows = $pdo->query('SELECT clave, valor, descripcion FROM configuracion ORDER BY clave')->fetchAll(PDO::FETCH_ASSOC);
$config = [];
foreach ($rows as $row) {
    $config[$row['clave']] = $row['valor'];
}

$mensaje = flash_tomar();
$modoTema = tema_modo($config);
$coloresClaro = tema_valores($config, false);
$coloresOscuro = tema_valores($config, true);

/** Valor guardado, escapado para HTML. */
$valor = fn(string $clave, string $predeterminado = ''): string => e($config[$clave] ?? $predeterminado);

/** Atributo selected si el valor guardado coincide. */
$seleccionado = fn(string $clave, string $opcion, string $predeterminado): string
    => ($config[$clave] ?? $predeterminado) === $opcion ? 'selected' : '';

/** Campo de secreto: nunca muestra el valor guardado; permite reemplazarlo o borrarlo. */
$campoSecreto = function (string $clave, string $etiqueta, string $placeholder) use ($config): string {
    $guardado = !empty($config[$clave]);
    return '<div class="campo"><label for="' . e($clave) . '">' . e($etiqueta) . '</label>'
        . '<input class="entrada" id="' . e($clave) . '" name="' . e($clave) . '" type="password" value="" autocomplete="new-password" placeholder="'
        . e($guardado ? 'Guardada · escribe para reemplazarla' : $placeholder) . '">'
        . ($guardado ? '<label class="check texto-chico"><input type="checkbox" name="borrar_' . e($clave) . '" value="1"> Borrar valor guardado</label>' : '')
        . '</div>';
};

/**
 * Interruptor para ofrecer (o no) una pasarela a los clientes, con su estado actual.
 * Apagada deja de ofrecerse, pero sus webhooks y los suscriptores existentes siguen funcionando.
 */
$interruptorPasarela = function (string $pasarela, string $nombre, bool $configurada) use ($config): string {
    $clave = 'pago_' . $pasarela . '_habilitado';
    $encendida = ($config[$clave] ?? '1') !== '0';
    [$estatus, $texto] = match (true) {
        !$configurada => ['inactiva', 'Sin configurar: captura sus claves para poder ofrecerla.'],
        !$encendida => ['inactiva', 'Desactivada: no se ofrece a los clientes. Los pagos en curso y las suscripciones existentes siguen funcionando.'],
        default => ['activa', 'Se ofrece a los clientes en Plan y pagos.'],
    };
    return '<input type="hidden" name="' . e($clave) . '" value="0">'
        . '<label class="interruptor"><span><strong>Ofrecer ' . e($nombre) . ' a los clientes</strong></span>'
        . '<input id="' . e($clave) . '" name="' . e($clave) . '" type="checkbox" value="1" ' . ($encendida ? 'checked' : '') . '></label>'
        . '<div>' . insignia_estatus($estatus) . ' <small class="texto-gris texto-chico">' . e($texto) . '</small></div>';
};

/** Grupo de ajustes: título y explicación a la izquierda, campos a la derecha. */
$grupoInicio = fn(string $id, string $titulo, string $explicacion): string
    => '<div class="ajuste-grupo" role="group" aria-labelledby="g-' . e($id) . '">'
     . '<div class="ajuste-grupo-info"><h3 id="g-' . e($id) . '">' . e($titulo) . '</h3><p>' . e($explicacion) . '</p></div>'
     . '<div class="ajuste-campos">';
$grupoFin = '</div></div>';

$secciones = [
    'identidad' => ['Identidad', 'qr', 'Nombre, logo y favicon'],
    'colores'   => ['Colores', 'paleta', 'Tema claro, oscuro y paleta'],
    'contacto'  => ['Contacto', 'telefono', 'Atención y redes sociales'],
    'ventas'    => ['Ventas', 'dinero', 'Moneda, impuestos y límites'],
    'correo'    => ['Correo', 'correo', 'Servidor SMTP y pruebas'],
    'pagos'     => ['Pagos', 'tarjeta', 'Pasarelas y métodos'],
];

pagina_inicio('Configuración', 'admin', 'configuracion');
?>
<div class="encabezado">
  <div>
    <h1>Configuración del portal</h1>
    <p>Lo que configures aquí se aplica a todo el portal. Solo los administradores pueden ver y cambiar esta página.</p>
  </div>
</div>

<?php if ($mensaje): ?><?= aviso($mensaje['tipo'], $mensaje['mensaje']) ?><?php endif; ?>

<form method="post" action="<?= url('auth/configuracion.php') ?>" class="ajustes" enctype="multipart/form-data" data-ajustes>
  <?= csrf_campo() ?>
  <input type="hidden" name="seccion" value="identidad" data-seccion-activa>

  <nav class="ajustes-nav" role="tablist" aria-label="Secciones de configuración" aria-orientation="vertical">
    <?php $primera = true; foreach ($secciones as $clave => [$titulo, $ico, $resumen]): ?>
      <button class="ajustes-tab <?= $primera ? 'activo' : '' ?>" type="button" role="tab" id="tab-<?= $clave ?>"
              aria-selected="<?= $primera ? 'true' : 'false' ?>" aria-controls="panel-<?= $clave ?>" data-tab="<?= $clave ?>">
        <?= icono($ico) ?><span><strong><?= e($titulo) ?></strong><small><?= e($resumen) ?></small></span>
      </button>
    <?php $primera = false; endforeach; ?>
  </nav>

  <div class="ajustes-paneles">

    <!-- ===================== IDENTIDAD ===================== -->
    <section id="panel-identidad" class="caja" role="tabpanel" aria-labelledby="tab-identidad" data-tab-panel="identidad">
      <div class="caja-cabeza"><div><h2>Identidad</h2><p>Cómo se presenta el portal a visitantes y clientes.</p></div></div>

      <?= $grupoInicio('datos', 'Datos del portal', 'El nombre aparece en el título de cada página, la cabecera, el menú y la marca de agua de los QR.') ?>
        <div class="campo">
          <label for="portal_nombre">Nombre del portal</label>
          <input class="entrada" id="portal_nombre" name="portal_nombre" value="<?= $valor('portal_nombre', APP_NOMBRE) ?>" maxlength="60" required>
        </div>
        <div class="campo">
          <label for="portal_dominio">Dominio</label>
          <input class="entrada entrada-codigo" id="portal_dominio" name="portal_dominio" value="<?= e(dominio_portal()) ?>" maxlength="100" placeholder="armaqr.com" autocomplete="off" spellcheck="false">
          <small>Las ligas cortas de los QR dinámicos usan https://<?= e(dominio_portal()) ?>/r/{código}. Sin https:// ni barras.</small>
        </div>
        <div class="campo">
          <label for="portal_descripcion">Descripción o propuesta de valor</label>
          <textarea class="entrada" id="portal_descripcion" name="portal_descripcion" rows="3" maxlength="300"><?= $valor('portal_descripcion', 'Genera QR y comparte información de forma rápida.') ?></textarea>
          <small>Una o dos frases. Máximo 300 caracteres.</small>
        </div>
      <?= $grupoFin ?>

      <?= $grupoInicio('logo', 'Logo', 'Se muestra junto al nombre en la cabecera y el menú. Usa una imagen cuadrada con fondo transparente.') ?>
        <div class="archivo-marca">
          <div class="archivo-preview">
            <?php if (!empty($config['portal_logo_url'])): ?><img src="<?= $valor('portal_logo_url') ?>" alt="Logo actual"><?php else: ?><span>Sin logo</span><?php endif; ?>
          </div>
          <div class="campo">
            <label for="portal_logo">Subir logo nuevo</label>
            <input class="entrada archivo" id="portal_logo" name="portal_logo" type="file" accept="image/png,image/jpeg,image/webp,image/gif">
            <small>PNG, JPG, WebP o GIF. Máximo 2 MB y 4096 × 4096 px.</small>
          </div>
        </div>
      <?= $grupoFin ?>

      <?= $grupoInicio('favicon', 'Favicon', 'El icono pequeño de la pestaña del navegador. Se ve mejor un diseño simple de 64 × 64 px o más.') ?>
        <div class="archivo-marca">
          <div class="archivo-preview archivo-preview-chico">
            <?php if (!empty($config['portal_favicon_url'])): ?><img src="<?= $valor('portal_favicon_url') ?>" alt="Favicon actual"><?php else: ?><span>Sin favicon</span><?php endif; ?>
          </div>
          <div class="campo">
            <label for="portal_favicon">Subir favicon nuevo</label>
            <input class="entrada archivo" id="portal_favicon" name="portal_favicon" type="file" accept="image/png,image/jpeg,image/webp,image/gif">
            <small>PNG, JPG, WebP o GIF. Máximo 2 MB.</small>
          </div>
        </div>
      <?= $grupoFin ?>
    </section>

    <!-- ===================== COLORES ===================== -->
    <section id="panel-colores" class="caja" role="tabpanel" aria-labelledby="tab-colores" data-tab-panel="colores" hidden>
      <div class="caja-cabeza"><div><h2>Colores</h2><p>Los cambios se ven al momento en esta página; se aplican a todos al guardar. Usa el botón de luna de arriba para revisar el tema oscuro.</p></div></div>

      <?= $grupoInicio('modo', 'Modo oscuro', 'Decide si los usuarios pueden usar el tema oscuro. La elección de cada usuario se guarda en su navegador.') ?>
        <div class="opcion-tipo opcion-tipo-3">
          <?php foreach (TEMA_MODOS as $modo => $texto):
              [$tituloModo, $detalleModo] = array_map('trim', explode(':', $texto, 2)) + [1 => ''];
          ?>
            <label>
              <input type="radio" name="tema_oscuro" value="<?= e($modo) ?>" <?= $modoTema === $modo ? 'checked' : '' ?>>
              <span class="opcion"><strong><?= e($tituloModo) ?></strong><small><?= e(ucfirst($detalleModo)) ?></small></span>
            </label>
          <?php endforeach; ?>
        </div>
      <?= $grupoFin ?>

      <?php foreach (tema_colores() as $grupo => $colores): ?>
        <?= $grupoInicio('c-' . md5($grupo), $grupo, [
            'Marca' => 'El color de acento identifica al portal: botones principales, enlaces y cifras destacadas.',
            'Base' => 'Fondos, texto y bordes de toda la interfaz.',
            'Bloques destacados' => 'Menú de administración, botón oscuro, banners y notificaciones.',
            'Estados' => 'Avisos y estatus de éxito, advertencia y error.',
            'Gráficas' => 'Series adicionales de las gráficas de estadísticas.',
        ][$grupo] ?? '') ?>
          <div class="colores-cabecera" aria-hidden="true"><span></span><span>Claro</span><span>Oscuro</span></div>
          <?php foreach ($colores as $variable => [$etiqueta, $uso]):
              $idClaro = tema_clave($variable, false);
              $idOscuro = tema_clave($variable, true);
          ?>
            <div class="color-fila">
              <div><strong><?= e($etiqueta) ?></strong><small><?= e($uso) ?></small></div>
              <input class="entrada-color" type="color" id="<?= e($idClaro) ?>" name="<?= e($idClaro) ?>" value="<?= e($coloresClaro[$variable]) ?>"
                     data-color-variable="<?= e($variable) ?>" data-color-tema="claro" aria-label="<?= e($etiqueta) ?> · tema claro">
              <input class="entrada-color" type="color" id="<?= e($idOscuro) ?>" name="<?= e($idOscuro) ?>" value="<?= e($coloresOscuro[$variable]) ?>"
                     data-color-variable="<?= e($variable) ?>" data-color-tema="oscuro" aria-label="<?= e($etiqueta) ?> · tema oscuro">
            </div>
          <?php endforeach; ?>
        <?= $grupoFin ?>
      <?php endforeach; ?>

      <?= $grupoInicio('restablecer', 'Restablecer', 'Vuelve a la paleta original. No afecta el modo oscuro elegido.') ?>
        <label class="check"><input type="checkbox" name="restablecer_colores" value="1"> <span>Restablecer todos los colores a los predeterminados al guardar</span></label>
      <?= $grupoFin ?>
    </section>

    <!-- ===================== CONTACTO ===================== -->
    <section id="panel-contacto" class="caja" role="tabpanel" aria-labelledby="tab-contacto" data-tab-panel="contacto" hidden>
      <div class="caja-cabeza"><div><h2>Contacto</h2><p>Datos que se muestran a clientes y visitantes.</p></div></div>

      <?= $grupoInicio('atencion', 'Atención al cliente', 'Por dónde te pueden escribir o llamar tus clientes.') ?>
        <div class="ajuste-fila">
          <div class="campo"><label for="contacto_correo">Correo de contacto</label><input class="entrada" id="contacto_correo" name="contacto_correo" type="email" value="<?= $valor('contacto_correo') ?>" placeholder="hola@tudominio.mx"></div>
          <div class="campo"><label for="contacto_horario">Horario de atención</label><input class="entrada" id="contacto_horario" name="contacto_horario" value="<?= $valor('contacto_horario') ?>" placeholder="Lunes a viernes, 9:00 a 18:00"></div>
        </div>
        <div class="ajuste-fila">
          <div class="campo"><label for="contacto_telefono">Teléfono</label><input class="entrada" id="contacto_telefono" name="contacto_telefono" type="tel" inputmode="tel" value="<?= $valor('contacto_telefono') ?>" placeholder="55 1234 5678"></div>
          <div class="campo"><label for="contacto_whatsapp">WhatsApp</label><input class="entrada" id="contacto_whatsapp" name="contacto_whatsapp" type="tel" inputmode="tel" value="<?= $valor('contacto_whatsapp') ?>" placeholder="55 1234 5678"></div>
        </div>
      <?= $grupoFin ?>

      <?= $grupoInicio('direccion', 'Dirección', 'Opcional. Útil si atiendes en un local físico.') ?>
        <div class="campo"><label for="contacto_direccion">Dirección</label><textarea class="entrada" id="contacto_direccion" name="contacto_direccion" rows="2"><?= $valor('contacto_direccion') ?></textarea></div>
      <?= $grupoFin ?>

      <?= $grupoInicio('redes', 'Redes sociales', 'Pega la dirección completa de cada perfil. Deja vacío lo que no uses.') ?>
        <div class="campo"><label for="contacto_instagram">Instagram</label><input class="entrada" id="contacto_instagram" name="contacto_instagram" type="url" value="<?= $valor('contacto_instagram') ?>" placeholder="https://instagram.com/tu_cuenta"></div>
        <div class="campo"><label for="contacto_facebook">Facebook</label><input class="entrada" id="contacto_facebook" name="contacto_facebook" type="url" value="<?= $valor('contacto_facebook') ?>" placeholder="https://facebook.com/tu_pagina"></div>
        <div class="campo"><label for="contacto_tiktok">TikTok</label><input class="entrada" id="contacto_tiktok" name="contacto_tiktok" type="url" value="<?= $valor('contacto_tiktok') ?>" placeholder="https://tiktok.com/@tu_cuenta"></div>
      <?= $grupoFin ?>
    </section>

    <!-- ===================== VENTAS ===================== -->
    <section id="panel-ventas" class="caja" role="tabpanel" aria-labelledby="tab-ventas" data-tab-panel="ventas" hidden>
      <div class="caja-cabeza"><div><h2>Ventas</h2><p>Moneda, impuestos y límites de los QR.</p></div></div>

      <?= $grupoInicio('moneda', 'Moneda e impuestos', 'Se usan para mostrar precios y calcular cobros.') ?>
        <div class="ajuste-fila">
          <div class="campo">
            <label for="ventas_moneda">Moneda</label>
            <select class="entrada" id="ventas_moneda" name="ventas_moneda">
              <option value="MXN" <?= $seleccionado('ventas_moneda', 'MXN', 'MXN') ?>>MXN · Peso mexicano</option>
              <option value="USD" <?= $seleccionado('ventas_moneda', 'USD', 'MXN') ?>>USD · Dólar estadounidense</option>
            </select>
          </div>
          <div class="campo">
            <label for="ventas_impuesto">Impuesto</label>
            <div class="entrada-sufijo"><input class="entrada" id="ventas_impuesto" name="ventas_impuesto" type="number" min="0" max="100" step="0.01" value="<?= $valor('ventas_impuesto', '16') ?>"><span>%</span></div>
          </div>
        </div>
      <?= $grupoFin ?>

      <?= $grupoInicio('limites', 'Límites del plan gratis', 'Cuánto dura y cuánto se puede escanear un QR del plan gratis.') ?>
        <div class="ajuste-fila">
          <div class="campo">
            <label for="ventas_duracion_qr">Vigencia de cada QR</label>
            <div class="entrada-sufijo"><input class="entrada" id="ventas_duracion_qr" name="ventas_duracion_qr" type="number" min="0" value="<?= $valor('ventas_duracion_qr', '365') ?>"><span>días</span></div>
          </div>
          <div class="campo">
            <label for="ventas_escaneos_qr">Escaneos por QR</label>
            <div class="entrada-sufijo"><input class="entrada" id="ventas_escaneos_qr" name="ventas_escaneos_qr" type="number" min="0" value="<?= $valor('ventas_escaneos_qr') ?>" placeholder="Sin límite"><span>escaneos</span></div>
            <small>Déjalo vacío para no limitar los escaneos.</small>
          </div>
        </div>
      <?= $grupoFin ?>
    </section>

    <!-- ===================== CORREO ===================== -->
    <section id="panel-correo" class="caja" role="tabpanel" aria-labelledby="tab-correo" data-tab-panel="correo" hidden>
      <div class="caja-cabeza"><div><h2>Correo</h2><p>Servidor SMTP para enviar correos desde el portal.</p></div></div>

      <?= $grupoInicio('smtp', 'Servidor', 'Los datos los da tu proveedor de correo. Puerto 587 usa TLS; puerto 465 usa SSL.') ?>
        <input type="hidden" name="smtp_enabled" value="0">
        <label class="interruptor"><span><strong>Habilitar envío por SMTP</strong></span><input id="smtp_enabled" name="smtp_enabled" type="checkbox" value="1" <?= ($config['smtp_enabled'] ?? '') === '1' ? 'checked' : '' ?>></label>
        <div class="fila-servidor">
          <div class="campo"><label for="smtp_host">Servidor SMTP</label><input class="entrada" id="smtp_host" name="smtp_host" value="<?= $valor('smtp_host') ?>" placeholder="smtp.tuproveedor.com"></div>
          <div class="campo"><label for="smtp_port">Puerto</label><input class="entrada" id="smtp_port" name="smtp_port" type="number" min="1" max="65535" value="<?= $valor('smtp_port', '587') ?>"></div>
          <div class="campo">
            <label for="smtp_encryption">Cifrado</label>
            <select class="entrada" id="smtp_encryption" name="smtp_encryption">
              <option value="tls" <?= $seleccionado('smtp_encryption', 'tls', 'tls') ?>>TLS</option>
              <option value="ssl" <?= $seleccionado('smtp_encryption', 'ssl', 'tls') ?>>SSL</option>
              <option value="none" <?= $seleccionado('smtp_encryption', 'none', 'tls') ?>>Ninguno</option>
            </select>
          </div>
        </div>
        <p class="aviso-campo" data-aviso-smtp hidden><?= icono('alerta', 16) ?> <span></span></p>
      <?= $grupoFin ?>

      <?= $grupoInicio('auth', 'Autenticación', 'Usuario y contraseña de la cuenta de correo. La contraseña nunca se vuelve a mostrar.') ?>
        <div class="ajuste-fila">
          <div class="campo"><label for="smtp_usuario">Usuario</label><input class="entrada" id="smtp_usuario" name="smtp_usuario" value="<?= $valor('smtp_usuario') ?>" autocomplete="off"></div>
          <?= $campoSecreto('smtp_password', 'Contraseña', 'Contraseña del servidor') ?>
        </div>
      <?= $grupoFin ?>

      <?= $grupoInicio('remitente', 'Remitente', 'Cómo verán tus clientes los correos que envía el portal.') ?>
        <div class="ajuste-fila">
          <div class="campo"><label for="smtp_from_nombre">Nombre</label><input class="entrada" id="smtp_from_nombre" name="smtp_from_nombre" value="<?= $valor('smtp_from_nombre') ?>" placeholder="<?= e(nombre_portal()) ?>"></div>
          <div class="campo"><label for="smtp_from_email">Correo</label><input class="entrada" id="smtp_from_email" name="smtp_from_email" type="email" value="<?= $valor('smtp_from_email') ?>" placeholder="no-responder@tudominio.mx"></div>
        </div>
      <?= $grupoFin ?>

      <?= $grupoInicio('prueba', 'Probar envío', 'Primero se guarda el formulario y después se envía un correo de prueba a esta dirección.') ?>
        <div class="campo">
          <label for="smtp_test">Enviar prueba a</label>
          <div class="prueba-smtp">
            <input class="entrada" id="smtp_test" name="smtp_test" type="email" value="<?= e($_SESSION['usuario_email'] ?? '') ?>" placeholder="tu@correo.com">
            <button class="boton boton-fantasma" type="submit" name="action" value="probar-correo"><?= icono('correo', 17) ?> Probar envío</button>
          </div>
        </div>
      <?= $grupoFin ?>
    </section>

    <!-- ===================== PAGOS ===================== -->
    <section id="panel-pagos" class="caja" role="tabpanel" aria-labelledby="tab-pagos" data-tab-panel="pagos" hidden>
      <div class="caja-cabeza"><div><h2>Pagos</h2><p>Pasarelas y métodos disponibles para las compras.</p></div></div>

      <p class="caja-cuerpo texto-gris texto-chico">Las claves no se vuelven a mostrar después de guardarlas y se almacenan sin cifrar, así que restringe el acceso a la base de datos. Una pasarela sin clave no se ofrece a los clientes. Los planes se activan, renuevan y bajan solo cuando llega el aviso (webhook) de la pasarela.</p>

      <!-- Stripe: tarjeta con cobro automático -->
      <?= $grupoInicio('stripe', 'Stripe', 'Tarjeta con cobro automático mensual o anual. Al cambiar de plan se cobra la diferencia proporcional.') ?>
        <?php $whsecValido = str_starts_with((string) ($config['pago_stripe_webhook'] ?? ''), 'whsec_'); ?>
        <?= $interruptorPasarela('stripe', 'Stripe', !empty($config['pago_stripe']) && $whsecValido) ?>
        <?php if (!empty($config['pago_stripe']) && !$whsecValido): ?>
          <?= aviso('error', empty($config['pago_stripe_webhook'])
              ? 'Falta el secreto del webhook: los pagos se cobran pero no activan el plan.'
              : 'El secreto del webhook guardado no empieza con whsec_: Stripe cobra pero el plan no se activa. Reemplázalo por el "Signing secret" del webhook.') ?>
        <?php endif; ?>
        <div class="ajuste-fila">
          <?= $campoSecreto('pago_stripe', 'Clave secreta', 'sk_live_… o sk_test_…') ?>
          <?= $campoSecreto('pago_stripe_webhook', 'Secreto del webhook', 'whsec_…') ?>
        </div>
        <div class="campo">
          <label for="webhook-stripe">URL del webhook</label>
          <input class="entrada" id="webhook-stripe" readonly value="<?= e(url_absoluta('webhooks/stripe.php')) ?>">
          <small class="texto-gris texto-chico">Regístrala en Stripe › Developers › Webhooks con los eventos: checkout.session.completed, customer.subscription.created, customer.subscription.updated, customer.subscription.deleted, invoice.paid, invoice.payment_failed y charge.refunded.</small>
        </div>
      <?= $grupoFin ?>

      <!-- Conekta: pago único con tarjeta, OXXO o SPEI -->
      <?= $grupoInicio('conekta', 'Conekta', 'Pago único de un periodo con tarjeta, OXXO o SPEI. No se renueva solo.') ?>
        <?= $interruptorPasarela('conekta', 'Conekta', !empty($config['pago_conekta'])) ?>
        <?= $campoSecreto('pago_conekta', 'Llave privada', 'key_…') ?>
        <div class="campo">
          <label for="webhook-conekta">URL del webhook</label>
          <input class="entrada" id="webhook-conekta" readonly value="<?= e(url_absoluta('webhooks/conekta.php')) ?>">
          <small class="texto-gris texto-chico">Regístrala en Conekta › Desarrolladores › Webhooks con los eventos: order.paid, order.expired, order.canceled y order.refunded.</small>
        </div>
      <?= $grupoFin ?>

      <?= $grupoInicio('metodos', 'Métodos de pago', 'Datos que se muestran al cliente según cómo prefiera pagar.') ?>
        <div class="campo"><label for="pago_tarjeta">Tarjeta</label><input class="entrada" id="pago_tarjeta" name="pago_tarjeta" value="<?= $valor('pago_tarjeta') ?>" placeholder="Crédito, débito, Apple Pay y Google Pay"></div>
        <div class="ajuste-fila">
          <div class="campo"><label for="pago_oxxo">OXXO</label><input class="entrada" id="pago_oxxo" name="pago_oxxo" value="<?= $valor('pago_oxxo') ?>" placeholder="Instrucciones de pago"></div>
          <div class="campo"><label for="pago_spei">SPEI</label><input class="entrada" id="pago_spei" name="pago_spei" value="<?= $valor('pago_spei') ?>" placeholder="CLABE y banco"></div>
        </div>
      <?= $grupoFin ?>
    </section>
  </div>

  <div class="ajustes-barra">
    <span class="ajustes-estado" data-estado-cambios aria-live="polite">Sin cambios</span>
    <button class="boton boton-principal" type="submit"><?= icono('check', 17) ?> Guardar configuración</button>
  </div>
</form>
<?php pagina_fin('admin'); ?>
