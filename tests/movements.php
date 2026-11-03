<?php
declare(strict_types=1);

// Prueba de integración del modelo. Requiere una base desechable con schema.sql.
use App\Config\Database;
use App\Config\Environment;
use App\Models\Category;
use App\Models\Movement;
use App\Models\User;

require dirname(__DIR__) . '/vendor/autoload.php';
Environment::load(dirname(__DIR__) . '/.env');
$db = Database::connection();
$users = new User($db);
$categories = new Category($db);
$model = new Movement($db);
$userA = null;
$userB = null;

try {
    $userA = $users->create('Usuario movimientos A', 'movement-a-' . bin2hex(random_bytes(6)) . '@example.test', 'ClaveSegura-2026');
    $userB = $users->create('Usuario movimientos B', 'movement-b-' . bin2hex(random_bytes(6)) . '@example.test', 'ClaveSegura-2026');
    $income = $categories->create($userA, 'Salario prueba', 'ingreso', null);
    $expense = $categories->create($userA, 'Comida prueba', 'gasto', null);
    $foreign = $categories->create($userB, 'Ajena prueba', 'gasto', null);

    $id = $model->create($userA, $income, 'ingreso', '1250.50', 'Pago de prueba', '2026-06-10');
    $foreignId = $model->create($userB, $foreign, 'gasto', '20.00', 'Movimiento privado', '2026-06-10');
    if ($id < 1 || $foreignId < 1 || count($model->allForUser($userA)) !== 1) throw new RuntimeException('Falló registrar/consultar o se filtró un movimiento ajeno.');
    if ($model->findForUser($id, $userB) !== null) throw new RuntimeException('Otro usuario consultó movimiento ajeno.');
    if ($model->create($userA, $foreign, 'gasto', '10.00', null, '2026-06-10') !== 0) throw new RuntimeException('Aceptó categoría ajena.');
    if ($model->create($userA, $income, 'gasto', '10.00', null, '2026-06-10') !== 0) throw new RuntimeException('Aceptó tipo distinto a categoría.');

    if (!$model->updateForUser($id, $userA, $expense, 'gasto', '12.25', 'Almuerzo', '2026-06-11')) throw new RuntimeException('Falló editar movimiento.');
    if ($model->updateForUser($id, $userB, $expense, 'gasto', '1.00', null, '2026-06-11')) throw new RuntimeException('Otro usuario editó movimiento ajeno.');
    if ($model->updateForUser($id, $userA, $foreign, 'gasto', '1.00', null, '2026-06-11')) throw new RuntimeException('Aceptó categoría ajena al editar.');
    if ($model->updateForUser($id, $userA, $income, 'gasto', '1.00', null, '2026-06-11')) throw new RuntimeException('Aceptó un tipo incompatible al editar.');

    if (count($model->allForUser($userA, ['tipo' => 'gasto'])) !== 1 || count($model->allForUser($userA, ['tipo' => 'ingreso'])) !== 0) throw new RuntimeException('Falló filtro por tipo.');
    if (count($model->allForUser($userA, ['categoria_id' => (string) $expense])) !== 1) throw new RuntimeException('Falló filtro por categoría.');
    if (count($model->allForUser($userA, ['categoria_id' => (string) $foreign])) !== 0) throw new RuntimeException('El filtro por categoría mostró movimientos ajenos.');
    if (count($model->allForUser($userA, ['desde' => '2026-06-11', 'hasta' => '2026-06-11'])) !== 1 || count($model->allForUser($userA, ['hasta' => '2026-06-10'])) !== 0) throw new RuntimeException('Falló filtro por fecha.');

    if ($model->deleteForUser($foreignId, $userA) || $model->findForUser($foreignId, $userB) === null) throw new RuntimeException('Otro usuario eliminó movimiento ajeno.');
    if (!$model->deleteForUser($id, $userA) || $model->findForUser($id, $userA) !== null) throw new RuntimeException('Falló eliminación.');
    echo "CRUD de movimientos, aislamiento y filtros: OK\n";
} finally {
    // Eliminar primero movimientos evita conflictos con la FK categoría RESTRICT.
    if ($userA !== null) $db->prepare('DELETE FROM movimientos WHERE usuario_id = :id')->execute(['id' => $userA]);
    if ($userB !== null) $db->prepare('DELETE FROM movimientos WHERE usuario_id = :id')->execute(['id' => $userB]);
    if ($userA !== null) $db->prepare('DELETE FROM usuarios WHERE id = :id')->execute(['id' => $userA]);
    if ($userB !== null) $db->prepare('DELETE FROM usuarios WHERE id = :id')->execute(['id' => $userB]);
}
