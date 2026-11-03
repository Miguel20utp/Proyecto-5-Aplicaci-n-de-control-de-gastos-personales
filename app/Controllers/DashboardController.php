<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Middleware\AuthMiddleware;
use App\Models\Dashboard;
use App\Services\Session;
use DateTimeImmutable;

final class DashboardController extends Controller
{
    public function index(): void
    {
        AuthMiddleware::requireUser();
        [$period, $from, $to, $errors] = self::resolvePeriod($_GET, new DateTimeImmutable('today'));
        if ($errors) {
            http_response_code(422);
            $period = 'todo'; $from = null; $to = null;
        }
        $model = new Dashboard(Database::connection());
        $userId = (int) $_SESSION['user']['id'];
        $this->view('dashboard/index', [
            'title' => 'Dashboard', 'user' => $_SESSION['user'], 'csrf' => Session::csrfToken(),
            'success' => Session::flash('success'), 'filters' => ['periodo' => $period, 'desde' => $period === 'personalizado' ? self::scalar($_GET['desde'] ?? '') : '', 'hasta' => $period === 'personalizado' ? self::scalar($_GET['hasta'] ?? '') : ''],
            'errors' => $errors, 'summary' => $model->summary($userId, $from, $to),
            'monthly' => $model->monthlyComparison($userId, $from, $to), 'categories' => $model->expensesByCategory($userId, $from, $to),
            'evolution' => $model->balanceEvolution($userId, $from, $to), 'recent' => $model->recentMovements($userId, $from, $to),
        ]);
    }

    /** @return array{string, ?string, ?string, list<string>} */
    public static function resolvePeriod(array $query, ?DateTimeImmutable $today = null): array
    {
        $today ??= new DateTimeImmutable('today');
        $period = self::scalar($query['periodo'] ?? 'todo');
        $from = null;
        $to = null;
        $errors = [];
        switch ($period) {
            case 'todo':
                break;
            case 'mes_actual':
                $from = $today->modify('first day of this month')->format('Y-m-d');
                $to = $today->modify('last day of this month')->format('Y-m-d');
                break;
            case 'mes_anterior':
                $previous = $today->modify('first day of last month');
                $from = $previous->format('Y-m-d');
                $to = $previous->modify('last day of this month')->format('Y-m-d');
                break;
            case 'anio_actual':
                $from = $today->format('Y-01-01');
                $to = $today->format('Y-12-31');
                break;
            case 'personalizado':
                $from = trim(self::scalar($query['desde'] ?? ''));
                $to = trim(self::scalar($query['hasta'] ?? ''));
                if (!self::validDate($from) || !self::validDate($to)) $errors[] = 'Indica una fecha inicial y final vÃ¡lidas.';
                elseif ($from > $to) $errors[] = 'La fecha inicial no puede ser posterior a la final.';
                break;
            default:
                $errors[] = 'Selecciona un perÃ­odo vÃ¡lido.';
        }
        return [$period, $from, $to, $errors];
    }

    private static function scalar(mixed $value): string { return is_scalar($value) ? (string) $value : ''; }
    private static function validDate(string $date): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }
}
