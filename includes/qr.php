<?php
/**
 * CÓDIGOS QR
 * --------------------------------------------------------------------
 * - Contenido: qr_contenido() valida los campos de cada tipo y arma el texto del QR
 *   exactamente igual que construirContenido() en assets/js/app.js (la vista previa).
 * - Estático: el texto va dentro del código; no se puede cambiar después.
 * - Dinámico: el código lleva a https://{dominio}/r/{codigo_corto}. r.php redirige a
 *   url_destino o, en los tipos de página (PDF, menú, cupón…), muestra la página del QR
 *   (includes/qr_paginas.php). Todo se puede cambiar sin reimprimir.
 * - Límites del plan: QR estáticos creados en el mes y QR dinámicos activos.
 * - Escaneos: qr_registrar_escaneo() guarda dispositivo, sistema y navegador; la IP solo
 *   como hash con sal (para contar personas distintas sin guardar la IP).
 */

declare(strict_types=1);

require_once __DIR__ . '/datos.php';
require_once __DIR__ . '/geoip.php';

// Tipos de contenido: QR_TIPOS (estáticos) y QR_TIPOS_DINAMICOS en includes/qr_tipos.php.
const QR_ALFABETO = 'abcdefghjkmnpqrstuvwxyz23456789'; // sin 0/o, 1/l/i: no se confunden al leerlos
const QR_LARGO_CODIGO = 6;                              // 31^6 ≈ 887 millones de códigos
const QR_LOGO_MAX_BYTES = 1048576;

/** Error de validación con mensaje para el usuario. */
final class QrInvalido extends InvalidArgumentException
{
}

/* ---------------------------------------------------------------------
 * Contenido
 * ------------------------------------------------------------------- */

/** Escapa un valor del formato WIFI: (\ ; , : "). */
function qr_escapar_wifi(string $s): string
{
    return (string) preg_replace('/([\\\\;,:"])/', '\\\\$1', $s);
}

/** Escapa un valor de vCard 3.0 (\ , ; y saltos de línea). */
function qr_escapar_vcard(string $s): string
{
    return str_replace(["\r\n", "\n", "\r"], '\\n', (string) preg_replace('/([\\\\,;])/', '\\\\$1', $s));
}

/** encodeURIComponent de JavaScript (para que el texto sea idéntico al de la vista previa). */
function qr_encode_uri_component(string $s): string
{
    return strtr(rawurlencode($s), ['%21' => '!', '%2A' => '*', '%27' => "'", '%28' => '(', '%29' => ')']);
}

/**
 * Valida los campos de un tipo de contenido y devuelve ['campos' => [...], 'texto' => '...'].
 * $v usa los nombres del formulario de app/crear.php. Lanza QrInvalido.
 */
