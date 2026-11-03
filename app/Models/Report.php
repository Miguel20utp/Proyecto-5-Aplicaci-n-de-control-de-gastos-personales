<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class Report
{
    public function __construct(private PDO $db) {}

    public function movementsForUser(int $userId, array $filters): array
    {
        [$where, $params] = $this->conditions($userId, $filters);
        $statement = $this->db->prepare("SELECT m.fecha, m.tipo, c.nombre AS categoria, m.descripcion, m.monto FROM movimientos m INNER JOIN categorias c ON c.id = m.categoria_id AND c.usuario_id = m.usuario_id WHERE {$where} ORDER BY m.fecha DESC, m.id DESC");
        $statement->execute($params);
        return $statement->fetchAll();
    }

    public function summaryForUser(int $userId, array $filters): array
    {
        [$where, $params] = $this->conditions($userId, $filters);
        $statement = $this->db->prepare("SELECT COALESCE(SUM(CASE WHEN m.tipo = 'ingreso' THEN m.monto ELSE 0 END), 0) AS ingresos, COALESCE(SUM(CASE WHEN m.tipo = 'gasto' THEN m.monto ELSE 0 END), 0) AS gastos, COUNT(*) AS cantidad FROM movimientos m INNER JOIN categorias c ON c.id = m.categoria_id AND c.usuario_id = m.usuario_id WHERE {$where}");
        $statement->execute($params);
        $row = $statement->fetch() ?: [];
        $income = (float) ($row['ingresos'] ?? 0);
        $expense = (float) ($row['gastos'] ?? 0);
        return ['ingresos' => $income, 'gastos' => $expense, 'saldo' => $income - $expense, 'cantidad' => (int) ($row['cantidad'] ?? 0)];
    }

    private function conditions(int $userId, array $filters): array
    {
        $where = 'm.usuario_id = :usuario_id';
        $params = ['usuario_id' => $userId];
        foreach (['desde' => '>=', 'hasta' => '<='] as $key => $operator) {
            if (($filters[$key] ?? '') !== '') { $where .= " AND m.fecha {$operator} :{$key}"; $params[$key] = $filters[$key]; }
        }
        if (($filters['tipo'] ?? '') !== '') { $where .= ' AND m.tipo = :tipo'; $params['tipo'] = $filters['tipo']; }
        if (($filters['categoria_id'] ?? '') !== '') { $where .= ' AND m.categoria_id = :categoria_id'; $params['categoria_id'] = (int) $filters['categoria_id']; }
        if (($filters['descripcion'] ?? '') !== '') { $where .= ' AND m.descripcion LIKE :descripcion'; $params['descripcion'] = '%' . $filters['descripcion'] . '%'; }
        return [$where, $params];
    }
}
