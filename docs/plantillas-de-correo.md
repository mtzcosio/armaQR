# Módulo de plantillas de correo administrables

Guía para replicar en otro proyecto PHP el sistema de plantillas de correo de Monse Party Shop. Con este sistema, un administrador edita desde un panel el asunto y el HTML de los correos automáticos, sin tocar código. El código sigue decidiendo **cuándo** se envía cada correo y **con qué datos**.

Está escrito para PHP procedural con PDO y MySQL, sin frameworks. Funciona igual en cualquier stack; solo cambian las piezas de infraestructura de la sección 2.

---

## 1. Idea general

```
Evento del sitio (pago confirmado, pedido pendiente, reset de contraseña…)
        │
        ▼
send_<algo>_email($datos)                ← función específica de cada correo
   ├─ arma $variables  (nombre => valor)
   ├─ arma $fallback_html (HTML por defecto, en código)
   └─ send_templated_email('CODIGO', $to, $variables, $fallback_subject, $fallback_html)
                │
                ├─ ¿hay plantilla con code='CODIGO' y status='active'?
                │      sí → renderiza {{variables}} en subject y content_html → envía
                │      no → envía el fallback (asunto + HTML del código)
                ▼
          send_html_email($to, $subject, $html)   ← SMTP o mail()
```

Reglas de diseño:

1. **Un solo punto de envío** (`send_templated_email()`). Ningún correo automático arma su HTML y llama al mailer por su cuenta.
2. **El código de la plantilla es el contrato** entre el código y el panel. El sitio busca, por ejemplo, `COMPRA_LISTA`. El admin solo controla el contenido de la plantilla que lleva ese código.
3. **Siempre hay un respaldo.** Cada correo automático tiene un HTML por defecto en código, así que el correo sale aunque nadie haya configurado nada en el panel.
4. **Solo cuentan las plantillas `active`.** Los borradores e inactivas se ignoran y se usa el respaldo.
5. **Hay un catálogo único de variables.** De él salen el panel lateral del editor, la validación al guardar, la alerta en vivo de variables desconocidas y los datos de ejemplo de la vista previa y del correo de prueba.

---

## 2. Requisitos de infraestructura (adaptar al proyecto destino)

El módulo asume que ya existen estas funciones. Si el proyecto destino no las tiene, hay que crearlas o mapearlas a sus equivalentes.

| Función | Qué debe hacer |
|---|---|
| `get_db()` | Devuelve un PDO (singleton) con `ERRMODE_EXCEPTION` y `FETCH_ASSOC`. |
| `send_html_email($to, $subject, $html)` | Envía un correo HTML y devuelve `['success' => bool, 'message' => string]`. Puede usar SMTP, `mail()`, PHPMailer, una API, etc. |
| `get_setting($key, $default)` | Lee la configuración (nombre de la tienda, correo de soporte, color, logo…). |
| `e($s)` | `htmlspecialchars($s, ENT_QUOTES, 'UTF-8')`. |
| `csrf_token()` / `csrf_check($t)` | Protección CSRF para los formularios del admin. |
| `flash_set($msg, $type)` / `redirect($url)` | Mensajes flash y redirección. |
| `slugify($s)` | Convierte texto a `palabras-con-guiones` sin acentos. |
| `require_admin_login()` | Protege las páginas del panel. |

---

## 3. Base de datos

