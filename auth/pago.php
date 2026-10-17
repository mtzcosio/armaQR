<?php

declare(strict_types=1);

/**
 * Acciones de pago (POST con CSRF). El plan nunca se cambia aquí: solo se manda al usuario a la
 * pasarela o se le pide un cambio; la suscripción local se actualiza al llegar el webhook.
 *  - pagar:      plan + periodicidad + pasarela. Sin suscripción de Stripe abre el Checkout de la
 *                pasarela elegida; con una de Stripe activa cambia el plan ahí (con prorrateo).
 *  - cancelar / reanudar: cancelación al final del periodo en Stripe (Conekta no se renueva sola).
 *  - portal:     portal de Stripe para cambiar tarjeta y ver facturas.
 *  - reembolsar: (admin) reembolso total de un pago, desde Administración › Pagos.
 */

require_once dirname(__DIR__) . '/includes/seguridad.php';
require_once dirname(__DIR__) . '/includes/pagos.php';
require_once dirname(__DIR__) . '/app/Core/Auth.php';

use App\Core\Auth;

/** Manda al navegador a la pasarela (URL externa). */
function ir_a_pasarela(string $url): never
{
    header('Cache-Control: no-store');
    header('Location: ' . $url, true, 303);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('app/plan.php');
}

$sesion = Auth::requireUser();
$accion = (string) ($_POST['accion'] ?? '');
$volver = $accion === 'reembolsar' ? 'admin/pagos.php' : 'app/plan.php';
if (!csrf_valido($_POST['csrf_token'] ?? null)) {
    flash('error', 'La sesión del formulario expiró. Vuelve a intentarlo.');
    redirigir($volver);
}

$usuario = datos_usuario_actual();
$conStripe = $usuario['pasarela'] === 'stripe' && !empty($usuario['suscripcion_externa'])
    && in_array($usuario['estatus_suscripcion'], ['activa', 'en_prueba', 'pago_pendiente'], true);

try {
    switch ($accion) {
        case 'pagar':
            $plan = datos_planes(true)[(string) ($_POST['plan'] ?? '')] ?? null;
            $periodicidad = (string) ($_POST['periodicidad'] ?? 'mensual');
            $pasarela = (string) ($_POST['pasarela'] ?? '');
            if ($plan === null || $plan['codigo'] === 'gratis' || !in_array($periodicidad, PAGOS_PERIODICIDADES, true)) {
                flash('error', 'Elige un plan de pago válido.');
                redirigir($volver);
            }
            if (pago_precio_plan($plan, $periodicidad) <= 0) {
                flash('error', 'Ese plan no tiene precio ' . $periodicidad . '.');
                redirigir($volver);
            }

            if ($conStripe) {
                if (!stripe_activo()) {
                    flash('error', 'Los cambios de plan no están disponibles por ahora. Tu suscripción sigue igual.');
                    redirigir($volver);
                }
                if ($pasarela !== 'stripe') {
                    flash('error', 'Tu suscripción se cobra automáticamente con tarjeta. Para pagar de otra forma, primero cancélala y espera a que termine el periodo.');
                    redirigir($volver);
                }
                if ($plan['codigo'] === $usuario['plan'] && $periodicidad === $usuario['periodicidad']) {
                    flash('info', 'Ya tienes ese plan.');
                    redirigir($volver);
                }
                stripe_cambiar_plan((string) $usuario['suscripcion_externa'], $plan, $periodicidad);
                flash('ok', "Pediste el cambio al plan {$plan['nombre']}. Se cobra la diferencia proporcional y el plan se actualiza en unos segundos.");
                redirigir($volver);
            }

            if ($pasarela === 'stripe' && stripe_activo()) {
                ir_a_pasarela(stripe_checkout($usuario, $plan, $periodicidad));
            }
            if ($pasarela === 'conekta' && conekta_activo()) {
                ir_a_pasarela(conekta_checkout($usuario, $plan, $periodicidad));
            }
            flash('error', 'Esa forma de pago no está disponible.');
            redirigir($volver);

        case 'cancelar':
        case 'reanudar':
            if (!$conStripe) {
                flash('info', 'Tu plan no se renueva automáticamente: al terminar el periodo pagado regresas al plan Gratis.');
                redirigir($volver);
            }
            stripe_cancelar_al_final((string) $usuario['suscripcion_externa'], $accion === 'cancelar');
            flash('ok', $accion === 'cancelar'
                ? 'Cancelaste tu suscripción. Conservas tu plan hasta el fin del periodo pagado.'
                : 'Reanudaste tu suscripción. Se seguirá cobrando automáticamente.');
            redirigir($volver);

        case 'portal':
            $cliente = (string) ($usuario['id_cliente_pasarela'] ?? '');
            if (!str_starts_with($cliente, 'cus_')) {
                flash('error', 'Aún no tienes una forma de pago registrada en Stripe.');
                redirigir($volver);
            }
            ir_a_pasarela(stripe_portal($cliente));

        case 'reembolsar':
            if (!Auth::isAdminRole($sesion)) {
                redirigir('app/index.php');
            }
            $pago = datos_consulta('SELECT id, pasarela, id_externo, monto, estatus FROM pagos WHERE id = :id', [':id' => (int) ($_POST['id'] ?? 0)])[0] ?? null;
            if ($pago === null || $pago['estatus'] !== 'pagado') {
                flash('error', 'Solo se pueden reembolsar pagos con estatus "Pagado".');
                redirigir($volver);
            }
            match ($pago['pasarela']) {
                'stripe' => stripe_reembolsar((string) $pago['id_externo']),
                'conekta' => conekta_reembolsar((string) $pago['id_externo'], (float) $pago['monto']),
                default => throw new PagoError('Esa pasarela no admite reembolsos desde aquí.'),
            };
            flash('ok', 'Reembolso solicitado por ' . dinero((float) $pago['monto']) . '. El plan del cliente no cambia; ajústalo en su ficha si hace falta.');
            redirigir($volver);
    }
} catch (PagoError $exception) {
    error_log('Pago (' . $accion . ', usuario ' . $usuario['id'] . '): ' . $exception->getMessage());
    flash('error', 'No pudimos completar la operación con la pasarela. ' . $exception->getMessage());
    redirigir($volver);
} catch (Throwable $exception) {
    error_log('Pago (' . $accion . ', usuario ' . $usuario['id'] . '): ' . $exception->getMessage());
    flash('error', 'Ocurrió un error al procesar el pago. Inténtalo de nuevo en unos minutos.');
    redirigir($volver);
}

redirigir($volver);
