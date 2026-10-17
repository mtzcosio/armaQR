<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/seguridad.php';
require_once dirname(__DIR__) . '/includes/datos.php';
require_once dirname(__DIR__) . '/includes/smtp.php';
require_once dirname(__DIR__) . '/includes/tema.php';
require_once dirname(__DIR__) . '/includes/archivos.php';
require_once dirname(__DIR__) . '/database/Database.php';
require_once dirname(__DIR__) . '/app/Core/Auth.php';

use App\Core\Auth;
use App\Core\Database;

/**
 * Claves que se pueden guardar desde el formulario.
 * portal_logo_url y portal_favicon_url solo se fijan al subir un archivo.
 */
const CONFIG_PERMITIDAS = [
    'portal_nombre', 'portal_dominio', 'portal_descripcion',
    'contacto_correo', 'contacto_telefono', 'contacto_whatsapp', 'contacto_horario',
    'contacto_direccion', 'contacto_instagram', 'contacto_facebook', 'contacto_tiktok',
    'ventas_moneda', 'ventas_impuesto', 'ventas_duracion_qr', 'ventas_escaneos_qr',
    'smtp_enabled', 'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_usuario',
    'smtp_password', 'smtp_from_email', 'smtp_from_nombre', 'pago_conekta',
    'pago_stripe', 'pago_stripe_webhook', 'pago_stripe_habilitado', 'pago_conekta_habilitado', 'pago_tarjeta', 'pago_oxxo', 'pago_spei',
];

/** Secretos: nunca se envían al navegador; si llegan vacíos se conserva el valor guardado. */
const CONFIG_SECRETAS = ['smtp_password', 'pago_conekta', 'pago_stripe', 'pago_stripe_webhook'];

/** Formato esperado de las claves de pasarela: [expresión, mensaje si no coincide]. */
const CONFIG_PREFIJOS_PAGO = [
    'pago_stripe' => ['/^(sk|rk)_(test|live)_\w+$/', 'La clave secreta de Stripe empieza con sk_test_ o sk_live_ (Developers › API keys).'],
    'pago_stripe_webhook' => ['/^whsec_\w+$/', 'El secreto del webhook de Stripe empieza con whsec_: lo da "stripe listen" o la página del webhook en Stripe (Signing secret). No es la clave secreta sk_….'],
    'pago_conekta' => ['/^key_\w+$/', 'La llave privada de Conekta empieza con key_.'],
];

/** Logo o favicon del portal: valida y guarda la imagen (includes/archivos.php) y devuelve su URL. */
function save_brand_image(array $file, string $field): ?string
{
    $ruta = imagen_guardar_subida($file, 'brand', $field);
    return $ruta === null ? null : url($ruta);
}

/** Lee la configuración guardada como [clave => valor]. */
function config_guardada(PDO $pdo): array
{
    $rows = $pdo->query('SELECT clave, valor FROM configuracion')->fetchAll(PDO::FETCH_ASSOC);
    return array_column($rows, 'valor', 'clave');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('admin/configuracion.php');
}

Auth::requireAdmin();

// Al terminar se vuelve a la sección en la que estaba el administrador.
$seccion = (string) ($_POST['seccion'] ?? '');
$volver = 'admin/configuracion.php'
    . (in_array($seccion, ['identidad', 'colores', 'contacto', 'ventas', 'correo', 'pagos'], true) ? '#' . $seccion : '');

if (!csrf_valido($_POST['csrf_token'] ?? null)) {
    flash('error', 'La sesión del formulario expiró. Vuelve a intentarlo.');
    redirigir($volver);
}

$valores = [];

try {
    foreach (['portal_logo', 'portal_favicon'] as $field) {
        if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException("El archivo de {$field} no fue válido.");
        }
        $savedPath = save_brand_image($_FILES[$field], $field);
        if ($savedPath === null) {
            throw new RuntimeException("El archivo de {$field} no fue válido.");
        }
        $valores[$field === 'portal_logo' ? 'portal_logo_url' : 'portal_favicon_url'] = $savedPath;
    }
} catch (Throwable $exception) {
    flash('error', $exception->getMessage());
    redirigir($volver);
}

// Los colores (claro y oscuro) y el modo oscuro vienen del registro de includes/tema.php.
$permitidas = array_merge(CONFIG_PERMITIDAS, tema_claves(), ['tema_oscuro']);
$clavesColor = tema_claves();

