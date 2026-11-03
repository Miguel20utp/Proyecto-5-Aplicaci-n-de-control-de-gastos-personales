<?php
declare(strict_types=1);

// Prueba de integración del modelo de autenticación. Requiere una base de pruebas
// creada con database/schema.sql y variables DB_* configuradas en .env.
use App\Config\Database;
use App\Config\Environment;
use App\Models\User;

require dirname(__DIR__) . '/vendor/autoload.php';
Environment::load(dirname(__DIR__) . '/.env');
$db = Database::connection();
$model = new User($db);
$email = 'auth-test-' . bin2hex(random_bytes(6)) . '@example.test';
$id = $model->create('Usuario de prueba', $email, 'ClaveSegura-2026');
$user = $model->findByEmail($email);
if ($id < 1 || $user === null || !password_verify('ClaveSegura-2026', $user['contrasena_hash'])) {
    throw new RuntimeException('Falló el registro o verificación de contraseña.');
}
if (password_verify('incorrecta', $user['contrasena_hash'])) {
    throw new RuntimeException('Se aceptó una contraseña incorrecta.');
}
$db->prepare('DELETE FROM usuarios WHERE id = :id')->execute(['id' => $id]);
echo "Registro y autenticación de modelo: OK\n";
