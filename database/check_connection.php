<?php
declare(strict_types=1);

use App\Config\Database;
use App\Config\Environment;

require dirname(__DIR__) . '/vendor/autoload.php';
Environment::load(dirname(__DIR__) . '/.env');

try {
    $statement = Database::connection()->prepare('SELECT 1');
    $statement->execute();
    fwrite(STDOUT, "Conexión PDO a MySQL exitosa.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, "No se pudo comprobar la conexión: {$exception->getMessage()}\n");
    exit(1);
}
