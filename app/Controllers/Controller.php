<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Models\Settings;
use RuntimeException;

abstract class Controller
{
    protected function view(string $name, array $data = []): void
    {
        $file = dirname(__DIR__) . '/Views/' . str_replace(['..', '\\'], ['', '/'], $name) . '.php';
        if (!is_file($file)) {
            throw new RuntimeException("Vista no encontrada: {$name}");
        }
        extract($data, EXTR_SKIP);
        $theme = !empty($_SESSION['user']['id']) ? (new Settings(Database::connection()))->theme((int) $_SESSION['user']['id']) : 'claro';
        ob_start();
        require $file;
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . '/Views/layouts/main.php';
    }
}
