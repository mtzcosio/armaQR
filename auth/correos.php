<?php

declare(strict_types=1);

/**
 * Acciones del módulo de plantillas de correo (todas por POST con CSRF):
 * guardar, alternar, duplicar, eliminar, probar, variable_guardar y variable_eliminar.
 */

require_once dirname(__DIR__) . '/includes/seguridad.php';
require_once dirname(__DIR__) . '/includes/correo.php';
require_once dirname(__DIR__) . '/app/Core/Auth.php';

use App\Core\Auth;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('admin/correos.php');
}

$admin = Auth::requireAdmin();

if (!csrf_valido($_POST['csrf_token'] ?? null)) {
    flash('error', 'La sesión del formulario expiró. Vuelve a intentarlo.');
    redirigir('admin/correos.php');
}

if (!correo_tablas_listas()) {
    flash('error', 'Faltan las tablas del módulo. Ejecuta: php database/migrate_plantillas_correo.php');
    redirigir('admin/correos.php');
}

$pdo = datos_pdo();
$accion = (string) ($_POST['accion'] ?? '');
$id = (int) ($_POST['id'] ?? 0);
$texto = fn(string $campo, int $max = 0): string => $max > 0
    ? mb_substr(trim((string) ($_POST[$campo] ?? '')), 0, $max)
    : trim((string) ($_POST[$campo] ?? ''));

/** Plantilla por id o null. */
$plantilla = function (int $id): ?array {
    return $id > 0 ? (datos_consulta('SELECT * FROM plantillas_correo WHERE id = :id', [':id' => $id])[0] ?? null) : null;
};

/** Vuelve al editor conservando lo capturado (no se pierde lo que no se guardó). */
$volverAlEditor = function (int $id, array $formulario, array $errores = []): never {
    $_SESSION['correo_formulario'] = ['id' => $id, 'campos' => $formulario, 'errores' => $errores];
    redirigir('admin/correo.php' . ($id > 0 ? '?id=' . $id : ''));
};

