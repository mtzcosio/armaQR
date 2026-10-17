<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/correo.php';
App\Core\Auth::requireAdmin();

if (!correo_tablas_listas()) {
    flash('error', 'Faltan las tablas del módulo de correos. Ejecuta en el servidor: php database/migrate_plantillas_correo.php');
    redirigir('admin/correos.php');
}

$mensaje = flash_tomar();
$q = trim((string) ($_GET['q'] ?? ''));
$personalizadas = correo_variables_personalizadas();
$categorias = array_values(array_unique(array_merge(array_keys(correo_catalogo_integrado()), array_column($personalizadas, 'categoria'))));

// Cuántas plantillas usan cada variable personalizada (para avisar antes de borrarla o renombrarla).
$usos = [];
foreach (datos_consulta('SELECT asunto, preheader, contenido_html FROM plantillas_correo') as $p) {
    foreach (correo_variables_usadas($p['asunto'] . ' ' . $p['preheader'] . ' ' . $p['contenido_html']) as $nombre) {
        $usos[$nombre] = ($usos[$nombre] ?? 0) + 1;
    }
}

$coincide = fn(string $nombre, string $descripcion) => $q === ''
    || str_contains(mb_strtolower($nombre . ' ' . $descripcion), mb_strtolower($q));

/** Formulario POST con CSRF hacia auth/correos.php */
$formInicio = fn(string $accion, int $id = 0, string $extra = '') => '<form method="post" action="' . e(url('auth/correos.php')) . '" ' . $extra . '>'
    . csrf_campo() . '<input type="hidden" name="accion" value="' . e($accion) . '"><input type="hidden" name="id" value="' . ($id ?: '') . '" data-variable-id>';

pagina_inicio('Variables de correo', 'admin', 'correos');
?>
<div class="encabezado">
  <div>
    <a class="miga" href="<?= url('admin/correos.php') ?>">← Correos</a>
    <h1>Variables de correo</h1>
    <p>Datos que puedes usar como {{variable}} en el asunto y el contenido de las plantillas.</p>
  </div>
  <div class="encabezado-acciones">
    <button class="boton boton-principal" type="button" data-nueva-variable><?= icono('mas', 17) ?> Nueva variable</button>
  </div>
</div>

<?php if ($mensaje): ?><?= aviso($mensaje['tipo'], $mensaje['mensaje']) ?><?php endif; ?>

<div class="aviso aviso-info"><?= icono('alerta', 18) ?><span>
  Las <strong>integradas</strong> las llena el sistema en cada envío. Las <strong>personalizadas</strong> usan su
  <em>valor real</em> en todos los correos (por ejemplo, un cupón o el horario de la promoción). Si una personalizada
  no tiene valor real, en el correo enviado se omite.
</span></div>

