<?php

declare(strict_types=1);

/**
 * Acciones del administrador sobre una cuenta (POST con CSRF):
 *  - asignar_plan: cambia el plan de la suscripción vigente o la crea si no hay.
 *  - suspender:    la cuenta no puede iniciar sesión (efecto inmediato: la sesión se revisa en cada página).
 *  - activar:      vuelve a 'activo' una cuenta suspendida o pendiente.
 *  - eliminar:     borrado lógico; los pagos se conservan.
 *  - reenviar_verificacion / verificar_manual: confirmación del correo.
 *  - nota_agregar / nota_eliminar: notas internas (solo las ve el equipo de administración).
 *
 * Protecciones: nadie puede suspenderse ni eliminarse a sí mismo, y nunca se
 * queda el sistema sin un administrador activo.
 */

require_once dirname(__DIR__) . '/includes/seguridad.php';
require_once dirname(__DIR__) . '/includes/recuperacion.php';
require_once dirname(__DIR__) . '/includes/verificacion.php';
require_once dirname(__DIR__) . '/includes/qr.php';
require_once dirname(__DIR__) . '/app/Core/Auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('admin/usuarios.php');
}

$admin = App\Core\Auth::requireAdmin();
$id = (int) ($_POST['usuario_id'] ?? 0);
$volver = 'admin/usuario.php?id=' . $id;

if (!csrf_valido($_POST['csrf_token'] ?? null)) {
    flash('error', 'La sesión del formulario expiró. Vuelve a intentarlo.');
    redirigir($volver);
}

$pdo = datos_pdo();
$usuario = datos_consulta(
    'SELECT id, nombre, email, rol, estatus, email_verificado_en FROM usuarios WHERE id = :id AND eliminado = 0',
    [':id' => $id]
)[0] ?? null;
if ($usuario === null) {
    flash('error', 'El usuario no existe o ya se eliminó.');
    redirigir('admin/usuarios.php');
}

$accion = (string) ($_POST['accion'] ?? '');
$esUnoMismo = (int) $usuario['id'] === (int) $admin['id'];

/** ¿Quedaría al menos otro administrador activo si este deja de estarlo? */
$quedaOtroAdmin = fn(): bool => (int) datos_consulta(
    "SELECT COUNT(*) AS n FROM usuarios WHERE rol = 'admin' AND estatus = 'activo' AND eliminado = 0 AND id <> :id",
    [':id' => $usuario['id']]
)[0]['n'] > 0;

/** Bloquea acciones que dejarían a alguien fuera del panel. */
$protegerAcceso = function (string $verbo) use ($esUnoMismo, $usuario, $quedaOtroAdmin, $volver): void {
    if ($esUnoMismo) {
        flash('error', "No puedes {$verbo} tu propia cuenta.");
        redirigir($volver);
    }
    if ($usuario['rol'] === 'admin' && !$quedaOtroAdmin()) {
        flash('error', "No puedes {$verbo} al único administrador activo.");
        redirigir($volver);
    }
};

$tocarUsuario = fn(string $set, array $params = []) => $pdo->prepare(
    "UPDATE usuarios SET {$set}, usuario_modificacion = :admin, fecha_modificacion = NOW() WHERE id = :id"
)->execute($params + [':admin' => $admin['id'], ':id' => $usuario['id']]);

