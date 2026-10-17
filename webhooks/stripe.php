<?php
/**
 * Webhook de Stripe. Configúralo en Stripe › Developers › Webhooks con la URL
 * https://{dominio}/webhooks/stripe.php y estos eventos:
 *   checkout.session.completed, checkout.session.async_payment_succeeded,
 *   customer.subscription.created, customer.subscription.updated, customer.subscription.deleted,
 *   customer.subscription.paused, customer.subscription.resumed,
 *   invoice.paid, invoice.payment_failed, invoice.voided, invoice.marked_uncollectible, charge.refunded
 * La versión de API del endpoint no importa: de cada aviso solo se usa el id y el objeto se vuelve
 * a pedir con STRIPE_VERSION (includes/pagos.php).
 * El "Signing secret" (whsec_…) va en Administración › Configuración › Pagos.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/pagos.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$payload = (string) file_get_contents('php://input');
if (!stripe_firma_valida($payload, (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? ''), pago_clave('pago_stripe_webhook'))) {
    // Se registra en el log de PHP: estos avisos no llegan a eventos_pasarela.
    error_log('Webhook de Stripe rechazado: firma inválida. Revisa el "Secreto del webhook" (whsec_…) en Configuración › Pagos.');
    http_response_code(400);
    exit('Firma inválida');
}

$evento = json_decode($payload, true);
if (!is_array($evento) || empty($evento['id']) || empty($evento['type'])) {
    http_response_code(400);
    exit('Evento inválido');
}

pagos_atender_webhook('stripe', (string) $evento['id'], (string) $evento['type'], $payload, fn() => stripe_procesar_evento($evento));
