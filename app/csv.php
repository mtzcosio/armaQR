<?php
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/qr_csv.php';

$usuario = datos_usuario_actual();
$plan = $usuario['plan_datos'];
$permiteDinamicos = $plan['max_qr_dinamicos'] !== 0;

// Resultado del último archivo: se muestra una vez; los creados quedan para descargar sus ligas.
$resultado = $_SESSION['csv_resultado'] ?? null;
unset($_SESSION['csv_resultado']);
$creados = [];
if (!empty($resultado['creados'])) {
    $_SESSION['csv_ultimo'] = $resultado['creados'];
    $ids = array_map('intval', $resultado['creados']);
    $stmt = datos_pdo()->prepare('SELECT id, nombre, tipo, codigo_corto, url_destino, contenido FROM qr_codigos WHERE usuario_id = ? AND id IN ('
        . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY id');
    $stmt->execute(array_merge([(int) $usuario['id']], $ids));
    $creados = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
$maxErrores = 50;

pagina_inicio('Crear QR desde CSV', 'app', 'qrs');
?>
<div class="encabezado">
  <div>
    <a class="miga" href="<?= url_app('index.php') ?>">← Mis QR</a>
    <h1>Crear QR desde CSV</h1>
    <p>Sube una hoja de cálculo con nombre y destino, y creamos todos tus QR de una vez.</p>
  </div>
  <?php if ($plan['permite_masivo']): ?>
    <div class="encabezado-acciones">
      <a class="boton boton-fantasma" href="<?= url('auth/qr-csv.php?plantilla=1') ?>"><?= icono('descargar', 17) ?> Descargar plantilla</a>
    </div>
  <?php endif; ?>
</div>

<?php if (!$plan['permite_masivo']): ?>
  <section class="caja caja-cuerpo vacio">
    <?= icono('archivo', 28) ?>
    <strong>Disponible en el plan Negocio</strong>
    <span>Crea cientos de QR a la vez desde Excel, ideal para catálogos, sucursales o letreros.</span>
    <a class="boton boton-principal" href="<?= url_app('plan.php') ?>">Ver planes</a>
  </section>
<?php pagina_fin('app'); return; endif; ?>

<?php if (!empty($resultado['errores'])): ?>
  <div class="aviso aviso-error" role="alert" id="resultado"><?= icono('alerta', 18) ?><div>
    <strong>No se creó ningún QR. Corrige «<?= e($resultado['archivo']) ?>» y vuelve a subirlo:</strong>
    <ul class="lista-errores">
      <?php foreach (array_slice($resultado['errores'], 0, $maxErrores) as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
      <?php if (count($resultado['errores']) > $maxErrores): ?><li>…y <?= count($resultado['errores']) - $maxErrores ?> errores más.</li><?php endif; ?>
    </ul>
  </div></div>
<?php endif; ?>

<?php if ($creados): ?>
  <section class="caja" id="resultado">
    <div class="caja-cabeza">
      <div><h2><?= icono('check', 20) ?> Creaste <?= count($creados) ?> <?= count($creados) === 1 ? 'QR' : 'QR' ?> desde «<?= e($resultado['archivo']) ?>»</h2>
        <p>Ya están en Mis QR, listos para descargar e imprimir.</p></div>
      <div class="encabezado-acciones">
        <a class="boton boton-fantasma" href="<?= url('auth/qr-csv.php?resultado=1') ?>"><?= icono('descargar', 16) ?> CSV con las ligas</a>
        <a class="boton boton-principal" href="<?= url_app('index.php') ?>">Ir a Mis QR</a>
      </div>
    </div>
    <div class="tabla-envoltura">
      <table class="tabla">
        <thead><tr><th>Nombre</th><th>Tipo</th><th>Liga corta</th><th>Destino</th></tr></thead>
        <tbody>
          <?php foreach ($creados as $q): ?>
            <tr>
              <td><?= e($q['nombre']) ?></td>
              <td><span class="tipo-qr <?= $q['tipo'] === 'dinamico' ? 'dinamico' : '' ?>"><?= $q['tipo'] === 'dinamico' ? 'Dinámico' : 'Estático' ?></span></td>
              <td class="sin-salto"><?= $q['codigo_corto'] ? '<code class="codigo">' . e(dominio_portal() . '/r/' . $q['codigo_corto']) . '</code>' : '<span class="texto-gris">—</span>' ?></td>
              <td class="texto-gris" style="max-width:320px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($q['url_destino'] ?? qr_contenido_guardado($q['contenido'])['valor']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
<?php endif; ?>

<div class="rejilla-2">
  <form class="caja" method="post" action="<?= url('auth/qr-csv.php') ?>" enctype="multipart/form-data">
    <?= csrf_campo() ?>
    <div class="caja-cabeza"><div><h2>Subir archivo</h2><p>Hasta <?= QR_CSV_MAX_FILAS ?> filas y 1 MB por archivo.</p></div></div>
    <div class="caja-cuerpo" style="display:grid;gap:18px">
      <div class="campo">
        <label for="archivo">Archivo CSV</label>
        <input class="entrada archivo" id="archivo" name="archivo" type="file" accept=".csv,text/csv,.txt" required>
        <small>En Excel: Archivo › Guardar como › <strong>CSV UTF-8</strong> (o CSV delimitado por comas).</small>
      </div>
      <?php if ($permiteDinamicos): ?>
        <div class="campo">
          <span class="etiqueta">Tipo de las filas que no lo indiquen</span>
          <div class="opcion-tipo">
            <label><input type="radio" name="tipo" value="dinamico" checked><span class="opcion"><strong>Dinámico</strong><small>Cambias el destino después y ves sus escaneos.</small></span></label>
            <label><input type="radio" name="tipo" value="estatico"><span class="opcion"><strong>Estático</strong><small>El destino va dentro del código.</small></span></label>
          </div>
        </div>
      <?php endif; ?>
      <p class="texto-chico texto-gris">Si alguna fila tiene un error no se crea ningún QR, para que no queden cargas a medias. Te diremos qué fila corregir.</p>
      <button class="boton boton-principal" type="submit"><?= icono('archivo', 17) ?> Crear QR</button>
    </div>
  </form>

  <section class="caja">
    <div class="caja-cabeza"><div><h2>Formato</h2><p>La primera fila lleva los encabezados.</p></div></div>
    <div class="tabla-envoltura">
      <table class="tabla">
        <thead><tr><th>Columna</th><th>Qué lleva</th></tr></thead>
        <tbody>
          <tr><td><code class="codigo">nombre</code></td><td>Obligatoria. Te ayuda a encontrarlo en tu lista.</td></tr>
          <tr><td><code class="codigo">destino</code></td><td>Obligatoria. Dirección web; si no empieza con https:// se lo agregamos.</td></tr>
          <tr><td><code class="codigo">tipo</code></td><td>Opcional: <em>dinámico</em> o <em>estático</em>.</td></tr>
          <tr><td><code class="codigo">color</code> · <code class="codigo">fondo</code></td><td>Opcionales: colores como #172036 y #ffffff.</td></tr>
        </tbody>
      </table>
    </div>
    <div class="caja-cuerpo"><a class="enlace texto-chico" href="<?= url('auth/qr-csv.php?plantilla=1') ?>">Descargar la plantilla con ejemplos</a></div>
  </section>
</div>
<?php pagina_fin('app'); ?>