function qr_contenido(string $tipo, array $v): array
{
    $t = fn(string $k, int $max) => mb_substr(trim((string) ($v[$k] ?? '')), 0, $max);

    switch ($tipo) {
        case 'url':
            $url = $t('url', 2048);
            if ($url === '') {
                throw new QrInvalido('Escribe la dirección web.');
            }
            if (!preg_match('/^[a-z][a-z0-9+.-]*:/i', $url)) {
                $url = 'https://' . $url;
            }
            if (!preg_match('#^https?://[^\s/?\#]+\.[^\s/?\#]+#iu', $url)) {
                throw new QrInvalido('La dirección web no es válida. Ejemplo: https://tunegocio.mx/menu');
            }
            return ['campos' => ['url' => $url], 'texto' => $url];

        case 'whatsapp':
            $telefono = preg_replace('/\D/', '', $t('telefono', 30));
            if (strlen($telefono) < 10 || strlen($telefono) > 15) {
                throw new QrInvalido('Escribe el número de WhatsApp a 10 dígitos (o con lada internacional).');
            }
            $numero = strlen($telefono) === 10 ? '52' . $telefono : $telefono;
            $mensaje = $t('mensaje', 500);
            return ['campos' => ['telefono' => $telefono, 'mensaje' => $mensaje],
                    'texto' => 'https://wa.me/' . $numero . ($mensaje !== '' ? '?text=' . qr_encode_uri_component($mensaje) : '')];

        case 'wifi':
            $red = $t('red', 32);
            $seguridad = in_array($v['seguridad'] ?? '', ['WPA', 'WEP', 'nopass'], true) ? $v['seguridad'] : 'WPA';
            $clave = $t('clave', 63);
            if ($red === '') {
                throw new QrInvalido('Escribe el nombre de la red WiFi.');
            }
            if ($seguridad !== 'nopass' && $clave === '') {
                throw new QrInvalido('Escribe la contraseña de la red o elige "Sin contraseña".');
            }
            return ['campos' => ['red' => $red, 'clave' => $seguridad === 'nopass' ? '' : $clave, 'seguridad' => $seguridad],
                    'texto' => 'WIFI:T:' . $seguridad . ';S:' . qr_escapar_wifi($red) . ';'
                             . ($seguridad === 'nopass' ? '' : 'P:' . qr_escapar_wifi($clave) . ';') . ';'];

        case 'vcard':
            $c = ['nombre' => $t('vc_nombre', 120), 'empresa' => $t('vc_empresa', 120), 'telefono' => $t('vc_telefono', 30), 'email' => $t('vc_email', 190)];
            if ($c['nombre'] === '') {
                throw new QrInvalido('Escribe el nombre del contacto.');
            }
            if ($c['email'] !== '' && !filter_var($c['email'], FILTER_VALIDATE_EMAIL)) {
                throw new QrInvalido('El correo del contacto no es válido.');
            }
            $lineas = ['BEGIN:VCARD', 'VERSION:3.0', 'N:;' . qr_escapar_vcard($c['nombre']) . ';;;', 'FN:' . qr_escapar_vcard($c['nombre'])];
            if ($c['empresa'] !== '') $lineas[] = 'ORG:' . qr_escapar_vcard($c['empresa']);
            if ($c['telefono'] !== '') $lineas[] = 'TEL:' . qr_escapar_vcard($c['telefono']);
            if ($c['email'] !== '') $lineas[] = 'EMAIL:' . qr_escapar_vcard($c['email']);
            $lineas[] = 'END:VCARD';
            return ['campos' => $c, 'texto' => implode("\n", $lineas)];

        case 'email':
            $email = $t('email', 190);
            $asunto = $t('asunto', 200);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new QrInvalido('Escribe un correo de destino válido.');
            }
            return ['campos' => ['email' => $email, 'asunto' => $asunto],
                    'texto' => 'mailto:' . $email . ($asunto !== '' ? '?subject=' . qr_encode_uri_component($asunto) : '')];

        case 'texto':
            $texto = $t('texto', 500);
            if ($texto === '') {
                throw new QrInvalido('Escribe el texto del QR.');
            }
            return ['campos' => ['texto' => $texto], 'texto' => $texto];
    }
    throw new QrInvalido('Elige un tipo de contenido válido.');
}

/** Lee el JSON de la columna contenido: ['tipo', 'campos', 'valor']. */
function qr_contenido_guardado(?string $json): array
{
    $c = json_decode((string) $json, true);
    return is_array($c) ? $c + ['campos' => [], 'valor' => ''] : ['campos' => [], 'valor' => (string) $json];
}

/** Colores del diseño (#RRGGBB); si no son válidos, los predeterminados. */
function qr_diseno(array $v): array
{
    $hex = fn($x, $def) => is_string($x) && preg_match('/^#[0-9a-fA-F]{6}$/', $x) ? strtolower($x) : $def;
    return ['color' => $hex($v['color'] ?? null, '#172036'), 'fondo' => $hex($v['fondo'] ?? null, '#ffffff')];
}

