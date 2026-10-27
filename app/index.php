<?php
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/qr.php';

$usuario = datos_usuario_actual();
$plan    = $usuario['plan_datos'];
$qrs     = datos_qrs_de($usuario['id']);
$esPago  = $plan['permite_analitica'];

$dinamicosActivos = count(array_filter($qrs, fn($q) => $q['tipo'] === 'dinamico' && $q['activo']));
$estaticosMes     = qr_estaticos_del_mes((int) $usuario['id']); // el mismo conteo que aplica el límite (incluye eliminados)
$conAnterior      = datos_periodo_visible($plan['dias_retencion_escaneos'], 60);
$escaneosMes      = $esPago ? array_sum(datos_serie_escaneos(30, $usuario['id'])) : 0;
$escaneosAnterior = $esPago && $conAnterior ? array_sum(datos_serie_escaneos(30, $usuario['id'], null, 30)) : 0;
$cambioMes        = datos_variacion($escaneosMes, $escaneosAnterior);
$retencion        = $plan['dias_retencion_escaneos'];
$porQr            = datos_escaneos_por_qr($retencion, (int) $usuario['id']); // escaneos de cada QR dentro de la retención del plan

/** Formulario POST de una acción sobre un QR (auth/qr.php). */
$accionQr = fn(string $accion, int $id, string $boton) => '<form method="post" action="' . e(url('auth/qr.php')) . '" class="en-linea">'
    . csrf_campo() . '<input type="hidden" name="accion" value="' . $accion . '"><input type="hidden" name="id" value="' . $id . '">' . $boton . '</form>';

pagina_inicio('Mis QR', 'app', 'qrs');
?>
<div class="encabezado">
  <div>
    <h1>Mis QR</h1>
    <p>Hola, <?= e(explode(' ', $usuario['nombre'])[0]) ?>. Aquí están todos tus códigos.</p>
  </div>
  <div class="encabezado-acciones">
    <?php if ($plan['permite_masivo']): ?>
      <a class="boton boton-fantasma" href="<?= url_app('csv.php') ?>"><?= icono('archivo', 17) ?> Crear desde CSV</a>
    <?php endif; ?>
    <a class="boton boton-principal" href="<?= url_app('crear.php') ?>"><?= icono('mas', 17) ?> Crear QR</a>
  </div>
</div>

<?php if (!$esPago): ?>
<div class="aviso-mejora">
  <span><?= icono('rayo') ?> Con el plan Pro puedes editar el destino de tus QR después de imprimirlos y ver quién los escanea.</span>
  <a class="boton boton-principal boton-chico" href="<?= url_app('plan.php') ?>">Ver planes</a>
</div>
<?php endif; ?>

<section class="cifras" aria-label="Resumen">
  <div class="cifra"><span>Códigos guardados</span><strong><?= numero(count($qrs)) ?></strong><small><?= numero(count($qrs) - count(array_filter($qrs, fn($q) => $q['activo']))) ?> pausados</small></div>
  <?php if ($esPago): ?>
    <div class="cifra destaca"><span>Escaneos en 30 días</span><strong><?= numero($escaneosMes) ?></strong><?php if (!$conAnterior): ?><small>Últimos 30 días</small><?php elseif ($escaneosAnterior > 0): ?><small class="<?= $cambioMes >= 0 ? 'sube' : 'baja' ?>"><?= ($cambioMes >= 0 ? '+' : '') . $cambioMes ?>% vs. 30 días anteriores</small><?php else: ?><small>Sin escaneos en los 30 días anteriores</small><?php endif; ?></div>
    <div class="cifra"><span>QR dinámicos activos</span><strong><?= $dinamicosActivos ?></strong><small>de <?= $plan['max_qr_dinamicos'] === null ? 'ilimitados' : $plan['max_qr_dinamicos'] ?> en tu plan</small></div>
  <?php else: ?>
    <div class="cifra"><span>QR estáticos este mes</span><strong><?= $estaticosMes ?></strong><small>de <?= $plan['max_qr_estaticos_mes'] === null ? 'ilimitados' : $plan['max_qr_estaticos_mes'] ?> en tu plan</small></div>
    <div class="cifra"><span>Escaneos</span><strong>—</strong><small>Disponible en Pro</small></div>
  <?php endif; ?>
</section>

