<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

final class Database
{
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
        }

        return self::$connection;
    }

    public static function ping(): void
    {
        self::connection()->query('SELECT 1');
    }
}