/* ---------------------------------------------------------------------
 * Códigos cortos y ligas
 * ------------------------------------------------------------------- */

function qr_codigo_aleatorio(): string
{
    $codigo = '';
    for ($i = 0; $i < QR_LARGO_CODIGO; $i++) {
        $codigo .= QR_ALFABETO[random_int(0, strlen(QR_ALFABETO) - 1)];
    }
    return $codigo;
}

/** Código corto que no esté en uso (la comparación de la BD no distingue mayúsculas). */
function qr_codigo_nuevo(): string
{
    for ($intento = 0; $intento < 20; $intento++) {
        $codigo = qr_codigo_aleatorio();
        if (!datos_consulta('SELECT 1 FROM qr_codigos WHERE codigo_corto = :c LIMIT 1', [':c' => $codigo])) {
            return $codigo;
        }
    }
    throw new RuntimeException('No se pudo generar un código corto único.');
}

/** Liga corta pública de un QR dinámico. */
function qr_liga_corta(string $codigo): string
{
    return 'https://' . dominio_portal() . '/r/' . $codigo;
}

/* ---------------------------------------------------------------------
 * Límites del plan
 * ------------------------------------------------------------------- */

/** QR estáticos creados este mes (los eliminados también cuentan). */
function qr_estaticos_del_mes(int $usuarioId): int
{
    return (int) datos_consulta(
        "SELECT COUNT(*) AS n FROM qr_codigos
         WHERE usuario_id = :u AND tipo = 'estatico' AND fecha_creacion >= DATE_FORMAT(NOW(), '%Y-%m-01')",
        [':u' => $usuarioId]
    )[0]['n'];
}

/** QR dinámicos activos (no pausados ni eliminados). */
function qr_dinamicos_activos(int $usuarioId, int $excluirId = 0): int
{
    return (int) datos_consulta(
        "SELECT COUNT(*) AS n FROM qr_codigos
         WHERE usuario_id = :u AND tipo = 'dinamico' AND activo = 1 AND eliminado = 0 AND id <> :x",
        [':u' => $usuarioId, ':x' => $excluirId]
    )[0]['n'];
}

/** Mensaje si el plan no permite crear otro QR de ese tipo, o null si sí puede. */
function qr_limite_alcanzado(int $usuarioId, array $plan, string $tipo): ?string
{
    if ($tipo === 'dinamico') {
        if ($plan['max_qr_dinamicos'] === 0) {
            return 'Los QR dinámicos están disponibles desde el plan Pro.';
        }
        if ($plan['max_qr_dinamicos'] !== null && qr_dinamicos_activos($usuarioId) >= $plan['max_qr_dinamicos']) {
            return "Llegaste al límite de {$plan['max_qr_dinamicos']} QR dinámicos activos de tu plan. Pausa o elimina alguno, o cambia de plan.";
        }
        return null;
    }
    if ($plan['max_qr_estaticos_mes'] !== null && qr_estaticos_del_mes($usuarioId) >= $plan['max_qr_estaticos_mes']) {
        return "Llegaste al límite de {$plan['max_qr_estaticos_mes']} QR estáticos de este mes. Se renueva el día 1, o cambia de plan.";
    }
    return null;
}

/** Ids (de más antiguo a más nuevo) que quedan fuera de un límite: los más nuevos. NULL = ilimitado. */
function qr_sobrantes(array $ids, ?int $limite): array
{
    return $limite === null ? [] : array_values(array_slice($ids, $limite));
}

/**
 * Pausa los QR dinámicos activos que excedan el límite del plan vigente (p. ej. al bajar de
 * Negocio a Pro) y conserva los más antiguos. Con un plan sin dinámicos (Gratis o vencido) no
 * pausa nada: r.php ya no redirige ninguno y, si vuelve a pagar, siguen activos. Devuelve cuántos pausó.
 * Sin suscripción vigente el usuario está en Gratis (igual que en datos_usuario_actual()).
 * $responsableId queda en usuario_modificacion: el admin que hizo el cambio o, si viene de la
 * pasarela, el propio dueño (su pago o cancelación lo provocó).
 */
