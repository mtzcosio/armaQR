<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/correo.php';
App\Core\Auth::requireAdmin();

if (!correo_tablas_listas()) {
    flash('error', 'Faltan las tablas del módulo de correos. Ejecuta en el servidor: php database/migrate_plantillas_correo.php');
    redirigir('admin/correos.php');
}

$sistema = correo_codigos_sistema();
$id = (int) ($_GET['id'] ?? 0);
$guardada = $id > 0 ? (datos_consulta('SELECT * FROM plantillas_correo WHERE id = :id', [':id' => $id])[0] ?? null) : null;
if ($id > 0 && !$guardada) {
    flash('error', 'La plantilla no existe o ya se eliminó.');
    redirigir('admin/correos.php');
}

// Valores iniciales: la plantilla guardada, el correo predeterminado de un código del sistema o vacío.
$codigoPedido = strtoupper((string) ($_GET['codigo'] ?? ''));
if ($guardada) {
    $campos = $guardada;
} elseif (isset($sistema[$codigoPedido])) {
    $base = $sistema[$codigoPedido];
    $campos = ['nombre' => $base['nombre'], 'codigo' => $codigoPedido, 'tipo' => $base['tipo'], 'asunto' => $base['asunto'],
               'preheader' => $base['preheader'], 'contenido_html' => $base['html']];
} else {
    $campos = ['tipo' => 'Personalizado'];
}

// Lo capturado y no guardado (errores de validación o envío de prueba) tiene prioridad.
$errores = [];
$formulario = $_SESSION['correo_formulario'] ?? null;
unset($_SESSION['correo_formulario']);
if ($formulario && (int) $formulario['id'] === $id) {
    $campos = array_merge($campos, array_filter($formulario['campos'], fn($v) => $v !== null));
    $errores = $formulario['errores'];
}
$valor = fn(string $clave): string => e((string) ($campos[$clave] ?? ''));

$codigo = (string) ($campos['codigo'] ?? '');
$estatus = $guardada['estatus'] ?? 'borrador';
$delSistema = isset($sistema[$codigo]);
$smtp = correo_config_smtp();
$mensaje = flash_tomar();

pagina_inicio($guardada ? 'Editar correo' : 'Nuevo correo', 'admin', 'correos', ['https://cdnjs.cloudflare.com/ajax/libs/quill/2.0.2/quill.snow.css']);
?>
<div class="encabezado">
  <div>
    <a class="miga" href="<?= url('admin/correos.php') ?>">← Correos</a>
    <h1><?= $guardada ? 'Editar «' . e($guardada['nombre']) . '»' : 'Nueva plantilla' ?></h1>
    <p><?= $guardada ? insignia_estatus($estatus) . ' · Actualizada el ' . e(fecha_corta($guardada['fecha_modificacion'])) : 'Escribe el correo y revisa la vista previa antes de activarlo.' ?></p>
  </div>
</div>

