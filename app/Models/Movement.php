<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class Movement
{
    public function __construct(private PDO $db) {}

    public function allForUser(int $userId, array $filters = []): array
    {
        $sql = 'SELECT m.id, m.categoria_id, c.nombre AS categoria, m.tipo, m.monto, m.descripcion, m.fecha FROM movimientos m INNER JOIN categorias c ON c.id = m.categoria_id AND c.usuario_id = m.usuario_id WHERE m.usuario_id = :usuario_id';
        $params = ['usuario_id' => $userId];
        if (($filters['desde'] ?? '') !== '') { $sql .= ' AND m.fecha >= :desde'; $params['desde'] = $filters['desde']; }
        if (($filters['hasta'] ?? '') !== '') { $sql .= ' AND m.fecha <= :hasta'; $params['hasta'] = $filters['hasta']; }
        if (($filters['categoria_id'] ?? '') !== '') { $sql .= ' AND m.categoria_id = :categoria_id'; $params['categoria_id'] = (int) $filters['categoria_id']; }
        if (($filters['tipo'] ?? '') !== '') { $sql .= ' AND m.tipo = :tipo'; $params['tipo'] = $filters['tipo']; }
        $sql .= ' ORDER BY m.fecha DESC, m.id DESC';
        $statement = $this->db->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    }

    public function findForUser(int $id, int $userId): ?array
    {
        $statement = $this->db->prepare('SELECT m.id, m.categoria_id, m.tipo, m.monto, m.descripcion, m.fecha FROM movimientos m WHERE m.id = :id AND m.usuario_id = :usuario_id');
        $statement->execute(['id' => $id, 'usuario_id' => $userId]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    public function create(int $userId, int $categoryId, string $type, string $amount, ?string $description, string $date): int
    {
        $statement = $this->db->prepare('INSERT INTO movimientos (usuario_id, categoria_id, tipo, monto, descripcion, fecha) SELECT :usuario_id, c.id, :tipo, :monto, :descripcion, :fecha FROM categorias c WHERE c.id = :categoria_id AND c.usuario_id = :usuario_id_categoria AND c.tipo = :tipo_categoria');
        $statement->execute(['usuario_id' => $userId, 'tipo' => $type, 'monto' => $amount, 'descripcion' => $description, 'fecha' => $date, 'categoria_id' => $categoryId, 'usuario_id_categoria' => $userId, 'tipo_categoria' => $type]);
        return $statement->rowCount() === 1 ? (int) $this->db->lastInsertId() : 0;
    }

    public function updateForUser(int $id, int $userId, int $categoryId, string $type, string $amount, ?string $description, string $date): bool
    {
        $category = $this->db->prepare('SELECT id FROM categorias WHERE id = :id AND usuario_id = :usuario_id AND tipo = :tipo');
        $category->execute(['id' => $categoryId, 'usuario_id' => $userId, 'tipo' => $type]);
        if (!$category->fetchColumn()) return false;
        $statement = $this->db->prepare('UPDATE movimientos m INNER JOIN categorias c ON c.id = :categoria_id AND c.usuario_id = :categoria_usuario_id AND c.tipo = :categoria_tipo SET m.categoria_id = c.id, m.tipo = :tipo, m.monto = :monto, m.descripcion = :descripcion, m.fecha = :fecha WHERE m.id = :id AND m.usuario_id = :usuario_id');
        $statement->execute(['categoria_id' => $categoryId, 'categoria_usuario_id' => $userId, 'categoria_tipo' => $type, 'tipo' => $type, 'monto' => $amount, 'descripcion' => $description, 'fecha' => $date, 'id' => $id, 'usuario_id' => $userId]);
        return $this->findForUser($id, $userId) !== null;
    }

    public function deleteForUser(int $id, int $userId): bool
    {
        $statement = $this->db->prepare('DELETE FROM movimientos WHERE id = :id AND usuario_id = :usuario_id');
        $statement->execute(['id' => $id, 'usuario_id' => $userId]);
        return $statement->rowCount() === 1;
    }
}
