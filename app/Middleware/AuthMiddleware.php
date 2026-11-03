<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Services\Session;

final class AuthMiddleware
{
    public static function requireUser(): void
    {
        Session::start();
        if (empty($_SESSION['user']['id'])) {
            Session::flash('error', 'Inicia sesión para continuar.');
            header('Location: /login', true, 303);
            exit;
        }
    }
}
