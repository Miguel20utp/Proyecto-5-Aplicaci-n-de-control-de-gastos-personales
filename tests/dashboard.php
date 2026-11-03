<?php
declare(strict_types=1);

// Integración del modelo; requiere una base desechable con database/schema.sql.
use App\Config\Database;
use App\Config\Environment;
use App\Controllers\DashboardController;
use App\Models\Category;
use App\Models\Dashboard;
use App\Models\Movement;
use App\Models\User;

require dirname(__DIR__) . '/vendor/autoload.php';
Environment::load(dirname(__DIR__) . '/.env');
$db = Database::connection();
$users = new User($db);
$categories = new Category($db);
$movements = new Movement($db);
$dashboard = new Dashboard($db);
$userA = $userB = null;

$fixedToday = new DateTimeImmutable('2026-11-02');
$expectedPeriods = [
    'todo' => ['todo', null, null],
    'mes_actual' => ['mes_actual', '2026-11-01', '2026-11-30'],
    'mes_anterior' => ['mes_anterior', '2026-10-01', '2026-10-31'],
    'anio_actual' => ['anio_actual', '2026-01-01', '2026-12-31'],
    'personalizado' => ['personalizado', '2026-01-01', '2026-01-31'],
];
foreach ($expectedPeriods as $key => $expected) {
    $query = ['periodo' => $key];
    if ($key === 'personalizado') $query += ['desde' => '2026-01-01', 'hasta' => '2026-01-31'];
    [$resolved, $from, $to, $errors] = DashboardController::resolvePeriod($query, $fixedToday);
    if ([$resolved, $from, $to] !== $expected || $errors !== []) throw new RuntimeException("Resolución incorrecta del período {$key}.");
}
foreach ([
    ['periodo' => 'personalizado', 'desde' => '2026-02-30', 'hasta' => '2026-03-01'],
    ['periodo' => 'personalizado', 'desde' => '2026-01-02', 'hasta' => '2026-01-01'],
    ['periodo' => 'personalizado', 'desde' => ['2026-01-01'], 'hasta' => '2026-01-31'],
    ['periodo' => 'desconocido'],
] as $invalidQuery) {
    [, , , $errors] = DashboardController::resolvePeriod($invalidQuery, $fixedToday);
    if (!$errors) throw new RuntimeException('Se aceptó un filtro de período inválido.');
}

try {
    $suffix = bin2hex(random_bytes(6));
    $userA = $users->create('Dashboard A', "dashboard-a-{$suffix}@example.test", 'ClaveSegura-2026');
    $userB = $users->create('Dashboard B', "dashboard-b-{$suffix}@example.test", 'ClaveSegura-2026');
    $incomeCategory = $categories->create($userA, 'Ingreso dashboard', 'ingreso', null);
    $foodCategory = $categories->create($userA, 'Comida dashboard', 'gasto', '#198754');
    $homeCategory = $categories->create($userA, 'Hogar dashboard', 'gasto', null);
    $foreignCategory = $categories->create($userB, 'Ajena dashboard', 'gasto', null);
    $movements->create($userA, $incomeCategory, 'ingreso', '1000.00', 'Ingreso periodo', '2026-01-05');
    $movements->create($userA, $foodCategory, 'gasto', '100.00', 'Gasto periodo', '2026-01-10');
    $movements->create($userA, $homeCategory, 'gasto', '50.00', 'Otro gasto', '2026-02-10');
    $movements->create($userA, $incomeCategory, 'ingreso', '200.00', 'Ingreso posterior', '2026-03-01');
    $movements->create($userB, $foreignCategory, 'gasto', '9999.00', 'Privado', '2026-01-10');

    $all = $dashboard->summary($userA);
    if ($all['ingresos'] !== 1200.0 || $all['gastos'] !== 150.0 || $all['saldo'] !== 1050.0 || $all['cantidad'] !== 4) throw new RuntimeException('Cálculo total de ingresos/gastos/saldo/cantidad incorrecto.');
    $jan = $dashboard->summary($userA, '2026-01-01', '2026-01-31');
    if ($jan['ingresos'] !== 1000.0 || $jan['gastos'] !== 100.0 || $jan['saldo'] !== 900.0 || $jan['cantidad'] !== 2) throw new RuntimeException('El filtro de fechas no calculó el período correctamente.');
    if ($all['ingresos'] === $jan['ingresos'] || $all['gastos'] === $jan['gastos'] || $all['cantidad'] === $jan['cantidad']) throw new RuntimeException('El historial completo no se distingue del período filtrado.');
    $janMonthly = $dashboard->monthlyComparison($userA, '2026-01-01', '2026-01-31');
    if (count($janMonthly) !== 1 || $janMonthly[0]['periodo'] !== '2026-01' || (float) $janMonthly[0]['ingresos'] !== 1000.0 || (float) $janMonthly[0]['gastos'] !== 100.0) throw new RuntimeException('La comparación mensual no respeta el período.');
    $byCategory = $dashboard->expensesByCategory($userA, '2026-01-01', '2026-02-28');
    if (count($byCategory) !== 2 || (float) $byCategory[0]['total'] !== 100.0) throw new RuntimeException('Estadísticas de gasto por categoría incorrectas.');
    if ((float) $dashboard->summary($userB)['gastos'] !== 9999.0 || (float) $dashboard->summary($userA)['gastos'] === 9999.0) throw new RuntimeException('El resumen no aísla los usuarios.');
    if ($dashboard->expensesByCategory($userA, null, null) === [] || count($dashboard->recentMovements($userA, null, null)) !== 4) throw new RuntimeException('Consultas de categoría o movimientos recientes incorrectas.');
    $evolution = $dashboard->balanceEvolution($userA, '2026-02-01', '2026-03-31');
    if (count($evolution) !== 2 || (float) $evolution[0]['saldo'] !== 850.0 || (float) $evolution[1]['saldo'] !== 1050.0) throw new RuntimeException('La evolución no incluye correctamente el saldo de apertura.');
    $empty = $dashboard->summary($userA, '2025-01-01', '2025-01-31');
    if ($empty['ingresos'] !== 0.0 || $empty['gastos'] !== 0.0 || $empty['saldo'] !== 0.0 || $empty['cantidad'] !== 0 || $dashboard->expensesByCategory($userA, '2025-01-01', '2025-01-31') !== []) throw new RuntimeException('El período vacío no devuelve ceros/listas vacías.');
    if ($dashboard->monthlyComparison($userA, '2025-01-01', '2025-01-31') !== [] || $dashboard->balanceEvolution($userA, '2025-01-01', '2025-01-31') !== [] || $dashboard->recentMovements($userA, '2025-01-01', '2025-01-31') !== []) throw new RuntimeException('Las consultas gráficas o recientes no manejan período vacío.');
    echo "Dashboard: cálculos, períodos, validación, aislamiento, evolución y resultados vacíos: OK\n";
} finally {
    if ($userA !== null) $db->prepare('DELETE FROM usuarios WHERE id = :id')->execute(['id' => $userA]);
    if ($userB !== null) $db->prepare('DELETE FROM usuarios WHERE id = :id')->execute(['id' => $userB]);
}
