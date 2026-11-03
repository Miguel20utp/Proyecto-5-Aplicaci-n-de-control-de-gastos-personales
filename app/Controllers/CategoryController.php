<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Middleware\AuthMiddleware;
use App\Models\Category;
use App\Services\Session;
use PDOException;

final class CategoryController extends Controller
{
    private function userId(): int { return (int) $_SESSION['user']['id']; }
    private function model(): Category { return new Category(Database::connection()); }

    public function index(): void
    {
        AuthMiddleware::requireUser();
        $this->view('categories/index', ['title' => 'Categorías', 'csrf' => Session::csrfToken(), 'categories' => $this->model()->allForUser($this->userId()), 'success' => Session::flash('success'), 'error' => Session::flash('error')]);
    }

    public function create(): void
    {
        AuthMiddleware::requireUser();
        $this->form();
    }

    public function store(): void
    {
        AuthMiddleware::requireUser();
        if (!$this->validCsrf()) return;
        [$name, $type, $color, $errors] = $this->input();
        if ($errors) { http_response_code(422); $this->form($errors, ['nombre' => $name, 'tipo' => $type, 'color' => $color]); return; }
        try { $this->model()->create($this->userId(), $name, $type, $color); }
        catch (PDOException $exception) { if ($exception->getCode() !== '23000') throw $exception; http_response_code(422); $this->form(['Ya existe una categoría con ese nombre y tipo.'], ['nombre' => $name, 'tipo' => $type, 'color' => $color]); return; }
        Session::flash('success', 'La categoría fue creada.');
        header('Location: /categorias', true, 303);
    }

    public function edit(): void
    {
        AuthMiddleware::requireUser();
        $category = $this->model()->findForUser($this->id(), $this->userId());
        if (!$category) { http_response_code(404); $this->notFound(); return; }
        $this->form([], $category, true);
    }

    public function update(): void
    {
        AuthMiddleware::requireUser();
        if (!$this->validCsrf()) return;
        $id = $this->id();
        $model = $this->model();
        $category = $model->findForUser($id, $this->userId());
        if (!$category) { http_response_code(404); $this->notFound(); return; }
        [$name, $type, $color, $errors] = $this->input();
        if ($errors) { http_response_code(422); $this->form($errors, ['id' => $id, 'nombre' => $name, 'tipo' => $type, 'color' => $color], true); return; }
        try { $result = $model->updateForUser($id, $this->userId(), $name, $type, $color); }
        catch (PDOException $exception) { if ($exception->getCode() !== '23000') throw $exception; http_response_code(422); $this->form(['Ya existe una categoría con ese nombre y tipo.'], ['id' => $id, 'nombre' => $name, 'tipo' => $type, 'color' => $color], true); return; }
        if ($result === 'not_found') { http_response_code(404); $this->notFound(); return; }
        if ($result === 'has_movements') { http_response_code(422); $this->form(['No se puede cambiar el tipo de una categoría que tiene movimientos asociados.'], ['id' => $id, 'nombre' => $name, 'tipo' => $type, 'color' => $color], true); return; }
        Session::flash('success', 'La categoría fue actualizada.');
        header('Location: /categorias', true, 303);
    }

    public function delete(): void
    {
        AuthMiddleware::requireUser();
        if (!$this->validCsrf()) return;
        $id = $this->id();
        $model = $this->model();
        $category = $model->findForUser($id, $this->userId());
        if (!$category) { http_response_code(404); $this->notFound(); return; }
        if ($model->hasMovements($id, $this->userId())) {
            Session::flash('error', 'No se puede eliminar esta categoría porque tiene movimientos asociados.');
        } elseif (!$model->deleteForUser($id, $this->userId())) {
            Session::flash('error', 'No se pudo eliminar la categoría. Actualiza la página e inténtalo de nuevo.');
        } else { Session::flash('success', 'La categoría fue eliminada.'); }
        header('Location: /categorias', true, 303);
    }

    private function input(): array
    {
        $name = trim((string) ($_POST['nombre'] ?? ''));
        $type = (string) ($_POST['tipo'] ?? '');
        $rawColor = trim((string) ($_POST['color'] ?? ''));
        $color = $rawColor === '' ? null : $rawColor;
        $length = function_exists('mb_strlen') ? mb_strlen($name) : strlen($name);
        $errors = [];
        if ($name === '' || $length > 80) $errors[] = 'El nombre es obligatorio y debe tener como máximo 80 caracteres.';
        if (!in_array($type, ['ingreso', 'gasto'], true)) $errors[] = 'Selecciona un tipo válido.';
        if ($color !== null && !preg_match('/^#[0-9a-fA-F]{6}$/', $color)) $errors[] = 'El color debe ser un código hexadecimal válido.';
        return [$name, $type, $color, $errors];
    }

    private function id(): int
    {
        $id = filter_var($_POST['id'] ?? $_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) { http_response_code(404); $this->notFound(); exit; }
        return (int) $id;
    }

    private function validCsrf(): bool
    {
        if (Session::verifyCsrf($_POST['_csrf'] ?? null)) return true;
        http_response_code(419); $this->view('errors/419', ['title' => 'Formulario vencido']); return false;
    }

    private function form(array $errors = [], array $values = [], bool $editing = false): void
    {
        $this->view('categories/form', ['title' => $editing ? 'Editar categoría' : 'Nueva categoría', 'csrf' => Session::csrfToken(), 'errors' => $errors, 'category' => $values, 'editing' => $editing]);
    }

    private function notFound(): void { $this->view('errors/404', ['title' => 'No encontrada']); }
}
