<?php
/**
 * PAGOS (Stripe y Conekta)
 * --------------------------------------------------------------------
 * - Stripe: suscripción con cobro automático a tarjeta (Checkout en modo subscription).
 *   Cambio de plan con prorrateo y portal de Stripe para cambiar tarjeta o ver facturas.
 * - Conekta: pago único de un periodo (mensual o anual) con tarjeta, OXXO o SPEI
 *   (Checkout alojado). No se renueva solo: al vencer, el usuario paga otro periodo.
 * - El plan se activa o se baja SOLO desde los webhooks (webhooks/stripe.php, webhooks/conekta.php).
 *   Antes de confiar en un evento se vuelve a consultar el objeto en la API de la pasarela.
 * - Idempotencia: cada evento queda en eventos_pasarela con llave única (pasarela, id_evento).
 * - Montos: las pasarelas trabajan en centavos; la BD en pesos (DECIMAL).
 * - Claves en Administración › Configuración › Pagos: pago_stripe, pago_stripe_webhook, pago_conekta.
 */

declare(strict_types=1);

require_once __DIR__ . '/datos.php';

const STRIPE_API = 'https://api.stripe.com';
const STRIPE_VERSION = '2024-06-20';            // fija la forma de las respuestas de la API
const CONEKTA_API = 'https://api.conekta.io';
const CONEKTA_ACCEPT = 'application/vnd.conekta-v2.1.0+json';
const PAGOS_TOLERANCIA_FIRMA = 300;             // segundos de diferencia aceptados en la firma de Stripe
const CONEKTA_HORAS_PAGO = 72;                  // vigencia de la liga de pago (y de la referencia OXXO/SPEI)
const PAGOS_PERIODICIDADES = ['mensual', 'anual'];

/** Error de comunicación o de datos con la pasarela; el mensaje se puede mostrar al usuario. */
final class PagoError extends RuntimeException
{
}

/* ---------------------------------------------------------------------
 * Configuración y utilidades
 * ------------------------------------------------------------------- */

function pago_clave(string $clave): string
{
    return trim((string) (configuracion_activa()[$clave] ?? ''));
}

/** Interruptor de Administración › Configuración › Pagos ('stripe' | 'conekta'). Sin valor guardado = habilitada. */
function pago_habilitado(string $pasarela): bool
{
    return (configuracion_activa()['pago_' . $pasarela . '_habilitado'] ?? '1') !== '0';
}

/**
 * ¿Se ofrece a los clientes para pagar? Requiere la clave y el interruptor encendido.
 * Desactivada, la pasarela deja de ofrecerse, pero sus webhooks se siguen atendiendo
 * (renovaciones de Stripe, pagos OXXO/SPEI que llegan después) y los suscriptores de
 * Stripe pueden cancelar o entrar a su portal.
 */
function stripe_activo(): bool
{
    return pago_clave('pago_stripe') !== '' && pago_habilitado('stripe');
}

function conekta_activo(): bool
{
    return pago_clave('pago_conekta') !== '' && pago_habilitado('conekta');
}

/** URL absoluta dentro del portal (las pasarelas necesitan direcciones completas para regresar). */
function url_absoluta(string $ruta = ''): string
{
    $https = (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return ($https ? 'https' : 'http') . '://' . $host . url($ruta);
}

function pago_centavos(float $pesos): int
{
    return (int) round($pesos * 100);
}

function pago_precio_plan(array $plan, string $periodicidad): float
{
    return $periodicidad === 'anual' ? (float) $plan['precio_anual'] : (float) $plan['precio_mensual'];
}

function pago_concepto(array $plan, string $periodicidad): string
{
    return 'Plan ' . $plan['nombre'] . ' · ' . $periodicidad;
}

/** Fin de un periodo que empieza en $desde (timestamp). */
function pagos_sumar_periodo(int $desde, string $periodicidad): int
{
    return (new DateTimeImmutable('@' . $desde))->modify($periodicidad === 'anual' ? '+1 year' : '+1 month')->getTimestamp();
}

/** Petición HTTP a una pasarela. Devuelve [código HTTP, JSON decodificado]. */
function pago_http(string $metodo, string $url, array $encabezados, ?string $cuerpo = null): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $metodo,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $encabezados,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
    ]);
    if ($cuerpo !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $cuerpo);
    }
    $respuesta = curl_exec($ch);
    if ($respuesta === false) {
        $error = curl_error($ch);
        throw new PagoError('No se pudo conectar con la pasarela: ' . $error);
    }
    $estado = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $datos = json_decode((string) $respuesta, true);
    return [$estado, is_array($datos) ? $datos : []];
}

