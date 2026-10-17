<?php
/**
 * DATOS DE EJEMPLO
 * --------------------------------------------------------------------
 * Todas las pantallas leen de este archivo. Cuando conectes la base de
 * datos, reemplaza cada función por su consulta (las tablas equivalentes
 * están indicadas en cada bloque) y las vistas seguirán funcionando.
 */

/** Tabla: planes */
function mock_planes(): array
{
    return [
        'gratis' => [
            'id' => 1, 'codigo' => 'gratis', 'nombre' => 'Gratis',
            'descripcion' => 'Para probar y para QR que no cambian.',
            'precio_mensual' => 0, 'precio_anual' => 0,
            'max_qr_estaticos_mes' => 50, 'max_qr_dinamicos' => 0, 'max_claves_api' => 0,
            'dias_retencion_escaneos' => null,
            'permite_logo' => false, 'permite_svg_pdf' => false, 'permite_analitica' => false,
            'permite_masivo' => false, 'marca_agua' => true,
            'caracteristicas' => ['50 QR estáticos al mes', 'Descarga en PNG', 'URL, texto, WiFi, WhatsApp y vCard'],
        ],
        'pro' => [
            'id' => 2, 'codigo' => 'pro', 'nombre' => 'Pro',
            'descripcion' => 'Para negocios que imprimen sus QR.',
            'precio_mensual' => 149, 'precio_anual' => 1490,
            'max_qr_estaticos_mes' => null, 'max_qr_dinamicos' => 50, 'max_claves_api' => 0,
            'dias_retencion_escaneos' => 365,
            'permite_logo' => true, 'permite_svg_pdf' => true, 'permite_analitica' => true,
            'permite_masivo' => false, 'marca_agua' => false,
            'caracteristicas' => ['50 QR dinámicos editables', 'Estadísticas de escaneo por 1 año', 'Tu logo y tus colores', 'Descarga en PNG, SVG y PDF', 'Sin marca de agua'],
        ],
        'negocio' => [
            'id' => 3, 'codigo' => 'negocio', 'nombre' => 'Negocio',
            'descripcion' => 'Para agencias y cadenas con muchos puntos.',
            'precio_mensual' => 499, 'precio_anual' => 4990,
            'max_qr_estaticos_mes' => null, 'max_qr_dinamicos' => null, 'max_claves_api' => 5,
            'dias_retencion_escaneos' => null,
            'permite_logo' => true, 'permite_svg_pdf' => true, 'permite_analitica' => true,
            'permite_masivo' => true, 'marca_agua' => false,
            'caracteristicas' => ['QR dinámicos ilimitados', 'Estadísticas sin límite de tiempo', 'Generación masiva desde CSV', 'API con 5 claves', 'Todo lo de Pro'],
        ],
    ];
}

/** Tablas: usuarios + suscripciones + pagos (agregado) */
function mock_usuarios(): array
{
    $u = [
        [1,  'Mariana López Ruiz',      'mariana@tacoselguero.mx',   'negocio', 'activo',     '2026-03-14', '2026-10-03 08:41', 'anual',   34, 18420, 4990],
        [2,  'Jorge Hernández',         'jorge.hdz@gmail.com',       'pro',     'activo',     '2026-04-02', '2026-10-02 19:12', 'mensual', 12,  3210,  894],
        [3,  'Lucía Pérez Gómez',       'lucia@cafemorelia.com',     'pro',     'activo',     '2026-05-21', '2026-10-01 11:05', 'anual',    8,  2890, 1490],
        [4,  'Ricardo Soto',            'rsoto@inmobiliariasoto.mx', 'pro',     'activo',     '2026-06-10', '2026-09-30 16:20', 'mensual', 21,  5402,  596],
        [5,  'Daniela Castro',          'dani.castro@hotmail.com',   'gratis',  'activo',     '2026-07-01', '2026-09-28 09:30', null,       6,     0,    0],
        [6,  'Fernando Ramírez',        'fer.ramirez@outlook.com',   'gratis',  'activo',     '2026-07-15', '2026-09-20 13:44', null,      11,     0,    0],
        [7,  'Ana Sofía Torres',        'anasofia@eventosdelta.mx',  'negocio', 'activo',     '2026-08-03', '2026-10-03 07:58', 'mensual', 57, 26110,  998],
        [8,  'Pedro Martínez',          'pedro.mtz@gmail.com',       'gratis',  'pendiente',  '2026-09-29', null,               null,       0,     0,    0],
        [9,  'Gabriela Núñez',          'gaby.nunez@yahoo.com.mx',   'gratis',  'activo',     '2026-08-19', '2026-09-15 18:02', null,       3,     0,    0],
        [10, 'Luis Alberto Vega',       'lvega@ferreteriavega.mx',   'pro',     'activo',     '2026-08-27', '2026-10-02 10:10', 'mensual',  9,  1180,  298],
        [11, 'Carmen Ortiz',            'carmen.ortiz@gmail.com',    'gratis',  'pendiente',  '2026-10-01', null,               null,       0,     0,    0],
        [12, 'Héctor Jiménez',          'hector@barlacantina.mx',    'pro',     'suspendido', '2026-05-05', '2026-08-11 22:15', 'mensual', 14,  3990,  447],
        [13, 'Valeria Mendoza',         'vale.mendoza@icloud.com',   'gratis',  'activo',     '2026-09-12', '2026-10-02 20:33', null,       2,     0,    0],
        [14, 'Óscar Delgado',           'oscar@dentalsonrisa.mx',    'pro',     'activo',     '2026-09-18', '2026-10-03 09:02', 'mensual',  4,   312,  149],
        [15, 'Patricia Rojas',          'paty.rojas@gmail.com',      'gratis',  'pendiente',  '2026-10-02', null,               null,       0,     0,    0],
        [16, 'Sergio Aguilar',          'saguilar@colegioaurora.edu.mx','gratis','activo',    '2026-09-25', '2026-09-30 08:15', null,       7,     0,    0],
    ];
    $campos = ['id','nombre','email','plan','estatus','fecha_registro','ultimo_acceso','periodicidad','total_qr','total_escaneos','total_pagado'];
    return array_map(fn($fila) => array_combine($campos, $fila), $u);
}

