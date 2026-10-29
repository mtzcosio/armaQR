<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

final class Database
{
    /** Zona horaria del proyecto (la misma de config.php): la conexión la fija en MySQL. */
    public const ZONA_HORARIA = 'America/Mexico_City';

    private static ?PDO $connection = null;

    public static function config(): array
    {
        $envFile = __DIR__ . '/.env';
        if (is_file($envFile)) {
            foreach (self::readEnvFile($envFile) as $key => $value) {
                putenv("{$key}={$value}");
            }
        }

        return [
            'host' => trim(getenv('DB_HOST') ?: ''),
            'port' => (int) (getenv('DB_PORT') ?: 3306),
            'name' => trim(getenv('DB_NAME') ?: ''),
            'user' => trim(getenv('DB_USER') ?: ''),
            'password' => trim(getenv('DB_PASSWORD') ?: ''),
            'charset' => trim(getenv('DB_CHARSET') ?: 'utf8mb4'),
        ];
    }

    private static function readEnvFile(string $path): array
    {
        $values = [];
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return $values;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode('=', $line, 2));
            $value = trim($value, "'\"");
            $values[$key] = $value;
        }

        return $values;
    }

    public static function connection(): PDO
    {
        if (self::$connection === null) {
            $config = self::config();
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $config['host'],
                $config['port'],
                $config['name'],
                $config['charset']
            );

            try {
                self::$connection = new PDO(
                    $dsn,
                    $config['user'],
                    $config['password'],
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                        PDO::ATTR_STRINGIFY_FETCHES => false,
                        // Si la BD no responde, falla a los 5 s en vez de dejar la página
                        // colgada hasta el límite de PHP (y con ella las demás pestañas del usuario).
                        PDO::ATTR_TIMEOUT => 5,
                    ]
                );
            } catch (PDOException $exception) {
                throw new PDOException(
                    "No se pudo conectar a MySQL/MariaDB: {$exception->getMessage()}",
                    0,
                    $exception
                );
            }
            // La BD usa la misma hora que PHP (America/Mexico_City): NOW(), CURRENT_TIMESTAMP y las
            // fechas que se comparan desde PHP quedan en la misma zona aunque el servidor esté en UTC.
            self::$connection->exec("SET time_zone = '" . self::desfase() . "'");
        }

        return self::$connection;
    }

    /** Desfase actual de la zona del proyecto respecto a UTC ('-06:00'). */
    public static function desfase(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone(self::ZONA_HORARIA)))->format('P');
    }

    public static function ping(): void
    {
        self::connection()->query('SELECT 1');
    }

    /**
     * ¿El error es porque no hay base de datos? Cualquier falla al conectar (credenciales, servidor,
     * tiempo agotado) o una conexión que se cae a media consulta.
     */
    public static function esCaida(\Throwable $error): bool
    {
        for ($e = $error; $e !== null; $e = $e->getPrevious()) {
            if ($e instanceof PDOException && str_starts_with($e->getMessage(), 'No se pudo conectar a MySQL/MariaDB')) {
                return true;
            }
            if ($e instanceof PDOException) {
                $codigo = (int) ($e->errorInfo[1] ?? 0);
                // 2002: no conecta · 2006: "server has gone away" · 2013: se perdió la conexión
                if (in_array($codigo, [2002, 2006, 2013], true) || preg_match('/SQLSTATE\[HY000\] \[(2002|2006|2013)\]/', $e->getMessage())) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Manejador global (se registra al cargar este archivo): si la base de datos no responde,
     * muestra una página 503 breve en lugar del error con el servidor y el usuario de la BD.
     * El detalle queda en el log de PHP. Cualquier otro error sigue su curso normal.
     */
    public static function manejarError(\Throwable $error): void
    {
        if (!self::esCaida($error)) {
            restore_exception_handler();
            throw $error;
        }
        error_log('Base de datos no disponible: ' . $error->getMessage());
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, 'No se pudo conectar a la base de datos: ' . $error->getMessage() . PHP_EOL);
            exit(1);
        }
        if (!headers_sent()) {
            http_response_code(503);
            header('Retry-After: 30');
            header('Cache-Control: no-store');
            header('Content-Type: text/html; charset=utf-8');
        }
        echo '<!doctype html><html lang="es-MX"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>Servicio no disponible</title><meta name="robots" content="noindex">'
            . '<style>body{margin:0;min-height:100vh;display:grid;place-items:center;font-family:system-ui,sans-serif;background:#f4f1ea;color:#172036}'
            . 'main{max-width:420px;padding:32px 24px;text-align:center}h1{font-size:24px;margin:0 0 10px}p{color:#5b6475;line-height:1.5;margin:0 0 20px}'
            . 'a{display:inline-block;padding:12px 20px;border-radius:10px;background:#172036;color:#fff;text-decoration:none;font-weight:600}</style></head>'
            . '<body><main><h1>Estamos teniendo un problema momentáneo</h1>'
            . '<p>No pudimos cargar esta página. Inténtalo de nuevo en unos segundos.</p>'
            . '<a href="javascript:location.reload()">Volver a intentar</a></main></body></html>';
        exit;
    }
}

set_exception_handler([Database::class, 'manejarError']);
