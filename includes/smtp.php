<?php
/**
 * Cliente SMTP mínimo: correos HTML (con versión de texto) y la prueba de la configuración.
 * Soporta STARTTLS (tls), conexión cifrada directa (ssl) o sin cifrado,
 * verifica el certificado del servidor y revisa el código de cada respuesta.
 */

declare(strict_types=1);

final class SmtpError extends RuntimeException
{
}

/** Quita saltos de línea para evitar inyección de encabezados o comandos SMTP. */
function smtp_limpiar(string $valor): string
{
    return trim(str_replace(["\r", "\n"], '', $valor));
}

/** Codifica un encabezado con caracteres no ASCII (RFC 2047). */
function smtp_encabezado(string $texto): string
{
    return preg_match('/[^\x20-\x7E]/', $texto) ? '=?UTF-8?B?' . base64_encode($texto) . '?=' : $texto;
}

/**
 * Lee una respuesta completa (puede tener varias líneas "250-...") y
 * valida que el código esté entre los esperados.
 *
 * @param resource $conexion
 */
function smtp_leer($conexion, array $esperados): string
{
    $respuesta = '';
    while (($linea = fgets($conexion, 1024)) !== false) {
        $respuesta .= $linea;
        // La última línea de una respuesta tiene un espacio después del código.
        if (strlen($linea) < 4 || $linea[3] === ' ') {
            break;
        }
    }
    $codigo = (int) substr($respuesta, 0, 3);
    if (!in_array($codigo, $esperados, true)) {
        throw new SmtpError('El servidor respondió: ' . (trim($respuesta) !== '' ? trim($respuesta) : 'sin respuesta'));
    }
    return $respuesta;
}

/** @param resource $conexion */
function smtp_comando($conexion, string $comando, array $esperados): string
{
    fwrite($conexion, $comando . "\r\n");
    return smtp_leer($conexion, $esperados);
}

/** Prepara el cuerpo del mensaje aplicando "dot-stuffing" (RFC 5321 §4.5.2). */
function smtp_cuerpo(string $texto): string
{
    $texto = str_replace(["\r\n", "\r"], "\n", $texto);
    $lineas = array_map(fn($l) => str_starts_with($l, '.') ? '.' . $l : $l, explode("\n", $texto));
    return implode("\r\n", $lineas);
}

/** Versión de texto plano de un HTML, para la parte alternativa del correo. */
function smtp_html_a_texto(string $html): string
{
    $html = preg_replace('#<(head|style|script)\b.*?</\1>#is', '', $html) ?? $html;
    // Los enlaces conservan su dirección: "Texto (https://...)".
    $html = preg_replace('#<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)</a>#is', '$2 ($1)', $html) ?? $html;
    $html = preg_replace('#<(br|/p|/div|/h[1-6]|/tr|/li|hr)\b[^>]*>#i', "\n", $html) ?? $html;
    $texto = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $texto = preg_replace("/[ \t]+/", ' ', $texto) ?? $texto;
    $texto = preg_replace("/\n\s*\n\s*\n+/", "\n\n", $texto) ?? $texto;
    return trim($texto);
}

/**
 * Arma el mensaje completo (encabezados + cuerpo multipart/alternative) listo para DATA.
 * Ambas partes van en base64: ninguna línea empieza con "." ni pasa de 76 caracteres.
 */
function smtp_mensaje(string $de, string $nombre, string $para, string $asunto, string $html): string
{
    $limite = '=_armaqr_' . bin2hex(random_bytes(8));
    $remitente = $nombre !== '' ? smtp_encabezado($nombre) . " <{$de}>" : $de;
    $encabezados = implode("\r\n", [
        'Date: ' . date('r'),
        'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . substr((string) strrchr($de, '@'), 1) . '>',
        "From: {$remitente}",
        "To: <{$para}>",
        'Subject: ' . smtp_encabezado(smtp_limpiar($asunto)),
        'MIME-Version: 1.0',
        "Content-Type: multipart/alternative; boundary=\"{$limite}\"",
    ]);
    $parte = fn(string $tipo, string $contenido) => "--{$limite}\r\n"
        . "Content-Type: {$tipo}; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: base64\r\n\r\n"
        . rtrim(chunk_split(base64_encode($contenido), 76, "\r\n")) . "\r\n";

    return $encabezados . "\r\n\r\n"
        . $parte('text/plain', smtp_html_a_texto($html))
        . $parte('text/html', $html)
        . "--{$limite}--";
}

