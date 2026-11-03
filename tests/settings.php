<?php
declare(strict_types=1);

// Integración sobre una base de pruebas desechable creada con database/schema.sql.
use App\Config\Database;
use App\Config\Environment;
use App\Models\Settings;
use App\Models\User;
use App\Services\Session;

require dirname(__DIR__) . '/vendor/autoload.php';
Environment::load(dirname(__DIR__) . '/.env');
$db = Database::connection();
$users = new User($db);
$a = $users->create('Perfil A', 'settings-a-' . bin2hex(random_bytes(5)) . '@example.test', 'ClaveInicial-2026');
$b = $users->create('Perfil B', 'settings-b-' . bin2hex(random_bytes(5)) . '@example.test', 'ClaveInicial-2026');
$model = new Settings($db);
try {
    $profile = $model->profile($a);
    if (!$profile || $profile['nombre'] !== 'Perfil A') throw new RuntimeException('Falló consultar el perfil.');
    if ($model->emailExists($model->profile($b)['correo'], $a) !== true) throw new RuntimeException('No detectó correo duplicado.');
    $model->updateProfile($a, 'Perfil actualizado', 'nuevo-' . bin2hex(random_bytes(5)) . '@example.test');
    if ($model->profile($a)['nombre'] !== 'Perfil actualizado' || $model->profile($b)['nombre'] !== 'Perfil B') throw new RuntimeException('Falló actualización o aislamiento del perfil.');
    $hash = password_hash('ClaveNueva-2026', PASSWORD_DEFAULT);
    $model->updatePassword($a, $hash);
    $stored = $model->profile($a)['contrasena_hash'];
    if (!password_verify('ClaveNueva-2026', $stored) || $stored === 'ClaveNueva-2026') throw new RuntimeException('La contraseña no quedó almacenada como hash.');
    if (password_verify('incorrecta', $stored)) throw new RuntimeException('La contraseña incorrecta fue aceptada.');
    $model->saveTheme($a, 'oscuro');
    if ($model->theme($a) !== 'oscuro' || $model->theme($b) !== 'claro') throw new RuntimeException('Falló guardar/aislar la preferencia de tema.');
    $model->saveTheme($a, 'claro');
    if ($model->theme($a) !== 'claro') throw new RuntimeException('Falló actualizar la preferencia.');
    Session::start(); $token = Session::csrfToken();
    if (!Session::verifyCsrf($token) || Session::verifyCsrf('token-invalido')) throw new RuntimeException('Falló la verificación CSRF.');
    echo "Perfil, contraseña hash, preferencias, aislamiento y token CSRF: OK\n";
} finally {
    $db->prepare('DELETE FROM usuarios WHERE id IN (:a,:b)')->execute(['a' => $a, 'b' => $b]);
}
