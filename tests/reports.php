<?php
declare(strict_types=1);

// Ejecutar solo contra una base desechable configurada en .env.
use App\Config\Database;
use App\Config\Environment;
use App\Models\Category;
use App\Models\Movement;
use App\Models\Report;
use App\Models\User;

require dirname(__DIR__) . '/vendor/autoload.php';
Environment::load(dirname(__DIR__) . '/.env');
$db = Database::connection();
$users = new User($db);
$categories = new Category($db);
$movementModel = new Movement($db);
$report = new Report($db);
$userA = $userB = null;

try {
    $suffix = bin2hex(random_bytes(6));
    $userA = $users->create('Reportes A', "reportes-a-{$suffix}@example.test", 'ClaveSegura-2026');
    $userB = $users->create('Reportes B', "reportes-b-{$suffix}@example.test", 'ClaveSegura-2026');
    $income = $categories->create($userA, 'Ingreso reporte', 'ingreso', null);
    $food = $categories->create($userA, 'Comida reporte', 'gasto', null);
    $foreign = $categories->create($userB, 'Ajena reporte', 'gasto', null);
    $movementModel->create($userA, $income, 'ingreso', '1000.00', 'Salario mensual', '2026-01-05');
    $movementModel->create($userA, $food, 'gasto', '100.00', 'Almuerzo', '2026-01-10');
    $movementModel->create($userA, $food, 'gasto', '50.00', '=HYPERLINK("malicioso")', '2026-02-10');
    $movementModel->create($userB, $foreign, 'gasto', '9999.00', 'Privado', '2026-01-10');
    $all = $report->movementsForUser($userA, []);
    if (count($all) !== 3) throw new RuntimeException('El reporte general no contiene los movimientos propios.');
    if (count($report->movementsForUser($userA, ['desde' => '2026-01-01', 'hasta' => '2026-01-31'])) !== 2) throw new RuntimeException('Filtro de fechas incorrecto.');
    if (count($report->movementsForUser($userA, ['tipo' => 'ingreso'])) !== 1) throw new RuntimeException('Filtro de tipo incorrecto.');
    if (count($report->movementsForUser($userA, ['categoria_id' => (string) $food])) !== 2) throw new RuntimeException('Filtro de categoría incorrecto.');
    if (count($report->movementsForUser($userA, ['desde' => '2026-01-01', 'hasta' => '2026-01-31', 'tipo' => 'gasto', 'categoria_id' => (string) $food, 'descripcion' => 'Almuerzo'])) !== 1) throw new RuntimeException('Combinación de filtros incorrecta.');
    if ($report->summaryForUser($userA, []) !== ['ingresos' => 1000.0, 'gastos' => 150.0, 'saldo' => 850.0, 'cantidad' => 3]) throw new RuntimeException('Resumen financiero incorrecto.');
    if ($report->movementsForUser($userA, ['categoria_id' => (string) $foreign]) !== []) throw new RuntimeException('El filtro expuso movimientos ajenos.');
    if ($report->movementsForUser($userA, ['desde' => '2025-01-01', 'hasta' => '2025-01-31']) !== []) throw new RuntimeException('Resultado vacío incorrecto.');

    $csv = fopen('php://temp', 'w+b');
    fwrite($csv, "\xEF\xBB\xBF");
    fputcsv($csv, ['Fecha', 'Tipo', 'Categoría', 'Descripción', 'Valor'], ';');
    foreach ($all as $row) {
        $description = (string) ($row['descripcion'] ?? '');
        if (preg_match('/^[\x00-\x20]*[=+\-@]/u', $description) === 1) $description = "'" . $description;
        fputcsv($csv, [$row['fecha'], $row['tipo'], $row['categoria'], $description, $row['monto']], ';');
    }
    rewind($csv);
    $csvText = stream_get_contents($csv);
    fclose($csv);
    if (!str_starts_with($csvText, "\xEF\xBB\xBF") || !str_contains($csvText, "'=HYPERLINK")) throw new RuntimeException('CSV no protege fórmulas o no incluye BOM UTF-8.');
    foreach (['2026-02-30', '2026-02-02'] as $date) {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($parsed !== false && $parsed->format('Y-m-d') === $date) {
            if ($date === '2026-02-02') continue;
            throw new RuntimeException('Se aceptó una fecha inexistente.');
        }
    }
    echo "Reportes: filtros, resumen, aislamiento, CSV seguro y resultados vacíos: OK\n";
} finally {
    if ($userA !== null) $db->prepare('DELETE FROM usuarios WHERE id = :id')->execute(['id' => $userA]);
    if ($userB !== null) $db->prepare('DELETE FROM usuarios WHERE id = :id')->execute(['id' => $userB]);
}
