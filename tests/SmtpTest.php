<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/smtp.php';

$fallas = [];

/** Crea un flujo en memoria con la respuesta simulada del servidor. */
function flujo(string $contenido)
{
    $f = fopen('php://memory', 'r+');
    fwrite($f, $contenido);
    rewind($f);
    return $f;
}

// Respuesta de varias líneas: debe leerse completa.
$ehlo = smtp_leer(flujo("250-mail.example.com\r\n250-STARTTLS\r\n250 AUTH LOGIN\r\n"), [250]);
if (!str_contains($ehlo, 'STARTTLS') || !str_contains($ehlo, 'AUTH LOGIN')) {
    $fallas[] = 'smtp_leer() debe leer todas las líneas de una respuesta multilínea.';
}

// Un código inesperado debe lanzar error con la respuesta del servidor.
try {
    smtp_leer(flujo("535 5.7.8 Authentication failed\r\n"), [235]);
    $fallas[] = 'smtp_leer() debe fallar con un código no esperado.';
} catch (SmtpError $e) {
    if (!str_contains($e->getMessage(), '535')) {
        $fallas[] = 'El error debe incluir la respuesta del servidor.';
    }
}

// Dot-stuffing y saltos CRLF.
if (smtp_cuerpo("hola\n.oculto\nfin") !== "hola\r\n..oculto\r\nfin") {
    $fallas[] = 'smtp_cuerpo() debe duplicar los puntos iniciales y usar CRLF.';
}

// Sin inyección de encabezados.
if (smtp_limpiar("Arma-QR\r\nBcc: x@y.com") !== 'Arma-QRBcc: x@y.com') {
    $fallas[] = 'smtp_limpiar() debe quitar saltos de línea.';
}
if (smtp_encabezado('Configuración') === 'Configuración' || smtp_encabezado('Hola') !== 'Hola') {
    $fallas[] = 'smtp_encabezado() debe codificar solo textos no ASCII.';
}

// Validaciones previas a conectar.
[$ok] = smtp_enviar_prueba(['host' => '', 'from_email' => 'a@b.com'], 'c@d.com');
[$ok2] = smtp_enviar_prueba(['host' => 'smtp.example.com', 'from_email' => 'no-es-correo'], 'c@d.com');
// Puerto 465 sin SSL: falla al instante (sin conectar ni esperar el tiempo límite).
$inicio = microtime(true);
[$ok3, $msg3] = smtp_enviar(['host' => 'smtp.example.com', 'port' => 465, 'encryption' => 'tls', 'from_email' => 'a@b.com'], 'c@d.com', 'x', '<p>x</p>');
if ($ok3 || !str_contains($msg3, '465') || microtime(true) - $inicio > 1) {
    $fallas[] = 'Con el puerto 465 y TLS debe fallar al instante explicando que requiere SSL.';
}
if ($ok || $ok2) {
    $fallas[] = 'smtp_enviar_prueba() debe rechazar configuraciones inválidas sin conectar.';
}

if ($fallas) {
    fwrite(STDERR, implode("\n", $fallas) . "\n");
    exit(1);
}
echo "SMTP (lectura multilínea, códigos, dot-stuffing, encabezados): OK\n";