```sql
CREATE TABLE email_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,                       -- nombre legible, solo para el admin
    code VARCHAR(100) NOT NULL UNIQUE,                -- contrato con el código: 'COMPRA_LISTA'
    type VARCHAR(50) NOT NULL DEFAULT 'Personalizado',-- solo organización/filtro
    status ENUM('draft','active','inactive') NOT NULL DEFAULT 'draft',
    sender_name VARCHAR(150) DEFAULT '',
    sender_email VARCHAR(150) DEFAULT '',
    subject VARCHAR(255) NOT NULL DEFAULT '',
    preheader VARCHAR(255) DEFAULT '',
    content_html LONGTEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE email_custom_variables (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(100) NOT NULL,                   -- texto libre; se agrupa en el panel
    name VARCHAR(100) NOT NULL UNIQUE,                -- sin llaves: 'codigo_descuento'
    description VARCHAR(255) DEFAULT '',
    sample_value VARCHAR(255) DEFAULT '',             -- valor usado en vista previa / prueba
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Estados:

- `draft` (borrador): se guarda, pero el sitio no la usa.
- `active` (activa): el sitio la usa para su código.
- `inactive` (inactiva): estuvo publicada y se apagó. El sitio vuelve a usar el respaldo.

---

## 4. Núcleo: `email_variables.php`

Este archivo debe incluirse en el **bootstrap común del sitio**, no solo en el admin, porque los envíos automáticos de la parte pública también lo necesitan.

```php
<?php
/**
 * Catálogo de variables para las plantillas de correo. Es la única fuente de
 * verdad para el panel del editor, la validación y los datos de ejemplo.
 *  - Integradas: fijas aquí.
 *  - Personalizadas: tabla email_custom_variables, combinadas aquí.
 */

/** Variables integradas, agrupadas por categoría: nombre => descripción. */
function email_builtin_variable_catalog() {
    return [
        'Datos del destinatario' => [
            'nombre'          => 'Primer nombre del destinatario.',
            'nombre_completo' => 'Nombre completo del destinatario.',
            'correo'          => 'Correo electrónico del destinatario.',
        ],
        'Información del sistema' => [
            'nombre_empresa' => 'Nombre de la empresa.',
            'correo_soporte' => 'Correo de soporte.',
            'fecha_actual'   => 'Fecha en la que se envía el correo.',
        ],
        'Información del pedido' => [
            'codigo_pedido' => 'Código del pedido.',
            'total_pedido'  => 'Total del pedido, ya formateado.',
            'url_pedido'    => 'Enlace al detalle del pedido.',
        ],
        // … una categoría por cada tipo de correo del proyecto
    ];
}

function email_custom_variable_rows() {
    static $rows = null;
    if ($rows === null) {
        $rows = get_db()->query('SELECT * FROM email_custom_variables ORDER BY category ASC, name ASC')->fetchAll();
    }
    return $rows;
}

/**
 * Catálogo combinado. Si una variable personalizada usa una categoría que ya
 * existe (sin distinguir mayúsculas, acentos ni espacios), se agrega dentro de
 * esa sección en vez de duplicarla.
 */
function email_variable_catalog() {
    $catalog = email_builtin_variable_catalog();
    $category_keys = [];
    foreach (array_keys($catalog) as $cat) {
        $category_keys[email_normalize_category_key($cat)] = $cat;
    }
    foreach (email_custom_variable_rows() as $row) {
        $norm = email_normalize_category_key($row['category']);
        if (!isset($category_keys[$norm])) {
            $category_keys[$norm] = $row['category'];
            $catalog[$row['category']] = [];
        }
        $catalog[$category_keys[$norm]][$row['name']] = $row['description'] !== '' ? $row['description'] : 'Variable personalizada.';
    }
    return $catalog;
}

function email_normalize_category_key($category) {
    $key = mb_strtolower(trim((string)$category), 'UTF-8');
    $key = strtr($key, ['á'=>'a','à'=>'a','ä'=>'a','â'=>'a','é'=>'e','è'=>'e','ë'=>'e','ê'=>'e',
                        'í'=>'i','ì'=>'i','ï'=>'i','î'=>'i','ó'=>'o','ò'=>'o','ö'=>'o','ô'=>'o',
                        'ú'=>'u','ù'=>'u','ü'=>'u','û'=>'u','ñ'=>'n']);
    return preg_replace('/\s+/', ' ', $key);
}

