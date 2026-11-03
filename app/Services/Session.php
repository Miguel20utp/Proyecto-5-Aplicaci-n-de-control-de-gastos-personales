<?php
declare(strict_types=1);

namespace App\Services;

use App\Config\Environment;

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        session_name((string) Environment::get('SESSION_NAME', 'CGP_SESSION_ID'));
        session_set_cookie_params([
            'lifetime' => 0, 'path' => '/', 'secure' => self::isHttps(),
            'httponly' => true, 'samesite' => 'Lax',
        ]);
        session_start();
    }

    public static function isHttps(): bool
    {
        return !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
    }

    public static function csrfToken(): string
    {
        self::start();
        return $_SESSION['_csrf'] ??= bin2hex(random_bytes(32));
    }

    public static function verifyCsrf(?string $token): bool
    {
        self::start();
        return is_string($token) && isset($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $token);
    }

    public static function flash(string $key, ?string $message = null): ?string
    {
        self::start();
        if ($message !== null) { $_SESSION['_flash'][$key] = $message; return null; }
        $value = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }
}
