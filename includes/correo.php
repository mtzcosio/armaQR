<?php
/**
 * PLANTILLAS DE CORREO
 * --------------------------------------------------------------------
 * Los administradores editan el asunto y el HTML de los correos automáticos
 * en Administración › Correos; el código decide cuándo se envía cada uno y
 * con qué datos. Ver docs/plantillas-de-correo.md.
 *
 * Reglas:
 *  1. Un solo punto de envío: correo_enviar_plantilla().
 *  2. El código de la plantilla (BIENVENIDA, …) es el contrato con el panel.
 *  3. Siempre hay respaldo: cada código del sistema tiene un correo
 *     predeterminado aquí, que sale si no hay plantilla activa.
 *  4. Solo cuentan las plantillas 'activa'.
 *  5. Un único catálogo de variables para el editor, la validación y los ejemplos.
 *
 * Seguridad: correo_render() inserta los valores sin escapar (algunas variables
 * son bloques HTML), así que quien arma los datos debe escapar con e() todo lo
 * que venga del usuario. correo_datos_base() y correo_bienvenida() ya lo hacen.
 */

declare(strict_types=1);

require_once __DIR__ . '/datos.php';
require_once __DIR__ . '/smtp.php';
require_once __DIR__ . '/tema.php';

const CORREO_ESTATUS = ['borrador' => 'Borrador', 'activa' => 'Activa', 'inactiva' => 'Inactiva'];
const CORREO_TIPOS = ['Bienvenida', 'Cuenta', 'Seguridad', 'Pagos', 'Notificación', 'Personalizado'];
const CORREO_PATRON_VARIABLE = '/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/';

/* ---------------------------------------------------------------------
 * Correos automáticos del sistema
 * ------------------------------------------------------------------- */

/**
 * Códigos que busca el sitio: cuándo se envían, qué variables llenan y su
 * contenido predeterminado (con {{variables}}). El panel usa este mapa para
 * listar los correos del sistema, advertir de variables que un código no llena
 * y precargar el editor con el contenido predeterminado.
 */
