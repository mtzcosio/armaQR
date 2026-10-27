<?php
/**
 * DATOS REALES
 * --------------------------------------------------------------------
 * Equivalentes de includes/mock.php que leen de MySQL. Cada función
 * devuelve la misma forma de arreglo que su versión mock_*, para que
 * las pantallas no tengan que cambiar.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../database/Database.php';
require_once __DIR__ . '/../app/Core/Auth.php';

/** Días que un plan de pago sigue activo tras vencer su periodo, mientras llega la renovación. */
const PAGOS_GRACIA_DIAS = 3;

/** Condición para el JOIN de la suscripción vigente (alias s): si ya venció (más la gracia), cuenta como Gratis. */
const SQL_SUSCRIPCION_AL_DIA = '(s.periodo_fin IS NULL OR s.periodo_fin > NOW() - INTERVAL ' . PAGOS_GRACIA_DIAS . ' DAY)';

function datos_pdo(): PDO
{
    return App\Core\Database::connection();
}

/** Configuración del portal [clave => valor]; vacía si la BD no responde. Se lee una vez por petición. */
function configuracion_activa(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    try {
        $rows = datos_pdo()->query('SELECT clave, valor FROM configuracion ORDER BY clave')->fetchAll(PDO::FETCH_ASSOC);
        $cache = array_column($rows, 'valor', 'clave');
    } catch (Throwable $exception) {
        $cache = [];
    }
    return $cache;
}

/** Nombre visible del portal: "Nombre del portal" de la configuración, o APP_NOMBRE si está vacío. */
function nombre_portal(): string
{
    $nombre = trim((string) (configuracion_activa()['portal_nombre'] ?? ''));
    return $nombre !== '' ? $nombre : APP_NOMBRE;
}

/** Deja solo el nombre de dominio: sin https://, ruta, puerto ni mayúsculas ("https://ArmaQR.com/" → "armaqr.com"). */
function normalizar_dominio(string $dominio): string
{
    $dominio = strtolower(trim($dominio));
    $dominio = (string) preg_replace('#^[a-z]+://#', '', $dominio);
    return (string) preg_replace('#[/:?\#].*$#', '', $dominio);
}

/** Dominio de las ligas cortas de los QR dinámicos: el de la configuración, o APP_DOMINIO si está vacío. */
function dominio_portal(): string
{
    $dominio = normalizar_dominio((string) (configuracion_activa()['portal_dominio'] ?? ''));
    return $dominio !== '' ? $dominio : APP_DOMINIO;
}