/** Lista plana de nombres válidos (sin llaves). */
function email_variable_names() {
    $names = [];
    foreach (email_variable_catalog() as $vars) {
        $names = array_merge($names, array_keys($vars));
    }
    return $names;
}

function email_builtin_variable_names() {
    $names = [];
    foreach (email_builtin_variable_catalog() as $vars) {
        $names = array_merge($names, array_keys($vars));
    }
    return $names;
}

/** true si $name ya existe (integrada o personalizada); $exclude_id para edición. */
function email_custom_variable_name_taken($name, $exclude_id = null) {
    $name = strtolower(trim($name));
    if (in_array($name, email_builtin_variable_names(), true)) {
        return true;
    }
    $sql = 'SELECT COUNT(*) FROM email_custom_variables WHERE name = :name';
    $params = ['name' => $name];
    if ($exclude_id) {
        $sql .= ' AND id != :id';
        $params['id'] = $exclude_id;
    }
    $stmt = get_db()->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn() > 0;
}

/** Datos de ejemplo para la vista previa y el correo de prueba (uno por variable integrada). */
function email_variable_sample_data() {
    $data = [
        'nombre'          => 'Juan',
        'nombre_completo' => 'Juan Pérez',
        'correo'          => 'juan.perez@ejemplo.com',
        'nombre_empresa'  => get_setting('store_name', 'Mi Empresa'),
        'correo_soporte'  => get_setting('email', 'soporte@ejemplo.com'),
        'fecha_actual'    => date('d/m/Y'),
        'codigo_pedido'   => 'PED-DEMO123',
        'total_pedido'    => '$178.00 MXN',
        'url_pedido'      => 'https://ejemplo.com/pedido/demo',
    ];
    foreach (email_custom_variable_rows() as $row) {
        $data[$row['name']] = $row['sample_value'] !== '' ? $row['sample_value'] : '[' . $row['name'] . ']';
    }
    return $data;
}

/** Sustituye {{variable}} por su valor; deja intacta la que no tenga dato. */
function email_render_variables($text, $data) {
    return preg_replace_callback('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', function ($m) use ($data) {
        $key = strtolower($m[1]);
        return array_key_exists($key, $data) ? $data[$key] : $m[0];
    }, (string)$text);
}

/** Variables usadas en $text que NO existen en el catálogo. */
function email_find_unknown_variables($text) {
    if (!preg_match_all('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', (string)$text, $matches)) {
        return [];
    }
    $known = email_variable_names();
    $unknown = [];
    foreach ($matches[1] as $name) {
        $name = strtolower($name);
        if (!in_array($name, $known, true) && !in_array($name, $unknown, true)) {
            $unknown[] = $name;
        }
    }
    return $unknown;
}

function email_template_types() {
    return ['Confirmación', 'Pago', 'Recordatorio', 'Notificación', 'Seguridad', 'Personalizado'];
}

/** Plantilla activa por código, o null (borrador/inactiva/inexistente). Cacheada por request. */
function email_active_template_by_code($code) {
    static $cache = [];
    if (!array_key_exists($code, $cache)) {
        $stmt = get_db()->prepare("SELECT * FROM email_templates WHERE code = :code AND status = 'active' LIMIT 1");
        $stmt->execute(['code' => $code]);
        $cache[$code] = $stmt->fetch() ?: null;
    }
    return $cache[$code];
}

/**
 * Punto único de envío de correos automáticos.
 *
 * @param string       $code             Código de la plantilla (ej. 'COMPRA_LISTA').
 * @param string       $to               Destinatario.
 * @param string|array $variables        Array asociativo o JSON ('{"nombre":"Ana"}').
 * @param string|null  $fallback_subject Asunto si no hay plantilla activa.
 * @param string|null  $fallback_html    HTML si no hay plantilla activa.
 * @return array ['success' => bool, 'message' => string]
 */