function correo_codigos_sistema(): array
{
    return [
        'BIENVENIDA' => [
            'nombre'    => 'Bienvenida a la cuenta',
            'tipo'      => 'Bienvenida',
            'cuando'    => 'Cuando el usuario confirma su correo (justo después de registrarse).',
            'variables' => ['nombre', 'nombre_completo', 'correo', 'plan', 'url_login',
                            'nombre_portal', 'correo_soporte', 'url_portal', 'color_acento', 'fecha_actual'],
            'asunto'    => '¡Te damos la bienvenida a {{nombre_portal}}, {{nombre}}!',
            'preheader' => 'Tu cuenta está lista. Crea tu primer código QR en menos de un minuto.',
            'html'      => correo_html_base(
                '<h1 style="margin:0 0 16px;font-size:24px;line-height:1.25;color:#172036;">¡Hola, {{nombre}}!</h1>'
                . '<p style="margin:0 0 14px;">Tu cuenta en <strong>{{nombre_portal}}</strong> está lista. Empiezas con el plan <strong>{{plan}}</strong>.</p>'
                . '<p style="margin:0 0 24px;">Ya puedes crear tus códigos QR, descargarlos e imprimirlos.</p>'
                . correo_boton_html('{{url_login}}', 'Entrar a mi cuenta')
                . '<p style="margin:24px 0 0;color:#5B6478;font-size:14px;">Si tienes dudas, escríbenos a <a href="mailto:{{correo_soporte}}" style="color:{{color_acento}};">{{correo_soporte}}</a>.</p>'
            ),
        ],
        'VERIFICAR_CORREO' => [
            'nombre'    => 'Confirmar correo',
            'tipo'      => 'Cuenta',
            'cuando'    => 'Al crear una cuenta, cuando el usuario pide reenviarlo desde su panel o lo reenvía un administrador.',
            'variables' => ['nombre', 'nombre_completo', 'correo', 'url_verificar', 'horas_vigencia',
                            'nombre_portal', 'correo_soporte', 'url_portal', 'color_acento', 'fecha_actual'],
            'asunto'    => 'Confirma tu correo en {{nombre_portal}}',
            'preheader' => 'Un clic y listo: así sabemos que este correo es tuyo.',
            'html'      => correo_html_base(
                '<h1 style="margin:0 0 16px;font-size:24px;line-height:1.25;color:#172036;">¡Hola, {{nombre}}!</h1>'
                . '<p style="margin:0 0 14px;">Gracias por crear tu cuenta en <strong>{{nombre_portal}}</strong>. Confirma que <strong>{{correo}}</strong> es tu correo para que podamos avisarte de tus pagos y ayudarte a recuperar tu acceso.</p>'
                . '<p style="margin:0 0 24px;">La liga vence en <strong>{{horas_vigencia}} horas</strong>.</p>'
                . correo_boton_html('{{url_verificar}}', 'Confirmar mi correo')
                . '<p style="margin:24px 0 0;color:#5B6478;font-size:14px;">Si el botón no funciona, copia esta dirección en tu navegador:<br>'
                . '<a href="{{url_verificar}}" style="color:{{color_acento}};word-break:break-all;">{{url_verificar}}</a></p>'
                . '<p style="margin:16px 0 0;color:#5B6478;font-size:14px;">Si no creaste esta cuenta, ignora este correo.</p>'
            ),
        ],
        'RECUPERAR_CONTRASENA' => [
            'nombre'    => 'Recuperar contraseña',
            'tipo'      => 'Seguridad',
            'cuando'    => 'Cuando alguien pide restablecer su contraseña (o un administrador le envía la liga).',
            'variables' => ['nombre', 'nombre_completo', 'correo', 'url_recuperar', 'minutos_vigencia',
                            'nombre_portal', 'correo_soporte', 'url_portal', 'color_acento', 'fecha_actual'],
            'asunto'    => 'Restablece tu contraseña de {{nombre_portal}}',
            'preheader' => 'La liga vence en {{minutos_vigencia}} minutos y solo funciona una vez.',
            'html'      => correo_html_base(
                '<h1 style="margin:0 0 16px;font-size:24px;line-height:1.25;color:#172036;">Hola, {{nombre}}</h1>'
                . '<p style="margin:0 0 14px;">Recibimos una solicitud para restablecer la contraseña de tu cuenta <strong>{{correo}}</strong>.</p>'
                . '<p style="margin:0 0 24px;">Elige una contraseña nueva con este botón. La liga vence en <strong>{{minutos_vigencia}} minutos</strong> y solo funciona una vez.</p>'
                . correo_boton_html('{{url_recuperar}}', 'Elegir contraseña nueva')
                . '<p style="margin:24px 0 0;color:#5B6478;font-size:14px;">Si el botón no funciona, copia esta dirección en tu navegador:<br>'
                . '<a href="{{url_recuperar}}" style="color:{{color_acento}};word-break:break-all;">{{url_recuperar}}</a></p>'
                . '<p style="margin:16px 0 0;color:#5B6478;font-size:14px;">Si no lo pediste, ignora este correo: tu contraseña no cambia.</p>'
            ),
        ],
    ];
}

/** Estructura de correo "a la antigua" (tablas, estilos en línea, 600 px). */
function correo_html_base(string $contenido): string
{
    return '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
        . '<body style="margin:0;padding:0;background:#F4F5F9;font-family:Arial,Helvetica,sans-serif;color:#172036;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F4F5F9;"><tr><td align="center" style="padding:32px 16px;">'
        . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#FFFFFF;border-radius:12px;">'
        . '<tr><td style="padding:24px 32px;border-bottom:4px solid {{color_acento}};font-size:20px;font-weight:bold;">{{nombre_portal}}</td></tr>'
        . '<tr><td style="padding:32px;font-size:16px;line-height:1.55;">' . $contenido . '</td></tr>'
        . '<tr><td style="padding:20px 32px;color:#8A92A6;font-size:12px;border-top:1px solid #DDE1EA;">{{nombre_portal}} · <a href="{{url_portal}}" style="color:#8A92A6;">{{url_portal}}</a></td></tr>'
        . '</table></td></tr></table></body></html>';
}

