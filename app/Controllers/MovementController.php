<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Middleware\AuthMiddleware;
use App\Models\Category;
use App\Models\Movement;
use App\Services\Session;

final class MovementController extends Controller
{
    private function userId(): int { return (int) $_SESSION['user']['id']; }
    private function model(): Movement { return new Movement(Database::connection()); }
    private function categories(): array { return (new Category(Database::connection()))->allForUser($this->userId()); }

    public function index(): void
    {
        AuthMiddleware::requireUser();
        $filters = ['desde' => trim($this->scalarString($_GET['desde'] ?? null)), 'hasta' => trim($this->scalarString($_GET['hasta'] ?? null)), 'categoria_id' => trim($this->scalarString($_GET['categoria_id'] ?? null)), 'tipo' => $this->scalarString($_GET['tipo'] ?? null)];
        $errors = $this->validateFilters($filters);
        if ($errors) { http_response_code(422); $filters = ['desde' => '', 'hasta' => '', 'categoria_id' => '', 'tipo' => '']; }
        $this->view('movements/index', ['title' => 'Movimientos', 'csrf' => Session::csrfToken(), 'movements' => $this->model()->allForUser($this->userId(), $errors ? [] : $filters), 'categories' => $this->categories(), 'filters' => $filters, 'errors' => $errors, 'success' => Session::flash('success'), 'error' => Session::flash('error')]);
    }

    public function create(): void { AuthMiddleware::requireUser(); $this->form(); }

    public function store(): void
    {
        AuthMiddleware::requireUser(); if (!$this->validCsrf()) return;
        [$values, $errors] = $this->input();
        if ($errors) { http_response_code(422); $this->form($errors, $values); return; }
        if (!$this->model()->create($this->userId(), (int) $values['categoria_id'], $values['tipo'], $values['monto'], $values['descripcion'] ?: null, $values['fecha'])) { http_response_code(422); $this->form(['La categoría debe pertenecer a tu cuenta y corresponder al tipo del movimiento.'], $values); return; }
        Session::flash('success', 'El movimiento fue registrado.'); header('Location: /movimientos', true, 303);
    }

    public function edit(): void
    {
        AuthMiddleware::requireUser(); $id = $this->id(); $movement = $this->model()->findForUser($id, $this->userId());
        if (!$movement) { http_response_code(404); $this->notFound(); return; }
        $this->form([], $movement, true);
    }

    public function update(): void
    {
        AuthMiddleware::requireUser(); if (!$this->validCsrf()) return; $id = $this->id(); $model = $this->model();
        if (!$model->findForUser($id, $this->userId())) { http_response_code(404); $this->notFound(); return; }
        [$values, $errors] = $this->input();
        if ($errors) { http_response_code(422); $values['id'] = $id; $this->form($errors, $values, true); return; }
        if (!$model->updateForUser($id, $this->userId(), (int) $values['categoria_id'], $values['tipo'], $values['monto'], $values['descripcion'] ?: null, $values['fecha'])) { http_response_code(422); $values['id'] = $id; $this->form(['La categoría debe pertenecer a tu cuenta y corresponder al tipo del movimiento.'], $values, true); return; }
        Session::flash('success', 'El movimiento fue actualizado.'); header('Location: /movimientos', true, 303);
    }

    public function delete(): void
    {
        AuthMiddleware::requireUser(); if (!$this->validCsrf()) return; $id = $this->id();
        if (!$this->model()->deleteForUser($id, $this->userId())) { http_response_code(404); $this->notFound(); return; }
        Session::flash('success', 'El movimiento fue eliminado.'); header('Location: /movimientos', true, 303);
    }

    private function input(): array
    {
        $values = ['categoria_id' => trim($this->scalarString($_POST['categoria_id'] ?? null)), 'tipo' => $this->scalarString($_POST['tipo'] ?? null), 'monto' => trim($this->scalarString($_POST['monto'] ?? null)), 'descripcion' => trim($this->scalarString($_POST['descripcion'] ?? null)), 'fecha' => trim($this->scalarString($_POST['fecha'] ?? null))];
        $errors = [];
        if (!filter_var($values['categoria_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) $errors[] = 'Selecciona una categoría válida.';
        if (!in_array($values['tipo'], ['ingreso', 'gasto'], true)) $errors[] = 'Selecciona un tipo válido.';
        if (!preg_match('/^(?:0|[1-9]\d{0,10})(?:\.\d{1,2})?$/', $values['monto']) || (float) $values['monto'] <= 0) $errors[] = 'El valor debe ser positivo y tener máximo dos decimales.';
        if (strlen($values['descripcion']) > 255) $errors[] = 'La descripción admite máximo 255 caracteres.';
        if (!$this->validDate($values['fecha'])) $errors[] = 'Selecciona una fecha válida.';
        return [$values, $errors];
    }

    private function validateFilters(array $filters): array
    {
        $errors = [];
        foreach (['desde', 'hasta'] as $field) if ($filters[$field] !== '' && !$this->validDate($filters[$field])) $errors[] = 'Indica fechas de filtro válidas.';
        if ($filters['desde'] !== '' && $filters['hasta'] !== '' && $filters['desde'] > $filters['hasta']) $errors[] = 'La fecha inicial no puede ser posterior a la final.';
        if ($filters['tipo'] !== '' && !in_array($filters['tipo'], ['ingreso', 'gasto'], true)) $errors[] = 'Selecciona un tipo de filtro válido.';
        if ($filters['categoria_id'] !== '' && (!filter_var($filters['categoria_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) || !(new Category(Database::connection()))->findForUser((int) $filters['categoria_id'], $this->userId()))) $errors[] = 'Selecciona una categoría propia válida.';
        return $errors;
    }

    private function validDate(string $date): bool { $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date); return $parsed !== false && $parsed->format('Y-m-d') === $date; }
    private function id(): int { $raw = $_POST['id'] ?? $_GET['id'] ?? null; $id = is_scalar($raw) ? filter_var((string) $raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false; if ($id === false) { http_response_code(404); $this->notFound(); exit; } return (int) $id; }
    private function scalarString(mixed $value): string { return is_scalar($value) ? (string) $value : ''; }
    private function validCsrf(): bool { $token = $_POST['_csrf'] ?? null; if (is_string($token) && Session::verifyCsrf($token)) return true; http_response_code(419); $this->view('errors/419', ['title' => 'Formulario vencido']); return false; }
    private function form(array $errors = [], array $values = [], bool $editing = false): void { $this->view('movements/form', ['title' => $editing ? 'Editar movimiento' : 'Nuevo movimiento', 'csrf' => Session::csrfToken(), 'errors' => $errors, 'movement' => $values, 'categories' => $this->categories(), 'editing' => $editing]); }
    private function notFound(): void { $this->view('errors/404', ['title' => 'No encontrado']); }
}