/** Ejecuta $fn dentro de una transacción (o de la que ya esté abierta). */
function pagos_transaccion(callable $fn): mixed
{
    $pdo = datos_pdo();
    if ($pdo->inTransaction()) {
        return $fn($pdo);
    }
    $pdo->beginTransaction();
    try {
        $resultado = $fn($pdo);
        $pdo->commit();
        return $resultado;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

/* ---------------------------------------------------------------------
 * Suscripciones locales (comunes a ambas pasarelas)
 * ------------------------------------------------------------------- */

/** Suscripción vigente del usuario, bloqueada para actualizarla. */
function pagos_vigente(PDO $pdo, int $usuarioId): ?array
{
    $stmt = $pdo->prepare('SELECT id, plan_id, pasarela, id_externo, periodo_fin, cancelar_al_final FROM suscripciones WHERE usuario_vigente = :u FOR UPDATE');
    $stmt->execute([':u' => $usuarioId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** Si el usuario se quedó sin suscripción vigente, lo regresa al plan gratis. */
function pagos_asegurar_gratis(PDO $pdo, int $usuarioId): void
{
    if (pagos_vigente($pdo, $usuarioId) !== null) {
        return;
    }
    $pdo->prepare(
        "INSERT INTO suscripciones (usuario_id, plan_id, estatus, usuario_creacion)
         SELECT :u, id, 'activa', NULL FROM planes WHERE codigo = 'gratis' AND eliminado = 0 LIMIT 1"
    )->execute([':u' => $usuarioId]);
}

/** Cierra una suscripción ('cancelada' | 'vencida') y, si era la vigente, regresa al usuario a Gratis. */
function pagos_terminar_suscripcion(int $suscripcionId, string $estatus): void
{
    pagos_transaccion(function (PDO $pdo) use ($suscripcionId, $estatus): void {
        $stmt = $pdo->prepare('SELECT usuario_id FROM suscripciones WHERE id = :id FOR UPDATE');
        $stmt->execute([':id' => $suscripcionId]);
        $usuarioId = $stmt->fetchColumn();
        if ($usuarioId === false) {
            return;
        }
        $pdo->prepare(
            "UPDATE suscripciones SET estatus = :e, cancelada_en = COALESCE(cancelada_en, NOW()), fecha_modificacion = NOW()
             WHERE id = :id AND estatus IN ('activa','en_prueba','pago_pendiente')"
        )->execute([':e' => $estatus, ':id' => $suscripcionId]);
        pagos_asegurar_gratis($pdo, (int) $usuarioId);
    });
}

/**
 * Crea o actualiza la suscripción (pasarela, id_externo) y la deja como vigente del usuario.
 * $d: usuario_id, plan (código), periodicidad, pasarela, id_externo, estatus, inicio y fin
 *     (timestamps), y opcionales prueba_fin y cancelar_al_final. Devuelve el id de la suscripción.
 */
function pagos_guardar_suscripcion(array $d): int
{
    $plan = datos_planes()[$d['plan']] ?? null;
    if ($plan === null || $plan['codigo'] === 'gratis') {
        throw new PagoError('El plan "' . $d['plan'] . '" no existe o no es de pago.');
    }

    return pagos_transaccion(function (PDO $pdo) use ($d, $plan): int {
        $stmt = $pdo->prepare('SELECT id, usuario_vigente FROM suscripciones WHERE pasarela = :p AND id_externo = :x FOR UPDATE');
        $stmt->execute([':p' => $d['pasarela'], ':x' => $d['id_externo']]);
        $existente = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        // Solo puede haber una vigente: la anterior (gratis u otra de pago) se cierra.
        $vigente = pagos_vigente($pdo, (int) $d['usuario_id']);
        if ($vigente && (!$existente || (int) $vigente['id'] !== (int) $existente['id'])) {
            $pdo->prepare("UPDATE suscripciones SET estatus = 'cancelada', cancelada_en = NOW(), fecha_modificacion = NOW() WHERE id = :id")
                ->execute([':id' => $vigente['id']]);
        }

        $valores = [
            ':plan' => $plan['id'],
            ':estatus' => $d['estatus'],
            ':per' => $d['periodicidad'],
            ':ini' => (int) $d['inicio'],
            ':fin' => (int) $d['fin'],
            ':prueba' => isset($d['prueba_fin']) ? (int) $d['prueba_fin'] : null,
            ':cancelar' => !empty($d['cancelar_al_final']) ? 1 : 0,
        ];
        if ($existente) {
            $pdo->prepare(
                'UPDATE suscripciones SET plan_id = :plan, estatus = :estatus, periodicidad = :per,
                        periodo_inicio = FROM_UNIXTIME(:ini), periodo_fin = FROM_UNIXTIME(:fin),
                        prueba_fin = FROM_UNIXTIME(:prueba), cancelar_al_final = :cancelar,
                        cancelada_en = NULL, fecha_modificacion = NOW()
                 WHERE id = :id'
            )->execute($valores + [':id' => $existente['id']]);
            return (int) $existente['id'];
        }
        $pdo->prepare(
            'INSERT INTO suscripciones (usuario_id, plan_id, estatus, periodicidad, pasarela, id_externo,
                                        periodo_inicio, periodo_fin, prueba_fin, cancelar_al_final)
             VALUES (:u, :plan, :estatus, :per, :pasarela, :externo, FROM_UNIXTIME(:ini), FROM_UNIXTIME(:fin),
                     FROM_UNIXTIME(:prueba), :cancelar)'
        )->execute($valores + [':u' => $d['usuario_id'], ':pasarela' => $d['pasarela'], ':externo' => $d['id_externo']]);
        return (int) $pdo->lastInsertId();
    });
}

/**
 * Pasa a 'vencida' las suscripciones cuyo periodo terminó hace más de PAGOS_GRACIA_DIAS
 * (sin renovación confirmada) y regresa a esos usuarios a Gratis. Devuelve cuántas venció.
 */
function pagos_vencer_suscripciones(?int $usuarioId = null): int
{
    $filas = datos_consulta(
        'SELECT id FROM suscripciones
         WHERE usuario_vigente IS NOT NULL AND periodo_fin IS NOT NULL
           AND periodo_fin < NOW() - INTERVAL ' . PAGOS_GRACIA_DIAS . ' DAY'
        . ($usuarioId !== null ? ' AND usuario_id = :u' : ''),
        $usuarioId !== null ? [':u' => $usuarioId] : []
    );
    foreach ($filas as $f) {
        pagos_terminar_suscripcion((int) $f['id'], 'vencida');
    }
    return count($filas);
}

/** Crea o actualiza un cobro. Un pago reembolsado no regresa a otro estatus. */
function pagos_registrar_pago(array $d): void
{
    datos_pdo()->prepare(
        'INSERT INTO pagos (usuario_id, suscripcion_id, pasarela, id_externo, concepto, monto, moneda,
                            estatus, metodo_pago, ultimos_digitos, fecha_pago)
         VALUES (:u, :s, :p, :x, :concepto, :monto, :moneda, :estatus, :metodo, :digitos,
                 FROM_UNIXTIME(:fecha))
         ON DUPLICATE KEY UPDATE
           suscripcion_id  = COALESCE(VALUES(suscripcion_id), suscripcion_id),
           monto           = VALUES(monto),
           estatus         = IF(estatus = \'reembolsado\', estatus, VALUES(estatus)),
           metodo_pago     = COALESCE(VALUES(metodo_pago), metodo_pago),
           ultimos_digitos = COALESCE(VALUES(ultimos_digitos), ultimos_digitos),
           fecha_pago      = COALESCE(VALUES(fecha_pago), fecha_pago)'
    )->execute([
        ':u' => $d['usuario_id'],
        ':s' => $d['suscripcion_id'] ?? null,
        ':p' => $d['pasarela'],
        ':x' => $d['id_externo'],
        ':concepto' => mb_substr($d['concepto'], 0, 200),
        ':monto' => $d['monto'],
        ':moneda' => $d['moneda'] ?? 'MXN',
        ':estatus' => $d['estatus'],
        ':metodo' => $d['metodo_pago'] ?? null,
        ':digitos' => $d['ultimos_digitos'] ?? null,
        ':fecha' => $d['fecha_pago'] ?? null,
    ]);
}

function pagos_marcar_reembolsado(string $pasarela, string $idExterno): void
{
    datos_pdo()->prepare("UPDATE pagos SET estatus = 'reembolsado' WHERE pasarela = :p AND id_externo = :x")
        ->execute([':p' => $pasarela, ':x' => $idExterno]);
}

/**
 * Atiende un webhook una sola vez: lo registra en eventos_pasarela, ejecuta $procesar y marca
 * el resultado. Responde 200 si se procesó (o ya estaba) y 500 si falló, para que la pasarela reintente.
 */
function pagos_atender_webhook(string $pasarela, string $idEvento, string $tipo, string $payload, callable $procesar): never
{
    $pdo = datos_pdo();
    $pdo->prepare(
        'INSERT INTO eventos_pasarela (pasarela, id_evento, tipo, payload) VALUES (:p, :id, :tipo, :payload)
         ON DUPLICATE KEY UPDATE id = id'
    )->execute([':p' => $pasarela, ':id' => $idEvento, ':tipo' => mb_substr($tipo, 0, 100), ':payload' => $payload]);

    $stmt = $pdo->prepare('SELECT id, procesado_en FROM eventos_pasarela WHERE pasarela = :p AND id_evento = :id');
    $stmt->execute([':p' => $pasarela, ':id' => $idEvento]);
    $evento = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($evento['procesado_en'] !== null) {
        http_response_code(200);
        exit('Evento ya procesado');
    }

    try {
        $procesar();
        pagos_vencer_suscripciones();
        $pdo->prepare('UPDATE eventos_pasarela SET procesado_en = NOW(), error = NULL WHERE id = :id')->execute([':id' => $evento['id']]);
        http_response_code(200);
        exit('OK');
    } catch (Throwable $exception) {
        error_log("Webhook {$pasarela} {$idEvento}: " . $exception->getMessage());
        $pdo->prepare('UPDATE eventos_pasarela SET error = :e WHERE id = :id')
            ->execute([':e' => mb_substr($exception->getMessage(), 0, 1000), ':id' => $evento['id']]);
        http_response_code(500);
        exit('Error al procesar el evento');
    }
}

/* ---------------------------------------------------------------------
 * Stripe
 * ------------------------------------------------------------------- */

/** Codifica parámetros con la notación de Stripe (items[0][price]=...); los booleanos como true/false. */
function stripe_codificar(array $params): string
{
    $normalizar = function (array $a) use (&$normalizar): array {
        foreach ($a as $k => $v) {
            $a[$k] = is_array($v) ? $normalizar($v) : (is_bool($v) ? ($v ? 'true' : 'false') : $v);
        }
        return $a;
    };
    return http_build_query($normalizar($params));
}

function stripe_peticion(string $metodo, string $ruta, array $params = [], ?string $idempotencia = null): array
{
    $clave = pago_clave('pago_stripe');
    if ($clave === '') {
        throw new PagoError('Stripe no está configurado.');
    }
    $consulta = stripe_codificar($params);
    $url = STRIPE_API . $ruta . ($metodo === 'GET' && $consulta !== '' ? '?' . $consulta : '');
    $encabezados = [
        'Authorization: Bearer ' . $clave,
        'Stripe-Version: ' . STRIPE_VERSION,
        'Content-Type: application/x-www-form-urlencoded',
    ];
    if ($idempotencia !== null) {
        $encabezados[] = 'Idempotency-Key: ' . $idempotencia;
    }
    [$estado, $datos] = pago_http($metodo, $url, $encabezados, $metodo === 'GET' ? null : $consulta);
    if ($estado >= 400 || $estado === 0) {
        throw new PagoError('Stripe: ' . ($datos['error']['message'] ?? "error HTTP {$estado}"));
    }
    return $datos;
}

/** Valida el encabezado Stripe-Signature (t=...,v1=...) con el secreto del webhook. */
function stripe_firma_valida(string $payload, string $encabezado, string $secreto, ?int $ahora = null): bool
{
    $marca = null;
    $firmas = [];
    foreach (explode(',', $encabezado) as $parte) {
        [$k, $v] = array_pad(explode('=', trim($parte), 2), 2, '');
        if ($k === 't' && ctype_digit($v)) {
            $marca = (int) $v;
        } elseif ($k === 'v1') {
            $firmas[] = $v;
        }
    }
    if ($secreto === '' || $marca === null || !$firmas || abs(($ahora ?? time()) - $marca) > PAGOS_TOLERANCIA_FIRMA) {
        return false;
    }
    $esperada = hash_hmac('sha256', $marca . '.' . $payload, $secreto);
    foreach ($firmas as $firma) {
        if (hash_equals($esperada, $firma)) {
            return true;
        }
    }
    return false;
}

/** Estatus de una suscripción de Stripe → estatus local. */
function stripe_estatus(string $estatus): string
{
    return match ($estatus) {
        'active' => 'activa',
        'trialing' => 'en_prueba',
        'past_due', 'unpaid', 'incomplete' => 'pago_pendiente',
        default => 'cancelada', // canceled, incomplete_expired, paused
    };
}

/**
 * Precio recurrente del plan en Stripe. Se busca por lookup_key (incluye el monto) y se crea
 * si no existe, así un cambio de precio en Administración › Planes genera un precio nuevo.
 */
function stripe_precio(array $plan, string $periodicidad): string
{
    $centavos = pago_centavos(pago_precio_plan($plan, $periodicidad));
    $llave = 'armaqr_' . $plan['codigo'] . '_' . $periodicidad . '_' . $centavos;
    $encontrados = stripe_peticion('GET', '/v1/prices', ['lookup_keys' => [$llave], 'active' => true, 'limit' => 1]);
    if (!empty($encontrados['data'][0]['id'])) {
        return $encontrados['data'][0]['id'];
    }
    $precio = stripe_peticion('POST', '/v1/prices', [
        'currency' => strtolower($plan['moneda'] ?? 'MXN'),
        'unit_amount' => $centavos,
        'lookup_key' => $llave,
        'recurring' => ['interval' => $periodicidad === 'anual' ? 'year' : 'month'],
        'product_data' => ['name' => nombre_portal() . ' · Plan ' . $plan['nombre']],
        'metadata' => ['plan' => $plan['codigo'], 'periodicidad' => $periodicidad],
    ], 'precio-' . $llave);
    return $precio['id'];
}

/** Abre Stripe Checkout para suscribirse. Devuelve la URL a la que se manda al usuario. */
function stripe_checkout(array $usuario, array $plan, string $periodicidad): string
{
    $meta = ['usuario_id' => (string) $usuario['id'], 'plan' => $plan['codigo'], 'periodicidad' => $periodicidad];
    $params = [
        'mode' => 'subscription',
        'line_items' => [['price' => stripe_precio($plan, $periodicidad), 'quantity' => 1]],
        'client_reference_id' => (string) $usuario['id'],
        'metadata' => $meta,
        'subscription_data' => ['metadata' => $meta],
        'success_url' => url_absoluta('app/plan.php?pago=exito'),
        'cancel_url' => url_absoluta('app/plan.php?pago=cancelado'),
        'locale' => 'es-419',
        'allow_promotion_codes' => true,
    ];
    $cliente = (string) ($usuario['id_cliente_pasarela'] ?? '');
    if (str_starts_with($cliente, 'cus_')) {
        $params['customer'] = $cliente;
    } else {
        $params['customer_email'] = $usuario['email'];
    }
    $sesion = stripe_peticion('POST', '/v1/checkout/sessions', $params);
    if (empty($sesion['url'])) {
        throw new PagoError('Stripe no devolvió la liga de pago.');
    }
    return $sesion['url'];
}

/** Cambia el plan de una suscripción activa; cobra la diferencia prorrateada de inmediato. */
function stripe_cambiar_plan(string $suscripcionId, array $plan, string $periodicidad): void
{
    $sub = stripe_peticion('GET', '/v1/subscriptions/' . rawurlencode($suscripcionId));
    $item = $sub['items']['data'][0]['id'] ?? null;
    if ($item === null) {
        throw new PagoError('La suscripción de Stripe no tiene conceptos.');
    }
    if (!empty($sub['cancel_at_period_end'])) {
        stripe_cancelar_al_final($suscripcionId, false);
    }
    // pending_if_incomplete: si el cobro falla, el plan no cambia.
    stripe_peticion('POST', '/v1/subscriptions/' . rawurlencode($suscripcionId), [
        'items' => [['id' => $item, 'price' => stripe_precio($plan, $periodicidad)]],
        'proration_behavior' => 'always_invoice',
        'payment_behavior' => 'pending_if_incomplete',
    ]);
}

/** Cancela al final del periodo ($cancelar = true) o deshace la cancelación. */
function stripe_cancelar_al_final(string $suscripcionId, bool $cancelar): void
{
    stripe_peticion('POST', '/v1/subscriptions/' . rawurlencode($suscripcionId), ['cancel_at_period_end' => $cancelar]);
}

/** Portal de Stripe para cambiar tarjeta y descargar facturas. */
function stripe_portal(string $cliente): string
{
    return stripe_peticion('POST', '/v1/billing_portal/sessions', [
        'customer' => $cliente,
        'return_url' => url_absoluta('app/plan.php'),
    ])['url'];
}

/** Guarda el customer de Stripe en el usuario si aún no lo tenía. */
function stripe_guardar_cliente(int $usuarioId, string $cliente): void
{
    if (str_starts_with($cliente, 'cus_')) {
        datos_pdo()->prepare('UPDATE usuarios SET id_cliente_pasarela = :c WHERE id = :u AND (id_cliente_pasarela IS NULL OR id_cliente_pasarela = \'\')')
            ->execute([':c' => $cliente, ':u' => $usuarioId]);
    }
}

/** Usuario dueño de una suscripción de Stripe: por metadata, por suscripción local o por customer. */
function stripe_usuario_de(array $sub): ?int
{
    $id = (int) ($sub['metadata']['usuario_id'] ?? 0);
    if ($id === 0) {
        $id = (int) (datos_consulta("SELECT usuario_id FROM suscripciones WHERE pasarela = 'stripe' AND id_externo = :x", [':x' => $sub['id']])[0]['usuario_id'] ?? 0);
    }
    if ($id === 0 && !empty($sub['customer'])) {
        $id = (int) (datos_consulta('SELECT id FROM usuarios WHERE id_cliente_pasarela = :c', [':c' => $sub['customer']])[0]['id'] ?? 0);
    }
    return $id > 0 && datos_usuario($id) !== null ? $id : null;
}

/** Código del plan de una suscripción de Stripe ('' si no la creó este portal). */
function stripe_plan_de(array $sub): string
{
    $precio = $sub['items']['data'][0]['price'] ?? [];
    return (string) ($precio['metadata']['plan'] ?? $sub['metadata']['plan'] ?? '');
}

/** De dos suscripciones de Stripe, la que se creó primero (a igual fecha, el id menor). */
function stripe_mas_antigua(array $a, array $b): array
{
    $orden = ((int) ($a['created'] ?? 0) <=> (int) ($b['created'] ?? 0)) ?: strcmp((string) $a['id'], (string) $b['id']);
    return $orden <= 0 ? $a : $b;
}

/**
 * Evita que Stripe le cobre dos suscripciones al mismo usuario (p. ej. dos Checkout pagados casi a la vez).
 * Se conserva la más reciente; la otra se cancela en Stripe de inmediato y se reembolsa su último cobro.
 * Devuelve true si la que sobraba era $sub (entonces no debe activarse).
 */
function stripe_resolver_duplicada(int $usuarioId, array $sub): bool
{
    $otraId = datos_consulta(
        "SELECT id_externo FROM suscripciones WHERE usuario_vigente = :u AND pasarela = 'stripe' AND id_externo <> :x",
        [':u' => $usuarioId, ':x' => $sub['id']]
    )[0]['id_externo'] ?? null;
    if ($otraId === null) {
        return false;
    }
    $otra = stripe_peticion('GET', '/v1/subscriptions/' . rawurlencode((string) $otraId));
    if (stripe_estatus((string) $otra['status']) === 'cancelada') {
        return false;
    }

    $sobra = stripe_mas_antigua($sub, $otra);
    error_log("Stripe: el usuario {$usuarioId} tenía dos suscripciones activas; se cancela y reembolsa {$sobra['id']}.");
    $factura = (string) ($sobra['latest_invoice'] ?? '');
    if ($factura !== '') {
        $f = stripe_peticion('GET', '/v1/invoices/' . rawurlencode($factura));
        if (($f['status'] ?? '') === 'paid' && !empty($f['charge']) && (int) $f['amount_paid'] > 0) {
            stripe_reembolsar($factura);
        }
    }
    stripe_peticion('DELETE', '/v1/subscriptions/' . rawurlencode((string) $sobra['id']));
    return $sobra['id'] === $sub['id'];
}

/** Trae la suscripción de Stripe y deja la local igual. Devuelve el id local (o null). */
function stripe_sincronizar_suscripcion(string $suscripcionId): ?int
{
    $sub = stripe_peticion('GET', '/v1/subscriptions/' . rawurlencode($suscripcionId));
    $usuarioId = stripe_usuario_de($sub);
    if ($usuarioId === null) {
        // Suscripción ajena al portal (otro producto en la misma cuenta) o de un usuario borrado:
        // se ignora con 200 para que Stripe no la reintente durante días.
        error_log("Stripe: se ignora la suscripción {$suscripcionId}; no pertenece a ningún usuario del portal.");
        return null;
    }
    stripe_guardar_cliente($usuarioId, (string) ($sub['customer'] ?? ''));

    $local = datos_consulta("SELECT id FROM suscripciones WHERE pasarela = 'stripe' AND id_externo = :x", [':x' => $suscripcionId])[0] ?? null;
    $estatus = stripe_estatus((string) $sub['status']);
    if ($estatus === 'cancelada') {
        if ($local) {
            pagos_terminar_suscripcion((int) $local['id'], 'cancelada');
        }
        return $local ? (int) $local['id'] : null;
    }
    if ($sub['status'] === 'incomplete' && !$local) {
        return null; // el primer cobro aún no pasa: no se activa nada
    }

    $plan = stripe_plan_de($sub);
    if ($plan === '') {
        // Otro producto del mismo cliente en Stripe: no es un plan del portal y no debe tocar el suyo.
        error_log("Stripe: se ignora la suscripción {$suscripcionId}; su precio no tiene plan del portal.");
        return $local ? (int) $local['id'] : null;
    }
    if (stripe_resolver_duplicada($usuarioId, $sub)) {
        if ($local) {
            pagos_terminar_suscripcion((int) $local['id'], 'cancelada');
        }
        return $local ? (int) $local['id'] : null;
    }

    $precio = $sub['items']['data'][0]['price'] ?? [];
    return pagos_guardar_suscripcion([
        'usuario_id' => $usuarioId,
        'plan' => $plan,
        'periodicidad' => ($precio['recurring']['interval'] ?? 'month') === 'year' ? 'anual' : 'mensual',
        'pasarela' => 'stripe',
        'id_externo' => $suscripcionId,
        'estatus' => $estatus,
        'inicio' => (int) $sub['current_period_start'],
        'fin' => (int) $sub['current_period_end'],
        'prueba_fin' => $sub['trial_end'] ?? null,
        'cancelar_al_final' => !empty($sub['cancel_at_period_end']),
    ]);
}

/** Trae una factura de Stripe y la registra como pago. */
function stripe_sincronizar_factura(string $facturaId): void
{
    $f = stripe_peticion('GET', '/v1/invoices/' . rawurlencode($facturaId), ['expand' => ['charge']]);
    if (empty($f['subscription']) || (int) $f['amount_due'] === 0) {
        return; // solo cobros de suscripciones; las facturas en cero (créditos) no son pagos
    }
    $suscripcionId = stripe_sincronizar_suscripcion((string) $f['subscription']);
    $local = datos_consulta(
        "SELECT s.id, s.usuario_id, s.periodicidad, p.nombre FROM suscripciones s JOIN planes p ON p.id = s.plan_id
         WHERE s.pasarela = 'stripe' AND s.id_externo = :x",
        [':x' => $f['subscription']]
    )[0] ?? null;
    if ($local === null) {
        return;
    }

    $estatus = match ($f['status']) {
        'paid' => 'pagado',
        'void', 'uncollectible' => 'fallido',
        default => (int) ($f['attempt_count'] ?? 0) > 0 ? 'fallido' : 'pendiente',
    };
    $cargo = is_array($f['charge'] ?? null) ? $f['charge'] : [];
    $detalle = $cargo['payment_method_details'] ?? [];
    $tipo = (string) ($detalle['type'] ?? '');
    pagos_registrar_pago([
        'usuario_id' => (int) $local['usuario_id'],
        'suscripcion_id' => $suscripcionId,
        'pasarela' => 'stripe',
        'id_externo' => $facturaId,
        'concepto' => 'Plan ' . $local['nombre'] . ' · ' . $local['periodicidad'] . ($f['billing_reason'] === 'subscription_update' ? ' (cambio de plan)' : ''),
        'monto' => ($estatus === 'pagado' ? (int) $f['amount_paid'] : (int) $f['amount_due']) / 100,
        'moneda' => strtoupper((string) $f['currency']),
        'estatus' => $estatus,
        'metodo_pago' => $tipo === 'card' ? 'tarjeta' : ($tipo !== '' ? $tipo : null),
        'ultimos_digitos' => $detalle['card']['last4'] ?? null,
        'fecha_pago' => $f['status_transitions']['paid_at'] ?? null,
    ]);
    if (!empty($cargo['refunded'])) {
        pagos_marcar_reembolsado('stripe', $facturaId);
    }
}

/** Reembolsa por completo el cobro de una factura. */
function stripe_reembolsar(string $facturaId): void
{
    $f = stripe_peticion('GET', '/v1/invoices/' . rawurlencode($facturaId));
    if (empty($f['charge'])) {
        throw new PagoError('La factura no tiene un cobro que reembolsar.');
    }
    stripe_peticion('POST', '/v1/refunds', ['charge' => $f['charge']], 'reembolso-' . $facturaId);
    pagos_marcar_reembolsado('stripe', $facturaId);
}

/** Despacha un evento de Stripe ya verificado. Los datos se vuelven a pedir a la API. */
function stripe_procesar_evento(array $evento): void
{
    $objeto = $evento['data']['object'] ?? [];
    switch ($evento['type'] ?? '') {
        case 'checkout.session.completed':
        case 'checkout.session.async_payment_succeeded':
            $sesion = stripe_peticion('GET', '/v1/checkout/sessions/' . rawurlencode((string) $objeto['id']));
            if (($sesion['mode'] ?? '') === 'subscription' && !empty($sesion['subscription'])) {
                stripe_guardar_cliente((int) $sesion['client_reference_id'], (string) ($sesion['customer'] ?? ''));
                stripe_sincronizar_suscripcion((string) $sesion['subscription']);
            }
            break;
        case 'customer.subscription.created':
        case 'customer.subscription.updated':
        case 'customer.subscription.deleted':
        case 'customer.subscription.paused':
        case 'customer.subscription.resumed':
            stripe_sincronizar_suscripcion((string) $objeto['id']);
            break;
        case 'invoice.paid':
        case 'invoice.payment_failed':
        case 'invoice.voided':
        case 'invoice.marked_uncollectible':
            stripe_sincronizar_factura((string) $objeto['id']);
            break;
        case 'charge.refunded':
            // El aviso llega en la versión de API del endpoint (desde 2025-03-31 el cargo ya no trae
            // 'invoice'); se vuelve a pedir con STRIPE_VERSION para tenerlo.
            $cargo = stripe_peticion('GET', '/v1/charges/' . rawurlencode((string) $objeto['id']));
            if (!empty($cargo['invoice'])) {
                stripe_sincronizar_factura((string) $cargo['invoice']);
            }
            break;
    }
}

/** Liga al cobro en el panel de Stripe. */
function stripe_url_panel(string $facturaId): string
{
    $prueba = str_starts_with(pago_clave('pago_stripe'), 'sk_test_') || str_starts_with(pago_clave('pago_stripe'), 'rk_test_');
    return 'https://dashboard.stripe.com/' . ($prueba ? 'test/' : '') . 'invoices/' . rawurlencode($facturaId);
}

/* ---------------------------------------------------------------------
 * Conekta
 * ------------------------------------------------------------------- */

function conekta_peticion(string $metodo, string $ruta, ?array $cuerpo = null): array
{
    $clave = pago_clave('pago_conekta');
    if ($clave === '') {
        throw new PagoError('Conekta no está configurado.');
    }
    [$estado, $datos] = pago_http($metodo, CONEKTA_API . $ruta, [
        'Accept: ' . CONEKTA_ACCEPT,
        'Accept-Language: es',
        'Authorization: Bearer ' . $clave,
        'Content-Type: application/json',
    ], $cuerpo === null ? null : json_encode($cuerpo, JSON_UNESCAPED_UNICODE));
    if ($estado >= 400 || $estado === 0) {
        throw new PagoError('Conekta: ' . ($datos['details'][0]['message'] ?? $datos['message'] ?? "error HTTP {$estado}"));
    }
    return $datos;
}

/** Customer de Conekta del usuario; se crea la primera vez (usuarios.id_cliente_conekta). */
function conekta_cliente(array $usuario): string
{
    $guardado = (string) (datos_consulta('SELECT id_cliente_conekta FROM usuarios WHERE id = :u', [':u' => $usuario['id']])[0]['id_cliente_conekta'] ?? '');
    if ($guardado !== '') {
        return $guardado;
    }
    $cliente = conekta_peticion('POST', '/customers', ['name' => $usuario['nombre'], 'email' => $usuario['email']]);
    datos_pdo()->prepare('UPDATE usuarios SET id_cliente_conekta = :c WHERE id = :u')->execute([':c' => $cliente['id'], ':u' => $usuario['id']]);
    return $cliente['id'];
}

/** Crea la orden con Checkout alojado (tarjeta, OXXO, SPEI). Devuelve la URL de pago. */
function conekta_checkout(array $usuario, array $plan, string $periodicidad): string
{
    $monto = pago_precio_plan($plan, $periodicidad);
    $concepto = pago_concepto($plan, $periodicidad);
    $orden = conekta_peticion('POST', '/orders', [
        'currency' => strtoupper($plan['moneda'] ?? 'MXN'),
        'customer_info' => ['customer_id' => conekta_cliente($usuario)],
        'line_items' => [['name' => nombre_portal() . ' · ' . $concepto, 'unit_price' => pago_centavos($monto), 'quantity' => 1]],
        'checkout' => [
            'type' => 'HostedPayment',
            'allowed_payment_methods' => ['card', 'cash', 'bank_transfer'],
            'success_url' => url_absoluta('app/plan.php?pago=exito'),
            'failure_url' => url_absoluta('app/plan.php?pago=cancelado'),
            'expires_at' => time() + CONEKTA_HORAS_PAGO * 3600,
        ],
        'metadata' => ['usuario_id' => (string) $usuario['id'], 'plan' => $plan['codigo'], 'periodicidad' => $periodicidad],
    ]);
    if (empty($orden['checkout']['url'])) {
        throw new PagoError('Conekta no devolvió la liga de pago.');
    }
    pagos_registrar_pago([
        'usuario_id' => (int) $usuario['id'],
        'pasarela' => 'conekta',
        'id_externo' => $orden['id'],
        'concepto' => $concepto,
        'monto' => $monto,
        'moneda' => strtoupper($plan['moneda'] ?? 'MXN'),
        'estatus' => 'pendiente',
    ]);
    return $orden['checkout']['url'];
}

/** Método de pago legible de un cargo de Conekta: [método, últimos 4 dígitos]. */
function conekta_metodo(array $cargo): array
{
    $pm = $cargo['payment_method'] ?? [];
    $objeto = (string) ($pm['object'] ?? '');
    $tipo = strtolower((string) ($pm['type'] ?? ''));
    if (str_contains($objeto, 'card') || in_array($tipo, ['credit', 'debit', 'card'], true)) {
        return ['tarjeta', isset($pm['last4']) ? (string) $pm['last4'] : null];
    }
    if (str_contains($objeto, 'cash') || $tipo === 'oxxo' || $tipo === 'cash') {
        return ['oxxo', null];
    }
    if (str_contains($objeto, 'bank_transfer') || $tipo === 'spei') {
        return ['spei', null];
    }
    return [$tipo !== '' ? $tipo : null, null];
}

/**
 * Activa o extiende el plan pagado con una orden de Conekta. Si ya tiene vigente el mismo plan
 * por Conekta, el periodo nuevo se suma al final del actual (renovación anticipada).
 */
function conekta_aplicar_periodo(PDO $pdo, int $usuarioId, array $plan, string $periodicidad, string $ordenId): int
{
    $vigente = pagos_vigente($pdo, $usuarioId);
    $finVigente = $vigente && $vigente['periodo_fin'] ? strtotime((string) $vigente['periodo_fin']) : 0;
    if ($vigente && $vigente['pasarela'] === 'conekta' && (int) $vigente['plan_id'] === $plan['id'] && $finVigente > time()) {
        $pdo->prepare('UPDATE suscripciones SET periodo_fin = FROM_UNIXTIME(:fin), periodicidad = :per, fecha_modificacion = NOW() WHERE id = :id')
            ->execute([':fin' => pagos_sumar_periodo($finVigente, $periodicidad), ':per' => $periodicidad, ':id' => $vigente['id']]);
        return (int) $vigente['id'];
    }
    $ahora = time();
    return pagos_guardar_suscripcion([
        'usuario_id' => $usuarioId,
        'plan' => $plan['codigo'],
        'periodicidad' => $periodicidad,
        'pasarela' => 'conekta',
        'id_externo' => $ordenId,
        'estatus' => 'activa',
        'inicio' => $ahora,
        'fin' => pagos_sumar_periodo($ahora, $periodicidad),
        'cancelar_al_final' => true, // no hay cobro automático: termina al vencer
    ]);
}

/** Trae la orden de Conekta y registra el pago; si quedó pagada, activa el plan (una sola vez). */
function conekta_sincronizar_orden(string $ordenId): void
{
    $orden = conekta_peticion('GET', '/orders/' . rawurlencode($ordenId));
    $meta = $orden['metadata'] ?? [];
    $usuarioId = (int) ($meta['usuario_id'] ?? 0);
    $plan = datos_planes()[(string) ($meta['plan'] ?? '')] ?? null;
    $periodicidad = in_array($meta['periodicidad'] ?? '', PAGOS_PERIODICIDADES, true) ? $meta['periodicidad'] : 'mensual';
    if ($usuarioId === 0 || $plan === null || datos_usuario($usuarioId) === null) {
        return; // orden que no generó este portal
    }

    $estatus = match ($orden['payment_status'] ?? '') {
        'paid' => 'pagado',
        'refunded' => 'reembolsado',
        'expired', 'canceled', 'declined', 'voided' => 'fallido',
        default => 'pendiente',
    };
    $cargo = $orden['charges']['data'][0] ?? [];
    [$metodo, $digitos] = conekta_metodo($cargo);

    pagos_transaccion(function (PDO $pdo) use ($orden, $ordenId, $usuarioId, $plan, $periodicidad, $estatus, $metodo, $digitos, $cargo): void {
        $stmt = $pdo->prepare("SELECT estatus FROM pagos WHERE pasarela = 'conekta' AND id_externo = :x FOR UPDATE");
        $stmt->execute([':x' => $ordenId]);
        $previo = $stmt->fetchColumn();
        $suscripcionId = null;
        if ($estatus === 'pagado' && $previo !== 'pagado' && $previo !== 'reembolsado') {
            $suscripcionId = conekta_aplicar_periodo($pdo, $usuarioId, $plan, $periodicidad, $ordenId);
        }
        pagos_registrar_pago([
            'usuario_id' => $usuarioId,
            'suscripcion_id' => $suscripcionId,
            'pasarela' => 'conekta',
            'id_externo' => $ordenId,
            'concepto' => pago_concepto($plan, $periodicidad),
            'monto' => (int) $orden['amount'] / 100,
            'moneda' => strtoupper((string) ($orden['currency'] ?? 'MXN')),
            'estatus' => $estatus,
            'metodo_pago' => $metodo,
            'ultimos_digitos' => $digitos,
            'fecha_pago' => $cargo['paid_at'] ?? null,
        ]);
        if ($estatus === 'reembolsado') {
            pagos_marcar_reembolsado('conekta', $ordenId);
        }
    });
}

/** Reembolsa por completo una orden pagada. */
function conekta_reembolsar(string $ordenId, float $monto): void
{
    conekta_peticion('POST', '/orders/' . rawurlencode($ordenId) . '/refunds', [
        'amount' => pago_centavos($monto),
        'reason' => 'requested_by_client',
    ]);
    pagos_marcar_reembolsado('conekta', $ordenId);
}

/** Despacha un evento de Conekta. La orden se vuelve a pedir a la API: no se confía en el cuerpo. */
function conekta_procesar_evento(array $evento): void
{
    $objeto = $evento['data']['object'] ?? [];
    $ordenId = ($objeto['object'] ?? '') === 'order' ? (string) ($objeto['id'] ?? '') : (string) ($objeto['order_id'] ?? '');
    $tipo = (string) ($evento['type'] ?? '');
    if ($ordenId !== '' && (str_starts_with($tipo, 'order.') || str_starts_with($tipo, 'charge.'))) {
        conekta_sincronizar_orden($ordenId);
    }
}