function send_templated_email($code, $to, $variables, $fallback_subject = null, $fallback_html = null) {
    if (is_string($variables)) {
        $data = json_decode($variables, true);
        if (!is_array($data)) {
            return ['success' => false, 'message' => 'Las variables enviadas no son un JSON válido.'];
        }
    } else {
        $data = (array)$variables;
    }

    $template = email_active_template_by_code($code);
    if ($template) {
        $subject = email_render_variables($template['subject'], $data);
        $html    = email_render_variables($template['content_html'], $data);
    } elseif ($fallback_html !== null) {
        $subject = (string)$fallback_subject;
        $html    = $fallback_html;
    } else {
        return ['success' => false, 'message' => "No existe una plantilla activa con el código \"{$code}\" y no se definió un contenido de respaldo."];
    }

    return send_html_email($to, $subject, $html);
}
```

### Escape de variables

`email_render_variables()` inserta los valores **tal cual, sin escapar**, porque algunas variables son bloques HTML a propósito (botones, listas con `<br>`). Por eso **cada función `_variable_data()` debe escapar con `e()` todo dato que venga del usuario** (nombre, correo, nombres de producto…) y dejar sin escapar solo los bloques HTML que arme ella misma.

---

## 5. Patrón para cada correo automático

Por cada correo que el sitio envía solo, se escriben tres funciones y se elige un código:

```php
/** 1) Datos: nombre_variable => valor (escapado si viene del usuario). */
function order_ready_email_variable_data($order, $items) {
    return [
        'nombre_completo' => e($order['customer_name']),
        'correo'          => e($order['customer_email']),
        'codigo_pedido'   => e($order['order_code']),
        'total_pedido'    => format_price($order['total']),
        'url_pedido'      => site_url('pedido.php?codigo=' . urlencode($order['order_code'])),
        'nombre_empresa'  => e(get_setting('store_name', 'Mi Empresa')),
        'correo_soporte'  => e(get_setting('email', '')),
        'fecha_actual'    => date('d/m/Y'),
        // bloque HTML armado aquí (sus partes de usuario, escapadas)
        'botones_descarga' => build_download_buttons_html($order, $items),
    ];
}

/** 2) HTML por defecto, usado si no hay plantilla activa con ese código. */
function email_default_order_ready_html($data) {
    return '<!DOCTYPE html><html><body style="margin:0;background:#f6f3f7;font-family:Arial,sans-serif;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">'
        . '<table role="presentation" width="600" style="max-width:600px;width:100%;background:#fff;">'
        . '<tr><td style="padding:30px;"><h1>¡Hola, ' . $data['nombre_completo'] . '!</h1>'
        . '<p>Tu pedido <strong>' . $data['codigo_pedido'] . '</strong> está listo.</p>'
        . $data['botones_descarga']
        . '</td></tr></table></td></tr></table></body></html>';
}

