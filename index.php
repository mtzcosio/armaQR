<?php

// Punto de entrada general: ejecuta el enrutador MVC cuando se abre la aplicación.
// Si esta constante ya existe, el archivo se está incluyendo desde una vista dedicada.
if (!defined('MVC_CONTROLLER_RUNNING')) {
    require_once __DIR__ . '/app/Core/Application.php';
    (new App\Core\Application())->run();
    exit;
}

// Carga los helpers comunes y el layout de la aplicación antes de renderizar una vista.
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/qr_tipos_info.php';
require_once __DIR__ . '/includes/estadisticas_vista.php';
$planes = datos_planes(true);

// Tablero de ejemplo de la sección de estadísticas (datos ficticios de un menú, siempre los mismos).
$est = qr_tipo_ejemplo_estadisticas('menu');
$grafDias = ['tipo' => 'line', 'etiquetas' => array_map(fn($f) => date('j/n', strtotime($f)), array_keys($est['serie'])),
             'series' => [['nombre' => 'Escaneos', 'datos' => array_values($est['serie'])]]];
pagina_inicio('Genera códigos QR gratis', 'publico', '', [],
    'Crea códigos QR gratis para tu negocio. Con un QR dinámico cambias el contenido sin reimprimir, muestras tu menú, PDF, cupón o tarjeta de contacto y ves cuántas personas lo escanean.');
?>
<?php cabecera_publica(); ?>