switch ($accion) {

    case 'asignar_plan':
        if ($usuario['rol'] !== 'cliente') {
            flash('error', 'Los administradores no tienen plan.');
            redirigir($volver);
        }
        $planes = datos_planes();
        $codigo = (string) ($_POST['plan'] ?? '');
        if (!isset($planes[$codigo])) {
            flash('error', 'Elige un plan válido.');
            redirigir($volver);
        }
        $plan = $planes[$codigo];
        $vigente = datos_consulta(
            'SELECT s.id, s.plan_id, s.periodicidad, s.pasarela FROM suscripciones s WHERE s.usuario_vigente = :id',
            [':id' => $usuario['id']]
        )[0] ?? null;
        if ($vigente && (int) $vigente['plan_id'] === $plan['id']) {
            flash('info', "{$usuario['nombre']} ya tiene el plan {$plan['nombre']}.");
            redirigir($volver);
        }
        // Gratis no tiene periodicidad; uno de pago conserva la que tenía o queda mensual.
        $periodicidad = $codigo === 'gratis' ? null : ($vigente['periodicidad'] ?? 'mensual');
        if ($vigente) {
            $pdo->prepare(
                'UPDATE suscripciones SET plan_id = :plan, periodicidad = :per, usuario_modificacion = :admin, fecha_modificacion = NOW()
                 WHERE id = :sid'
            )->execute([':plan' => $plan['id'], ':per' => $periodicidad, ':admin' => $admin['id'], ':sid' => $vigente['id']]);
        } else {
            $pdo->prepare(
                "INSERT INTO suscripciones (usuario_id, plan_id, estatus, periodicidad, usuario_creacion)
                 VALUES (:u, :plan, 'activa', :per, :admin)"
            )->execute([':u' => $usuario['id'], ':plan' => $plan['id'], ':per' => $periodicidad, ':admin' => $admin['id']]);
        }
        $aviso = $vigente && $vigente['pasarela'] ? ' Ojo: no cambia lo que cobra la pasarela; ajústalo también allá.' : '';
        $pausados = qr_ajustar_al_plan((int) $usuario['id'], (int) $admin['id']);
        if ($pausados > 0) {
            $aviso .= ' Se pausaron sus ' . numero($pausados) . ' QR dinámicos más nuevos porque excedían el límite del plan.';
        }
        flash('ok', "Asignaste el plan {$plan['nombre']} a {$usuario['nombre']}. No genera cobro.{$aviso}");
        redirigir($volver);

    case 'suspender':
        $protegerAcceso('suspender');
        if ($usuario['estatus'] === 'suspendido') {
            flash('info', 'La cuenta ya estaba suspendida.');
            redirigir($volver);
        }
        $tocarUsuario("estatus = 'suspendido'");
        recuperacion_anular_tokens((int) $usuario['id']); // una liga pendiente ya no debe servir
        flash('ok', "Suspendiste a {$usuario['nombre']}. Ya no puede iniciar sesión y, si estaba dentro, se le sacó del panel.");
        redirigir($volver);

    case 'activar':
        if ($usuario['estatus'] === 'activo') {
            flash('info', 'La cuenta ya estaba activa.');
            redirigir($volver);
        }
        $tocarUsuario("estatus = 'activo'");
        flash('ok', "Activaste la cuenta de {$usuario['nombre']}. Ya puede iniciar sesión.");
        redirigir($volver);

    case 'eliminar':
        $protegerAcceso('eliminar');
        $pdo->beginTransaction();
        try {
            // El correo se libera (con prefijo) para que la persona pueda volver a registrarse;
            // el original queda legible en la fila para contabilidad y soporte.
            $tocarUsuario('eliminado = 1, email = :email', [
                ':email' => substr('eliminado-' . $usuario['id'] . '-' . $usuario['email'], 0, 190),
            ]);
            $pdo->prepare(
                "UPDATE suscripciones SET estatus = 'cancelada', cancelada_en = NOW(), usuario_modificacion = :admin
                 WHERE usuario_vigente = :id"
            )->execute([':admin' => $admin['id'], ':id' => $usuario['id']]);
            recuperacion_anular_tokens((int) $usuario['id']);
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            error_log('Error al eliminar usuario: ' . $exception->getMessage());
            flash('error', 'No se pudo eliminar la cuenta. Inténtalo de nuevo.');
            redirigir($volver);
        }
        flash('ok', "Eliminaste la cuenta de {$usuario['nombre']} ({$usuario['email']}). Sus pagos se conservan.");
        redirigir('admin/usuarios.php');

    case 'reenviar_verificacion':
        if ($usuario['email_verificado_en'] !== null) {
            flash('info', 'Su correo ya está confirmado.');
            redirigir($volver);
        }
        if ($usuario['estatus'] !== 'activo') {
            flash('error', 'La cuenta no está activa: no podría usar la liga.');
            redirigir($volver);
        }
        [$ok, $detalle] = verificacion_solicitar($usuario);
        flash($ok ? 'ok' : 'error', $ok
            ? "Enviamos a {$usuario['email']} una liga para confirmar su correo (vence en " . VERIFICACION_HORAS . ' horas).'
            : 'No se pudo enviar el correo: ' . $detalle);
        redirigir($volver);

    case 'verificar_manual':
        if ($usuario['email_verificado_en'] !== null) {
            flash('info', 'Su correo ya estaba confirmado.');
            redirigir($volver);
        }
        verificacion_marcar((int) $usuario['id'], (int) $admin['id']);
        flash('ok', "Marcaste como confirmado el correo de {$usuario['nombre']}.");
        redirigir($volver);

    case 'nota_agregar':
        $nota = trim((string) ($_POST['nota'] ?? ''));
        if ($nota === '') {
            flash('error', 'Escribe la nota antes de guardarla.');
            redirigir($volver . '#notas');
        }
        if (mb_strlen($nota) > 2000) {
            flash('error', 'La nota puede tener hasta 2000 caracteres.');
            redirigir($volver . '#notas');
        }
        $pdo->prepare('INSERT INTO notas_usuario (usuario_id, autor_id, nota) VALUES (:u, :a, :n)')
            ->execute([':u' => $usuario['id'], ':a' => $admin['id'], ':n' => $nota]);
        flash('ok', 'Guardaste la nota.');
        redirigir($volver . '#notas');

    case 'nota_eliminar':
        // Solo notas de este usuario (el id del formulario no basta para borrar notas ajenas).
        $borradas = $pdo->prepare('DELETE FROM notas_usuario WHERE id = :n AND usuario_id = :u');
        $borradas->execute([':n' => (int) ($_POST['nota_id'] ?? 0), ':u' => $usuario['id']]);
        flash($borradas->rowCount() ? 'ok' : 'error', $borradas->rowCount() ? 'Eliminaste la nota.' : 'La nota ya no existe.');
        redirigir($volver . '#notas');

    default:
        redirigir($volver);
}