/** Botón compatible con clientes de correo. */
function correo_boton_html(string $url, string $texto): string
{
    return '<p style="margin:0;text-align:center;"><a href="' . $url . '" style="display:inline-block;padding:13px 26px;'
        . 'background:{{color_acento}};color:#FFFFFF;text-decoration:none;font-weight:bold;border-radius:8px;">' . $texto . '</a></p>';
}

/* ---------------------------------------------------------------------
 * Catálogo de variables
 * ------------------------------------------------------------------- */

/** Variables integradas por categoría: nombre => descripción. */
function correo_catalogo_integrado(): array
{
    return [
        'Destinatario' => [
            'nombre'          => 'Primer nombre del destinatario.',
            'nombre_completo' => 'Nombre completo del destinatario.',
            'correo'          => 'Correo electrónico del destinatario.',
        ],
        'Portal' => [
            'nombre_portal'  => 'Nombre del portal (Configuración › Identidad).',
            'correo_soporte' => 'Correo de contacto (Configuración › Contacto).',
            'url_portal'     => 'Dirección de la página principal.',
            'color_acento'   => 'Color de acento del portal, en formato #RRGGBB.',
            'fecha_actual'   => 'Fecha en la que se envía el correo.',
        ],
        'Cuenta' => [
            'plan'      => 'Nombre del plan de la cuenta.',
            'url_login' => 'Enlace a la página para iniciar sesión.',
        ],
        'Seguridad' => [
            'url_recuperar'    => 'Liga de un solo uso para elegir una contraseña nueva.',
            'minutos_vigencia' => 'Minutos que dura la liga de recuperación.',
            'url_verificar'    => 'Liga de un solo uso para confirmar el correo.',
            'horas_vigencia'   => 'Horas que dura la liga para confirmar el correo.',
        ],
    ];
}

function correo_variables_integradas(): array
{
    return array_merge(...array_values(array_map('array_keys', correo_catalogo_integrado())));
}

/** Variables personalizadas (tabla variables_correo). Vacío si la tabla aún no existe. */
function correo_variables_personalizadas(): array
{
    static $filas = null;
    if ($filas === null) {
        try {
            $filas = datos_consulta('SELECT * FROM variables_correo ORDER BY categoria, nombre');
        } catch (Throwable $exception) {
            $filas = [];
        }
    }
    return $filas;
}

