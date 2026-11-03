<?php
declare(strict_types=1);

use App\Config\Environment;
use App\Controllers\AuthController;
use App\Controllers\CategoryController;
use App\Controllers\MovementController;
use App\Services\Router;
use App\Services\Session;

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/vendor/autoload.php';
Environment::load(BASE_PATH . '/.env');

$debug = filter_var(Environment::get('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN);
set_exception_handler(static function (Throwable $exception) use ($debug): void {
    error_log((string) $exception);
    http_response_code(500);
    if ($debug) {
        echo '<pre>' . htmlspecialchars((string) $exception, ENT_QUOTES, 'UTF-8') . '</pre>';
        return;
    }
    echo '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Error</title><body><main><h1>Ha ocurrido un error</h1><p>Inténtalo de nuevo más tarde.</p></main></body></html>';
});
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

$router = new Router();
$router->get('/', [AuthController::class, 'dashboard']);
$router->get('/register', [AuthController::class, 'registerForm']);
$router->post('/register', [AuthController::class, 'register']);
$router->get('/login', [AuthController::class, 'loginForm']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);
$router->get('/categorias', [CategoryController::class, 'index']);
$router->get('/categorias/nueva', [CategoryController::class, 'create']);
$router->post('/categorias', [CategoryController::class, 'store']);
$router->get('/categorias/editar', [CategoryController::class, 'edit']);
$router->post('/categorias/actualizar', [CategoryController::class, 'update']);
$router->post('/categorias/eliminar', [CategoryController::class, 'delete']);
$router->get('/movimientos', [MovementController::class, 'index']);
$router->get('/movimientos/nuevo', [MovementController::class, 'create']);
$router->post('/movimientos', [MovementController::class, 'store']);
$router->get('/movimientos/editar', [MovementController::class, 'edit']);
$router->post('/movimientos/actualizar', [MovementController::class, 'update']);
$router->post('/movimientos/eliminar', [MovementController::class, 'delete']);
Session::start();
$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
