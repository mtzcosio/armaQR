<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/seguridad.php';
require_once __DIR__ . '/datos.php';
require_once __DIR__ . '/tema.php';
require_once __DIR__ . '/../app/Core/Auth.php';

/** Íconos SVG en línea (trazo de 1.75px, heredan el color del texto) */
function icono(string $nombre, int $tam = 18): string
{
    $p = [
        'inicio'     => '<path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1z"/>',
        'qr'         => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3zM20 14v1M14 20h1M17 17h4v4h-4"/>',
        'mas'        => '<path d="M12 5v14M5 12h14"/>',
        'grafica'    => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'tarjeta'    => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20M6 15h4"/>',
        'usuarios'   => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.5a3.5 3.5 0 0 1 0 7M18 14.5a6.5 6.5 0 0 1 3.5 5.5"/>',
        'dinero'     => '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 9v.01M18 15v.01"/>',
        'ajustes'    => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
        'salir'      => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
        'buscar'     => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'descargar'  => '<path d="M12 3v12M7 10l5 5 5-5M4 21h16"/>',
        'candado'    => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
        'ojo'        => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'pausa'      => '<path d="M9 5v14M15 5v14"/>',
        'play'       => '<path d="M7 4.5v15l12-7.5z"/>',
        'editar'     => '<path d="M4 20h4L19 9l-4-4L4 16zM14 6l4 4"/>',
        'correo'     => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'externo'    => '<path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
        'check'      => '<path d="m5 12 4.5 4.5L19 7"/>',
        'x'          => '<path d="M6 6l12 12M18 6 6 18"/>',
        'menu'       => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'copiar'     => '<rect x="8" y="8" width="13" height="13" rx="2"/><path d="M16 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h3"/>',
        'bloquear'   => '<circle cx="12" cy="12" r="9"/><path d="m5.6 5.6 12.8 12.8"/>',
        'alerta'     => '<path d="M12 3 2 20h20zM12 10v4M12 17v.01"/>',
        'rayo'       => '<path d="M13 2 4 14h7l-1 8 9-12h-7z"/>',
        'archivo'    => '<path d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8z"/><path d="M14 3v5h5"/>',
        'paleta'     => '<path d="M12 3a9 9 0 1 0 0 18c1.1 0 1.8-.9 1.8-1.9 0-.5-.2-.9-.5-1.3-.3-.3-.5-.8-.5-1.3 0-1 .8-1.8 1.8-1.8H17a4 4 0 0 0 4-4C21 6.5 17 3 12 3z"/><circle cx="7.5" cy="11" r="1"/><circle cx="10" cy="7" r="1"/><circle cx="14.5" cy="7" r="1"/><circle cx="17" cy="11" r="1"/>',
        'telefono'   => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/>',
        'luna'       => '<path d="M20 14.5A8 8 0 1 1 9.5 4a6.5 6.5 0 0 0 10.5 10.5z"/>',
        'sol'        => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
    ];
    $svg = $p[$nombre] ?? '';
    return '<svg class="ico" width="' . $tam . '" height="' . $tam . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $svg . '</svg>';
}

/** URL dentro de /app con parámetros opcionales */
function url_app(string $ruta, array $params = []): string
{
    return url('app/' . $ruta) . ($params ? '?' . http_build_query($params) : '');
}

/**
 * URL de un archivo estático con su fecha de modificación como versión (?v=...),
 * para que el navegador descargue la copia nueva cada vez que el archivo cambia.
 */
function asset(string $ruta): string
{
    $archivo = dirname(__DIR__) . '/' . ltrim($ruta, '/');
    return url($ruta) . (is_file($archivo) ? '?v=' . filemtime($archivo) : '');
}

/** Mensaje de aviso accesible. $tipo: ok | error | info */
function aviso(string $tipo, string $mensaje): string
{
    $clase = ['ok' => 'aviso-ok', 'error' => 'aviso-error'][$tipo] ?? 'aviso-info';
    $rol = $tipo === 'error' ? 'alert' : 'status';
    return '<div class="aviso ' . $clase . '" role="' . $rol . '">' . icono($tipo === 'ok' ? 'check' : 'alerta', 18) . '<span>' . e($mensaje) . '</span></div>';
}

/** Botón para alternar el tema oscuro; vacío si el administrador lo desactivó. */
function boton_tema(string $clase = ''): string
{
    if (tema_modo(configuracion_activa()) === 'desactivado') {
        return '';
    }
    return '<button class="boton-icono boton-tema ' . e($clase) . '" type="button" data-cambiar-tema aria-pressed="false" aria-label="Activar modo oscuro" title="Cambiar tema">'
        . '<span class="tema-ico-luna">' . icono('luna') . '</span><span class="tema-ico-sol">' . icono('sol') . '</span></button>';
}

