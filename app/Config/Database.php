<?php
declare(strict_types=1);

namespace App\Config;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $driver = (string) Environment::get('DB_DRIVER', 'mysql');
        $host = (string) Environment::get('DB_HOST', '127.0.0.1');
        $port = (string) Environment::get('DB_PORT', '3306');
        $database = (string) Environment::get('DB_DATABASE', '');
        $charset = (string) Environment::get('DB_CHARSET', 'utf8mb4');
        $username = (string) Environment::get('DB_USERNAME', '');
        $password = (string) Environment::get('DB_PASSWORD', '');

        if ($driver !== 'mysql') {
            throw new RuntimeException('DB_DRIVER debe ser mysql.');
        }
        if ($database === '') {
            throw new RuntimeException('Configura DB_DATABASE en el archivo .env.');
        }
        if (!preg_match('/^[A-Za-z0-9_]+$/', $charset)) {
            throw new RuntimeException('DB_CHARSET contiene un valor no válido.');
        }

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $database, $charset);

        try {
            self::$connection = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $exception) {
            error_log('Database connection failed: ' . $exception->getMessage());
            throw new RuntimeException('No fue posible conectar con la base de datos. Verifica la configuración y que MySQL esté activo.');
        }

        return self::$connection;
    }
}