/** 3) Envío: delega en send_templated_email(). */
function send_order_confirmation_email($order, $items) {
    $data = order_ready_email_variable_data($order, $items);
    return send_templated_email(
        'COMPRA_LISTA',
        $order['customer_email'],
        $data,
        '🎉 ¡Tu compra está lista!',
        email_default_order_ready_html($data)
    );
}
```

Luego se llama a `send_order_confirmation_email()` desde donde ocurra el evento (checkout, webhook, etc.).

Consejos:

- Para un correo **manual** (por ejemplo, un botón "Enviar recordatorio" en el detalle de un pedido), se usa el mismo patrón con su propio código. El botón hace POST con CSRF a una acción que llama a `send_<algo>_email()`.
- Dos correos pueden **compartir** la función `_variable_data()` si usan las mismas variables (por ejemplo, "pago pendiente" y "recordatorio de pago").
- Conviene documentar en algún lado (README o CLAUDE.md) **qué códigos busca el sitio y qué variables llena cada uno**. El admin no tiene otra forma de saberlo.
- Usa HTML de correo "a la antigua": tablas, estilos inline, ancho máximo de 600 px y URLs absolutas.

---

## 6. Panel de administración

Todas las páginas requieren sesión de admin y todas las acciones que modifican datos son **POST con CSRF**.

### 6.1 `email_templates.php`: listado

- Filtros GET: `q` (busca en `name` y `code`), `tipo` (de `email_template_types()`) y `estado` (`draft|active|inactive`), más paginación.
- Columnas: nombre, código, tipo, estado (badge), fecha de actualización y acciones.
- Acciones por fila: Ver, Editar, Duplicar, Activar/Desactivar, Enviar prueba y Eliminar.

### 6.2 `email_template_form.php`: crear/editar (`?id=N` para editar)

Campos:

| Campo | Notas |
|---|---|
| Nombre | Obligatorio. |
| Código único | Si viene vacío, se genera desde el nombre: `strtoupper(str_replace('-', '_', slugify($code ?: $name)))`. Se valida que sea único (`code = :code AND id != :id`). |
| Tipo | `<select>` de `email_template_types()`. |
| Estado | Solo lectura. Lo cambian los botones de guardado. |
| Remitente (nombre/correo) | Solo lectura. Muestra lo que realmente usa `send_html_email()` (configuración global). |
| Asunto | Admite `{{variables}}`. |
| Preheader | Admite `{{variables}}` (ver la sección 8). |
| Contenido | Editor visual (Quill) con conmutador a HTML crudo. Se guarda en un `<input type="hidden" name="content_html">`. |

Dos botones *submit* distinguidos por un campo oculto `save_action`:

- **"Guardar borrador"** (`save_action=draft`) guarda con `status='draft'` sin exigir asunto ni contenido.
- **"Guardar plantilla"** (`save_action=publish`) guarda con `status='active'` y exige asunto y contenido. El contenido cuenta como vacío si `trim(strip_tags($html)) === ''` y además no tiene ninguna `<img`.

Validación del servidor, siempre:

```php
$unknown = array_unique(array_merge(
    email_find_unknown_variables($subject),
    email_find_unknown_variables($preheader),
    email_find_unknown_variables($content_html)
));
foreach ($unknown as $uv) {
    $errors[] = 'La variable {{' . $uv . '}} no está registrada en el catálogo de variables.';
}
```

Panel lateral de variables:

- Es un acordeón por categoría generado con `email_variable_catalog()`. Cada variable muestra `{{nombre}}`, su descripción y dos botones: **Copiar** e **Insertar**.
- **Insertar** escribe el token donde estaba el foco por última vez: en el asunto o el preheader (inserción en el cursor del `<input>`) o en el editor Quill (en su posición de cursor).
- Tiene un buscador que filtra por nombre y descripción, y abre las categorías que coinciden.
- Incluye un enlace a `email_variables.php`, en otra pestaña, para crear variables nuevas.

Botones extra del editor:

- **Insertar botón**: pega un `<p style="text-align:center"><a href="#" style="…">Texto del botón</a></p>`.
- **Insertar separador**: un `<hr>`. Quill no soporta `<hr>` de fábrica, así que hay que registrar un *BlockEmbed* propio (sección 7).
- **Imagen**: el botón de imagen de Quill se reemplaza por un selector de la biblioteca de medios del proyecto, si existe. Las imágenes deben quedar con **URL absoluta**.

### 6.3 Vista previa (en el cliente, sin guardar)

Al cargar la página se inyectan en JS el catálogo y los datos de ejemplo:

```php
<script>
var KNOWN_VARIABLES = <?= json_encode(email_variable_names()) ?>;
var SAMPLE_DATA     = <?= json_encode(email_variable_sample_data()) ?>;
</script>
```

La vista previa renderiza el contenido **actual** del formulario con `SAMPLE_DATA` y lo muestra en un `<iframe srcdoc>`, que aísla los estilos del correo de los del admin. Encima del iframe muestra De, Asunto y Preheader.

### 6.4 `email_template_send_test.php`: correo de prueba (POST)

- Recibe `test_email` (validado con `FILTER_VALIDATE_EMAIL`) y una de dos fuentes:
  - **Desde el editor**: campos `inline_subject` e `inline_content_html`, que permiten probar **lo que está en pantalla aunque no se haya guardado**. Un JS copia los valores actuales a esos campos ocultos antes de abrir el modal.
  - **Desde el listado**: `id`. Se lee la plantilla de la BD.
- Renderiza con `email_variable_sample_data()`, antepone `[PRUEBA] ` al asunto y envía con `send_html_email()`.
- Redirige de vuelta al editor o al listado, según de dónde vino, con un flash del resultado.

### 6.5 Otras acciones (POST + CSRF)

| Archivo | Lógica |
|---|---|
| `email_template_toggle.php` | Si está `active`, pasa a `inactive`; en cualquier otro caso, pasa a `active`. |
| `email_template_duplicate.php` | Copia todos los campos con `code = CODIGO_COPIA` (si ya existe, agrega un sufijo aleatorio `_COPIA_A1B2`) y `status='draft'`, y redirige al editor de la copia. |
| `email_template_delete.php` | `DELETE` por id. |
| `email_template_view.php` | Vista de solo lectura con el HTML en un iframe. |

### 6.6 `email_variables.php`: variables personalizadas

- Listado agrupado por categoría (mismo acordeón que el editor), con filtros `q` y `categoria`.
- Crear y editar en un modal: nombre, categoría (con autocompletado de las existentes, vía `<datalist>`), descripción y valor de ejemplo.
- El nombre se normaliza con `strtolower(str_replace('-', '_', slugify($input)))` y se rechaza si `email_custom_variable_name_taken()`, que también protege a las integradas.
- `email_variable_delete.php` borra la variable (POST + CSRF).

**Limitación importante:** una variable personalizada solo tiene valor en la vista previa y en el correo de prueba (su `sample_value`). En un envío real, solo se reemplaza si alguna función `_variable_data()` la llena. Si no, el destinatario ve `{{nombre_variable}}` literal. Hay que dejarlo claro en la interfaz, o bien hacer que `send_templated_email()` mezcle valores fijos para estas variables (ver la sección 8).

---

## 7. JavaScript del editor (fragmentos clave)

```js
function renderVariables(text) {
    return String(text || '').replace(/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/g, function (match, name) {
        var key = name.toLowerCase();
        return Object.prototype.hasOwnProperty.call(SAMPLE_DATA, key) ? SAMPLE_DATA[key] : match;
    });
}