function mock_usuario(int $id): ?array
{
    foreach (mock_usuarios() as $u) {
        if ($u['id'] === $id) return $u;
    }
    return null;
}

/** Tabla: pagos */
function mock_pagos(): array
{
    $p = [
        [1048, 7,  'Plan Negocio · mensual', 499,  'pagado',      'tarjeta', '4417', '2026-10-03 07:58', 'stripe'],
        [1047, 14, 'Plan Pro · mensual',     149,  'pagado',      'tarjeta', '0921', '2026-09-18 12:40', 'stripe'],
        [1046, 10, 'Plan Pro · mensual',     149,  'pagado',      'oxxo',    null,   '2026-09-27 17:22', 'mercadopago'],
        [1045, 2,  'Plan Pro · mensual',     149,  'pagado',      'tarjeta', '5532', '2026-09-02 09:00', 'stripe'],
        [1044, 4,  'Plan Pro · mensual',     149,  'fallido',     'tarjeta', '7781', '2026-09-10 09:00', 'stripe'],
        [1043, 4,  'Plan Pro · mensual',     149,  'pagado',      'tarjeta', '7781', '2026-09-12 14:31', 'stripe'],
        [1042, 12, 'Plan Pro · mensual',     149,  'fallido',     'tarjeta', '3304', '2026-09-05 09:00', 'stripe'],
        [1041, 7,  'Plan Negocio · mensual', 499,  'pagado',      'tarjeta', '4417', '2026-09-03 07:58', 'stripe'],
        [1040, 3,  'Plan Pro · anual',       1490, 'pagado',      'spei',    null,   '2026-05-21 10:12', 'mercadopago'],
        [1039, 1,  'Plan Negocio · anual',   4990, 'pagado',      'tarjeta', '1188', '2026-03-14 18:47', 'stripe'],
        [1038, 6,  'Plan Pro · mensual',     149,  'reembolsado', 'tarjeta', '2290', '2026-08-02 11:03', 'stripe'],
    ];
    $campos = ['id','usuario_id','concepto','monto','estatus','metodo_pago','ultimos_digitos','fecha_pago','pasarela'];
    return array_map(fn($fila) => array_combine($campos, $fila), $p);
}

/** Tabla: qr_codigos */
function mock_qrs(): array
{
    $q = [
        [101, 7, 'Menú terraza',            'dinamico', 'url',      'Mq8Tz2', 'https://eventosdelta.mx/menu-terraza',   true,  4120, '2026-10-03 09:31', '2026-08-04'],
        [102, 7, 'Invitación boda Ramos',   'dinamico', 'url',      'Bd4Ram', 'https://eventosdelta.mx/boda-ramos',      true,  2875, '2026-10-02 23:10', '2026-08-20'],
        [103, 7, 'WiFi salón principal',    'estatico', 'wifi',     null,     'WIFI:S:Delta-Invitados;T:WPA;P:****;;',  true,     0, null,               '2026-08-05'],
        [104, 7, 'Encuesta de servicio',    'dinamico', 'url',      'Enc7Qx', 'https://forms.gle/delta-encuesta',        false,  640, '2026-09-12 18:44', '2026-08-25'],
        [105, 1, 'Menú sucursal Centro',    'dinamico', 'url',      'Tg1Cen', 'https://tacoselguero.mx/menu',            true,  9310, '2026-10-03 09:48', '2026-03-15'],
        [106, 1, 'Pedidos por WhatsApp',    'dinamico', 'whatsapp', 'Tg2Wsp', 'https://wa.me/5215512345678',             true,  5022, '2026-10-03 09:40', '2026-03-15'],
        [107, 2, 'Portafolio',              'dinamico', 'url',      'Jh9Prt', 'https://jorgehdz.dev',                    true,   980, '2026-10-01 12:05', '2026-04-03'],
        [108, 3, 'Carta de cafés',          'dinamico', 'url',      'Cm3Cft', 'https://cafemorelia.com/carta',           true,  2890, '2026-10-03 08:12', '2026-05-22'],
        [109, 5, 'Mi tarjeta de contacto',  'estatico', 'vcard',    null,     'BEGIN:VCARD…Daniela Castro…END:VCARD',   true,     0, null,               '2026-07-02'],
        [110, 4, 'Casa en venta Coyoacán',  'dinamico', 'url',      'Rs5Cyc', 'https://inmobiliariasoto.mx/p/coyoacan-22', true, 1733, '2026-10-02 17:25', '2026-06-11'],
        [111, 10,'Catálogo herramientas',   'dinamico', 'url',      'Fv2Cat', 'https://ferreteriavega.mx/catalogo.pdf',  true,  1180, '2026-10-02 10:51', '2026-08-28'],
        [112, 13,'Link a mi Instagram',     'estatico', 'url',      null,     'https://instagram.com/vale.mendoza',      true,     0, null,               '2026-09-12'],
    ];
    $campos = ['id','usuario_id','nombre','tipo','tipo_contenido','codigo_corto','destino','activo','total_escaneos','ultimo_escaneo','fecha_creacion'];
    return array_map(fn($fila) => array_combine($campos, $fila), $q);
}