/**
 * Envía un correo HTML por SMTP.
 *
 * $cfg: host, port, encryption (tls|ssl|none), usuario, password, from_email, from_nombre
 * Devuelve [true, mensaje] o [false, motivo].
 */
function smtp_enviar(array $cfg, string $para, string $asunto, string $html): array
{
    $host = smtp_limpiar((string) ($cfg['host'] ?? ''));
    $puerto = (int) ($cfg['port'] ?? 587);
    $cifrado = (string) ($cfg['encryption'] ?? 'tls');
    $de = filter_var(smtp_limpiar((string) ($cfg['from_email'] ?? '')), FILTER_VALIDATE_EMAIL);
    $para = filter_var(smtp_limpiar($para), FILTER_VALIDATE_EMAIL);
    $nombre = smtp_limpiar((string) ($cfg['from_nombre'] ?? ''));

    if ($host === '' || $puerto < 1 || $puerto > 65535) {
        return [false, 'Configura un servidor y un puerto SMTP válidos.'];
    }
    if ($de === false || $para === false) {
        return [false, 'El correo remitente o el de destino no es válido.'];
    }
    // El 465 es SSL implícito (RFC 8314): el servidor no saluda hasta negociar TLS, así que
    // con TLS/STARTTLS o sin cifrado solo se esperaría al tiempo límite. Se avisa al instante.
    if ($puerto === 465 && $cifrado !== 'ssl') {
        return [false, 'El puerto 465 requiere cifrado SSL. Cámbialo en Configuración › Correo (o usa el puerto 587 con TLS).'];
    }

    $contexto = stream_context_create(['ssl' => [
        'verify_peer' => true,
        'verify_peer_name' => true,
        'peer_name' => $host,
    ]]);
    $destino = ($cifrado === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $puerto;
    $conexion = @stream_socket_client($destino, $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $contexto);
    if ($conexion === false) {
        $pista = ($puerto === 465 && $cifrado !== 'ssl') ? ' El puerto 465 normalmente usa SSL.' : '';
        return [false, "No se pudo conectar a {$host}:{$puerto} ({$errstr}).{$pista}"];
    }
    stream_set_timeout($conexion, 10);

    try {
        $local = smtp_limpiar(gethostname() ?: 'localhost');
        smtp_leer($conexion, [220]);
        $ehlo = smtp_comando($conexion, "EHLO {$local}", [250]);

        if ($cifrado === 'tls') {
            if (stripos($ehlo, 'STARTTLS') === false) {
                throw new SmtpError('El servidor no ofrece STARTTLS. Usa SSL o revisa el puerto.');
            }
            smtp_comando($conexion, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($conexion, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new SmtpError('No se pudo establecer TLS con el servidor (¿certificado inválido?).');
            }
            smtp_comando($conexion, "EHLO {$local}", [250]);
        }

        $usuario = (string) ($cfg['usuario'] ?? '');
        if ($usuario !== '') {
            smtp_comando($conexion, 'AUTH LOGIN', [334]);
            smtp_comando($conexion, base64_encode($usuario), [334]);
            smtp_comando($conexion, base64_encode((string) ($cfg['password'] ?? '')), [235]);
        }

        smtp_comando($conexion, "MAIL FROM:<{$de}>", [250]);
        smtp_comando($conexion, "RCPT TO:<{$para}>", [250, 251]);
        smtp_comando($conexion, 'DATA', [354]);
        smtp_comando($conexion, smtp_mensaje($de, $nombre, $para, $asunto, $html) . "\r\n.", [250]);
        fwrite($conexion, "QUIT\r\n"); // el mensaje ya se aceptó; no importa la respuesta

        return [true, "Correo enviado a {$para}."];
    } catch (SmtpError $error) {
        return [false, $error->getMessage()];
    } finally {
        fclose($conexion);
    }
}

/** Correo de prueba de la configuración SMTP. */
function smtp_enviar_prueba(array $cfg, string $para): array
{
    [$ok, $mensaje] = smtp_enviar($cfg, $para, 'Prueba de configuración SMTP',
        '<p>Este correo confirma que el envío SMTP está configurado correctamente.</p>');
    return [$ok, $ok ? "Correo de prueba enviado a {$para}." : $mensaje];
}