foreach ($permitidas as $clave) {
    if (array_key_exists($clave, $valores) || !array_key_exists($clave, $_POST)) {
        continue;
    }
    $value = $_POST[$clave];
    $valor = is_array($value) ? implode(';', array_map('strval', $value)) : trim((string) $value);
    if (in_array($clave, CONFIG_SECRETAS, true) && $valor === '') {
        continue;
    }
    if (in_array($clave, $clavesColor, true)) {
        if (!tema_color_valido($valor)) {
            continue; // se ignora cualquier valor que no sea #RRGGBB
        }
        $valor = strtoupper($valor);
    }
    if ($clave === 'portal_dominio') {
        $valor = normalizar_dominio($valor);
        if (!preg_match('/^(?=.{3,100}$)([a-z0-9](-?[a-z0-9])*\.)+[a-z]{2,}$/', $valor)) {
            flash('error', 'El dominio no es válido. Escríbelo como armaqr.com, sin https:// ni barras.');
            redirigir($volver);
        }
    }
    // Cada clave de pasarela tiene un prefijo fijo; así no se pega una en el campo de otra.
    if (isset(CONFIG_PREFIJOS_PAGO[$clave]) && !preg_match(CONFIG_PREFIJOS_PAGO[$clave][0], $valor)) {
        flash('error', CONFIG_PREFIJOS_PAGO[$clave][1]);
        redirigir($volver);
    }
    if ($clave === 'tema_oscuro' && !isset(TEMA_MODOS[$valor])) {
        continue;
    }
    $valores[$clave] = $valor;
}

$restablecerColores = !empty($_POST['restablecer_colores']);
if ($restablecerColores) {
    $valores = array_diff_key($valores, array_flip($clavesColor));
}

// Permite borrar explícitamente un secreto guardado.
foreach (CONFIG_SECRETAS as $clave) {
    if (!empty($_POST['borrar_' . $clave])) {
        $valores[$clave] = '';
    }
}

$pdo = Database::connection();
$upsert = $pdo->prepare(
    'INSERT INTO configuracion (clave, valor, descripcion)
     VALUES (:clave, :valor, :descripcion)
     ON DUPLICATE KEY UPDATE valor = VALUES(valor), actualizado_en = CURRENT_TIMESTAMP'
);

try {
    $pdo->beginTransaction();
    if ($restablecerColores) {
        $marcadores = implode(',', array_fill(0, count($clavesColor), '?'));
        $pdo->prepare("DELETE FROM configuracion WHERE clave IN ({$marcadores})")->execute($clavesColor);
    }
    foreach ($valores as $clave => $valor) {
        $upsert->execute([
            ':clave' => $clave,
            ':valor' => $valor,
            ':descripcion' => 'Configuración del portal',
        ]);
    }
    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error al guardar configuración: ' . $exception->getMessage());
    flash('error', 'No se pudo guardar la configuración. Inténtalo de nuevo.');
    redirigir($volver);
}

if (($_POST['action'] ?? '') === 'probar-correo') {
    $config = config_guardada($pdo);
    $destino = (string) ($_POST['smtp_test'] ?? '');
    if (!filter_var($destino, FILTER_VALIDATE_EMAIL)) {
        $destino = (string) ($_SESSION['usuario_email'] ?? '');
    }

    if (($config['smtp_enabled'] ?? '0') !== '1') {
        flash('error', 'Activa "Habilitar envío por SMTP" antes de probar.');
    } else {
        [$ok, $mensaje] = smtp_enviar_prueba([
            'host' => $config['smtp_host'] ?? '',
            'port' => $config['smtp_port'] ?? 587,
            'encryption' => $config['smtp_encryption'] ?? 'tls',
            'usuario' => $config['smtp_usuario'] ?? '',
            'password' => $config['smtp_password'] ?? '',
            'from_email' => $config['smtp_from_email'] ?? '',
            'from_nombre' => $config['smtp_from_nombre'] ?? '',
        ], $destino);
        flash($ok ? 'ok' : 'error', $ok ? $mensaje : 'La prueba falló: ' . $mensaje);
    }
    redirigir('admin/configuracion.php#correo');
}

flash('ok', $restablecerColores ? 'Configuración guardada. Los colores volvieron a los predeterminados.' : 'Configuración guardada.');
redirigir($volver);
