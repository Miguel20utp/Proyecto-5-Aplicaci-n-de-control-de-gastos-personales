<?php
declare(strict_types=1);

// Prueba de integración CRUD y permisos. Requiere una base desechable con schema.sql.
use App\Config\Database;
use App\Config\Environment;
use App\Models\Category;
use App\Models\User;

require dirname(__DIR__) . '/vendor/autoload.php';
Environment::load(dirname(__DIR__) . '/.env');
$db = Database::connection();
$users = new User($db);
$emailA = 'category-a-' . bin2hex(random_bytes(6)) . '@example.test';
$emailB = 'category-b-' . bin2hex(random_bytes(6)) . '@example.test';
$userA = $users->create('Usuario categorías A', $emailA, 'ClaveSegura-2026');
$userB = $users->create('Usuario categorías B', $emailB, 'ClaveSegura-2026');
$model = new Category($db);
$category = 0;
$inUse = 0;
try {
    $category = $model->create($userA, 'Salario', 'ingreso', '#198754');
    if ($category < 1 || $model->findForUser($category, $userA)['tipo'] !== 'ingreso') throw new RuntimeException('Falló la creación/consulta de categoría.');
    if ($model->findForUser($category, $userB) !== null) throw new RuntimeException('Otro usuario pudo consultar la categoría.');
    if ($model->updateForUser($category, $userB, 'Alterada', 'gasto', null)) throw new RuntimeException('Otro usuario pudo modificar la categoría.');
    $model->updateForUser($category, $userA, 'Salario mensual', 'ingreso', '#22aa66');
    if (($model->findForUser($category, $userA)['nombre'] ?? '') !== 'Salario mensual') throw new RuntimeException('Falló la actualización.');
    if ($model->deleteForUser($category, $userB)) throw new RuntimeException('Otro usuario pudo eliminar la categoría.');
    if (!$model->deleteForUser($category, $userA) || $model->findForUser($category, $userA) !== null) throw new RuntimeException('Falló la eliminación.');

    $inUse = $model->create($userA, 'Comida', 'gasto', null);
    $db->prepare('INSERT INTO movimientos (usuario_id, categoria_id, tipo, monto, descripcion, fecha) VALUES (:usuario_id, :categoria_id, :tipo, :monto, :descripcion, :fecha)')->execute(['usuario_id' => $userA, 'categoria_id' => $inUse, 'tipo' => 'gasto', 'monto' => '12.50', 'descripcion' => 'Prueba categoría', 'fecha' => date('Y-m-d')]);
    if (!$model->hasMovements($inUse, $userA)) throw new RuntimeException('No detectó movimientos asociados.');
    if ($model->deleteForUser($inUse, $userA) || $model->findForUser($inUse, $userA) === null) throw new RuntimeException('Eliminó una categoría en uso.');
    echo "CRUD, aislamiento por usuario y protección de categorías en uso: OK\n";
} finally {
    $db->prepare('DELETE FROM usuarios WHERE id IN (:a, :b)')->execute(['a' => $userA, 'b' => $userB]);
}
