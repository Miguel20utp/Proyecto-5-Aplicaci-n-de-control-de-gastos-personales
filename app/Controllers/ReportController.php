<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Middleware\AuthMiddleware;
use App\Models\Category;
use App\Models\Report;
use DateTimeImmutable;

final class ReportController extends Controller
{
    public function index(): void
    {
        AuthMiddleware::requireUser();
        $filters = $this->filters($_GET);
        $errors = $this->validate($filters);
        $model = new Report(Database::connection());
        $userId = (int) $_SESSION['user']['id'];
        if ($errors) http_response_code(422);
        $effective = $errors ? ['desde' => '', 'hasta' => '', 'tipo' => '', 'categoria_id' => '', 'descripcion' => ''] : $filters;
        $this->view('reports/index', ['title' => 'Reportes', 'user' => $_SESSION['user'], 'filters' => $filters, 'errors' => $errors, 'categories' => (new Category(Database::connection()))->allForUser($userId), 'movements' => $model->movementsForUser($userId, $effective), 'summary' => $model->summaryForUser($userId, $effective)]);
    }

    public function export(): void
    {
        AuthMiddleware::requireUser();
        $filters = $this->filters($_GET);
        $errors = $this->validate($filters);
        if ($errors) {
            http_response_code(422);
            header('Content-Type: text/plain; charset=UTF-8');
            echo implode("\n", $errors);
            return;
        }
        $rows = (new Report(Database::connection()))->movementsForUser((int) $_SESSION['user']['id'], $filters);
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="reporte-movimientos.csv"');
        header('X-Content-Type-Options: nosniff');
        $output = fopen('php://output', 'wb');
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, ['Fecha', 'Tipo', 'Categoría', 'Descripción', 'Valor'], ';');
        foreach ($rows as $row) {
            fputcsv($output, [$row['fecha'], $row['tipo'], $this->safeCell((string) $row['categoria']), $this->safeCell((string) ($row['descripcion'] ?? '')), $row['monto']], ';');
        }
        fclose($output);
    }

    private function safeCell(string $value): string
    {
        return preg_match('/^[\x00-\x20]*[=+\-@]/u', $value) === 1 ? "'" . $value : $value;
    }

    private function filters(array $input): array
    {
        $scalar = static fn(mixed $v): string => is_scalar($v) ? trim((string) $v) : '';
        return ['desde' => $scalar($input['desde'] ?? ''), 'hasta' => $scalar($input['hasta'] ?? ''), 'tipo' => $scalar($input['tipo'] ?? ''), 'categoria_id' => $scalar($input['categoria_id'] ?? ''), 'descripcion' => $scalar($input['descripcion'] ?? '')];
    }

    private function validate(array $filters): array
    {
        $errors = [];
        foreach (['desde', 'hasta'] as $field) if ($filters[$field] !== '' && !$this->validDate($filters[$field])) $errors[] = 'Indica fechas válidas.';
        if ($filters['desde'] !== '' && $filters['hasta'] !== '' && $filters['desde'] > $filters['hasta']) $errors[] = 'La fecha inicial no puede ser posterior a la final.';
        if ($filters['tipo'] !== '' && !in_array($filters['tipo'], ['ingreso', 'gasto'], true)) $errors[] = 'Selecciona un tipo válido.';
        if ($filters['categoria_id'] !== '' && (!filter_var($filters['categoria_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) || !(new Category(Database::connection()))->findForUser((int) $filters['categoria_id'], (int) $_SESSION['user']['id']))) $errors[] = 'Selecciona una categoría propia válida.';
        if (strlen($filters['descripcion']) > 255) $errors[] = 'La descripción admite máximo 255 caracteres.';
        return $errors;
    }

    private function validDate(string $date): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }
}