/** Ejecuta una consulta preparada y devuelve todas las filas. */
function datos_consulta(string $sql, array $params = []): array
{
    $stmt = datos_pdo()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function datos_entero_o_nulo(mixed $v): ?int
{
    return $v === null ? null : (int) $v;
}

/* ---------------------------------------------------------------------
 * Planes
 * ------------------------------------------------------------------- */

/** Lista legible de lo que incluye un plan, derivada de sus límites. */
function datos_caracteristicas(array $p): array
{
    $c = [];
    if ($p['max_qr_dinamicos'] === null) {
        $c[] = 'QR dinámicos ilimitados';
    } elseif ($p['max_qr_dinamicos'] > 0) {
        $c[] = numero($p['max_qr_dinamicos']) . ' QR dinámicos editables';
    }
    $c[] = $p['max_qr_estaticos_mes'] === null
        ? 'QR estáticos ilimitados'
        : numero($p['max_qr_estaticos_mes']) . ' QR estáticos al mes';
    if ($p['permite_analitica']) {
        $dias = $p['dias_retencion_escaneos'];
        $c[] = $dias === null ? 'Estadísticas sin límite de tiempo'
            : ($dias === 365 ? 'Estadísticas de escaneo por 1 año' : "Estadísticas de escaneo por {$dias} días");
    }
    if ($p['permite_logo']) {
        $c[] = 'Tu logo y tus colores';
    }
    $c[] = $p['permite_svg_pdf'] ? 'Descarga en PNG, SVG y PDF' : 'Descarga en PNG';
    if ($p['permite_masivo']) {
        $c[] = 'Generación masiva desde CSV';
    }
    // max_claves_api no se anuncia: la API pública aún no existe.
    if (!$p['marca_agua']) {
        $c[] = 'Sin marca de agua';
    }
    return $c;
}

/** Tabla: planes. Arreglo indexado por código ('gratis', 'pro', ...). */
function datos_planes(bool $soloPublicos = false): array
{
    $filas = datos_consulta(
        'SELECT id, codigo, nombre, descripcion, precio_mensual, precio_anual, moneda,
                max_qr_estaticos_mes, max_qr_dinamicos, max_claves_api, dias_retencion_escaneos,
                permite_logo, permite_svg_pdf, permite_analitica, permite_masivo, marca_agua, es_publico
         FROM planes
         WHERE eliminado = 0' . ($soloPublicos ? ' AND es_publico = 1' : '') . '
         ORDER BY orden, id'
    );

    $planes = [];
    foreach ($filas as $f) {
        $p = [
            'id' => (int) $f['id'],
            'codigo' => $f['codigo'],
            'nombre' => $f['nombre'],
            'descripcion' => (string) $f['descripcion'],
            'precio_mensual' => (float) $f['precio_mensual'],
            'precio_anual' => (float) $f['precio_anual'],
            'moneda' => $f['moneda'],
            'max_qr_estaticos_mes' => datos_entero_o_nulo($f['max_qr_estaticos_mes']),
            'max_qr_dinamicos' => datos_entero_o_nulo($f['max_qr_dinamicos']),
            'max_claves_api' => (int) $f['max_claves_api'],
            'dias_retencion_escaneos' => datos_entero_o_nulo($f['dias_retencion_escaneos']),
            'permite_logo' => (bool) $f['permite_logo'],
            'permite_svg_pdf' => (bool) $f['permite_svg_pdf'],
            'permite_analitica' => (bool) $f['permite_analitica'],
            'permite_masivo' => (bool) $f['permite_masivo'],
            'marca_agua' => (bool) $f['marca_agua'],
            'es_publico' => (bool) $f['es_publico'],
        ];
        $p['caracteristicas'] = datos_caracteristicas($p);
        $planes[$p['codigo']] = $p;
    }
    return $planes;
}

/** Campos de límite del plan: vacío = ilimitado (NULL). */
const PLAN_LIMITES = [
    'max_qr_estaticos_mes' => 'QR estáticos al mes',
    'max_qr_dinamicos' => 'QR dinámicos',
    'dias_retencion_escaneos' => 'Días de estadísticas',
];
const PLAN_FUNCIONES = ['permite_logo', 'permite_svg_pdf', 'permite_analitica', 'permite_masivo', 'marca_agua', 'es_publico'];
const PLAN_MAXIMO = 1_000_000;

/**
 * Valida el formulario de Administración › Planes. Devuelve las columnas a guardar o el mensaje
 * de error. El plan Gratis siempre cuesta 0 (no se puede comprar).
 */
function datos_plan_desde_formulario(array $entrada, bool $esGratis): array|string
{
    $d = ['descripcion' => mb_substr(trim((string) ($entrada['descripcion'] ?? '')), 0, 500)];

    foreach (['precio_mensual' => 'mensual', 'precio_anual' => 'anual'] as $campo => $texto) {
        $valor = trim((string) ($entrada[$campo] ?? ''));
        if ($esGratis || $valor === '') {
            $d[$campo] = 0.0;
            continue;
        }
        if (!is_numeric($valor) || (float) $valor < 0 || (float) $valor > 999999.99) {
            return "El precio {$texto} debe ser un número entre 0 y 999,999.99.";
        }
        $d[$campo] = round((float) $valor, 2);
    }

    foreach (PLAN_LIMITES as $campo => $texto) {
        $valor = trim((string) ($entrada[$campo] ?? ''));
        if ($valor === '') {
            $d[$campo] = null;
            continue;
        }
        if (!ctype_digit($valor) || (int) $valor > PLAN_MAXIMO) {
            return "{$texto}: escribe un número entero de 0 a " . numero(PLAN_MAXIMO) . ', o déjalo vacío para que sea ilimitado.';
        }
        $d[$campo] = (int) $valor;
    }
    if ($d['dias_retencion_escaneos'] !== null && $d['dias_retencion_escaneos'] < 7) {
        return 'Días de estadísticas: el periodo más corto que se muestra es de 7 días.';
    }

    $claves = trim((string) ($entrada['max_claves_api'] ?? ''));
    if ($claves !== '' && (!ctype_digit($claves) || (int) $claves > 1000)) {
        return 'Claves API: escribe un número entero de 0 a 1,000.';
    }
    $d['max_claves_api'] = (int) $claves;

    foreach (PLAN_FUNCIONES as $campo) {
        $d[$campo] = !empty($entrada[$campo]) ? 1 : 0;
    }
    return $d;
}

/** ¿Un plan con esa retención deja ver un periodo de $dias días? NULL = sin límite. */
function datos_periodo_visible(?int $retencion, int $dias): bool
{
    return $retencion === null || $dias <= $retencion;
}

/* ---------------------------------------------------------------------
 * Usuarios (+ suscripción vigente y totales)
 * ------------------------------------------------------------------- */

function datos_usuarios_consulta(string $filtro, array $params): array
{
    $filas = datos_consulta(
        "SELECT u.id, u.nombre, u.email, u.rol, u.estatus, u.email_verificado_en, u.zona_horaria,
                u.id_cliente_pasarela, u.fecha_creacion AS fecha_registro, u.ultimo_acceso, u.eliminado,
                COALESCE(p.codigo, 'gratis') AS plan, s.periodicidad, s.estatus AS estatus_suscripcion,
                s.periodo_fin, s.pasarela, s.id_externo AS suscripcion_externa, s.cancelar_al_final,
                (SELECT COUNT(*) FROM qr_codigos q WHERE q.usuario_id = u.id AND q.eliminado = 0) AS total_qr,
                (SELECT COALESCE(SUM(q.total_escaneos), 0) FROM qr_codigos q WHERE q.usuario_id = u.id AND q.eliminado = 0) AS total_escaneos,
                (SELECT COALESCE(SUM(pg.monto), 0) FROM pagos pg WHERE pg.usuario_id = u.id AND pg.estatus = 'pagado') AS total_pagado
         FROM usuarios u
         LEFT JOIN suscripciones s ON s.usuario_vigente = u.id AND " . SQL_SUSCRIPCION_AL_DIA . "
         LEFT JOIN planes p ON p.id = s.plan_id
         WHERE {$filtro}
         ORDER BY u.fecha_creacion DESC",
        $params
    );

    return array_map(fn($f) => array_merge($f, [
        'id' => (int) $f['id'],
        'eliminado' => (bool) $f['eliminado'],
        'total_qr' => (int) $f['total_qr'],
        'total_escaneos' => (int) $f['total_escaneos'],
        'total_pagado' => (float) $f['total_pagado'],
    ]), $filas);
}

/** Tabla: usuarios + suscripciones + pagos (agregado). */
function datos_usuarios(bool $incluirEliminados = false): array
{
    return datos_usuarios_consulta($incluirEliminados ? '1 = 1' : 'u.eliminado = 0', []);
}

function datos_usuario(int $id): ?array
{
    return datos_usuarios_consulta('u.id = :id AND u.eliminado = 0', [':id' => $id])[0] ?? null;
}

/** Usuario con sesión iniciada, con los datos de su plan en 'plan_datos'. */
function datos_usuario_actual(): array
{
    $sesion = App\Core\Auth::requireUser();
    $u = datos_usuario((int) $sesion['id']) ?? $sesion;
    $planes = datos_planes();
    $u['plan'] = $u['plan'] ?? 'gratis';
    $u['plan_datos'] = $planes[$u['plan']] ?? $planes['gratis'] ?? reset($planes);
    return $u;
}

/* ---------------------------------------------------------------------
 * Códigos QR
 * ------------------------------------------------------------------- */

/** Texto legible del contenido de un QR estático (columna JSON 'contenido'). */
function datos_qr_contenido_texto(?string $json): string
{
    $valor = json_decode((string) $json, true);
    if (is_string($valor)) {
        return $valor;
    }
    if (!is_array($valor)) {
        return (string) $json;
    }
    foreach (['valor', 'texto', 'url', 'contenido'] as $clave) {
        if (isset($valor[$clave]) && is_string($valor[$clave])) {
            return $valor[$clave];
        }
    }
    return implode(' · ', array_filter($valor, 'is_scalar'));
}

/** Tabla: qr_codigos. */
function datos_qrs(?int $usuarioId = null): array
{
    $filas = datos_consulta(
        'SELECT id, usuario_id, nombre, tipo, tipo_contenido, contenido, codigo_corto, url_destino,
                diseno, logo_ruta, activo, total_escaneos, ultimo_escaneo, fecha_creacion
         FROM qr_codigos
         WHERE eliminado = 0' . ($usuarioId !== null ? ' AND usuario_id = :usuario_id' : '') . '
         ORDER BY fecha_creacion DESC',
        $usuarioId !== null ? [':usuario_id' => $usuarioId] : []
    );

    return array_map(fn($f) => [
        'id' => (int) $f['id'],
        'usuario_id' => (int) $f['usuario_id'],
        'nombre' => $f['nombre'],
        'tipo' => $f['tipo'],
        'tipo_contenido' => $f['tipo_contenido'],
        'codigo_corto' => $f['codigo_corto'],
        'destino' => $f['tipo'] === 'dinamico' ? (string) $f['url_destino'] : datos_qr_contenido_texto($f['contenido']),
        'activo' => (bool) $f['activo'],
        'total_escaneos' => (int) $f['total_escaneos'],
        'ultimo_escaneo' => $f['ultimo_escaneo'],
        'fecha_creacion' => $f['fecha_creacion'],
        'diseno' => (json_decode((string) $f['diseno'], true) ?: []) + ['color' => '#172036', 'fondo' => '#ffffff'],
        'logo_ruta' => $f['logo_ruta'],
    ], $filas);
}

function datos_qrs_de(int $usuarioId): array
{
    return datos_qrs($usuarioId);
}

/* ---------------------------------------------------------------------
 * Pagos
 * ------------------------------------------------------------------- */

/** Tabla: pagos (más recientes primero). */
function datos_pagos(?int $usuarioId = null): array
{
    $filas = datos_consulta(
        'SELECT id, usuario_id, concepto, monto, estatus, metodo_pago, ultimos_digitos, id_externo,
                COALESCE(fecha_pago, fecha_creacion) AS fecha_pago, pasarela
         FROM pagos' . ($usuarioId !== null ? ' WHERE usuario_id = :usuario_id' : '') . '
         ORDER BY COALESCE(fecha_pago, fecha_creacion) DESC',
        $usuarioId !== null ? [':usuario_id' => $usuarioId] : []
    );

    return array_map(fn($f) => array_merge($f, [
        'id' => (int) $f['id'],
        'usuario_id' => (int) $f['usuario_id'],
        'monto' => (float) $f['monto'],
        'metodo_pago' => (string) $f['metodo_pago'],
    ]), $filas);
}

/* ---------------------------------------------------------------------
 * Series diarias y desgloses de escaneos
 * ------------------------------------------------------------------- */

/**
 * Rango [desde, hasta) de $dias días que termina hoy, recorrido $desplazamiento días hacia atrás.
 * Devuelve también el arreglo de fechas en cero para rellenar los días sin datos.
 */
function datos_rango(int $dias, int $desplazamiento = 0): array
{
    $fin = strtotime('tomorrow') - $desplazamiento * 86400;
    $serie = [];
    for ($i = $dias; $i >= 1; $i--) {
        $serie[date('Y-m-d', $fin - $i * 86400)] = 0;
    }
    return [date('Y-m-d H:i:s', $fin - $dias * 86400), date('Y-m-d H:i:s', $fin), $serie];
}

/** Filtros SQL comunes para escaneos por usuario y/o QR. */
function datos_filtro_escaneos(?int $usuarioId, ?int $qrId): array
{
    $sql = '';
    $params = [];
    if ($usuarioId !== null) {
        $sql .= ' AND q.usuario_id = :usuario_id';
        $params[':usuario_id'] = $usuarioId;
    }
    if ($qrId !== null) {
        $sql .= ' AND q.id = :qr_id';
        $params[':qr_id'] = $qrId;
    }
    return [$sql, $params];
}

/** Tabla: qr_escaneos agregada por día. */
function datos_serie_escaneos(int $dias, ?int $usuarioId = null, ?int $qrId = null, int $desplazamiento = 0): array
{
    [$desde, $hasta, $serie] = datos_rango($dias, $desplazamiento);
    [$filtro, $params] = datos_filtro_escaneos($usuarioId, $qrId);
    $filas = datos_consulta(
        "SELECT DATE(e.fecha_escaneo) AS dia, COUNT(*) AS n
         FROM qr_escaneos e JOIN qr_codigos q ON q.id = e.qr_id
         WHERE e.fecha_escaneo >= :desde AND e.fecha_escaneo < :hasta{$filtro}
         GROUP BY DATE(e.fecha_escaneo)",
        $params + [':desde' => $desde, ':hasta' => $hasta]
    );
    foreach ($filas as $f) {
        $serie[$f['dia']] = (int) $f['n'];
    }
    return $serie;
}

/** Personas distintas (por hash de IP) en el periodo. */
function datos_escaneos_unicos(int $dias, ?int $usuarioId = null, ?int $qrId = null): int
{
    [$desde, $hasta] = datos_rango($dias);
    [$filtro, $params] = datos_filtro_escaneos($usuarioId, $qrId);
    return (int) (datos_consulta(
        "SELECT COUNT(DISTINCT e.ip_hash) AS n
         FROM qr_escaneos e JOIN qr_codigos q ON q.id = e.qr_id
         WHERE e.fecha_escaneo >= :desde AND e.fecha_escaneo < :hasta{$filtro}",
        $params + [':desde' => $desde, ':hasta' => $hasta]
    )[0]['n'] ?? 0);
}

/**
 * Escaneos y último escaneo de cada QR en los últimos $dias días (NULL = desde siempre), para no
 * mostrar más historia de la que deja ver el plan: [qr_id => ['total' => n, 'ultimo' => fecha]].
 */
function datos_escaneos_por_qr(?int $dias, int $usuarioId): array
{
    [$filtro, $params] = datos_filtro_escaneos($usuarioId, null);
    if ($dias !== null) {
        $filtro .= ' AND e.fecha_escaneo >= :desde';
        $params[':desde'] = date('Y-m-d H:i:s', strtotime('tomorrow') - $dias * 86400); // mismo inicio que datos_rango()
    }
    $res = [];
    foreach (datos_consulta(
        "SELECT e.qr_id, COUNT(*) AS n, MAX(e.fecha_escaneo) AS ultimo
         FROM qr_escaneos e JOIN qr_codigos q ON q.id = e.qr_id
         WHERE 1 = 1{$filtro}
         GROUP BY e.qr_id",
        $params
    ) as $f) {
        $res[(int) $f['qr_id']] = ['total' => (int) $f['n'], 'ultimo' => $f['ultimo']];
    }
    return $res;
}

/** Desgloses de escaneos del periodo: dispositivo y sistema (en %), ciudades y horas (conteos). */
function datos_desgloses(int $dias, ?int $usuarioId = null, ?int $qrId = null): array
{
    [$desde, $hasta] = datos_rango($dias);
    [$filtro, $params] = datos_filtro_escaneos($usuarioId, $qrId);
    $params += [':desde' => $desde, ':hasta' => $hasta];
    $base = "FROM qr_escaneos e JOIN qr_codigos q ON q.id = e.qr_id
             WHERE e.fecha_escaneo >= :desde AND e.fecha_escaneo < :hasta{$filtro}";

    $porcentajes = function (array $filas): array {
        $total = array_sum(array_column($filas, 'n'));
        $res = [];
        foreach ($filas as $f) {
            $res[$f['k']] = $total ? (int) round($f['n'] / $total * 100) : 0;
        }
        return $res;
    };

    $nombresDispositivo = ['movil' => 'Móvil', 'tablet' => 'Tablet', 'escritorio' => 'Escritorio', 'otro' => 'Otro'];
    $dispositivo = $porcentajes(datos_consulta("SELECT COALESCE(e.dispositivo, 'otro') AS k, COUNT(*) AS n {$base} GROUP BY k ORDER BY n DESC", $params));
    $dispositivoLegible = [];
    foreach ($dispositivo as $k => $v) {
        $dispositivoLegible[$nombresDispositivo[$k] ?? $k] = $v;
    }

    $sistema = $porcentajes(datos_consulta("SELECT COALESCE(e.sistema_operativo, 'Otro') AS k, COUNT(*) AS n {$base} GROUP BY k ORDER BY n DESC LIMIT 5", $params));

    $ciudades = [];
    foreach (datos_consulta("SELECT COALESCE(e.ciudad, 'Desconocida') AS k, COUNT(*) AS n {$base} GROUP BY k ORDER BY n DESC", $params) as $i => $f) {
        if ($i < 5) {
            $ciudades[$f['k']] = (int) $f['n'];
        } else {
            $ciudades['Otras'] = ($ciudades['Otras'] ?? 0) + (int) $f['n'];
        }
    }

    $horas = array_fill(0, 24, 0);
    foreach (datos_consulta("SELECT HOUR(e.fecha_escaneo) AS k, COUNT(*) AS n {$base} GROUP BY k", $params) as $f) {
        $horas[(int) $f['k']] = (int) $f['n'];
    }

    return ['dispositivo' => $dispositivoLegible, 'sistema' => $sistema, 'ciudades' => $ciudades, 'horas' => $horas];
}

/** Registros de usuarios por día. */
function datos_serie_registros(int $dias): array
{
    [$desde, $hasta, $serie] = datos_rango($dias);
    $filas = datos_consulta(
        "SELECT DATE(fecha_creacion) AS dia, COUNT(*) AS n FROM usuarios
         WHERE rol = 'cliente' AND fecha_creacion >= :desde AND fecha_creacion < :hasta
         GROUP BY DATE(fecha_creacion)",
        [':desde' => $desde, ':hasta' => $hasta]
    );
    foreach ($filas as $f) {
        $serie[$f['dia']] = (int) $f['n'];
    }
    return $serie;
}

/** Ingresos cobrados por mes (últimos $meses), indexados por 'Y-m'. */
function datos_ingresos_mensuales(int $meses): array
{
    $serie = [];
    for ($i = $meses - 1; $i >= 0; $i--) {
        $serie[date('Y-m', strtotime("first day of -{$i} month"))] = 0.0;
    }
    $filas = datos_consulta(
        "SELECT DATE_FORMAT(COALESCE(fecha_pago, fecha_creacion), '%Y-%m') AS mes, SUM(monto) AS total
         FROM pagos
         WHERE estatus = 'pagado' AND COALESCE(fecha_pago, fecha_creacion) >= :desde
         GROUP BY mes",
        [':desde' => array_key_first($serie) . '-01 00:00:00']
    );
    foreach ($filas as $f) {
        if (isset($serie[$f['mes']])) {
            $serie[$f['mes']] = (float) $f['total'];
        }
    }
    return $serie;
}

/** Resumen para el panel de administración (solo cuentas de clientes). */
function datos_resumen_admin(): array
{
    $clientes = array_filter(datos_usuarios(), fn($u) => $u['rol'] === 'cliente');
    $planes = datos_planes();
    $dePago = array_filter($clientes, fn($u) => $u['plan'] !== 'gratis' && $u['estatus'] === 'activo');

    $mrr = 0.0;
    foreach ($dePago as $u) {
        $p = $planes[$u['plan']] ?? null;
        if ($p && $u['estatus_suscripcion'] === 'activa') {
            $mrr += $u['periodicidad'] === 'anual' ? $p['precio_anual'] / 12 : $p['precio_mensual'];
        }
    }

    $mesActual = date('Y-m');
    $pagosMes = array_filter(datos_pagos(), fn($p) => str_starts_with((string) $p['fecha_pago'], $mesActual));

    return [
        'total'          => count($clientes),
        'de_pago'        => count($dePago),
        'gratis'         => count(array_filter($clientes, fn($u) => $u['plan'] === 'gratis' && $u['estatus'] === 'activo')),
        'sin_verificar'  => count(array_filter($clientes, fn($u) => $u['estatus'] === 'activo' && $u['email_verificado_en'] === null)),
        'suspendidos'    => count(array_filter($clientes, fn($u) => $u['estatus'] === 'suspendido')),
        'mrr'            => $mrr,
        'ingresos_mes'   => array_sum(array_map(fn($p) => $p['estatus'] === 'pagado' ? $p['monto'] : 0, $pagosMes)),
        'qr_total'       => array_sum(array_column($clientes, 'total_qr')),
        'escaneos_total' => array_sum(array_column($clientes, 'total_escaneos')),
        'pagos_fallidos' => count(array_filter($pagosMes, fn($p) => $p['estatus'] === 'fallido')),
    ];
}

/** Variación porcentual entre dos totales (0 si no hay base). */
function datos_variacion(float $actual, float $anterior): int
{
    return $anterior > 0 ? (int) round(($actual / $anterior - 1) * 100) : 0;
}
