<?php
declare(strict_types=1);

namespace App\Controllers;

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
        ob_start();
        require $file;
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . '/Views/layouts/main.php';
    }
}