switch ($accion) {

    /* ------------------------------------------------------------------ */
    case 'guardar':
        $formulario = [
            'nombre'           => $texto('nombre', 150),
            'codigo'           => correo_codigo_desde($texto('codigo') !== '' ? $texto('codigo') : $texto('nombre')),
            'tipo'             => in_array($texto('tipo'), CORREO_TIPOS, true) ? $texto('tipo') : 'Personalizado',
            'remitente_nombre' => $texto('remitente_nombre', 150),
            'remitente_correo' => $texto('remitente_correo', 190),
            'asunto'           => $texto('asunto', 255),
            'preheader'        => $texto('preheader', 255),
            'contenido_html'   => (string) ($_POST['contenido_html'] ?? ''),
        ];
        $activar = ($_POST['guardar'] ?? '') === 'activar';
        $existente = $plantilla($id);
        if ($id > 0 && !$existente) {
            flash('error', 'La plantilla ya no existe.');
            redirigir('admin/correos.php');
        }

        $errores = [];
        if ($formulario['nombre'] === '') {
            $errores[] = 'Escribe un nombre para la plantilla.';
        }
        if ($formulario['codigo'] === '') {
            $errores[] = 'El código debe tener letras o números.';
        } elseif (datos_consulta('SELECT COUNT(*) AS n FROM plantillas_correo WHERE codigo = :c AND id <> :id',
            [':c' => $formulario['codigo'], ':id' => $id])[0]['n'] > 0) {
            $errores[] = "Ya existe otra plantilla con el código {$formulario['codigo']}.";
        }
        if ($formulario['remitente_correo'] !== '' && !filter_var($formulario['remitente_correo'], FILTER_VALIDATE_EMAIL)) {
            $errores[] = 'El correo del remitente no es válido.';
        }
        if ($activar && $formulario['asunto'] === '') {
            $errores[] = 'Para activar la plantilla escribe un asunto.';
        }
        if ($activar && correo_contenido_vacio($formulario['contenido_html'])) {
            $errores[] = 'Para activar la plantilla escribe el contenido del correo.';
        }
        $todo = $formulario['asunto'] . ' ' . $formulario['preheader'] . ' ' . $formulario['contenido_html'];
        foreach (correo_variables_desconocidas($todo) as $variable) {
            $errores[] = 'La variable {{' . $variable . '}} no está en el catálogo. Créala en Variables o corrígela.';
        }
        if ($errores) {
            $volverAlEditor($id, $formulario, $errores);
        }

        $valores = $formulario + ['estatus' => $activar ? 'activa' : 'borrador', 'usuario' => (int) $admin['id']];
        $parametros = array_combine(array_map(fn($k) => ':' . $k, array_keys($valores)), array_values($valores));
        if ($existente) {
            $pdo->prepare(
                'UPDATE plantillas_correo SET nombre = :nombre, codigo = :codigo, tipo = :tipo, estatus = :estatus,
                        remitente_nombre = :remitente_nombre, remitente_correo = :remitente_correo, asunto = :asunto,
                        preheader = :preheader, contenido_html = :contenido_html, usuario_modificacion = :usuario
                 WHERE id = :id'
            )->execute($parametros + [':id' => $id]);
        } else {
            $pdo->prepare(
                'INSERT INTO plantillas_correo (nombre, codigo, tipo, estatus, remitente_nombre, remitente_correo,
                                                asunto, preheader, contenido_html, usuario_modificacion)
                 VALUES (:nombre, :codigo, :tipo, :estatus, :remitente_nombre, :remitente_correo,
                         :asunto, :preheader, :contenido_html, :usuario)'
            )->execute($parametros);
            $id = (int) $pdo->lastInsertId();
        }
        unset($_SESSION['correo_formulario']);
        flash('ok', $activar ? 'Plantilla guardada y activa: el sitio ya la usa.' : 'Borrador guardado. El sitio no lo usa hasta que lo actives.');
        redirigir('admin/correo.php?id=' . $id);

    /* ------------------------------------------------------------------ */
    case 'alternar':
        $p = $plantilla($id) ?? redirigir('admin/correos.php');
        if ($p['estatus'] === 'activa') {
            $pdo->prepare("UPDATE plantillas_correo SET estatus = 'inactiva' WHERE id = :id")->execute([':id' => $id]);
            flash('ok', "«{$p['nombre']}» quedó inactiva" . (isset(correo_codigos_sistema()[$p['codigo']]) ? '; el sitio vuelve a usar el correo predeterminado.' : '.'));
        } elseif (trim($p['asunto']) === '' || correo_contenido_vacio((string) $p['contenido_html'])) {
            flash('error', "«{$p['nombre']}» no tiene asunto o contenido. Complétala en el editor antes de activarla.");
        } else {
            $pdo->prepare("UPDATE plantillas_correo SET estatus = 'activa' WHERE id = :id")->execute([':id' => $id]);
            flash('ok', "«{$p['nombre']}» está activa.");
        }
        redirigir('admin/correos.php');

    /* ------------------------------------------------------------------ */
    case 'duplicar':
        $p = $plantilla($id) ?? redirigir('admin/correos.php');
        $codigo = substr($p['codigo'], 0, 90) . '_COPIA';
        while (datos_consulta('SELECT COUNT(*) AS n FROM plantillas_correo WHERE codigo = :c', [':c' => $codigo])[0]['n'] > 0) {
            $codigo = substr($p['codigo'], 0, 84) . '_COPIA_' . strtoupper(bin2hex(random_bytes(2)));
        }
        $pdo->prepare(
            "INSERT INTO plantillas_correo (nombre, codigo, tipo, estatus, remitente_nombre, remitente_correo,
                                            asunto, preheader, contenido_html, usuario_modificacion)
             VALUES (:nombre, :codigo, :tipo, 'borrador', :rn, :rc, :asunto, :preheader, :html, :usuario)"
        )->execute([
            ':nombre' => mb_substr($p['nombre'] . ' (copia)', 0, 150), ':codigo' => $codigo, ':tipo' => $p['tipo'],
            ':rn' => $p['remitente_nombre'], ':rc' => $p['remitente_correo'], ':asunto' => $p['asunto'],
            ':preheader' => $p['preheader'], ':html' => $p['contenido_html'], ':usuario' => (int) $admin['id'],
        ]);
        flash('ok', "Copia creada como borrador con el código {$codigo}.");
        redirigir('admin/correo.php?id=' . (int) $pdo->lastInsertId());

    /* ------------------------------------------------------------------ */
    case 'eliminar':
        $p = $plantilla($id) ?? redirigir('admin/correos.php');
        $pdo->prepare('DELETE FROM plantillas_correo WHERE id = :id')->execute([':id' => $id]);
        flash('ok', "Eliminaste «{$p['nombre']}»" . (isset(correo_codigos_sistema()[$p['codigo']]) ? '. El sitio vuelve a usar el correo predeterminado.' : '.'));
        redirigir('admin/correos.php');

    /* ------------------------------------------------------------------ */
    case 'probar':
        $destino = $texto('correo_prueba');
        $desdeEditor = ($_POST['origen'] ?? '') === 'editor';
        if ($desdeEditor) {
            // Se prueba lo que está en pantalla, aunque no se haya guardado.
            $fuente = [
                'asunto' => $texto('asunto', 255), 'preheader' => $texto('preheader', 255),
                'contenido_html' => (string) ($_POST['contenido_html'] ?? ''),
                'remitente_nombre' => $texto('remitente_nombre', 150), 'remitente_correo' => $texto('remitente_correo', 190),
            ];
            $_SESSION['correo_formulario'] = ['id' => $id, 'campos' => $fuente + [
                'nombre' => $texto('nombre', 150), 'codigo' => $texto('codigo', 100), 'tipo' => $texto('tipo'),
            ], 'errores' => []];
            $volver = 'admin/correo.php' . ($id > 0 ? '?id=' . $id : '');
        } else {
            $fuente = $plantilla($id) ?? redirigir('admin/correos.php');
            $volver = 'admin/correos.php';
        }

        if (!filter_var($destino, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Escribe un correo válido para la prueba.');
            redirigir($volver);
        }
        if (correo_contenido_vacio((string) $fuente['contenido_html'])) {
            flash('error', 'La plantilla no tiene contenido que enviar.');
            redirigir($volver);
        }
        // Con datos de ejemplo; las variables sin dato quedan visibles para detectarlas.
        $correo = correo_componer($fuente, correo_datos_ejemplo(), null, false);
        [$ok, $mensaje] = correo_enviar_html($destino, '[PRUEBA] ' . $correo['asunto'], $correo['html'],
            $correo['remitente_nombre'], $correo['remitente_correo']);
        flash($ok ? 'ok' : 'error', $ok ? "Prueba enviada a {$destino}." : 'La prueba no se envió: ' . $mensaje);
        redirigir($volver);

    /* ------------------------------------------------------------------ */
    case 'variable_guardar':
        $nombre = correo_nombre_variable_desde($texto('nombre'));
        $campos = [
            'categoria'     => $texto('categoria', 100),
            'descripcion'   => $texto('descripcion', 255),
            'valor_ejemplo' => $texto('valor_ejemplo', 255),
            'valor'         => $texto('valor', 500),
        ];
        $error = null;
        if ($nombre === '') {
            $error = 'El nombre de la variable debe tener letras o números.';
        } elseif ($campos['categoria'] === '') {
            $error = 'Elige o escribe una categoría.';
        } elseif (in_array($nombre, correo_variables_integradas(), true)) {
            $error = "{{{$nombre}}} es una variable integrada del sistema; usa otro nombre.";
        } elseif (datos_consulta('SELECT COUNT(*) AS n FROM variables_correo WHERE nombre = :n AND id <> :id',
            [':n' => $nombre, ':id' => $id])[0]['n'] > 0) {
            $error = "Ya existe la variable {{{$nombre}}}.";
        }
        if ($error) {
            flash('error', $error);
            redirigir('admin/correo-variables.php');
        }

        $parametros = [':nombre' => $nombre, ':categoria' => $campos['categoria'], ':descripcion' => $campos['descripcion'],
                       ':ejemplo' => $campos['valor_ejemplo'], ':valor' => $campos['valor']];
        if ($id > 0) {
            $anterior = datos_consulta('SELECT nombre FROM variables_correo WHERE id = :id', [':id' => $id])[0]['nombre'] ?? null;
            $pdo->prepare('UPDATE variables_correo SET nombre = :nombre, categoria = :categoria, descripcion = :descripcion,
                                  valor_ejemplo = :ejemplo, valor = :valor WHERE id = :id')->execute($parametros + [':id' => $id]);
            $aviso = $anterior !== null && $anterior !== $nombre
                ? " Cambiaste el nombre: las plantillas que usen {{{$anterior}}} deben actualizarse."
                : '';
            flash('ok', "Variable {{{$nombre}}} actualizada.{$aviso}");
        } else {
            $pdo->prepare('INSERT INTO variables_correo (nombre, categoria, descripcion, valor_ejemplo, valor)
                           VALUES (:nombre, :categoria, :descripcion, :ejemplo, :valor)')->execute($parametros);
            flash('ok', "Variable {{{$nombre}}} creada.");
        }
        redirigir('admin/correo-variables.php');

    /* ------------------------------------------------------------------ */
    case 'variable_eliminar':
        $nombre = datos_consulta('SELECT nombre FROM variables_correo WHERE id = :id', [':id' => $id])[0]['nombre'] ?? null;
        if ($nombre !== null) {
            $pdo->prepare('DELETE FROM variables_correo WHERE id = :id')->execute([':id' => $id]);
            flash('ok', "Variable {{{$nombre}}} eliminada.");
        }
        redirigir('admin/correo-variables.php');

    default:
        redirigir('admin/correos.php');
}
