<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * Crea la tabla de documentos legales y publica la versión 1 de los Términos y
 * condiciones y del Aviso de privacidad si todavía no existen.
 * Es seguro ejecutarla varias veces: no reemplaza documentos ya publicados.
 *
 *   php database/migrate_documentos_legales.php
 *
 * El texto inicial es un punto de partida redactado a partir de cómo funciona el
 * portal; debe revisarlo un abogado antes de usarse en producción.
 */

require_once __DIR__ . '/Database.php';

$pdo = App\Core\Database::connection();
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS documentos_legales (
        id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        tipo               ENUM('terminos','privacidad') NOT NULL,
        version            INT UNSIGNED NOT NULL DEFAULT 0,      -- 0 en borradores
        titulo             VARCHAR(150) NOT NULL,
        contenido_html     MEDIUMTEXT   NOT NULL,
        estatus            ENUM('borrador','publicado') NOT NULL DEFAULT 'borrador',
        autor_id           INT UNSIGNED NULL,
        publicado_en       DATETIME     NULL,
        fecha_creacion     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        fecha_modificacion DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY ix_legales_tipo (tipo, estatus, version),
        CONSTRAINT fk_legales_autor FOREIGN KEY (autor_id) REFERENCES usuarios(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

$terminos = <<<'HTML'
<p>Estos Términos y condiciones regulan el uso de <strong>{{nombre_portal}}</strong> ({{dominio}}), un servicio en línea para crear, personalizar, descargar y medir códigos QR. Al crear una cuenta o usar el servicio aceptas estos términos. Si no estás de acuerdo, no uses el servicio.</p>
<h2>1. El servicio</h2>
<p>{{nombre_portal}} permite crear dos tipos de códigos QR:</p>
<ul>
<li><strong>QR estáticos:</strong> el contenido (una liga, una red WiFi, un contacto, un texto) va dentro del propio código. Funcionan sin depender de {{nombre_portal}} y no se pueden modificar después de crearlos.</li>
<li><strong>QR dinámicos:</strong> el código lleva a una liga corta de {{nombre_portal}} (por ejemplo, {{dominio}}/r/abc123) que redirige al destino que elijas. Puedes cambiar el destino sin reimprimir el código y consultar estadísticas de escaneo. Funcionan mientras tu cuenta y tu plan los incluyan.</li>
</ul>
<h2>2. Tu cuenta</h2>
<ul>
<li>Debes proporcionar un nombre y un correo electrónico válidos, y mantener tu contraseña en secreto. Eres responsable de lo que se haga con tu cuenta.</li>
<li>Te pediremos confirmar tu correo; lo usamos para avisos de pagos, seguridad y para ayudarte a recuperar tu acceso.</li>
<li>Debes tener al menos 18 años o contar con autorización de quien ejerza la patria potestad o tutela.</li>
</ul>
<h2>3. Planes, precios y pagos</h2>
<ul>
<li>Ofrecemos un plan <strong>Gratis</strong> y planes de pago (<strong>Pro</strong> y <strong>Negocio</strong>) con los límites y funciones descritos en la página de precios. Los precios están en pesos mexicanos e incluyen IVA, salvo que se indique otra cosa.</li>
<li>Los planes de pago se cobran por adelantado, de forma mensual o anual, y se renuevan automáticamente hasta que los canceles.</li>
<li>Los pagos los procesan pasarelas de pago autorizadas (como Stripe, Mercado Pago o Conekta). {{nombre_portal}} no almacena el número completo de tu tarjeta.</li>
<li>Al subir de plan pagas la diferencia proporcional del periodo; al bajar, el cambio aplica en tu siguiente cobro.</li>
<li>Si un cobro no se puede procesar, podremos suspender las funciones de pago de tu cuenta hasta que se regularice.</li>
<li>Emitimos factura (CFDI) con los datos fiscales que registres en tu cuenta.</li>
</ul>
<h2>4. Cancelación y qué pasa con tus QR</h2>
<ul>
<li>Puedes cancelar tu plan de pago cuando quieras desde "Plan y pagos". Conservarás las funciones de tu plan hasta el final del periodo pagado; no hacemos reembolsos por periodos parciales, salvo que la ley lo exija.</li>
<li>Al terminar el periodo, tus <strong>QR dinámicos se pausan</strong>: quien los escanee verá un aviso de que no están disponibles. Si vuelves a contratar un plan que los incluya, se reactivan con la misma liga.</li>
<li>Tus <strong>QR estáticos siguen funcionando</strong> siempre, porque su contenido va dentro del código.</li>
<li>Si eliminas un QR dinámico, su liga corta deja de funcionar de forma permanente.</li>
</ul>
<h2>5. Uso aceptable</h2>
<p>No puedes usar {{nombre_portal}} para crear o redirigir a contenido que:</p>
<ul>
<li>sea fraudulento o engañoso, o busque obtener contraseñas o datos bancarios (phishing);</li>
<li>distribuya virus, malware o cualquier software dañino;</li>
<li>sea ilegal, infrinja derechos de terceros (marcas, derechos de autor, privacidad) o promueva violencia, odio o explotación;</li>
<li>suplante la identidad de otra persona o empresa.</li>
</ul>
<p>Podemos <strong>pausar o eliminar</strong> cualquier QR, y suspender la cuenta relacionada, si detectamos o nos reportan un uso que incumpla estos términos. Si es posible, te avisaremos del motivo.</p>
<h2>6. Tu contenido</h2>
<p>Eres el responsable del contenido, los destinos y los logos que uses en tus QR, y declaras tener derecho a usarlos. Nos das permiso para almacenarlos y mostrarlos únicamente para prestarte el servicio.</p>
<h2>7. Propiedad intelectual</h2>
<p>El software, el diseño y la marca de {{nombre_portal}} nos pertenecen. Los códigos QR que generes son tuyos y puedes usarlos libremente, incluso con fines comerciales. En el plan Gratis las descargas pueden incluir una pequeña marca de agua de {{nombre_portal}}.</p>
<h2>8. Disponibilidad y responsabilidad</h2>
<ul>
<li>Hacemos lo posible por mantener el servicio disponible, pero puede haber interrupciones por mantenimiento o causas ajenas a nosotros.</li>
<li>Antes de imprimir, prueba tus QR con varios celulares. No somos responsables por pérdidas derivadas de códigos impresos con errores, de destinos configurados incorrectamente o de la indisponibilidad de sitios de terceros.</li>
<li>Nuestra responsabilidad total frente a ti se limita a lo que hayas pagado por el servicio en los 12 meses anteriores al hecho que la origine.</li>
</ul>
<h2>9. Cambios a estos términos</h2>
<p>Podemos actualizar estos términos. Publicaremos la nueva versión en esta página con su fecha de vigencia y, si los cambios son importantes, te avisaremos por correo. Si sigues usando el servicio después de la fecha de vigencia, aceptas la nueva versión.</p>
<h2>10. Ley aplicable y contacto</h2>
<p>Estos términos se rigen por las leyes de los Estados Unidos Mexicanos. Para cualquier controversia, las partes se someten a los tribunales competentes de la Ciudad de México, renunciando a cualquier otro fuero. Lo anterior no limita los derechos que te otorga la Ley Federal de Protección al Consumidor.</p>
<p>Para dudas sobre estos términos escríbenos a <a href="mailto:{{correo_contacto}}">{{correo_contacto}}</a>.</p>
HTML;

$privacidad = <<<'HTML'
<p><strong>{{nombre_portal}}</strong> ({{dominio}}), con domicilio en {{direccion}}, es responsable del tratamiento de tus datos personales conforme a la Ley Federal de Protección de Datos Personales en Posesión de los Particulares y su normativa aplicable. Este aviso explica qué datos recabamos, para qué los usamos y cómo puedes ejercer tus derechos.</p>
<h2>1. Datos que recabamos</h2>
<ul>
<li><strong>Datos de tu cuenta:</strong> nombre, correo electrónico y contraseña. La contraseña se guarda cifrada de forma irreversible; nadie en {{nombre_portal}} puede verla.</li>
<li><strong>Datos de facturación (si los registras):</strong> RFC, razón social, código postal y régimen fiscal.</li>
<li><strong>Datos de pago:</strong> el monto, la fecha, el método de pago y, en pagos con tarjeta, solo los últimos 4 dígitos. Los datos completos de tu tarjeta los recibe y protege directamente la pasarela de pago; {{nombre_portal}} no los almacena.</li>
<li><strong>Contenido de tus QR:</strong> nombres, destinos, contenido (por ejemplo, datos de contacto o de una red WiFi) y logos que subas.</li>
<li><strong>Datos de uso:</strong> fecha de registro, último acceso y notas internas de soporte relacionadas con tu cuenta.</li>
</ul>
<h2>2. Datos de quienes escanean tus QR dinámicos</h2>
<p>Cuando alguien escanea un QR dinámico registramos la fecha y hora, el tipo de dispositivo, el sistema operativo, el navegador, el idioma y, cuando está disponible, el país o la ciudad aproximada. <strong>No guardamos la dirección IP completa</strong>: solo una huella cifrada e irreversible que nos permite contar visitantes distintos sin identificarlos. No recabamos nombres, teléfonos ni correos de quienes escanean. Esta información se muestra al dueño del QR de forma agregada, como estadísticas.</p>
<h2>3. Para qué usamos tus datos</h2>
<p><strong>Finalidades necesarias</strong> para prestarte el servicio:</p>
<ul>
<li>crear y administrar tu cuenta, y verificar tu correo;</li>
<li>generar, guardar y redirigir tus códigos QR, y mostrarte sus estadísticas;</li>
<li>procesar tus pagos y emitir tus facturas;</li>
<li>enviarte avisos sobre tu cuenta, tus pagos y la seguridad (por ejemplo, la recuperación de tu contraseña);</li>
<li>prevenir fraudes y usos indebidos, como QR que lleven a sitios engañosos;</li>
<li>atender tus solicitudes de soporte y cumplir obligaciones legales y fiscales.</li>
</ul>
<p><strong>Finalidades adicionales</strong>, que no son necesarias para el servicio:</p>
<ul>
<li>enviarte novedades, promociones y encuestas de satisfacción;</li>
<li>elaborar estadísticas internas para mejorar el servicio.</li>
</ul>
<p>Si no quieres que usemos tus datos para las finalidades adicionales, escríbenos a <a href="mailto:{{correo_contacto}}">{{correo_contacto}}</a>. Esto no afectará el servicio que recibes.</p>
<h2>4. Con quién compartimos tus datos</h2>
<p>Solo compartimos tus datos con proveedores que nos ayudan a prestar el servicio y que están obligados a protegerlos:</p>
<ul>
<li>pasarelas de pago (como Stripe, Mercado Pago o Conekta), para procesar tus cobros;</li>
<li>nuestro proveedor de hospedaje y base de datos, donde se almacena la información;</li>
<li>nuestro proveedor de correo electrónico, para enviarte avisos;</li>
<li>el Servicio de Administración Tributaria (SAT) y su proveedor de certificación, para emitir tus facturas;</li>
<li>autoridades competentes, cuando la ley nos obligue.</li>
</ul>
<p>No vendemos ni rentamos tus datos personales.</p>
<h2>5. Tus derechos ARCO</h2>
<p>Puedes <strong>Acceder</strong> a tus datos, <strong>Rectificarlos</strong> si son incorrectos, <strong>Cancelarlos</strong> cuando consideres que no se requieren y <strong>Oponerte</strong> a su uso para finalidades específicas. También puedes revocar tu consentimiento o limitar el uso de tus datos.</p>
<p>Envía tu solicitud a <a href="mailto:{{correo_contacto}}">{{correo_contacto}}</a> desde el correo de tu cuenta, indicando tu nombre, el derecho que quieres ejercer y, si aplica, los datos a corregir. Responderemos en un máximo de 20 días hábiles. Muchos datos los puedes corregir tú directamente desde tu cuenta.</p>
<h2>6. Cuánto tiempo conservamos tus datos</h2>
<ul>
<li>Los datos de tu cuenta, mientras la tengas activa. Si la eliminas, se dan de baja del servicio y se conservan bloqueados solo el tiempo que exijan las obligaciones legales.</li>
<li>Los registros de pagos y facturas, el tiempo que exijan las disposiciones fiscales.</li>
<li>Las estadísticas de escaneo, según los días de historial que incluya tu plan.</li>
</ul>
<h2>7. Cookies y almacenamiento en tu navegador</h2>
<p>Usamos una cookie de sesión, necesaria para mantenerte conectado y proteger los formularios, y guardamos en tu navegador preferencias como el tema claro u oscuro. No usamos cookies de publicidad ni de rastreo de terceros.</p>
<h2>8. Seguridad</h2>
<p>Protegemos tus datos con conexiones cifradas, contraseñas cifradas, ligas de un solo uso para recuperar el acceso y acceso restringido al personal autorizado. Ningún sistema es infalible; si detectamos un incidente que afecte tus datos de forma importante, te lo informaremos.</p>
<h2>9. Cambios a este aviso</h2>
<p>Podemos actualizar este aviso de privacidad. Publicaremos la nueva versión en esta página con su fecha de vigencia y, si los cambios son importantes, te avisaremos por correo.</p>
<p>Para cualquier duda sobre tus datos personales escríbenos a <a href="mailto:{{correo_contacto}}">{{correo_contacto}}</a>.</p>
HTML;

$admin = $pdo->query("SELECT id FROM usuarios WHERE rol = 'admin' AND eliminado = 0 ORDER BY id LIMIT 1")->fetchColumn() ?: null;
$insertar = $pdo->prepare(
    "INSERT INTO documentos_legales (tipo, version, titulo, contenido_html, estatus, autor_id, publicado_en)
     VALUES (:t, 1, :ti, :c, 'publicado', :a, NOW())"
);
foreach (['terminos' => ['Términos y condiciones', $terminos], 'privacidad' => ['Aviso de privacidad', $privacidad]] as $tipo => [$titulo, $contenido]) {
    $existe = $pdo->prepare("SELECT COUNT(*) FROM documentos_legales WHERE tipo = :t AND estatus = 'publicado'");
    $existe->execute([':t' => $tipo]);
    if ((int) $existe->fetchColumn() > 0) {
        echo "{$titulo}: ya existe una versión publicada, no se modificó.\n";
        continue;
    }
    $insertar->execute([':t' => $tipo, ':ti' => $titulo, ':c' => $contenido, ':a' => $admin]);
    echo "{$titulo}: versión 1 publicada.\n";
}
echo "Tabla documentos_legales lista.\n";
