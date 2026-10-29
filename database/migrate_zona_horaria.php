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
 * corridos. Los escaneos registrados ya con el código nuevo están bien y no se deben mover, por eso
 * se indica hasta qué escaneo ajustar:
 *
 *   php database/migrate_zona_horaria.php                 # muestra los últimos escaneos para elegir
 *   php database/migrate_zona_horaria.php --hasta-id=28   # ajusta los escaneos con id ≤ 28
 *
 * Corre una sola vez: deja la marca configuracion.zona_horaria_escaneos.
 */

require_once __DIR__ . '/Database.php';
date_default_timezone_set(App\Core\Database::ZONA_HORARIA);

$pdo = App\Core\Database::connection();

$hecho = $pdo->query("SELECT valor FROM configuracion WHERE clave = 'zona_horaria_escaneos'")->fetchColumn();
if ($hecho !== false && $hecho !== '') {
    echo "Los escaneos ya se habían pasado a la hora de la Ciudad de México ({$hecho}). No se cambió nada.\n";
    exit;
}

// Diferencia entre la zona del servidor (con la que se guardaron) y la del proyecto, en minutos.
$pdo->exec('SET time_zone = @@global.time_zone');
$servidor = (int) $pdo->query('SELECT TIMESTAMPDIFF(MINUTE, UTC_TIMESTAMP(), NOW())')->fetchColumn();
$pdo->exec("SET time_zone = '" . App\Core\Database::desfase() . "'");
$proyecto = intdiv((new DateTimeImmutable())->getOffset(), 60);
$ajuste = $proyecto - $servidor;
$ahora = (string) $pdo->query('SELECT NOW()')->fetchColumn();

$hastaId = null;
foreach (array_slice($argv, 1) as $argumento) {
    if (preg_match('/^--hasta-id=(\d+)$/', $argumento, $m)) {
        $hastaId = (int) $m[1];
    }
}

if ($ajuste === 0) {
    echo "El servidor ya usa la misma zona horaria que el proyecto: no hay que ajustar escaneos.\n";
} elseif ($hastaId === null) {
    // Sin --hasta-id solo muestra: el corte es el último escaneo anterior a subir el código nuevo.
    printf("El servidor guardaba en UTC%+d y el proyecto usa UTC%+d: hay que sumar %d minutos.\n", $servidor / 60, $proyecto / 60, $ajuste);
    echo "Hora actual del proyecto: {$ahora}\n\nÚltimos escaneos:\n";
    foreach ($pdo->query('SELECT id, fecha_escaneo FROM qr_escaneos ORDER BY id DESC LIMIT 20')->fetchAll(PDO::FETCH_ASSOC) as $f) {
        echo "  id {$f['id']}  {$f['fecha_escaneo']}\n";
    }
    echo "\nLos escaneos registrados con el código nuevo ya están en hora de México (cerca de la hora actual).\n"
        . "Elige el id del último escaneo ANTERIOR a subir el código nuevo y corre:\n"
        . "  php database/migrate_zona_horaria.php --hasta-id=ID\n";
    exit;
}

$pdo->beginTransaction();
$escaneos = 0;
if ($ajuste !== 0) {
    $consulta = $pdo->prepare("UPDATE qr_escaneos SET fecha_escaneo = fecha_escaneo + INTERVAL {$ajuste} MINUTE WHERE id <= :hasta");
    $consulta->execute([':hasta' => $hastaId]);
    $escaneos = $consulta->rowCount();
    // El último escaneo de cada QR se vuelve a tomar de sus escaneos ya ajustados.
    $pdo->exec('UPDATE qr_codigos q SET ultimo_escaneo = (SELECT MAX(e.fecha_escaneo) FROM qr_escaneos e WHERE e.qr_id = q.id) WHERE q.ultimo_escaneo IS NOT NULL');
    echo "Se ajustaron {$escaneos} escaneos (id ≤ {$hastaId}) {$ajuste} minutos.\n";
}
$pdo->prepare(
    "INSERT INTO configuracion (clave, valor, descripcion) VALUES ('zona_horaria_escaneos', :valor, 'Escaneos pasados a la hora del proyecto')
     ON DUPLICATE KEY UPDATE valor = VALUES(valor)"
)->execute([':valor' => date('Y-m-d H:i') . ($hastaId !== null ? " (id ≤ {$hastaId}, " . sprintf('%+d', $ajuste) . ' min)' : ' (sin ajuste)')]);
$pdo->commit();

echo "Zona horaria de los escaneos lista.\n";
