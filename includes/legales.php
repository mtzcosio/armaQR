<?php
/**
 * DOCUMENTOS LEGALES (Términos y condiciones, Aviso de privacidad)
 * --------------------------------------------------------------------
 * - Cada documento tiene versiones: publicar crea una versión nueva y las anteriores
 *   quedan en el historial (se puede saber qué decía en cualquier fecha y restaurarla).
 * - Hay como máximo un borrador por documento; el público solo ve lo publicado.
 * - El HTML se limpia con una lista de etiquetas permitidas al guardar y al mostrar.
 * - {{variables}} toman los datos de Configuración, para que no se desactualicen.
 */

declare(strict_types=1);

require_once __DIR__ . '/datos.php';

const LEGAL_TIPOS = [
    'terminos'   => ['titulo' => 'Términos y condiciones', 'ruta' => 'terminos.php'],
    'privacidad' => ['titulo' => 'Aviso de privacidad', 'ruta' => 'privacidad.php'],
];

/** Variables disponibles: nombre => descripción. */
const LEGAL_VARIABLES = [
    'nombre_portal'   => 'Nombre del portal (Configuración › Identidad)',
    'dominio'         => 'Dominio del portal (Configuración › Identidad)',
    'correo_contacto' => 'Correo de contacto (Configuración › Contacto)',
    'direccion'       => 'Dirección (Configuración › Contacto)',
];

/* ---------------------------------------------------------------------
 * Limpieza del HTML
 * ------------------------------------------------------------------- */

/** Etiquetas permitidas => atributos permitidos. */
const LEGAL_ETIQUETAS = [
    'p' => [], 'br' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [],
    'ul' => [], 'ol' => [], 'li' => [], 'blockquote' => [], 'a' => ['href'],
];
/** Se eliminan con todo su contenido. */
const LEGAL_PROHIBIDAS = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'select', 'textarea', 'svg', 'math', 'template', 'noscript', 'head', 'title', 'meta', 'link'];

/** Deja solo etiquetas y atributos seguros; las demás etiquetas se quitan conservando su texto. */
function legal_sanitizar(string $html): string
{
    if (trim($html) === '') {
        return '';
    }
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"><div id="raiz">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
    libxml_clear_errors();

    $limpiar = function (DOMNode $nodo) use (&$limpiar): void {
        foreach (iterator_to_array($nodo->childNodes) as $hijo) {
            if ($hijo instanceof DOMComment || $hijo instanceof DOMProcessingInstruction) {
                $nodo->removeChild($hijo);
                continue;
            }
            if (!$hijo instanceof DOMElement) {
                continue;
            }
            $etiqueta = strtolower($hijo->tagName);
            if (in_array($etiqueta, LEGAL_PROHIBIDAS, true)) {
                $nodo->removeChild($hijo);
                continue;
            }
            $limpiar($hijo);
            if (!isset(LEGAL_ETIQUETAS[$etiqueta])) {
                // Etiqueta no permitida: se sustituye por su contenido.
                while ($hijo->firstChild) {
                    $nodo->insertBefore($hijo->firstChild, $hijo);
                }
                $nodo->removeChild($hijo);
                continue;
            }
            foreach (iterator_to_array($hijo->attributes) as $atributo) {
                if (!in_array(strtolower($atributo->name), LEGAL_ETIQUETAS[$etiqueta], true)) {
                    $hijo->removeAttribute($atributo->name);
                }
            }
            if ($etiqueta === 'a') {
                $href = trim($hijo->getAttribute('href'));
                if (!preg_match('#^(https?://|mailto:|/|\{\{\s*[a-z_]+\s*\}\})#i', $href)) {
                    $hijo->removeAttribute('href');
                }
            }
        }
    };
    $raiz = $doc->getElementById('raiz');
    $limpiar($raiz);

    $salida = '';
    foreach ($raiz->childNodes as $hijo) {
        $salida .= $doc->saveHTML($hijo);
    }
    // saveHTML() codifica las llaves dentro de href ({{correo_contacto}} → %7B%7B…%7D%7D):
    // se restauran para que la variable se siga sustituyendo.
    $salida = (string) preg_replace('/%7B%7B\s*([a-z_]+)\s*%7D%7D/i', '{{$1}}', $salida);
    return trim($salida);
}

