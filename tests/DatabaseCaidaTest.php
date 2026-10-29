<?php

declare(strict_types=1);

// Database::esCaida(): reconoce cuándo la base de datos no responde, para mostrar la página 503
// (Database::manejarError) en lugar del error con el servidor y el usuario de la BD. Sin conexión real.
require_once dirname(__DIR__) . '/database/Database.php';

use App\Core\Database;

$fallas = [];
$prueba = function (string $nombre, bool $ok) use (&$fallas) {
    if (!$ok) $fallas[] = $nombre;
};

$sinConexion = new PDOException('No se pudo conectar a MySQL/MariaDB: SQLSTATE[HY000] [2002] tiempo agotado', 0,
    new PDOException('SQLSTATE[HY000] [2002] tiempo agotado'));
$prueba('No conecta (2002)', Database::esCaida($sinConexion));

$seFue = new PDOException('SQLSTATE[HY000]: General error: 2006 MySQL server has gone away');
$seFue->errorInfo = ['HY000', 2006, 'MySQL server has gone away'];
$prueba('Se cayó a media consulta (2006)', Database::esCaida($seFue));

$perdida = new PDOException('SQLSTATE[HY000]: General error: 2013 Lost connection');
$perdida->errorInfo = ['HY000', 2013, 'Lost connection to MySQL server during query'];
$prueba('Se perdió la conexión (2013)', Database::esCaida(new RuntimeException('envuelto', 0, $perdida)));

$sintaxis = new PDOException('SQLSTATE[42000]: Syntax error');
$sintaxis->errorInfo = ['42000', 1064, 'You have an error in your SQL syntax'];
$prueba('Un error de SQL no es caída', !Database::esCaida($sintaxis));
$prueba('Otro error no es caída', !Database::esCaida(new RuntimeException('otro')));

restore_exception_handler();
if ($fallas) {
    fwrite(STDERR, "Fallaron:\n  - " . implode("\n  - ", $fallas) . "\n");
    exit(1);
}
echo "Base de datos: detección de caídas para la página 503: OK\n";