<?php if ($mensaje): ?><?= aviso($mensaje['tipo'], $mensaje['mensaje']) ?><?php endif; ?>
<?php if ($errores): ?>
  <div class="aviso aviso-error" role="alert"><?= icono('alerta', 18) ?><div><strong>No se guardó. Corrige esto:</strong><ul class="lista-errores"><?php foreach ($errores as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div></div>
<?php endif; ?>
<?php if ($delSistema && $estatus !== 'activa'): ?>
  <?= aviso('info', "Este es el correo del sistema {$codigo}. Mientras esta plantilla no esté activa, el sitio envía el correo predeterminado.") ?>
<?php endif; ?>

<form id="form-correo" class="editor-correo" method="post" action="<?= url('auth/correos.php') ?>" data-editor-correo>
  <?= csrf_campo() ?>
  <input type="hidden" name="accion" value="guardar" data-accion>
  <input type="hidden" name="origen" value="editor">
  <input type="hidden" name="id" value="<?= $id ?: '' ?>">
  <input type="hidden" name="contenido_html" id="contenido_html" value="<?= $valor('contenido_html') ?>">

  <div class="editor-correo-principal">
    <section class="caja">
      <?php
        $grupo = fn(string $titulo, string $explicacion) => '<div class="ajuste-grupo-info"><h3>' . e($titulo) . '</h3><p>' . e($explicacion) . '</p></div>';
      ?>
      <div class="ajuste-grupo">
        <?= $grupo('Plantilla', 'El código es lo que busca el sitio para saber qué correo enviar.') ?>
        <div class="ajuste-campos">
          <div class="campo"><label for="nombre">Nombre</label><input class="entrada" id="nombre" name="nombre" value="<?= $valor('nombre') ?>" maxlength="150" required placeholder="Ej. Bienvenida a la cuenta"></div>
          <div class="ajuste-fila">
            <div class="campo">
              <label for="codigo">Código</label>
              <input class="entrada entrada-codigo" id="codigo" name="codigo" value="<?= $valor('codigo') ?>" maxlength="100" placeholder="Se genera del nombre" autocomplete="off" spellcheck="false">
              <small><?= $delSistema ? 'Código del sistema: si lo cambias, el sitio dejará de usar esta plantilla.' : 'Mayúsculas, números y guion bajo.' ?></small>
            </div>
            <div class="campo">
              <label for="tipo">Tipo</label>
              <select class="entrada" id="tipo" name="tipo">
                <?php foreach (CORREO_TIPOS as $t): ?><option <?= $t === ($campos['tipo'] ?? '') ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>
      </div>

      <div class="ajuste-grupo">
        <?= $grupo('Remitente', 'Déjalo vacío para usar el de Configuración › Correo. Muchos proveedores rechazan remitentes de otro dominio.') ?>
        <div class="ajuste-fila">
          <div class="campo"><label for="remitente_nombre">Nombre</label><input class="entrada" id="remitente_nombre" name="remitente_nombre" value="<?= $valor('remitente_nombre') ?>" maxlength="150" placeholder="<?= e($smtp['from_nombre']) ?>"></div>
          <div class="campo"><label for="remitente_correo">Correo</label><input class="entrada" id="remitente_correo" name="remitente_correo" type="email" value="<?= $valor('remitente_correo') ?>" maxlength="190" placeholder="<?= e($smtp['from_email'] ?: 'Sin configurar') ?>"></div>
        </div>
      </div>

      <div class="ajuste-grupo">
        <?= $grupo('Asunto y preheader', 'El preheader es el texto que las bandejas de entrada muestran junto al asunto. Ambos admiten {{variables}}.') ?>
        <div class="ajuste-campos">
          <div class="campo"><label for="asunto">Asunto</label><input class="entrada" id="asunto" name="asunto" value="<?= $valor('asunto') ?>" maxlength="255" data-destino-variable></div>
          <div class="campo"><label for="preheader">Preheader</label><input class="entrada" id="preheader" name="preheader" value="<?= $valor('preheader') ?>" maxlength="255" data-destino-variable></div>
        </div>
      </div>
    </section>

    <section class="caja editor-contenido">
      <div class="caja-cabeza">
        <div class="segmentado" role="tablist" aria-label="Contenido">
          <button type="button" role="tab" aria-selected="true" data-vista="editar">Editar</button>
          <button type="button" role="tab" aria-selected="false" data-vista="previa">Vista previa</button>
        </div>
        <div class="editor-herramientas" data-solo-editar>
          <button class="boton boton-fantasma boton-chico" type="button" data-insertar-boton><?= icono('mas', 15) ?> Botón</button>
          <button class="boton boton-fantasma boton-chico" type="button" data-insertar-separador>Separador</button>
          <button class="boton boton-fantasma boton-chico" type="button" data-cambiar-modo aria-pressed="false">HTML</button>
        </div>
      </div>
      <div class="caja-cuerpo">
        <div class="aviso aviso-error aviso-compacto" data-variables-desconocidas hidden role="status"><?= icono('alerta', 16) ?><span></span></div>
        <div class="aviso aviso-advertencia aviso-compacto" data-variables-sin-dato hidden role="status"><?= icono('alerta', 16) ?><span></span></div>

        <div data-panel-vista="editar">
          <div id="editor-visual" class="editor-visual"></div>
          <textarea id="editor-html" class="entrada editor-html" hidden spellcheck="false" aria-label="Código HTML del correo" data-destino-variable></textarea>
          <p class="texto-chico texto-gris mt" data-nota-modo></p>
        </div>

        <div data-panel-vista="previa" hidden>
          <dl class="previa-sobre">
            <dt>De</dt><dd data-previa-de></dd>
            <dt>Asunto</dt><dd data-previa-asunto></dd>
            <dt>Preheader</dt><dd data-previa-preheader></dd>
          </dl>
          <iframe class="previa-correo" title="Vista previa del correo" sandbox="" data-previa-marco></iframe>
          <p class="texto-chico texto-gris mt">Con datos de ejemplo. Las variables sin dato se ven como {{variable}}.</p>
        </div>
      </div>
    </section>
  </div>

  <aside class="caja editor-variables" aria-labelledby="titulo-variables">
    <div class="caja-cabeza"><div><h2 id="titulo-variables">Variables</h2><p>Con <?= icono('mas', 13) ?> se insertan donde estaba el cursor.</p></div></div>
    <div class="caja-cuerpo">
      <label class="buscador"><span class="oculto-visual">Buscar variable</span><?= icono('buscar', 16) ?><input class="entrada" type="search" placeholder="Buscar variable" data-buscar-variable></label>
      <?php foreach (correo_catalogo() as $categoria => $variables): ?>
        <details class="variables-grupo" open>
          <summary><?= e($categoria) ?> <span class="cuenta"><?= count($variables) ?></span></summary>
          <ul>
            <?php foreach ($variables as $nombre => $descripcion): ?>
              <li data-variable="<?= e($nombre) ?>" data-texto-busqueda="<?= e(mb_strtolower($nombre . ' ' . $descripcion)) ?>">
                <code>{{<?= e($nombre) ?>}}</code>
                <small><?= e($descripcion) ?></small>
                <span class="variables-acciones">
                  <button class="boton-icono" type="button" title="Copiar" aria-label="Copiar {{<?= e($nombre) ?>}}" data-copiar-variable><?= icono('copiar', 15) ?></button>
                  <button class="boton-icono" type="button" title="Insertar" aria-label="Insertar {{<?= e($nombre) ?>}}" data-insertar-variable><?= icono('mas', 16) ?></button>
                </span>
              </li>
            <?php endforeach; ?>
          </ul>
        </details>
      <?php endforeach; ?>
      <p class="texto-chico texto-gris" data-sin-variables hidden>Ninguna variable coincide.</p>
      <a class="enlace texto-chico" href="<?= url('admin/correo-variables.php') ?>" target="_blank" rel="noopener">Crear variables personalizadas ↗</a>
    </div>
  </aside>

  <div class="barra-guardar">
    <span class="ajustes-estado" data-estado-cambios aria-live="polite"><?= $guardada ? 'Sin cambios' : 'Plantilla nueva' ?></span>
    <span class="barra-guardar-acciones">
      <button class="boton boton-fantasma" type="button" data-abrir-prueba-editor><?= icono('correo', 16) ?> Enviar prueba</button>
      <button class="boton boton-fantasma" type="submit" name="guardar" value="borrador" formnovalidate data-guardar>Guardar borrador</button>
      <button class="boton boton-principal" type="submit" name="guardar" value="activar" data-guardar><?= icono('check', 16) ?> Guardar y activar</button>
    </span>
  </div>

  <dialog id="dialogo-prueba-editor" aria-labelledby="prueba-editor-titulo">
    <div class="dialogo">
      <h2 id="prueba-editor-titulo">Enviar prueba</h2>
      <p>Se envía lo que está en pantalla, aunque no lo hayas guardado, con datos de ejemplo y el asunto empezando con [PRUEBA].</p>
      <div class="campo">
        <label for="correo_prueba">Enviar a</label>
        <input class="entrada" id="correo_prueba" name="correo_prueba" type="email" value="<?= e($_SESSION['usuario_email'] ?? '') ?>">
      </div>
      <div class="dialogo-acciones">
        <button type="button" class="boton boton-fantasma" data-dialogo-cancelar>Cancelar</button>
        <button type="button" class="boton boton-tinta" data-enviar-prueba><?= icono('correo', 16) ?> Enviar prueba</button>
      </div>
    </div>
  </dialog>
</form>

<script>
  window.CORREO = <?= json_encode([
      'variables'      => correo_nombres_variables(),
      'ejemplo'        => correo_datos_ejemplo(),
      'sistema'        => array_map(fn($c) => $c['variables'], $sistema),
      // Variables con dato en cualquier envío: las del portal y las personalizadas con valor real.
      'siempre'        => array_merge(array_keys(correo_datos_base()), array_keys(correo_valores_personalizados())),
      'remitente'      => trim($smtp['from_nombre'] . ' <' . ($smtp['from_email'] ?: 'sin configurar') . '>'),
      'guardada'       => (bool) $guardada,
  ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
</script>
<?php pagina_fin('admin', ['https://cdnjs.cloudflare.com/ajax/libs/quill/2.0.2/quill.js', asset('assets/js/correos.js')]); ?>