function qr_ajustar_al_plan(int $usuarioId, ?int $responsableId = null): int
{
    $plan = datos_consulta(
        'SELECT p.max_qr_dinamicos FROM suscripciones s JOIN planes p ON p.id = s.plan_id
         WHERE s.usuario_vigente = :u AND ' . SQL_SUSCRIPCION_AL_DIA,
        [':u' => $usuarioId]
    )[0] ?? datos_consulta("SELECT max_qr_dinamicos FROM planes WHERE codigo = 'gratis' AND eliminado = 0")[0] ?? null;
    $limite = $plan === null ? 0 : datos_entero_o_nulo($plan['max_qr_dinamicos']);
    if ($limite === 0) {
        return 0;
    }
    $ids = array_map('intval', array_column(datos_consulta(
        "SELECT id FROM qr_codigos WHERE usuario_id = :u AND tipo = 'dinamico' AND activo = 1 AND eliminado = 0
         ORDER BY fecha_creacion, id",
        [':u' => $usuarioId]
    ), 'id'));
    $sobran = qr_sobrantes($ids, $limite);
    if ($sobran) {
        datos_pdo()->prepare(
            'UPDATE qr_codigos SET activo = 0, usuario_modificacion = ?, fecha_modificacion = NOW()
             WHERE id IN (' . implode(',', array_fill(0, count($sobran), '?')) . ')'
        )->execute([$responsableId ?? $usuarioId, ...$sobran]);
    }
    return count($sobran);
}

/** Suma al contador mensual (tabla uso_mensual, la usa la vista vw_plan_usuario). */
function qr_registrar_uso(int $usuarioId, string $tipo, int $cantidad = 1): void
{
    $columna = $tipo === 'dinamico' ? 'qr_dinamicos_creados' : 'qr_estaticos_generados';
    datos_pdo()->prepare(
        "INSERT INTO uso_mensual (usuario_id, periodo, {$columna}) VALUES (:u, DATE_FORMAT(UTC_TIMESTAMP(), '%Y-%m'), :n)
         ON DUPLICATE KEY UPDATE {$columna} = {$columna} + :n2"
    )->execute([':u' => $usuarioId, ':n' => $cantidad, ':n2' => $cantidad]);
}

/**
 * Inserta un QR nuevo y devuelve su id. $contenido viene de qr_contenido().
 * Un dinámico recibe su código corto y redirige al texto del contenido.
 * No valida límites del plan ni registra el uso: eso lo hace quien llama.
 */
function qr_insertar(int $usuarioId, string $nombre, string $tipo, string $tipoContenido, array $contenido, array $diseno, ?string $logo = null): int
{
    $pdo = datos_pdo();
    $pdo->prepare(
        'INSERT INTO qr_codigos (usuario_id, nombre, tipo, tipo_contenido, contenido, codigo_corto, url_destino, diseno, logo_ruta, activo, usuario_creacion, usuario_modificacion)
         VALUES (:u, :nombre, :tipo, :tc, :contenido, :codigo, :destino, :diseno, :logo, 1, :u2, :u3)'
    )->execute([
        ':u' => $usuarioId, ':nombre' => $nombre, ':tipo' => $tipo, ':tc' => $tipoContenido,
        ':contenido' => json_encode(['tipo' => $tipoContenido, 'campos' => $contenido['campos'], 'valor' => $contenido['texto']], JSON_UNESCAPED_UNICODE),
        ':codigo' => $tipo === 'dinamico' ? qr_codigo_nuevo() : null, ':destino' => $tipo === 'dinamico' ? $contenido['texto'] : null,
        ':diseno' => json_encode($diseno), ':logo' => $logo, ':u2' => $usuarioId, ':u3' => $usuarioId,
    ]);
    return (int) $pdo->lastInsertId();
}