/** Marca: el logo configurado o un patrón localizador de QR (el cuadrito de las esquinas) */
function marca(int $tam = 26): string
{
    $logo = configuracion_activa()['portal_logo_url'] ?? '';
    if ($logo !== '') {
        return '<img class="marca-logo" src="' . e($logo) . '" alt="' . e(nombre_portal()) . '" width="' . $tam . '" height="' . $tam . '" style="object-fit:contain">';
    }
    return '<svg width="' . $tam . '" height="' . $tam . '" viewBox="0 0 28 28" aria-hidden="true">'
        . '<rect x="1.5" y="1.5" width="25" height="25" rx="5" fill="none" stroke="var(--rosa)" stroke-width="3"/>'
        . '<rect x="8.5" y="8.5" width="11" height="11" rx="2.5" fill="var(--tinta)"/></svg>';
}

/** Insignia de plan */
function insignia_plan(string $plan): string
{
    $nombres = ['gratis' => 'Gratis', 'pro' => 'Pro', 'negocio' => 'Negocio'];
    return '<span class="plan plan-' . e($plan) . '">' . e($nombres[$plan] ?? $plan) . '</span>';
}

/** Insignia de estatus */
function insignia_estatus(string $estatus): string
{
    $mapa = [
        'activo'      => ['Activo', 'ok'],
        'pendiente'   => ['Sin verificar', 'aviso'],
        'suspendido'  => ['Suspendido', 'error'],
        'pagado'      => ['Pagado', 'ok'],
        'por_pagar'   => ['Por pagar', 'aviso'],
        'fallido'     => ['Fallido', 'error'],
        'reembolsado' => ['Reembolsado', 'neutro'],
        'pausado'     => ['Pausado', 'neutro'],
        'borrador'    => ['Borrador', 'aviso'],
        'activa'      => ['Activa', 'ok'],
        'inactiva'    => ['Inactiva', 'neutro'],
    ];
    [$texto, $clase] = $mapa[$estatus] ?? [$estatus, 'neutro'];
    return '<span class="estatus estatus-' . $clase . '">' . e($texto) . '</span>';
}

/**
 * Abre la página.
 * $area: 'publico' | 'app' | 'admin'
 */
function pagina_inicio(string $titulo, string $area = 'publico', string $activo = '', array $estilos = []): void
{
    $tituloCompleto = $titulo ? "$titulo · " . nombre_portal() : nombre_portal();
    ?>
<!doctype html>
<html lang="es-MX">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?= tema_script(configuracion_activa()) ?>
  <title><?= e($tituloCompleto) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800&family=Public+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <?php $favicon = configuracion_activa()['portal_favicon_url'] ?? ''; ?>
  <?php if ($favicon !== ''): ?><link rel="icon" href="<?= e($favicon) ?>" type="image/<?= e(pathinfo($favicon, PATHINFO_EXTENSION)) ?>"><?php endif; ?>
  <?php foreach ($estilos as $hoja): ?><link rel="stylesheet" href="<?= e($hoja) ?>"><?php endforeach; ?>
  <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
  <?= tema_css(configuracion_activa()) ?>
</head>
<body class="area-<?= e($area) ?>">
<a class="saltar" href="#contenido">Saltar al contenido</a>
<?php
    if ($area === 'app' || $area === 'admin') {
        panel_inicio($area, $activo);
    }
}