/**
 * Usuario con sesión iniciada en el panel /app.
 * Para ver la experiencia del plan gratis agrega ?demo=gratis a la URL.
 */
function mock_usuario_actual(): array
{
    $u = mock_usuario(7);
    if (($_GET['demo'] ?? '') === 'gratis') {
        $u = mock_usuario(5);
    }
    $u['plan_datos'] = mock_planes()[$u['plan']];
    return $u;
}

function mock_qrs_de(int $usuarioId): array
{
    return array_values(array_filter(mock_qrs(), fn($q) => $q['usuario_id'] === $usuarioId));
}

/** Tabla: qr_escaneos agregada por día (últimos N días). Semilla fija para que no cambie en cada recarga. */
function mock_serie_diaria(int $dias, int $base, int $semilla = 7): array
{
    mt_srand($semilla);
    $serie = [];
    for ($i = $dias - 1; $i >= 0; $i--) {
        $fecha = date('Y-m-d', strtotime("-$i days"));
        $finde = in_array((int) date('N', strtotime($fecha)), [5, 6, 7], true);
        $serie[$fecha] = (int) round($base * ($finde ? 1.45 : 1) * (0.7 + mt_rand(0, 60) / 100));
    }
    mt_srand();
    return $serie;
}

/** Desgloses de escaneos (dispositivo, país, ciudad, hora) */
function mock_desgloses(): array
{
    return [
        'dispositivo' => ['Móvil' => 86, 'Tablet' => 6, 'Escritorio' => 8],
        'sistema'     => ['Android' => 58, 'iOS' => 36, 'Otro' => 6],
        'ciudades'    => ['Ciudad de México' => 3120, 'Guadalajara' => 1210, 'Monterrey' => 980, 'Puebla' => 640, 'Querétaro' => 455, 'Otras' => 1230],
        'horas'       => [2, 1, 0, 0, 1, 3, 8, 15, 22, 26, 30, 38, 52, 61, 55, 41, 37, 44, 63, 78, 82, 60, 31, 9],
    ];
}

/** Resumen para el panel de administración */
function mock_resumen_admin(): array
{
    $usuarios = mock_usuarios();
    $planes   = mock_planes();
    $dePago   = array_filter($usuarios, fn($u) => $u['plan'] !== 'gratis' && $u['estatus'] === 'activo');

    $mrr = 0;
    foreach ($dePago as $u) {
        $p = $planes[$u['plan']];
        $mrr += $u['periodicidad'] === 'anual' ? $p['precio_anual'] / 12 : $p['precio_mensual'];
    }

    $mesActual = date('Y-m');
    $ingresosMes = array_sum(array_map(
        fn($p) => $p['estatus'] === 'pagado' && str_starts_with($p['fecha_pago'], $mesActual) ? $p['monto'] : 0,
        mock_pagos()
    ));

    return [
        'total'           => count($usuarios),
        'de_pago'         => count($dePago),
        'gratis'          => count(array_filter($usuarios, fn($u) => $u['plan'] === 'gratis' && $u['estatus'] === 'activo')),
        'sin_verificar'   => count(array_filter($usuarios, fn($u) => $u['estatus'] === 'pendiente')),
        'suspendidos'     => count(array_filter($usuarios, fn($u) => $u['estatus'] === 'suspendido')),
        'mrr'             => $mrr,
        'ingresos_mes'    => $ingresosMes,
        'qr_total'        => array_sum(array_column($usuarios, 'total_qr')),
        'escaneos_total'  => array_sum(array_column($usuarios, 'total_escaneos')),
        'pagos_fallidos'  => count(array_filter(mock_pagos(), fn($p) => $p['estatus'] === 'fallido')),
    ];
}