<section class="caja" data-tabla-filtrable>
  <div class="herramientas">
    <div class="segmentado" role="tablist" aria-label="Filtrar">
      <button type="button" role="tab" data-filtro="" aria-selected="true">Todos</button>
      <button type="button" role="tab" data-filtro="dinamico" aria-selected="false">Dinámicos</button>
      <button type="button" role="tab" data-filtro="estatico" aria-selected="false">Estáticos</button>
      <button type="button" role="tab" data-filtro="pausado" aria-selected="false">Pausados</button>
    </div>
    <label class="buscador"><span class="oculto-visual">Buscar</span><?= icono('buscar', 16) ?><input class="entrada" type="search" placeholder="Buscar por nombre o destino" data-buscar></label>
    <span class="texto-gris texto-chico" data-contador></span>
  </div>

  <div class="lista-qr">
    <?php foreach ($qrs as $q):
        $esDinamico = $q['tipo'] === 'dinamico';
        $ligaCorta  = $esDinamico ? 'https://' . dominio_portal() . '/r/' . $q['codigo_corto'] : '';
        $contenido  = $esDinamico ? $ligaCorta : $q['destino'];
        $etiquetas  = $q['tipo'] . ($q['activo'] ? '' : ' pausado');
    ?>
    <article class="item-qr <?= $q['activo'] ? '' : 'pausado' ?>" data-fila data-etiquetas="<?= e($etiquetas) ?>">
      <div class="miniqr" data-qr="<?= e($contenido) ?>" data-qr-tam="52" role="img" aria-label="QR de <?= e($q['nombre']) ?>"></div>
      <div style="min-width:0">
        <h3><?= e($q['nombre']) ?></h3>
        <div class="meta">
          <span class="tipo-qr <?= $esDinamico ? 'dinamico' : '' ?>"><?= $esDinamico ? 'Dinámico' : 'Estático' ?></span>
          <span><?= e(qr_tipo_nombre($q['tipo_contenido'])) ?></span>
          <span>Creado el <?= e(fecha_corta($q['fecha_creacion'])) ?></span>
          <?php if (!$q['activo']): ?><?= insignia_estatus('pausado') ?><?php endif; ?>
        </div>
        <p class="destino">
          <?php if ($esDinamico): ?>
            <?= e(dominio_portal() . '/r/' . $q['codigo_corto']) ?> → <?= e($q['destino']) ?>
          <?php else: ?>
            <?= e($q['destino']) ?>
          <?php endif; ?>
        </p>
      </div>
      <div class="escaneos">
        <?php if ($esDinamico): ?>
          <strong><?= numero($porQr[$q['id']]['total'] ?? 0) ?></strong><small><?= $retencion === null ? 'escaneos' : 'escaneos en ' . numero($retencion) . ' días' ?></small>
        <?php else: ?>
          <small>Sin medición</small>
        <?php endif; ?>
      </div>
      <div class="acciones">
        <?php if ($esDinamico): ?>
          <a class="boton-icono" href="<?= url_app('estadisticas.php', ['qr' => $q['id']]) ?>" title="Ver estadísticas" aria-label="Ver estadísticas de <?= e($q['nombre']) ?>"><?= icono('grafica') ?></a>
          <button class="boton-icono" type="button" data-copiar="<?= e($ligaCorta) ?>" data-copiar-mensaje="Copiaste la liga corta" title="Copiar liga corta" aria-label="Copiar liga corta"><?= icono('copiar') ?></button>
          <?= $accionQr($q['activo'] ? 'pausar' : 'reanudar', (int) $q['id'], $q['activo']
              ? '<button class="boton-icono" type="submit" title="Pausar" aria-label="Pausar ' . e($q['nombre']) . '" data-confirmar="¿Pausar “' . e($q['nombre']) . '”?"'
                . ' data-detalle="Quien lo escanee verá un aviso de que el código no está disponible. Puedes reanudarlo cuando quieras." data-accion="Pausar QR">' . icono('pausa') . '</button>'
              : '<button class="boton-icono" type="submit" title="Reanudar" aria-label="Reanudar ' . e($q['nombre']) . '">' . icono('play') . '</button>') ?>
        <?php endif; ?>
        <a class="boton-icono" href="<?= url_app('crear.php', ['editar' => $q['id']]) ?>" title="<?= $esDinamico ? 'Cambiar destino' : 'Editar nombre y diseño' ?>" aria-label="Editar <?= e($q['nombre']) ?>"><?= icono('editar') ?></a>
        <button class="boton-icono" type="button" title="Descargar PNG" aria-label="Descargar <?= e($q['nombre']) ?>" data-descargar-qr="<?= e(json_encode([
            'texto' => $contenido, 'nombre' => $q['nombre'], 'color' => $q['diseno']['color'], 'fondo' => $q['diseno']['fondo'],
            'logo' => $q['logo_ruta'] ? url($q['logo_ruta']) : '', 'marca' => $plan['marca_agua'] ? 'Creado con ' . nombre_portal() : '',
        ], JSON_UNESCAPED_UNICODE)) ?>"><?= icono('descargar') ?></button>
        <?= $accionQr('eliminar', (int) $q['id'], '<button class="boton-icono boton-icono-peligro" type="submit" title="Eliminar" aria-label="Eliminar ' . e($q['nombre']) . '"'
            . ' data-confirmar="¿Eliminar “' . e($q['nombre']) . '”?" data-accion="Eliminar" data-peligro data-detalle="'
            . ($esDinamico ? 'Su liga corta dejará de funcionar: el código impreso ya no llevará a ningún lado.' : 'Se quitará de tu lista. Los códigos ya impresos siguen funcionando.') . '">' . icono('x') . '</button>') ?>
      </div>
    </article>
    <?php endforeach; ?>

    <div class="vacio" data-sin-resultados hidden>
      <strong>No hay códigos con ese filtro</strong>
      <span>Prueba con otra búsqueda o crea uno nuevo.</span>
    </div>
    <?php if (!$qrs): ?>
      <div class="vacio">
        <strong>Aún no tienes códigos</strong>
        <span>Crea el primero en menos de un minuto.</span>
        <a class="boton boton-principal" href="<?= url_app('crear.php') ?>">Crear QR</a>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php pagina_fin('app', [CDN_QR]); ?>
