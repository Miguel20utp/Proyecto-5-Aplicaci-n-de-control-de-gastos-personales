<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class Category
{
    public function __construct(private PDO $db) {}

    public function allForUser(int $userId): array
    {
        $statement = $this->db->prepare('SELECT c.id, c.nombre, c.tipo, c.color, c.creado_en, (SELECT COUNT(*) FROM movimientos m WHERE m.categoria_id = c.id) AS movimientos FROM categorias c WHERE c.usuario_id = :usuario_id ORDER BY c.tipo, c.nombre');
        $statement->execute(['usuario_id' => $userId]);
        return $statement->fetchAll();
    }

    public function findForUser(int $id, int $userId): ?array
    {
        $statement = $this->db->prepare('SELECT id, nombre, tipo, color FROM categorias WHERE id = :id AND usuario_id = :usuario_id LIMIT 1');
        $statement->execute(['id' => $id, 'usuario_id' => $userId]);
        $category = $statement->fetch();
        return is_array($category) ? $category : null;
    }

    public function create(int $userId, string $name, string $type, ?string $color): int
    {
        $statement = $this->db->prepare('INSERT INTO categorias (usuario_id, nombre, tipo, color) VALUES (:usuario_id, :nombre, :tipo, :color)');
        $statement->execute(['usuario_id' => $userId, 'nombre' => $name, 'tipo' => $type, 'color' => $color]);
        return (int) $this->db->lastInsertId();
    }

    public function updateForUser(int $id, int $userId, string $name, string $type, ?string $color): string
    {
        $this->db->beginTransaction();
        try {
            $lock = $this->db->prepare('SELECT tipo FROM categorias WHERE id = :id AND usuario_id = :usuario_id FOR UPDATE');
            $lock->execute(['id' => $id, 'usuario_id' => $userId]);
            $category = $lock->fetch();
            if (!is_array($category)) {
                $this->db->commit();
                return 'not_found';
            }

            $usage = $this->db->prepare('SELECT COUNT(*) FROM movimientos WHERE categoria_id = :id FOR UPDATE');
            $usage->execute(['id' => $id]);
            if ((int) $usage->fetchColumn() > 0 && $type !== $category['tipo']) {
                $this->db->commit();
                return 'has_movements';
            }

            $statement = $this->db->prepare('UPDATE categorias SET nombre = :nombre, tipo = :tipo, color = :color WHERE id = :id AND usuario_id = :usuario_id');
            $statement->execute(['nombre' => $name, 'tipo' => $type, 'color' => $color, 'id' => $id, 'usuario_id' => $userId]);
            $this->db->commit();
            return 'updated';
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    public function deleteForUser(int $id, int $userId): bool
    {
        $this->db->beginTransaction();
        try {
            $lock = $this->db->prepare('SELECT id FROM categorias WHERE id = :id AND usuario_id = :usuario_id FOR UPDATE');
            $lock->execute(['id' => $id, 'usuario_id' => $userId]);
            if (!$lock->fetch()) { $this->db->commit(); return false; }

            $usage = $this->db->prepare('SELECT COUNT(*) FROM movimientos WHERE categoria_id = :id');
            $usage->execute(['id' => $id]);
            if ((int) $usage->fetchColumn() > 0) { $this->db->commit(); return false; }

            $delete = $this->db->prepare('DELETE FROM categorias WHERE id = :id AND usuario_id = :usuario_id');
            $delete->execute(['id' => $id, 'usuario_id' => $userId]);
            $this->db->commit();
            return $delete->rowCount() === 1;
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    public function hasMovements(int $id, int $userId): bool
    {
        $statement = $this->db->prepare('SELECT COUNT(*) FROM movimientos WHERE categoria_id = :id');
        $statement->execute(['id' => $id]);
        return (int) $statement->fetchColumn() > 0;
    }
}