function findUnknownVariables(text) {
    var found = {}, re = /\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/g, m;
    while ((m = re.exec(String(text || ''))) !== null) {
        var key = m[1].toLowerCase();
        if (KNOWN_VARIABLES.indexOf(key) === -1) { found[key] = true; }
    }
    return Object.keys(found);
}

// Separador <hr> para Quill (no viene de fábrica y lo descarta al pegar HTML)
var BlockEmbed = Quill.import('blots/block/embed');
class DividerBlot extends BlockEmbed {
    static create() {
        var node = super.create();
        node.setAttribute('style', 'border:none;border-top:1px solid #eeeeee;margin:20px 0;');
        return node;
    }
}
DividerBlot.blotName = 'divider';
DividerBlot.tagName = 'hr';
Quill.register(DividerBlot);

var quill = new Quill('#editor', {
    theme: 'snow',
    modules: { toolbar: {
        container: [[{ header: [2, 3, false] }], ['bold', 'italic', 'underline'],
                    [{ list: 'ordered' }, { list: 'bullet' }], ['link', 'image'], ['clean']],
        handlers: { image: function () { /* abrir selector de medios */ } }
    }}
});
quill.clipboard.dangerouslyPasteHTML(document.getElementById('content_html').value || '');

// Conmutador visual ⇄ HTML crudo
var showingHtml = false;
function getCurrentContentHtml() {
    return showingHtml ? htmlSource.value : quill.root.innerHTML;
}
toggleBtn.addEventListener('click', function () {
    if (!showingHtml) { htmlSource.value = quill.root.innerHTML; /* ocultar Quill, mostrar textarea */ }
    else { quill.clipboard.dangerouslyPasteHTML(htmlSource.value); /* al revés */ }
    showingHtml = !showingHtml;
});

