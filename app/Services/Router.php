<?php
declare(strict_types=1);

namespace App\Services;

use App\Controllers\HomeController;
use RuntimeException;

final class Router
{
    /** @var array<string, array<string, callable|array{class-string, string}>> */
    private array $routes = [];

    public function get(string $path, callable|array $handler): void
    {
        $this->routes['GET'][$this->normalize($path)] = $handler;
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = $this->normalize((string) (parse_url($uri, PHP_URL_PATH) ?: '/'));
        $handler = $this->routes[strtoupper($method)][$path] ?? null;
        if ($handler === null) {
            http_response_code(404);
            (new HomeController())->notFound();
            return;
        }
        if (is_array($handler) && is_string($handler[0])) {
            $handler = [new $handler[0](), $handler[1]];
        }
        $handler();
    }

    private function normalize(string $path): string
    {
        $path = '/' . trim($path, '/');
        return $path === '//' ? '/' : $path;
    }
}
