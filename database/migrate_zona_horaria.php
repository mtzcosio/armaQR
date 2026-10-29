<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * Pasa a la hora de la Ciudad de México los escaneos guardados antes de que la conexión fijara
 * la zona horaria (Database::connection() hace SET time_zone). Hasta entonces MySQL los guardaba
 * con la hora de su servidor (UTC en Hostinger), así que "Hora del día" y el mapa de calor salían
 * corridos. Corre una sola vez: deja la marca configuracion.zona_horaria_escaneos.
 *
 *   php database/migrate_zona_horaria.php
 */

require_once __DIR__ . '/Database.php';
date_default_timezone_set(App\Core\Database::ZONA_HORARIA);

$pdo = App\Core\Database::connection();

$hecho = $pdo->query("SELECT valor FROM configuracion WHERE clave = 'zona_horaria_escaneos'")->fetchColumn();
if ($hecho !== false && $hecho !== '') {
    echo "Los escaneos ya estaban en la hora de la Ciudad de México ({$hecho}). No se cambió nada.\n";
    exit;
}

// Diferencia entre la zona del servidor (con la que se guardaron) y la de PHP, en minutos.
$pdo->exec('SET time_zone = @@global.time_zone');
$servidor = (int) $pdo->query('SELECT TIMESTAMPDIFF(MINUTE, UTC_TIMESTAMP(), NOW())')->fetchColumn();
$php = intdiv((new DateTimeImmutable())->getOffset(), 60);
$pdo->exec("SET time_zone = '" . App\Core\Database::desfase() . "'");
$ajuste = $php - $servidor;

$pdo->beginTransaction();
if ($ajuste !== 0) {
    $escaneos = $pdo->exec("UPDATE qr_escaneos SET fecha_escaneo = fecha_escaneo + INTERVAL {$ajuste} MINUTE");
    $pdo->exec("UPDATE qr_codigos SET ultimo_escaneo = ultimo_escaneo + INTERVAL {$ajuste} MINUTE WHERE ultimo_escaneo IS NOT NULL");
    echo "Se ajustaron {$escaneos} escaneos {$ajuste} minutos (servidor UTC" . sprintf('%+d', $servidor / 60) . ", PHP UTC" . sprintf('%+d', $php / 60) . ").\n";
} else {
    echo "El servidor ya usaba la misma zona horaria que PHP: no hubo que ajustar escaneos.\n";
}
$pdo->prepare(
    "INSERT INTO configuracion (clave, valor, descripcion) VALUES ('zona_horaria_escaneos', :valor, 'Escaneos pasados a la hora de PHP')
     ON DUPLICATE KEY UPDATE valor = VALUES(valor)"
)->execute([':valor' => date('Y-m-d H:i') . ' (' . sprintf('%+d', $ajuste) . ' min)']);
$pdo->commit();

echo "Zona horaria de los escaneos lista.\n";