/** Clave para comparar categorías sin distinguir mayúsculas, acentos ni espacios. */
function correo_normalizar_categoria(string $categoria): string
{
    $clave = mb_strtolower(trim($categoria), 'UTF-8');
    $clave = strtr($clave, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
    return (string) preg_replace('/\s+/', ' ', $clave);
}

/**
 * Catálogo combinado [categoría => [nombre => descripción]]. Una variable
 * personalizada con una categoría existente se agrega dentro de esa sección.
 */
function correo_catalogo(?array $personalizadas = null): array
{
    $catalogo = correo_catalogo_integrado();
    $claves = [];
    foreach (array_keys($catalogo) as $categoria) {
        $claves[correo_normalizar_categoria($categoria)] = $categoria;
    }
    foreach ($personalizadas ?? correo_variables_personalizadas() as $fila) {
        $norma = correo_normalizar_categoria((string) $fila['categoria']);
        if (!isset($claves[$norma])) {
            $claves[$norma] = $fila['categoria'];
            $catalogo[$fila['categoria']] = [];
        }
        $catalogo[$claves[$norma]][$fila['nombre']] = $fila['descripcion'] !== '' ? $fila['descripcion'] : 'Variable personalizada.';
    }
    return $catalogo;
}

function correo_nombres_variables(?array $personalizadas = null): array
{
    return array_merge(...array_values(array_map('array_keys', correo_catalogo($personalizadas))));
}

/** Datos de ejemplo para la vista previa y el correo de prueba. */
function correo_datos_ejemplo(?array $personalizadas = null): array
{
    $datos = correo_datos_base() + [
        'nombre'          => 'Juan',
        'nombre_completo' => 'Juan Pérez',
        'correo'          => 'juan.perez@ejemplo.com',
        'plan'             => 'Pro',
        'url_login'        => correo_url('login.php'),
        'url_recuperar'    => correo_url('restablecer.php?token=ejemplo'),
        'minutos_vigencia' => '60',
        'url_verificar'    => correo_url('verificar.php?token=ejemplo'),
        'horas_vigencia'   => '24',
    ];
    foreach ($personalizadas ?? correo_variables_personalizadas() as $fila) {
        $datos[$fila['nombre']] = e($fila['valor_ejemplo'] !== '' ? $fila['valor_ejemplo'] : ($fila['valor'] !== '' ? $fila['valor'] : '[' . $fila['nombre'] . ']'));
    }
    return $datos;
}

/* ---------------------------------------------------------------------
 * Render y validación
 * ------------------------------------------------------------------- */

/**
 * Sustituye {{variable}} por su valor. Las que no tienen dato se dejan
 * intactas, salvo con $quitarSinDato (envío real): así el destinatario nunca
 * ve llaves sueltas.
 */
function correo_render(string $texto, array $datos, bool $quitarSinDato = false): string
{
    return (string) preg_replace_callback(CORREO_PATRON_VARIABLE, function (array $m) use ($datos, $quitarSinDato) {
        $clave = strtolower($m[1]);
        return array_key_exists($clave, $datos) ? (string) $datos[$clave] : ($quitarSinDato ? '' : $m[0]);
    }, $texto);
}

/** Nombres de variables usados en un texto, sin repetir. */
function correo_variables_usadas(string $texto): array
{
    preg_match_all(CORREO_PATRON_VARIABLE, $texto, $m);
    return array_values(array_unique(array_map('strtolower', $m[1])));
}

/** Variables usadas que no existen en el catálogo. */
function correo_variables_desconocidas(string $texto, ?array $conocidas = null): array
{
    return array_values(array_diff(correo_variables_usadas($texto), $conocidas ?? correo_nombres_variables()));
}

/** Inserta el preheader como texto oculto al inicio del <body> (lo muestran las bandejas de entrada). */
function correo_insertar_preheader(string $html, string $preheader): string
{
    if (trim($preheader) === '') {
        return $html;
    }
    $oculto = '<div style="display:none;max-height:0;overflow:hidden;opacity:0;mso-hide:all;">' . $preheader . '</div>';
    return preg_match('/<body[^>]*>/i', $html)
        ? (string) preg_replace('/(<body[^>]*>)/i', '$1' . str_replace(['\\', '$'], ['\\\\', '\\$'], $oculto), $html, 1)
        : $oculto . $html;
}

/** Código de plantilla a partir de un texto: "Pago recibido" → PAGO_RECIBIDO. */
function correo_codigo_desde(string $texto): string
{
    $texto = strtr(mb_strtoupper(trim($texto), 'UTF-8'), ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N']);
    return substr(trim((string) preg_replace('/[^A-Z0-9]+/', '_', $texto), '_'), 0, 100);
}

/** Nombre de variable a partir de un texto: "Código de descuento" → codigo_de_descuento. */
function correo_nombre_variable_desde(string $texto): string
{
    return strtolower(correo_codigo_desde($texto));
}

/** El contenido cuenta como vacío si no tiene texto visible ni imágenes. */
function correo_contenido_vacio(string $html): bool
{
    // &nbsp; se decodifica como U+00A0, que trim() no quita.
    $texto = str_replace("\u{00A0}", ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    return trim($texto) === '' && stripos($html, '<img') === false;
}

/* ---------------------------------------------------------------------
 * Datos y configuración
 * ------------------------------------------------------------------- */

/** URL absoluta dentro del portal (los correos necesitan direcciones completas). */
function correo_url(string $ruta = ''): string
{
    $https = ($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return ($https ? 'https' : 'http') . '://' . $host . url($ruta);
}

/** Variables de "Portal" que todo correo tiene disponibles (ya escapadas). */
function correo_datos_base(): array
{
    $config = configuracion_activa();
    return [
        'nombre_portal'  => e(nombre_portal()),
        'correo_soporte' => e((string) ($config['contacto_correo'] ?? '')),
        'url_portal'     => e(correo_url()),
        'color_acento'   => tema_valores($config, false)['rosa'],
        'fecha_actual'   => fecha_corta(date('Y-m-d')),
    ];
}

/** Valores reales de las variables personalizadas (columna "valor"), ya escapados. */
function correo_valores_personalizados(): array
{
    $valores = [];
    foreach (correo_variables_personalizadas() as $fila) {
        if ((string) $fila['valor'] !== '') {
            $valores[$fila['nombre']] = e((string) $fila['valor']);
        }
    }
    return $valores;
}

/** ¿Existen las tablas del módulo? (database/migrate_plantillas_correo.php) */
function correo_tablas_listas(): bool
{
    try {
        datos_pdo()->query('SELECT 1 FROM plantillas_correo LIMIT 1');
        datos_pdo()->query('SELECT 1 FROM variables_correo LIMIT 1');
        return true;
    } catch (Throwable $exception) {
        return false;
    }
}

/** Plantilla activa con ese código, o null (borrador, inactiva, inexistente o sin tabla). */
function correo_plantilla_activa(string $codigo): ?array
{
    static $cache = [];
    if (!array_key_exists($codigo, $cache)) {
        try {
            $cache[$codigo] = datos_consulta(
                "SELECT * FROM plantillas_correo WHERE codigo = :codigo AND estatus = 'activa' LIMIT 1",
                [':codigo' => $codigo]
            )[0] ?? null;
        } catch (Throwable $exception) {
            $cache[$codigo] = null;
        }
    }
    return $cache[$codigo];
}

/** Configuración SMTP guardada en Configuración › Correo. */
function correo_config_smtp(): array
{
    $c = configuracion_activa();
    return [
        'habilitado'  => ($c['smtp_enabled'] ?? '0') === '1',
        'host'        => $c['smtp_host'] ?? '',
        'port'        => $c['smtp_port'] ?? 587,
        'encryption'  => $c['smtp_encryption'] ?? 'tls',
        'usuario'     => $c['smtp_usuario'] ?? '',
        'password'    => $c['smtp_password'] ?? '',
        'from_email'  => $c['smtp_from_email'] ?? '',
        'from_nombre' => ($c['smtp_from_nombre'] ?? '') !== '' ? $c['smtp_from_nombre'] : nombre_portal(),
    ];
}

/* ---------------------------------------------------------------------
 * Composición y envío
 * ------------------------------------------------------------------- */

/**
 * Arma el correo final a partir de la plantilla activa o, si no hay, del
 * contenido predeterminado. Función pura: no consulta la BD ni envía nada.
 *
 * $predeterminado: ['asunto' => ..., 'preheader' => ..., 'html' => ...] con {{variables}}.
 * Devuelve asunto, html, remitente_nombre, remitente_correo y origen ('plantilla'|'predeterminado'),
 * o null si no hay de dónde sacar el contenido.
 */
function correo_componer(?array $plantilla, array $datos, ?array $predeterminado, bool $envioReal = true): ?array
{
    if ($plantilla) {
        $fuente = ['asunto' => $plantilla['asunto'], 'preheader' => $plantilla['preheader'] ?? '', 'html' => $plantilla['contenido_html'] ?? ''];
        $origen = 'plantilla';
    } elseif ($predeterminado) {
        $fuente = $predeterminado + ['preheader' => ''];
        $origen = 'predeterminado';
    } else {
        return null;
    }

    // El asunto es texto plano: se decodifican las entidades de los valores escapados.
    $asunto = html_entity_decode(correo_render((string) $fuente['asunto'], $datos, $envioReal), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $html = correo_render((string) $fuente['html'], $datos, $envioReal);
    $html = correo_insertar_preheader($html, correo_render((string) $fuente['preheader'], $datos, $envioReal));

    return [
        'asunto'           => $asunto,
        'html'             => $html,
        'remitente_nombre' => trim((string) ($plantilla['remitente_nombre'] ?? '')),
        'remitente_correo' => trim((string) ($plantilla['remitente_correo'] ?? '')),
        'origen'           => $origen,
    ];
}

/** Envía un HTML con la configuración SMTP; el remitente de la plantilla, si lo hay, sustituye al global. */
function correo_enviar_html(string $para, string $asunto, string $html, string $remitenteNombre = '', string $remitenteCorreo = ''): array
{
    $cfg = correo_config_smtp();
    if (!$cfg['habilitado']) {
        return [false, 'El envío por SMTP está desactivado (Configuración › Correo).'];
    }
    if ($remitenteNombre !== '') {
        $cfg['from_nombre'] = $remitenteNombre;
    }
    if ($remitenteCorreo !== '') {
        $cfg['from_email'] = $remitenteCorreo;
    }
    return smtp_enviar($cfg, $para, $asunto, $html);
}

/**
 * Punto único de envío de los correos automáticos.
 * $datos: [variable => valor] ya escapados; se completan con las variables del
 * portal y los valores fijos de las variables personalizadas, sin pisar los del código.
 * Devuelve [bool, mensaje].
 */
function correo_enviar_plantilla(string $codigo, string $para, array $datos): array
{
    $datos += correo_datos_base() + correo_valores_personalizados();
    $correo = correo_componer(correo_plantilla_activa($codigo), $datos, correo_codigos_sistema()[$codigo] ?? null);
    if ($correo === null) {
        return [false, "No hay una plantilla activa con el código {$codigo} ni un correo predeterminado."];
    }
    return correo_enviar_html($para, $correo['asunto'], $correo['html'], $correo['remitente_nombre'], $correo['remitente_correo']);
}

/* ---------------------------------------------------------------------
 * Correos automáticos
 * ------------------------------------------------------------------- */

/** BIENVENIDA: al crear una cuenta. $usuario: nombre, email y, opcional, plan. */
function correo_bienvenida(array $usuario): array
{
    $nombreCompleto = trim((string) $usuario['nombre']);
    return correo_enviar_plantilla('BIENVENIDA', (string) $usuario['email'], [
        'nombre'          => e((string) (preg_split('/\s+/', $nombreCompleto)[0] ?? $nombreCompleto)),
        'nombre_completo' => e($nombreCompleto),
        'correo'          => e((string) $usuario['email']),
        'plan'            => e((string) ($usuario['plan'] ?? 'Gratis')),
        'url_login'       => e(correo_url('login.php')),
    ]);
}

/** RECUPERAR_CONTRASENA: liga de un solo uso para elegir una contraseña nueva. */
function correo_recuperar_contrasena(array $usuario, string $urlRecuperar, int $minutos): array
{
    $nombreCompleto = trim((string) $usuario['nombre']);
    return correo_enviar_plantilla('RECUPERAR_CONTRASENA', (string) $usuario['email'], [
        'nombre'           => e((string) (preg_split('/\s+/', $nombreCompleto)[0] ?? $nombreCompleto)),
        'nombre_completo'  => e($nombreCompleto),
        'correo'           => e((string) $usuario['email']),
        'url_recuperar'    => e($urlRecuperar),
        'minutos_vigencia' => (string) $minutos,
    ]);
}

/** VERIFICAR_CORREO: liga de un solo uso para confirmar el correo de la cuenta. */
function correo_verificar_correo(array $usuario, string $urlVerificar, int $horas): array
{
    $nombreCompleto = trim((string) $usuario['nombre']);
    return correo_enviar_plantilla('VERIFICAR_CORREO', (string) $usuario['email'], [
        'nombre'          => e((string) (preg_split('/\s+/', $nombreCompleto)[0] ?? $nombreCompleto)),
        'nombre_completo' => e($nombreCompleto),
        'correo'          => e((string) $usuario['email']),
        'url_verificar'   => e($urlVerificar),
        'horas_vigencia'  => (string) $horas,
    ]);
}