// Posición del cursor: usar la que Quill guarda al perder el foco
function currentQuillRange() {
    return quill.getSelection(true) || lastQuillRange || { index: quill.getLength(), length: 0 };
}

// Antes de enviar el formulario
form.addEventListener('submit', function () {
    document.getElementById('content_html').value = getCurrentContentHtml();
});

// Alerta en vivo: muestra un banner si hay variables desconocidas
quill.on('text-change', checkUnknownVariables);
subject.addEventListener('input', checkUnknownVariables);

// Vista previa
previewFrame.srcdoc = renderVariables(getCurrentContentHtml());
```

Ojo: Quill reescribe el HTML a su propio modelo y puede descartar estilos o estructuras complejas (tablas). Si una plantilla necesita un HTML de correo con tablas, el admin debe editarla en **modo HTML crudo** y guardarla sin volver al editor visual.

---

## 8. Mejoras recomendadas al replicar

En el proyecto original se detectaron estos puntos débiles. Conviene resolverlos desde el inicio:

1. **Usar el preheader en el envío real.** En el original solo aparece en la vista previa. En `send_templated_email()`, si la plantilla tiene `preheader`, inyéctalo como texto oculto al inicio del `<body>`:
   ```php
   $pre = email_render_variables($template['preheader'] ?? '', $data);
   if ($pre !== '') {
       $hidden = '<div style="display:none;max-height:0;overflow:hidden;opacity:0;">' . e($pre) . '</div>';
       $html = preg_match('/<body[^>]*>/i', $html)
           ? preg_replace('/(<body[^>]*>)/i', '$1' . $hidden, $html, 1)
           : $hidden . $html;
   }
   ```
2. **Remitente por plantilla.** Las columnas `sender_name` y `sender_email` existen, pero el envío real usa siempre el remitente global. O se quitan, o se pasan a `send_html_email()`, que tendría que aceptar un `from` opcional.
3. **Avisar del borrador.** Muestra una alerta en el editor cuando la plantilla está en `draft` o `inactive` y su código corresponde a un correo automático, con un texto como "el sitio está usando el correo por defecto".
4. **Declarar qué variables llena cada código.** Por ejemplo, con un mapa `email_code_variables() = ['COMPRA_LISTA' => ['nombre', 'total_pedido', …]]`. Con él, el editor puede advertir cuando se usa una variable que ese correo no llena, y el panel puede listar los códigos "del sistema".
5. **Valores reales para variables personalizadas.** Una opción es añadir una columna `value` a `email_custom_variables` y que `send_templated_email()` haga `$data += custom_values()` antes de renderizar, sin pisar los valores que vienen del código.
6. **Proteger la eliminación** de plantillas cuyo código usa el sistema, o al menos confirmarla con una advertencia.

---

## 9. Checklist de implementación

- [ ] Infraestructura de la sección 2 disponible (`get_db`, `send_html_email`, `e`, CSRF, flash, `slugify`, auth).
- [ ] Tablas `email_templates` y `email_custom_variables` creadas.
- [ ] `email_variables.php` con el catálogo **adaptado al dominio** del nuevo proyecto, incluido en el bootstrap común.
- [ ] `email_variable_sample_data()` con un valor de ejemplo para **cada** variable integrada.
- [ ] Por cada correo automático: código elegido, `_variable_data()` con datos de usuario escapados, `email_default_*_html()` y `send_*_email()` que llama a `send_templated_email()`.
- [ ] Llamadas a `send_*_email()` en los puntos del flujo donde ocurre cada evento.
- [ ] Páginas del admin: listado, formulario (Quill + HTML + variables + vista previa), envío de prueba, activar/desactivar, duplicar, eliminar, ver y variables personalizadas.
- [ ] Todas las acciones del admin por POST con CSRF.
- [ ] Documentados los códigos de plantilla que usa el sistema y sus variables.
- [ ] Prueba completa: sin plantilla, sale el respaldo. Con la plantilla en borrador, sigue saliendo el respaldo. Con la plantilla activa, sale la plantilla con sus variables reemplazadas.

---

## 10. Implementación en este proyecto (Arma-QR)

El módulo vive en **Administración › Correos**. Nombres equivalentes a los de esta guía:

| Guía | Este proyecto |
|---|---|
| `email_templates` / `email_custom_variables` | `plantillas_correo` / `variables_correo` (`php database/migrate_plantillas_correo.php`) |
| estados `draft` / `active` / `inactive` | `borrador` / `activa` / `inactiva` |
| `email_variables.php` | `includes/correo.php` |
| `send_templated_email()` | `correo_enviar_plantilla($codigo, $para, $datos)` |
| `send_html_email()` | `correo_enviar_html()` → `smtp_enviar()` en `includes/smtp.php` |
| páginas del admin | `admin/correos.php`, `admin/correo.php`, `admin/correo-ver.php`, `admin/correo-variables.php` |
| acciones POST | `auth/correos.php` (`accion` = guardar, alternar, duplicar, eliminar, probar, variable_guardar, variable_eliminar) |
| JS del editor | `assets/js/correos.js` (Quill 2.0.2 desde cdnjs) |

Mejoras de la sección 8 ya incluidas: preheader en el envío real, remitente por plantilla,
mapa de variables por código (`correo_codigos_sistema()`), aviso de borrador en el editor,
valor real para variables personalizadas (columna `valor`) y advertencia al eliminar plantillas del sistema.
Además: el contenido predeterminado de cada código está escrito con `{{variables}}`, así que el
botón **Personalizar** precarga el editor con exactamente lo que se envía hoy; y en el envío real las
variables sin dato se quitan para que el destinatario nunca vea llaves sueltas.

### Correos automáticos

| Código | Cuándo | Variables que llena |
|---|---|---|
| `VERIFICAR_CORREO` | Al crear una cuenta (`auth/registro.php`), al pedir otra liga desde el panel del cliente o desde el admin (lógica en `includes/verificacion.php`) | nombre, nombre_completo, correo, url_verificar, horas_vigencia + las del portal |
| `BIENVENIDA` | Cuando el usuario confirma su correo (`auth/verificar.php`) | nombre, nombre_completo, correo, plan, url_login + las del portal |
| `RECUPERAR_CONTRASENA` | Al pedir una liga en `recuperar.php` o con el botón del admin en el detalle del usuario (`auth/recuperar.php`, lógica en `includes/recuperacion.php`) | nombre, nombre_completo, correo, url_recuperar, minutos_vigencia + las del portal |

Variables del portal, disponibles en todos los envíos: `nombre_portal`, `correo_soporte`, `url_portal`,
`color_acento`, `fecha_actual`.

La recuperación y la verificación comparten `includes/tokens.php` (tokens de un solo uso con hash, vencimiento según el reloj de la BD).

Para agregar un correo automático: declara el código en `correo_codigos_sistema()` (con sus variables y su
contenido predeterminado), escribe una función `correo_<algo>()` que arme los datos escapados con `e()` y
llame a `correo_enviar_plantilla()`, y llámala desde el evento. `tests/CorreoTest.php` verifica que el
predeterminado solo use variables que el código declara llenar.
