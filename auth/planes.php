<?php

declare(strict_types=1);

/**
 * Planes (POST con CSRF, solo administradores): guarda precios, límites y funciones de un plan.
 *  - Un límite vacío se guarda como NULL (ilimitado). El plan Gratis siempre cuesta 0.
 *  - Si baja el tope de QR dinámicos, a cada cliente del plan se le pausan los que sobren
 *    (los más nuevos), igual que al bajar de plan (qr_ajustar_al_plan).
 *  - Un cambio de precio no toca lo que ya cobra Stripe: ver el aviso en admin/planes.php.
 */

require_once dirname(__DIR__) . '/includes/seguridad.php';
require_once dirname(__DIR__) . '/includes/qr.php';
require_once dirname(__DIR__) . '/app/Core/Auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('admin/planes.php');
}
$admin = App\Core\Auth::requireAdmin();
$volver = 'admin/planes.php';
if (!csrf_valido($_POST['csrf_token'] ?? null)) {
    flash('error', 'La sesión del formulario expiró. Vuelve a intentarlo.');
    redirigir($volver);
}

$plan = null;
foreach (datos_planes() as $p) {
    if ($p['id'] === (int) ($_POST['id'] ?? 0)) {
        $plan = $p;
    }
}
if ($plan === null) {
    flash('error', 'Ese plan no existe.');
    redirigir($volver);
}

$datos = datos_plan_desde_formulario($_POST, $plan['codigo'] === 'gratis');
if (is_string($datos)) {
    flash('error', "Plan {$plan['nombre']}: {$datos}");
    redirigir($volver);
}

$columnas = array_keys($datos);
datos_pdo()->prepare(
    'UPDATE planes SET ' . implode(', ', array_map(fn($c) => "{$c} = :{$c}", $columnas)) . ',
            usuario_modificacion = :admin, fecha_modificacion = NOW()
     WHERE id = :id AND eliminado = 0'
)->execute(array_combine(array_map(fn($c) => ':' . $c, $columnas), array_values($datos)) + [':admin' => $admin['id'], ':id' => $plan['id']]);

$mensaje = "Guardaste el plan {$plan['nombre']}.";

$nuevoTope = $datos['max_qr_dinamicos'];
if ($nuevoTope !== null && $nuevoTope > 0 && ($plan['max_qr_dinamicos'] === null || $nuevoTope < $plan['max_qr_dinamicos'])) {
    $pausados = 0;
    // Dueños de dinámicos activos con este plan; en Gratis también quienes no tienen suscripción vigente.
    $duenos = datos_consulta(
        "SELECT DISTINCT q.usuario_id FROM qr_codigos q
         LEFT JOIN suscripciones s ON s.usuario_vigente = q.usuario_id AND " . SQL_SUSCRIPCION_AL_DIA . "
         WHERE q.tipo = 'dinamico' AND q.activo = 1 AND q.eliminado = 0
           AND (s.plan_id = :p OR (s.id IS NULL AND :gratis = 1))",
        [':p' => $plan['id'], ':gratis' => (int) ($plan['codigo'] === 'gratis')]
    );
    foreach ($duenos as $d) {
        $pausados += qr_ajustar_al_plan((int) $d['usuario_id'], (int) $admin['id']);
    }
    if ($pausados > 0) {
        $mensaje .= ' Se pausaron ' . numero($pausados) . ' QR dinámicos de clientes que excedían el nuevo límite.';
    }
}

if ($plan['codigo'] !== 'gratis'
    && ($datos['precio_mensual'] !== $plan['precio_mensual'] || $datos['precio_anual'] !== $plan['precio_anual'])) {
    $mensaje .= ' El precio nuevo aplica a las contrataciones nuevas y a los pagos de Conekta; quien ya está suscrito con Stripe conserva su precio hasta que cambie de plan.';
}

flash('ok', $mensaje);
redirigir($volver);
