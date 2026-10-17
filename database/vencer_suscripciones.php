<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * Pasa a 'vencida' las suscripciones de pago cuyo periodo terminó (más los días de gracia)
 * y regresa a esos usuarios al plan Gratis. Programarlo una vez al día (cron o Programador de tareas):
 *
 *   php database/vencer_suscripciones.php
 *
 * Aunque no corra, un plan vencido ya cuenta como Gratis en el panel y en las redirecciones;
 * este script solo deja la tabla suscripciones al día.
 */

require_once dirname(__DIR__) . '/includes/pagos.php';

echo pagos_vencer_suscripciones() . " suscripciones vencidas.\n";