/* ---------------------------------------------------------------------
 * Consultas
 * ------------------------------------------------------------------- */

/** QR de un usuario (fila completa) o null. */
function qr_del_usuario(int $qrId, int $usuarioId): ?array
{
    return datos_consulta(
        'SELECT * FROM qr_codigos WHERE id = :id AND usuario_id = :u AND eliminado = 0',
        [':id' => $qrId, ':u' => $usuarioId]
    )[0] ?? null;
}

/**
 * QR dinámico por su código corto, con lo necesario para decidir si redirige (estatus de la
 * cuenta del dueño y si su plan incluye QR dinámicos) y para mostrar su página.
 */
function qr_por_codigo(string $codigo): ?array
{
    if (!preg_match('/^[a-z0-9]{4,12}$/i', $codigo)) {
        return null;
    }
    return datos_consulta(
        "SELECT q.id, q.usuario_id, q.codigo_corto, q.tipo_contenido, q.contenido, q.diseno, q.url_destino, q.activo, q.eliminado,
                u.estatus AS estatus_dueno, u.eliminado AS dueno_eliminado, p.id AS plan_id, p.max_qr_dinamicos, p.marca_agua
         FROM qr_codigos q
         JOIN usuarios u ON u.id = q.usuario_id
         LEFT JOIN suscripciones s ON s.usuario_vigente = u.id AND " . SQL_SUSCRIPCION_AL_DIA . "
         LEFT JOIN planes p ON p.id = s.plan_id
         WHERE q.codigo_corto = :c AND q.tipo = 'dinamico' LIMIT 1",
        [':c' => $codigo]
    )[0] ?? null;
}

/**
 * ¿Redirige? Devuelve null si sí, o el motivo: 'no_existe' | 'pausado'.
 * Un QR deja de redirigir si se pausó o si su dueño ya no puede usar QR dinámicos
 * (cuenta suspendida o plan sin dinámicos); sus datos se conservan.
 */
function qr_motivo_sin_redireccion(?array $qr): ?string
{
    if ($qr === null || (int) $qr['eliminado'] === 1 || (int) $qr['dueno_eliminado'] === 1) {
        return 'no_existe';
    }
    // Sin suscripción vigente cuenta como plan gratis; max_qr_dinamicos NULL en un plan = ilimitados.
    $planSinDinamicos = $qr['plan_id'] === null || ($qr['max_qr_dinamicos'] !== null && (int) $qr['max_qr_dinamicos'] === 0);
    // Los tipos de página no tienen URL de destino: r.php muestra su página.
    $sinDestino = qr_tipo_usa_url((string) ($qr['tipo_contenido'] ?? 'url')) && (string) $qr['url_destino'] === '';
    if ((int) $qr['activo'] !== 1 || $qr['estatus_dueno'] !== 'activo' || $planSinDinamicos || $sinDestino) {
        return 'pausado';
    }
    return null;
}

/* ---------------------------------------------------------------------
 * Escaneos
 * ------------------------------------------------------------------- */

/** Robots y vistas previas de enlaces (WhatsApp, Slack…): redirigen, pero no cuentan como escaneo. */
function qr_es_robot(string $ua): bool
{
    return $ua === '' || (bool) preg_match('/bot|crawler|spider|preview|facebookexternalhit|whatsapp|slack|telegram|discord|curl|wget|python|headless/i', $ua);
}

