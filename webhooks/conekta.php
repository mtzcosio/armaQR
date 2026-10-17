<?php
/**
 * Webhook de Conekta. Configúralo en Conekta › Desarrolladores › Webhooks con la URL
 * https://{dominio}/webhooks/conekta.php (eventos order.paid, order.expired, order.canceled,
 * order.refunded; puedes dejar todos).
 * No se confía en el cuerpo: la orden se vuelve a consultar en la API de Conekta con la
 * llave privada antes de activar nada.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/pagos.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$payload = (string) file_get_contents('php://input');
$evento = json_decode($payload, true);
if (!is_array($evento) || empty($evento['id']) || empty($evento['type'])) {
    http_response_code(400);
    exit('Evento inválido');
}

// Conekta manda un "webhook_ping" al dar de alta la URL. Aunque la pasarela esté desactivada
// se siguen atendiendo los avisos (p. ej. un pago OXXO hecho después de desactivarla).
if ($evento['type'] === 'webhook_ping' || pago_clave('pago_conekta') === '') {
    http_response_code(200);
    exit('OK');
}

pagos_atender_webhook('conekta', (string) $evento['id'], (string) $evento['type'], $payload, fn() => conekta_procesar_evento($evento));