/** El documento cuenta como vacío si no tiene texto visible. */
function legal_vacio(string $html): bool
{
    return trim(str_replace("\u{00A0}", ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'))) === '';
}

/* ---------------------------------------------------------------------
 * Variables
 * ------------------------------------------------------------------- */

/** Valores actuales de las variables, ya escapados. */
function legal_valores(): array
{
    $config = configuracion_activa();
    $correo = trim((string) ($config['contacto_correo'] ?? ''));
    $direccion = trim((string) ($config['contacto_direccion'] ?? ''));
    return [
        'nombre_portal'   => e(nombre_portal()),
        'dominio'         => e(dominio_portal()),
        'correo_contacto' => e($correo !== '' ? $correo : 'hola@' . dominio_portal()),
        'direccion'       => $direccion !== '' ? nl2br(e($direccion), false) : 'el domicilio publicado en la sección de contacto del portal',
    ];
}

/** Sustituye {{variable}}; las desconocidas se dejan tal cual para que el administrador las vea. */
function legal_render(string $html, ?array $valores = null): string
{
    $valores ??= legal_valores();
    return (string) preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/i', fn($m) => $valores[strtolower($m[1])] ?? $m[0], $html);
}

/* ---------------------------------------------------------------------
 * Consultas
 * ------------------------------------------------------------------- */

function legal_tabla_lista(): bool
{
    try {
        datos_pdo()->query('SELECT 1 FROM documentos_legales LIMIT 1');
        return true;
    } catch (Throwable $exception) {
        return false;
    }
}

/** Versión publicada vigente (la más reciente), o null. */
function legal_publicado(string $tipo): ?array
{
    return datos_consulta(
        "SELECT d.*, u.nombre AS autor FROM documentos_legales d LEFT JOIN usuarios u ON u.id = d.autor_id
         WHERE d.tipo = :t AND d.estatus = 'publicado' ORDER BY d.version DESC LIMIT 1",
        [':t' => $tipo]
    )[0] ?? null;
}

/** Una versión publicada concreta, o null. */
function legal_version(string $tipo, int $version): ?array
{
    return datos_consulta(
        "SELECT d.*, u.nombre AS autor FROM documentos_legales d LEFT JOIN usuarios u ON u.id = d.autor_id
         WHERE d.tipo = :t AND d.estatus = 'publicado' AND d.version = :v LIMIT 1",
        [':t' => $tipo, ':v' => $version]
    )[0] ?? null;
}

function legal_borrador(string $tipo): ?array
{
    return datos_consulta(
        "SELECT d.*, u.nombre AS autor FROM documentos_legales d LEFT JOIN usuarios u ON u.id = d.autor_id
         WHERE d.tipo = :t AND d.estatus = 'borrador' LIMIT 1",
        [':t' => $tipo]
    )[0] ?? null;
}

function legal_historial(string $tipo): array
{
    return datos_consulta(
        "SELECT d.id, d.version, d.titulo, d.publicado_en, u.nombre AS autor FROM documentos_legales d LEFT JOIN usuarios u ON u.id = d.autor_id
         WHERE d.tipo = :t AND d.estatus = 'publicado' ORDER BY d.version DESC",
        [':t' => $tipo]
    );
}

/* ---------------------------------------------------------------------
 * Escritura
 * ------------------------------------------------------------------- */

/** Crea o reemplaza el borrador del documento. */
function legal_guardar_borrador(string $tipo, string $titulo, string $html, int $autorId): void
{
    $html = legal_sanitizar($html);
    $borrador = legal_borrador($tipo);
    if ($borrador) {
        datos_pdo()->prepare('UPDATE documentos_legales SET titulo = :ti, contenido_html = :c, autor_id = :a WHERE id = :id')
            ->execute([':ti' => $titulo, ':c' => $html, ':a' => $autorId, ':id' => $borrador['id']]);
    } else {
        datos_pdo()->prepare("INSERT INTO documentos_legales (tipo, version, titulo, contenido_html, estatus, autor_id) VALUES (:t, 0, :ti, :c, 'borrador', :a)")
            ->execute([':t' => $tipo, ':ti' => $titulo, ':c' => $html, ':a' => $autorId]);
    }
}

/** Publica una versión nueva (la siguiente), descarta el borrador y devuelve el número de versión. */
function legal_publicar(string $tipo, string $titulo, string $html, int $autorId): int
{
    $pdo = datos_pdo();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT COALESCE(MAX(version), 0) + 1 FROM documentos_legales WHERE tipo = :t AND estatus = 'publicado' FOR UPDATE");
        $stmt->execute([':t' => $tipo]);
        $version = (int) $stmt->fetchColumn();
        $pdo->prepare("INSERT INTO documentos_legales (tipo, version, titulo, contenido_html, estatus, autor_id, publicado_en) VALUES (:t, :v, :ti, :c, 'publicado', :a, NOW())")
            ->execute([':t' => $tipo, ':v' => $version, ':ti' => $titulo, ':c' => legal_sanitizar($html), ':a' => $autorId]);
        $pdo->prepare("DELETE FROM documentos_legales WHERE tipo = :t AND estatus = 'borrador'")->execute([':t' => $tipo]);
        $pdo->commit();
        return $version;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function legal_descartar_borrador(string $tipo): void
{
    datos_pdo()->prepare("DELETE FROM documentos_legales WHERE tipo = :t AND estatus = 'borrador'")->execute([':t' => $tipo]);
}