/** Estructura de panel: barra lateral + barra superior */
function panel_inicio(string $area, string $activo): void
{
    if ($area === 'admin') {
        $u = App\Core\Auth::requireAdmin();

        $menu = [
            'resumen'       => ['admin/index.php',          'Resumen',                'inicio'],
            'usuarios'      => ['admin/usuarios.php',       'Usuarios',               'usuarios'],
            'pagos'         => ['admin/pagos.php',          'Pagos',                  'dinero'],
            'qrs'           => ['admin/qrs.php',            'Códigos QR',             'qr'],
            'planes'        => ['admin/planes.php',         'Planes',                 'ajustes'],
            'correos'       => ['admin/correos.php',        'Correos',                'correo'],
            'legales'       => ['admin/legales.php',        'Legales',                'archivo'],
            'configuracion' => ['admin/configuracion.php',  'Configuración',           'candado'],
        ];
        $quien = ['nombre' => $u['nombre'], 'detalle' => 'Administrador'];
    } else {
        $u = App\Core\Auth::requireUser();
        $menu = [
            'qrs'          => ['app/index.php',        'Mis QR',        'qr'],
            'crear'        => ['app/crear.php',        'Crear QR',      'mas'],
            'estadisticas' => ['app/estadisticas.php', 'Estadísticas',  'grafica'],
            'plan'         => ['app/plan.php',         'Plan y pagos',  'tarjeta'],
        ];
        $quien = ['nombre' => $u['nombre'], 'detalle' => App\Core\Auth::isAdminRole($u) ? 'Administrador' : 'Cliente'];
    }
    ?>
<div class="panel">
  <aside class="lateral" id="lateral">
    <a class="lateral-marca" href="<?= url('index.php') ?>"><?= marca() ?><span><?= e(nombre_portal()) ?></span></a>
    <?php if ($area === 'admin'): ?><p class="lateral-area">Administración</p><?php endif; ?>
    <nav class="lateral-nav" aria-label="Menú principal">
      <?php foreach ($menu as $clave => [$ruta, $texto, $ico]): ?>
        <a href="<?= url($ruta) ?>" class="<?= $clave === $activo ? 'activo' : '' ?>" <?= $clave === $activo ? 'aria-current="page"' : '' ?>>
          <?= icono($ico) ?><span><?= e($texto) ?></span>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="lateral-pie">
      <div class="quien">
        <span class="avatar"><?= e(iniciales($quien['nombre'])) ?></span>
        <span><strong><?= e($quien['nombre']) ?></strong><small><?= e($quien['detalle']) ?></small></span>
      </div>
      <a class="lateral-salir" href="<?= url('auth/logout.php') ?>"><?= icono('salir', 16) ?> Cerrar sesión</a>
    </div>
  </aside>
  <div class="panel-cuerpo">
    <header class="barra">
      <button class="boton-icono menu-movil" type="button" data-abrir-lateral aria-label="Abrir menú"><?= icono('menu') ?></button>
      <a class="barra-marca" href="<?= url('index.php') ?>"><?= marca(22) ?></a>
      <div class="barra-espacio"></div>
      <?= boton_tema() ?>
      <?php if ($area === 'app'): ?>
        <a class="boton boton-principal boton-chico" href="<?= url('app/crear.php') ?>"><?= icono('mas', 16) ?> Crear QR</a>
      <?php else: ?>
        <a class="boton boton-fantasma boton-chico" href="<?= url('app/index.php') ?>"><?= icono('ojo', 16) ?> Ver como cliente</a>
      <?php endif; ?>
    </header>
    <main id="contenido" class="contenido">
<?php
    // Panel del cliente: mensajes de las acciones y aviso para confirmar el correo.
    if ($area === 'app') {
        $f = flash_tomar();
        if ($f) {
            echo aviso($f['tipo'], $f['mensaje']);
        }
        if (($u['email_verificado_en'] ?? null) === null) {
            echo '<div class="aviso aviso-advertencia aviso-con-accion" role="status">' . icono('correo', 18)
                . '<span>Confirma tu correo <strong>' . e($u['email']) . '</strong> con la liga que te enviamos. Así podrás recuperar tu cuenta si olvidas tu contraseña.</span>'
                . '<form method="post" action="' . e(url('auth/verificar.php')) . '">' . csrf_campo()
                . '<input type="hidden" name="accion" value="reenviar">'
                . '<button class="boton boton-fantasma boton-chico" type="submit">Reenviar correo</button></form></div>';
        }
    }
}

/** Cierra la página */
function pagina_fin(string $area = 'publico', array $scripts = []): void
{
    if ($area === 'app' || $area === 'admin') {
        echo "    </main>\n  </div>\n</div>\n<div class=\"velo\" data-cerrar-lateral></div>\n";
        echo '<dialog id="dialogo-confirmar"><div class="dialogo">'
            . '<h2 data-dialogo-titulo></h2><p data-dialogo-texto></p>'
            . '<div class="dialogo-acciones"><button type="button" class="boton boton-fantasma" data-dialogo-cancelar>Cancelar</button>'
            . '<button type="button" class="boton boton-tinta" data-dialogo-ok>Confirmar</button></div></div></dialog>' . "\n";
    }
    foreach ($scripts as $s) {
        echo '<script src="' . e($s) . '"></script>' . "\n";
    }
    echo '<script src="' . e(asset('assets/js/app.js')) . '"></script>' . "\n";
    echo "</body>\n</html>\n";
}

/** CDN usados */
// qrcode-generator: maneja UTF-8 (acentos, ñ) y permite exportar SVG. qrcodejs 1.0.0 fallaba con acentos.
const CDN_QR    = 'https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js';
const CDN_CHART = 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js';