<section class="caja">
  <form class="herramientas" method="get" action="<?= url('admin/correo-variables.php') ?>">
    <label class="buscador"><span class="oculto-visual">Buscar</span><?= icono('buscar', 16) ?><input class="entrada" type="search" name="q" value="<?= e($q) ?>" placeholder="Nombre o descripción"></label>
    <button class="boton boton-fantasma boton-chico" type="submit">Buscar</button>
    <?php if ($q !== ''): ?><a class="enlace texto-chico" href="<?= url('admin/correo-variables.php') ?>">Quitar búsqueda</a><?php endif; ?>
  </form>

  <?php
    $hay = false;
    foreach (correo_catalogo() as $categoria => $variables):
        $filas = array_filter($variables, fn($descripcion, $nombre) => $coincide($nombre, $descripcion), ARRAY_FILTER_USE_BOTH);
        if (!$filas) continue;
        $hay = true;
  ?>
    <div class="variables-seccion">
      <h3><?= e($categoria) ?></h3>
      <div class="tabla-envoltura">
        <table class="tabla">
          <thead><tr><th>Variable</th><th>Descripción</th><th>Valor de ejemplo</th><th>Valor real</th><th><span class="oculto-visual">Acciones</span></th></tr></thead>
          <tbody>
            <?php foreach ($filas as $nombre => $descripcion):
                $fila = null;
                foreach ($personalizadas as $p) if ($p['nombre'] === $nombre) $fila = $p;
                $usadaEn = $usos[$nombre] ?? 0;
            ?>
            <tr>
              <td class="sin-salto"><code class="codigo">{{<?= e($nombre) ?>}}</code></td>
              <td><?= e($descripcion) ?><?= $usadaEn ? '<div class="texto-chico texto-gris">En ' . $usadaEn . ' ' . ($usadaEn === 1 ? 'plantilla' : 'plantillas') . '</div>' : '' ?></td>
              <?php if ($fila): ?>
                <td class="texto-gris"><?= $fila['valor_ejemplo'] !== '' ? e($fila['valor_ejemplo']) : '—' ?></td>
                <td><?= $fila['valor'] !== '' ? e($fila['valor']) : '<span class="estatus estatus-aviso">Sin valor</span>' ?></td>
                <td class="acciones">
                  <button class="boton-icono" type="button" title="Editar" aria-label="Editar {{<?= e($nombre) ?>}}" data-editar-variable='<?= e(json_encode([
                      'id' => (int) $fila['id'], 'nombre' => $fila['nombre'], 'categoria' => $fila['categoria'], 'descripcion' => $fila['descripcion'],
                      'valor_ejemplo' => $fila['valor_ejemplo'], 'valor' => $fila['valor'], 'usos' => $usadaEn,
                  ])) ?>'><?= icono('editar') ?></button>
                  <?= $formInicio('variable_eliminar', (int) $fila['id'], 'class="en-linea"') ?>
                    <button class="boton-icono boton-icono-peligro" type="submit" title="Eliminar" aria-label="Eliminar {{<?= e($nombre) ?>}}"
                      data-confirmar="¿Eliminar {{<?= e($nombre) ?>}}?" data-accion="Eliminar" data-peligro
                      data-detalle="<?= e($usadaEn ? "La usan {$usadaEn} plantilla(s): no podrás volver a guardarlas hasta quitarla de su contenido." : 'Ninguna plantilla la usa.') ?>"><?= icono('x') ?></button>
                  </form>
                </td>
              <?php else: ?>
                <td class="texto-gris">Automático</td>
                <td><span class="estatus estatus-neutro">Integrada</span></td>
                <td></td>
              <?php endif; ?>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (!$hay): ?><div class="vacio"><strong>Ninguna variable coincide</strong><span>Cambia la búsqueda.</span></div><?php endif; ?>
</section>

<dialog id="dialogo-variable" aria-labelledby="variable-titulo">
  <?= $formInicio('variable_guardar', 0, 'class="dialogo"') ?>
    <h2 id="variable-titulo" data-variable-titulo>Nueva variable</h2>
    <div class="campo">
      <label for="v-nombre">Nombre</label>
      <input class="entrada entrada-codigo" id="v-nombre" name="nombre" required maxlength="100" placeholder="codigo_descuento" autocomplete="off" spellcheck="false">
      <small>Minúsculas, números y guion bajo. Se usa como {{nombre}}.</small>
      <small class="texto-advertencia" data-aviso-renombrar hidden></small>
    </div>
    <div class="campo">
      <label for="v-categoria">Categoría</label>
      <input class="entrada" id="v-categoria" name="categoria" required maxlength="100" list="categorias-variables" placeholder="Promociones">
      <datalist id="categorias-variables"><?php foreach ($categorias as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist>
    </div>
    <div class="campo"><label for="v-descripcion">Descripción</label><input class="entrada" id="v-descripcion" name="descripcion" maxlength="255" placeholder="Para qué sirve"></div>
    <div class="ajuste-fila">
      <div class="campo"><label for="v-ejemplo">Valor de ejemplo</label><input class="entrada" id="v-ejemplo" name="valor_ejemplo" maxlength="255"><small>Vista previa y pruebas.</small></div>
      <div class="campo"><label for="v-valor">Valor real</label><input class="entrada" id="v-valor" name="valor" maxlength="500"><small>Lo que reciben tus clientes.</small></div>
    </div>
    <div class="dialogo-acciones">
      <button type="button" class="boton boton-fantasma" data-dialogo-cancelar>Cancelar</button>
      <button type="submit" class="boton boton-tinta">Guardar variable</button>
    </div>
  </form>
</dialog>
<?php pagina_fin('admin', [asset('assets/js/correos.js')]); ?>