<main id="contenido">
  <!-- Héroe: el generador funcionando es lo primero que se ve -->
  <section class="heroe">
    <div class="envoltura">
      <div class="heroe-texto">
        <h1>Tu código QR, listo mientras escribes.</h1>
        <p>Pega una liga, tu WhatsApp o la clave del Wi-Fi y el código aparece al instante. Crea tu cuenta para guardarlo, cambiar el contenido después de imprimirlo y ver en gráficas cada escaneo: cuándo, desde qué ciudad y con qué celular.</p>

        <form class="generador" data-generador="vista-heroe" data-dominio="<?= e(dominio_portal()) ?>" onsubmit="return false">
          <div class="segmentado" role="group" aria-label="Tipo de contenido">
            <button type="button" data-tipo-boton="url" aria-pressed="true">Sitio web</button>
            <button type="button" data-tipo-boton="whatsapp" aria-pressed="false">WhatsApp</button>
            <button type="button" data-tipo-boton="wifi" aria-pressed="false">Wi-Fi</button>
            <button type="button" data-tipo-boton="texto" aria-pressed="false">Texto</button>
          </div>

          <div class="generador-panel" data-panel-tipo="url">
            <label class="oculto-visual" for="g-url">Dirección web</label>
            <input class="entrada" id="g-url" type="url" inputmode="url" data-campo="url" placeholder="tunegocio.mx/menu" value="tacoselguero.mx/menu">
          </div>
          <div class="generador-panel" data-panel-tipo="whatsapp" hidden>
            <div class="fila-campos">
              <div class="campo"><label for="g-tel">Número a 10 dígitos</label><input class="entrada" id="g-tel" inputmode="tel" data-campo="telefono" placeholder="55 1234 5678"></div>
              <div class="campo"><label for="g-msg">Mensaje inicial</label><input class="entrada" id="g-msg" data-campo="mensaje" placeholder="Hola, quiero hacer un pedido"></div>
            </div>
          </div>
          <div class="generador-panel" data-panel-tipo="wifi" hidden>
            <div class="fila-campos">
              <div class="campo"><label for="g-red">Nombre de la red</label><input class="entrada" id="g-red" data-campo="red" placeholder="Cafe-Invitados"></div>
              <div class="campo"><label for="g-clave">Contraseña</label><input class="entrada" id="g-clave" data-campo="clave" placeholder="••••••••"></div>
            </div>
          </div>
          <div class="generador-panel" data-panel-tipo="texto" hidden>
            <label class="oculto-visual" for="g-texto">Texto</label>
            <textarea class="entrada" id="g-texto" data-campo="texto" placeholder="Escribe el texto que quieras mostrar"></textarea>
          </div>

          <div class="cabecera-acciones">
            <button class="boton boton-tinta" type="button" data-descargar-png="mi-qr"><?= icono('descargar', 17) ?> Descargar PNG</button>
            <a class="boton boton-fantasma" href="<?= url('registro.php') ?>">Guardarlo en mi cuenta</a>
          </div>
          <p class="generador-nota">Sin registro descargas QR estáticos en PNG. Nunca caducan.</p>
        </form>
      </div>

      <div class="heroe-lienzo" id="vista-heroe">
        <div class="impreso">
          <p class="impreso-titulo">Escanea el menú</p>
          <p class="impreso-sub">Mesa 4 · Terraza</p>
          <div class="qr-vivo" data-qr-vivo data-qr-tam="220" aria-label="Vista previa del código QR" role="img"></div>
          <div class="impreso-pie"><span>Apunta la cámara</span><strong><?= e(nombre_portal()) ?></strong></div>
        </div>
      </div>
    </div>
  </section>

  <!-- Cómo funciona: es una secuencia real, por eso va numerada -->
  <section class="seccion seccion-papel" id="como-funciona">
    <div class="envoltura">
      <div class="seccion-cabeza">
        <h2>De la idea al mostrador en tres pasos</h2>
        <p>No necesitas diseñador ni instalar nada. Todo pasa en el navegador.</p>
      </div>
      <div class="pasos">
        <div class="paso"><h3>Elige qué quieres compartir</h3><p>Tu sitio web, WhatsApp, la red Wi-Fi o tu tarjeta de contacto. Con un QR dinámico, también tu menú, un PDF, un cupón o un evento.</p></div>
        <div class="paso"><h3>Dale tu estilo</h3><p>Cambia los colores y agrega tu logo al centro para que se reconozca tu marca.</p></div>
        <div class="paso"><h3>Descarga e imprime</h3><p>En PNG para redes, en SVG o PDF para imprenta sin perder calidad.</p></div>
      </div>
    </div>
  </section>

  <!-- Estático vs dinámico: la diferencia que explica los planes -->
  <section class="seccion" id="dinamicos">
    <div class="envoltura">
      <div class="seccion-cabeza">
        <h2>¿Ya imprimiste 500 volantes y cambió tu página?</h2>
        <p>Con un QR dinámico cambias el contenido desde tu cuenta y el código impreso sigue funcionando.</p>
      </div>
      <div class="comparar">
        <div>
          <h3>QR estático</h3>
          <p class="lead">El contenido va dentro del código. Si cambia, hay que generar e imprimir uno nuevo.</p>
          <div class="ruta"><span>Código QR</span><i>lleva a</i><span>tunegocio.mx/menu</span></div>
          <ul class="lista-check">
            <li><?= icono('check') ?> Gratis y sin caducidad</li>
            <li><?= icono('check') ?> Funciona sin internet para Wi-Fi y contacto</li>
            <li class="no"><?= icono('x') ?> No se puede editar</li>
            <li class="no"><?= icono('x') ?> No sabes cuántas veces lo escanean</li>
          </ul>
        </div>
        <div>
          <h3>QR dinámico</h3>
          <p class="lead">El código apunta a una liga corta de <?= e(nombre_portal()) ?> que lleva a tu sitio o a una página con tu información.</p>
          <div class="ruta"><span>Código QR</span><i>lleva a</i><span class="destaca"><?= e(dominio_portal()) ?>/r/Mq8Tz2</span><i>y de ahí a</i><span>tu sitio o tu página</span></div>
          <ul class="lista-check">
            <li><?= icono('check') ?> Cambias el contenido sin reimprimir</li>
            <li><?= icono('check') ?> Muestra tu menú, PDF, cupón o contacto en una página con tus colores</li>
            <li><?= icono('check') ?> Cada escaneo se guarda en tus gráficas: día, hora, ciudad y dispositivo (<a class="enlace" href="#estadisticas">ver ejemplo</a>)</li>
            <li><?= icono('check') ?> Lo pausas cuando termina una promoción</li>
            <li><?= icono('check') ?> Incluido desde el plan Pro</li>
          </ul>
        </div>
      </div>
    </div>
  </section>

  <!-- Estadísticas: cada escaneo se guarda y se ve en gráficas (ejemplo con includes/estadisticas_vista.php) -->
  <section class="seccion seccion-papel" id="estadisticas">
    <div class="envoltura">
      <div class="seccion-cabeza">
        <h2>Cada escaneo queda guardado en tus gráficas</h2>
        <p>Cuando alguien escanea tu QR dinámico, registramos al instante cuándo fue, desde qué ciudad y con qué celular. Así sabes qué impreso funciona, dónde y a qué hora conviene lanzar tu promoción.</p>
      </div>

      <ol class="escaneo-flujo">
        <li><span class="escaneo-flujo-icono"><?= icono('celular', 24) ?></span><div><strong>Alguien escanea tu QR</strong><p>En la mesa, en un volante o en tu vitrina.</p></div></li>
        <li><span class="escaneo-flujo-icono"><?= icono('reloj', 24) ?></span><div><strong>Se registra al momento</strong><p>Fecha y hora, país y ciudad aproximados, tipo de celular, sistema y navegador.</p></div></li>
        <li><span class="escaneo-flujo-icono"><?= icono('grafica', 24) ?></span><div><strong>Lo ves en tus gráficas</strong><p>Por día, por hora y por lugar, de cada código o de todos juntos. Y lo exportas a CSV.</p></div></li>
      </ol>

      <div class="estadisticas-ejemplo tablero-landing">
        <span class="estadisticas-ejemplo-sello"><?= icono('grafica', 15) ?> Ejemplo: el menú de un restaurante durante 30 días</span>
        <section class="cifras" aria-label="Resumen de ejemplo">
          <div class="cifra destaca"><span>Escaneos</span><strong><?= numero($est['total']) ?></strong><small>Últimos 30 días</small></div>
          <div class="cifra"><span>Personas distintas</span><strong><?= numero($est['unicos']) ?></strong><small>Sin guardar su IP</small></div>
          <div class="cifra"><span>Mejor día</span><strong><?= e(fecha_corta($est['mejor'])) ?></strong><small><?= numero($est['mejor_n']) ?> escaneos</small></div>
          <div class="cifra"><span>Ciudad principal</span><strong><?= e($est['ciudades'][0]['ciudad']) ?></strong><small><?= numero($est['ciudades'][0]['n']) ?> escaneos</small></div>
        </section>

        <div class="rejilla-mitad">
          <section class="caja">
            <div class="caja-cabeza"><div><h3>Escaneos por día</h3><p>Cada punto es un día de escaneos</p></div></div>
            <div class="caja-cuerpo"><div class="grafica grafica-chica"><canvas data-grafica='<?= e(json_encode($grafDias)) ?>' aria-label="Gráfica de ejemplo: escaneos por día" role="img"></canvas></div></div>
          </section>
          <section class="caja">
            <div class="caja-cabeza"><div><h3>¿Desde dónde lo escanean?</h3><p>País y ciudad aproximados</p></div></div>
            <div class="caja-cuerpo"><?= estadisticas_ubicacion(['paises' => [], 'ciudades' => array_slice($est['ciudades'], 0, 4), 'sin_ubicacion' => 0], false) ?></div>
          </section>
        </div>

        <section class="caja">
          <div class="caja-cabeza"><div><h3>Día y hora</h3><p>Los momentos de la semana en que más lo escanean</p></div></div>
          <div class="caja-cuerpo"><?= estadisticas_mapa_calor($est['mapa']) ?></div>
        </section>
      </div>

      <div class="estadisticas-landing-pie">
        <p><?= icono('candado', 18) ?> <span>No guardamos nombres, teléfonos ni direcciones IP: la ubicación se calcula al momento y solo queda la ciudad aproximada.</span></p>
        <div class="cabecera-acciones">
          <a class="boton boton-principal" href="<?= url('registro.php?plan=pro') ?>">Quiero ver mis escaneos</a>
          <a class="boton boton-fantasma" href="<?= e(url('tipo.php?t=menu')) ?>#estadisticas">Ver un ejemplo completo</a>
        </div>
      </div>
    </div>
  </section>

  <!-- Tipos de QR dinámicos: mismo catálogo que "Crear QR" (includes/qr_tipos.php) -->
  <section class="seccion" id="tipos">
    <div class="envoltura">
      <div class="seccion-cabeza">
        <h2>Una página para cada cosa que quieras compartir</h2>
        <p>Con un QR dinámico eliges entre <?= count(QR_TIPOS_DINAMICOS) ?> tipos. Llenas los datos, eliges tus colores y ves en un teléfono cómo quedará antes de guardarlo. Toca un tipo para ver un ejemplo.</p>
      </div>
      <ul class="tipos-landing">
        <?php foreach (QR_TIPOS_DINAMICOS as $clave => $t): ?>
          <li><a href="<?= e(url('tipo.php?t=' . $clave)) ?>"><span class="tipo-icono"><?= icono($t['icono'], 22) ?></span><strong><?= e($t['nombre']) ?></strong><small><?= e($t['descripcion']) ?></small><span class="tipos-landing-ver">Ver ejemplo <?= icono('derecha', 14) ?></span></a></li>
        <?php endforeach; ?>
      </ul>
      <p class="tipos-landing-nota">Disponibles desde el plan Pro. <a class="enlace" href="<?= url('registro.php?plan=pro') ?>">Crear mi cuenta</a></p>
    </div>
  </section>

  <!-- Usos -->
  <section class="seccion seccion-papel">
    <div class="envoltura">
      <div class="seccion-cabeza">
        <h2>Lo usan negocios como el tuyo</h2>
      </div>
      <div class="usos">
        <div class="uso"><h3>Restaurantes y cafés</h3><p>El menú en la mesa, con secciones y precios. Cambias platillos del día sin reimprimir los porta-menús.</p><span class="tipo-qr dinamico">Dinámico</span></div>
        <div class="uso"><h3>Inmobiliarias</h3><p>Un QR en cada letrero de “Se vende” con la galería de fotos de la propiedad. Sabes cuál genera más interés.</p><span class="tipo-qr dinamico">Dinámico</span></div>
        <div class="uso"><h3>Eventos y bodas</h3><p>Fecha, lugar con mapa y botón para agregarlo al calendario. Después del evento, cámbialo por la galería de fotos.</p><span class="tipo-qr dinamico">Dinámico</span></div>
        <div class="uso"><h3>Tiendas y consultorios</h3><p>Pedidos por WhatsApp, la red Wi-Fi para clientes o tu tarjeta de contacto.</p><span class="tipo-qr">Estático</span></div>
      </div>
    </div>
  </section>

  <!-- Precios -->
  <section class="seccion" id="precios">
    <div class="envoltura">
      <div class="precios-cabeza">
        <div class="seccion-cabeza">
          <h2>Empieza gratis. Paga cuando lo necesites.</h2>
          <p>Precios en pesos mexicanos con IVA incluido. Cancela cuando quieras.</p>
        </div>
        <div class="segmentado" role="group" aria-label="Periodo de pago">
          <button type="button" data-periodo="mensual" aria-pressed="true">Mensual</button>
          <button type="button" data-periodo="anual" aria-pressed="false">Anual · 2 meses gratis</button>
        </div>
      </div>

      <div class="precios">
        <?php foreach ($planes as $p):
            $anualMes = $p['precio_anual'] > 0 ? round($p['precio_anual'] / 12) : 0;
            $ahorro = $p['precio_mensual'] * 12 - $p['precio_anual'];
            $recomendado = $p['codigo'] === 'pro';
        ?>
        <article class="precio <?= $recomendado ? 'recomendado' : '' ?>"
                 data-mensual="<?= e(dinero($p['precio_mensual'], false)) ?>"
                 data-anual-mes="<?= e(dinero($anualMes, false)) ?>"
                 data-ahorro="<?= $ahorro > 0 ? e('Pagas ' . dinero($p['precio_anual'], false) . ' al año y ahorras ' . dinero($ahorro, false)) : '' ?>">
          <div class="precio-nombre">
            <h3><?= e($p['nombre']) ?></h3>
            <?php if ($recomendado): ?><span class="precio-nota">El más elegido</span><?php endif; ?>
          </div>
          <p class="precio-desc"><?= e($p['descripcion']) ?></p>
          <div>
            <p class="precio-monto"><span data-monto><?= e(dinero($p['precio_mensual'], false)) ?></span> <small>al mes</small></p>
            <p class="precio-ahorro" data-ahorro></p>
          </div>
          <ul class="lista-check">
            <?php foreach ($p['caracteristicas'] as $c): ?><li><?= icono('check') ?> <?= e($c) ?></li><?php endforeach; ?>
          </ul>
          <a class="boton <?= $recomendado ? 'boton-principal' : 'boton-fantasma' ?> boton-ancho" href="<?= url('registro.php?plan=' . $p['codigo']) ?>">
            <?= $p['codigo'] === 'gratis' ? 'Crear cuenta gratis' : 'Empezar con ' . e($p['nombre']) ?>
          </a>
        </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- Preguntas -->
  <section class="seccion seccion-papel" id="preguntas">
    <div class="envoltura">
      <div class="seccion-cabeza"><h2>Preguntas frecuentes</h2></div>
      <div class="preguntas">
        <details><summary>¿Mis QR dejan de funcionar si cancelo?</summary><p>Los QR estáticos funcionan para siempre porque el contenido va dentro del código. Los dinámicos dependen de tu suscripción: si cancelas, siguen funcionando hasta el fin del periodo pagado. Después dejan de abrir su contenido, pero no se borran: si vuelves a contratar, la misma liga vuelve a funcionar.</p></details>
        <details><summary>¿Puedo pagar en OXXO o con transferencia?</summary><p>Sí. Aceptamos tarjeta de crédito y débito, pago en efectivo en OXXO y transferencia SPEI. Los planes anuales también se pueden pagar en una sola exhibición.</p></details>
        <details><summary>¿Emiten factura?</summary><p>Todavía no emitimos factura electrónica (CFDI) desde la plataforma. Te avisaremos por correo en cuanto esté disponible.</p></details>
        <details><summary>¿Qué datos ven de quien escanea mi QR?</summary><p>Fecha y hora, país y ciudad aproximados, tipo de dispositivo, sistema operativo y navegador. No guardamos nombres, teléfonos ni direcciones IP: la ubicación se calcula al momento y de la IP solo queda una huella cifrada, que no permite saber cuál era.</p></details>
        <details><summary>¿Puedo cambiar de plan después?</summary><p>Cuando quieras. Con cobro automático con tarjeta, el cambio aplica de inmediato y se ajusta la diferencia proporcional: pagas la diferencia al subir y recibes un saldo a favor al bajar. Si pagas por periodo (OXXO, SPEI o pago único con tarjeta), el plan nuevo empieza en cuanto se confirma tu pago.</p></details>
      </div>
    </div>
  </section>

  <section class="seccion">
    <div class="envoltura">
      <div class="llamado">
        <div>
          <h2>Crea tu primer QR dinámico hoy</h2>
          <p>Regístrate gratis y pasa a Pro cuando quieras editar tus códigos y darles su propia página.</p>
        </div>
        <a class="boton boton-principal" href="<?= url('registro.php?plan=pro') ?>">Crear cuenta gratis</a>
      </div>
    </div>
  </section>
</main>

<?php pie_publico(); ?>
<?php pagina_fin('publico', [CDN_QR, CDN_CHART]); ?>
