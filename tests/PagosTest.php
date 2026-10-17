<?php

declare(strict_types=1);

// Lógica de pagos (includes/pagos.php) que no llama a las pasarelas ni escribe en la BD.
require_once dirname(__DIR__) . '/includes/pagos.php';

$fallas = [];
$prueba = function (string $nombre, bool $ok, string $detalle = '') use (&$fallas) {
    if (!$ok) $fallas[] = $nombre . ($detalle !== '' ? "  [{$detalle}]" : '');
};

// --- Firma del webhook de Stripe ---
$secreto = 'whsec_prueba';
$payload = '{"id":"evt_1","type":"invoice.paid"}';
$t = 1_700_000_000;
$firma = hash_hmac('sha256', $t . '.' . $payload, $secreto);
$prueba('Firma válida', stripe_firma_valida($payload, "t={$t},v1={$firma}", $secreto, $t + 10));
$prueba('Firma válida entre varias v1', stripe_firma_valida($payload, "t={$t},v1=abc,v1={$firma},v0=xyz", $secreto, $t));
$prueba('Firma con otro secreto', !stripe_firma_valida($payload, "t={$t},v1={$firma}", 'whsec_otro', $t));
$prueba('Cuerpo alterado', !stripe_firma_valida($payload . ' ', "t={$t},v1={$firma}", $secreto, $t));
$prueba('Firma vieja (repetición)', !stripe_firma_valida($payload, "t={$t},v1={$firma}", $secreto, $t + PAGOS_TOLERANCIA_FIRMA + 1));
$prueba('Sin secreto configurado', !stripe_firma_valida($payload, "t={$t},v1={$firma}", '', $t));
$prueba('Encabezado vacío', !stripe_firma_valida($payload, '', $secreto, $t));

// --- Codificación de parámetros de Stripe ---
$cuerpo = urldecode(stripe_codificar([
    'mode' => 'subscription',
    'line_items' => [['price' => 'price_1', 'quantity' => 1]],
    'cancel_at_period_end' => false,
    'allow_promotion_codes' => true,
    'expand' => ['charge'],
]));
$prueba('Arreglos anidados', str_contains($cuerpo, 'line_items[0][price]=price_1') && str_contains($cuerpo, 'line_items[0][quantity]=1'), $cuerpo);
$prueba('Booleanos como texto', str_contains($cuerpo, 'cancel_at_period_end=false') && str_contains($cuerpo, 'allow_promotion_codes=true'), $cuerpo);
$prueba('Listas', str_contains($cuerpo, 'expand[0]=charge'), $cuerpo);

// --- Estatus de Stripe → local ---
foreach (['active' => 'activa', 'trialing' => 'en_prueba', 'past_due' => 'pago_pendiente', 'unpaid' => 'pago_pendiente',
          'canceled' => 'cancelada', 'incomplete_expired' => 'cancelada'] as $stripe => $local) {
    $prueba("Estatus {$stripe}", stripe_estatus($stripe) === $local, stripe_estatus($stripe));
}

// --- Plan de una suscripción de Stripe ---
$subPrecio = ['id' => 'sub_1', 'metadata' => ['plan' => 'negocio'], 'items' => ['data' => [['price' => ['metadata' => ['plan' => 'pro']]]]]];
$prueba('Plan desde el precio', stripe_plan_de($subPrecio) === 'pro');
$prueba('Plan desde la suscripción', stripe_plan_de(['id' => 'sub_1', 'metadata' => ['plan' => 'negocio'], 'items' => ['data' => [['price' => []]]]]) === 'negocio');
$prueba('Suscripción ajena sin plan', stripe_plan_de(['id' => 'sub_1', 'items' => ['data' => [['price' => ['metadata' => []]]]]]) === '');

// --- Suscripciones duplicadas: se cancela la más antigua ---
$vieja = ['id' => 'sub_B', 'created' => 1_700_000_000];
$nueva = ['id' => 'sub_A', 'created' => 1_700_000_060];
$prueba('Más antigua por fecha', stripe_mas_antigua($vieja, $nueva)['id'] === 'sub_B' && stripe_mas_antigua($nueva, $vieja)['id'] === 'sub_B');
$empate = ['id' => 'sub_A', 'created' => 1_700_000_000];
$prueba('Empate por id, sin importar el orden', stripe_mas_antigua($vieja, $empate)['id'] === 'sub_A' && stripe_mas_antigua($empate, $vieja)['id'] === 'sub_A');

// --- Montos y periodos ---
$prueba('Centavos sin error de redondeo', pago_centavos(149.99) === 14999 && pago_centavos(1490.0) === 149000);
$prueba('Precio según periodicidad', pago_precio_plan(['precio_mensual' => 149.0, 'precio_anual' => 1490.0], 'anual') === 1490.0);
$inicio = (new DateTimeImmutable('2026-03-15 10:00:00'))->getTimestamp();
$prueba('Periodo mensual', date('Y-m-d', pagos_sumar_periodo($inicio, 'mensual')) === '2026-04-15');
$prueba('Periodo anual', date('Y-m-d', pagos_sumar_periodo($inicio, 'anual')) === '2027-03-15');

// --- Método de pago de Conekta ---
$prueba('Conekta tarjeta', conekta_metodo(['payment_method' => ['object' => 'card_payment', 'type' => 'credit', 'last4' => '4242']]) === ['tarjeta', '4242']);
$prueba('Conekta OXXO', conekta_metodo(['payment_method' => ['object' => 'cash_payment', 'type' => 'oxxo']])[0] === 'oxxo');
$prueba('Conekta SPEI', conekta_metodo(['payment_method' => ['object' => 'bank_transfer_payment', 'type' => 'spei']])[0] === 'spei');
$prueba('Conekta sin cargo', conekta_metodo([]) === [null, null]);

if ($fallas) {
    fwrite(STDERR, "Fallaron:\n  - " . implode("\n  - ", $fallas) . "\n");
    exit(1);
}
echo "Pagos: firma de Stripe, parámetros, estatus, plan, duplicadas, montos, periodos y métodos de Conekta: OK\n";
