<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Middleware\AuthMiddleware;
use App\Models\User;
use App\Services\Session;
use PDOException;

final class AuthController extends Controller
{
    public function registerForm(): void
    {
        Session::start();
        if (!empty($_SESSION['user']['id'])) { header('Location: /', true, 303); return; }
        $this->view('auth/register', ['title' => 'Crear cuenta', 'csrf' => Session::csrfToken()]);
    }

    public function register(): void
    {
        if (!Session::verifyCsrf($_POST['_csrf'] ?? null)) { http_response_code(419); $this->view('errors/419', ['title' => 'Formulario vencido']); return; }
        $name = trim((string) ($_POST['nombre'] ?? ''));
        $email = strtolower(trim((string) ($_POST['correo'] ?? '')));
        $password = (string) ($_POST['contrasena'] ?? '');
        $errors = [];
        $nameLength = function_exists('mb_strlen') ? mb_strlen($name) : strlen($name);
        if ($name === '' || $nameLength > 120) $errors[] = 'El nombre es obligatorio y debe tener como máximo 120 caracteres.';
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 254) $errors[] = 'Ingresa un correo electrónico válido.';
        if (strlen($password) < 8 || strlen($password) > 72) $errors[] = 'La contraseña debe tener entre 8 y 72 caracteres.';
        if ($errors) { http_response_code(422); $this->view('auth/register', ['title' => 'Crear cuenta', 'csrf' => Session::csrfToken(), 'errors' => $errors, 'nombre' => $name, 'correo' => $email]); return; }
        try {
            (new User(Database::connection()))->create($name, $email, $password);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                http_response_code(422); $this->view('auth/register', ['title' => 'Crear cuenta', 'csrf' => Session::csrfToken(), 'errors' => ['Ya existe una cuenta con ese correo.'], 'nombre' => $name, 'correo' => $email]); return;
            }
            throw $exception;
        }
        Session::flash('success', 'Tu cuenta fue creada. Ya puedes iniciar sesión.');
        header('Location: /login', true, 303);
    }

    public function loginForm(): void
    {
        Session::start();
        if (!empty($_SESSION['user']['id'])) { header('Location: /', true, 303); return; }
        $this->view('auth/login', ['title' => 'Iniciar sesión', 'csrf' => Session::csrfToken(), 'success' => Session::flash('success'), 'error' => Session::flash('error')]);
    }

    public function login(): void
    {
        if (!Session::verifyCsrf($_POST['_csrf'] ?? null)) { http_response_code(419); $this->view('errors/419', ['title' => 'Formulario vencido']); return; }
        $email = strtolower(trim((string) ($_POST['correo'] ?? '')));
        $password = (string) ($_POST['contrasena'] ?? '');
        $user = filter_var($email, FILTER_VALIDATE_EMAIL) ? (new User(Database::connection()))->findByEmail($email) : null;
        if ($user === null || !password_verify($password, $user['contrasena_hash'])) {
            http_response_code(422); $this->view('auth/login', ['title' => 'Iniciar sesión', 'csrf' => Session::csrfToken(), 'error' => 'El correo o la contraseña no son correctos.', 'correo' => $email]); return;
        }
        Session::start();
        session_regenerate_id(true);
        $_SESSION['user'] = ['id' => (int) $user['id'], 'nombre' => $user['nombre'], 'correo' => $user['correo']];
        unset($_SESSION['_csrf']);
        Session::flash('success', 'Bienvenido, ' . $user['nombre'] . '.');
        header('Location: /', true, 303);
    }

    public function logout(): void
    {
        if (!Session::verifyCsrf($_POST['_csrf'] ?? null)) { http_response_code(419); $this->view('errors/419', ['title' => 'Formulario vencido']); return; }
        Session::start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', ['expires' => time() - 42000, 'path' => $params['path'], 'domain' => $params['domain'], 'secure' => $params['secure'], 'httponly' => $params['httponly'], 'samesite' => $params['samesite'] ?? 'Lax']);
        }
        session_destroy();
        header('Location: /login', true, 303);
    }

    public function dashboard(): void
    {
        AuthMiddleware::requireUser();
        $this->view('home', ['title' => 'Inicio', 'user' => $_SESSION['user'], 'csrf' => Session::csrfToken(), 'success' => Session::flash('success')]);
    }
}