/** Dispositivo, sistema y navegador a partir del User-Agent (aproximado, sin librerías). */
function qr_detectar_agente(string $ua): array
{
    $so = match (true) {
        (bool) preg_match('/iPhone|iPad|iPod/i', $ua) => 'iOS',
        (bool) preg_match('/Android/i', $ua) => 'Android',
        (bool) preg_match('/Windows/i', $ua) => 'Windows',
        (bool) preg_match('/Mac OS X|Macintosh/i', $ua) => 'macOS',
        (bool) preg_match('/Linux/i', $ua) => 'Linux',
        default => 'Otro',
    };
    $dispositivo = match (true) {
        (bool) preg_match('/iPad|Tablet/i', $ua) || ($so === 'Android' && !preg_match('/Mobile/i', $ua)) => 'tablet',
        (bool) preg_match('/Mobi|iPhone|iPod|Android/i', $ua) => 'movil',
        in_array($so, ['Windows', 'macOS', 'Linux'], true) => 'escritorio',
        default => 'otro',
    };
    $navegador = match (true) {
        (bool) preg_match('/Edg\//', $ua) => 'Edge',
        (bool) preg_match('/SamsungBrowser/', $ua) => 'Samsung',
        (bool) preg_match('/OPR\/|Opera/', $ua) => 'Opera',
        (bool) preg_match('/Firefox|FxiOS/', $ua) => 'Firefox',
        (bool) preg_match('/Chrome|CriOS/', $ua) => 'Chrome',
        (bool) preg_match('/Safari/', $ua) => 'Safari',
        default => 'Otro',
    };
    return ['dispositivo' => $dispositivo, 'sistema_operativo' => $so, 'navegador' => $navegador];
}

/** Sal secreta para el hash de la IP; se crea una vez y se guarda en la configuración. */
function qr_sal(): string
{
    $sal = configuracion_activa()['escaneos_sal'] ?? '';
    if ($sal === '') {
        datos_pdo()->prepare("INSERT IGNORE INTO configuracion (clave, valor, descripcion) VALUES ('escaneos_sal', :v, 'Sal para anonimizar IPs de escaneos')")
            ->execute([':v' => bin2hex(random_bytes(32))]);
        $sal = (string) datos_consulta("SELECT valor FROM configuracion WHERE clave = 'escaneos_sal'")[0]['valor'];
    }
    return $sal;
}

/** Registra un escaneo y actualiza los contadores del QR. */
function qr_registrar_escaneo(int $qrId, array $servidor): void
{
    $ua = mb_substr((string) ($servidor['HTTP_USER_AGENT'] ?? ''), 0, 500);
    $agente = qr_detectar_agente($ua);
    $ip = (string) ($servidor['REMOTE_ADDR'] ?? '');
    $idioma = preg_match('/^([a-zA-Z]{2,3}(?:-[a-zA-Z]{2})?)/', (string) ($servidor['HTTP_ACCEPT_LANGUAGE'] ?? ''), $m) ? $m[1] : null;
    // País, región y ciudad aproximados (Cloudflare o la base local de DB-IP); la IP no se guarda.
    $ubicacion = geoip_ubicacion($servidor);

    $pdo = datos_pdo();
    $pdo->prepare(
        'INSERT INTO qr_escaneos (qr_id, ip_hash, pais, region, ciudad, dispositivo, sistema_operativo, navegador, idioma, referer)
         VALUES (:qr, :ip, :pais, :region, :ciudad, :disp, :so, :nav, :idioma, :ref)'
    )->execute([
        ':qr' => $qrId, ':ip' => $ip !== '' ? hash('sha256', $ip . qr_sal()) : null,
        ':pais' => $ubicacion['pais'], ':region' => $ubicacion['region'], ':ciudad' => $ubicacion['ciudad'],
        ':disp' => $agente['dispositivo'], ':so' => $agente['sistema_operativo'], ':nav' => $agente['navegador'],
        ':idioma' => $idioma, ':ref' => mb_substr((string) ($servidor['HTTP_REFERER'] ?? ''), 0, 500) ?: null,
    ]);
    $pdo->prepare('UPDATE qr_codigos SET total_escaneos = total_escaneos + 1, ultimo_escaneo = NOW() WHERE id = :id')
        ->execute([':id' => $qrId]);
}
