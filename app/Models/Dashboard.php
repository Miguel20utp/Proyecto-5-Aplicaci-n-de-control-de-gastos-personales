<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class Dashboard
{
    public function __construct(private PDO $db) {}

    public function summary(int $userId, ?string $from = null, ?string $to = null): array
    {
        [$where, $params] = $this->period($userId, $from, $to);
        $sql = "SELECT COALESCE(SUM(CASE WHEN tipo = 'ingreso' THEN monto ELSE 0 END), 0) AS ingresos,
                       COALESCE(SUM(CASE WHEN tipo = 'gasto' THEN monto ELSE 0 END), 0) AS gastos,
                       COUNT(*) AS movimientos FROM movimientos WHERE {$where}";
        $statement = $this->db->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch() ?: [];
        $income = (float) ($row['ingresos'] ?? 0);
        $expense = (float) ($row['gastos'] ?? 0);
        return ['ingresos' => $income, 'gastos' => $expense, 'saldo' => $income - $expense, 'cantidad' => (int) ($row['movimientos'] ?? 0)];
    }

    public function monthlyComparison(int $userId, ?string $from, ?string $to): array
    {
        [$where, $params] = $this->period($userId, $from, $to);
        $sql = "SELECT DATE_FORMAT(fecha, '%Y-%m') AS periodo,
                       COALESCE(SUM(CASE WHEN tipo = 'ingreso' THEN monto ELSE 0 END), 0) AS ingresos,
                       COALESCE(SUM(CASE WHEN tipo = 'gasto' THEN monto ELSE 0 END), 0) AS gastos
                FROM movimientos WHERE {$where} GROUP BY DATE_FORMAT(fecha, '%Y-%m') ORDER BY periodo";
        $statement = $this->db->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    }

    public function expensesByCategory(int $userId, ?string $from, ?string $to): array
    {
        [$where, $params] = $this->period($userId, $from, $to, 'm');
        $sql = "SELECT c.id, c.nombre AS categoria, c.color, COALESCE(SUM(m.monto), 0) AS total
                FROM movimientos m INNER JOIN categorias c ON c.id = m.categoria_id AND c.usuario_id = m.usuario_id
                WHERE {$where} AND m.tipo = 'gasto' GROUP BY c.id, c.nombre, c.color ORDER BY total DESC";
        $statement = $this->db->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    }

    public function balanceEvolution(int $userId, ?string $from, ?string $to): array
    {
        $opening = 0.0;
        if ($from !== null) {
            $statement = $this->db->prepare('SELECT COALESCE(SUM(CASE WHEN tipo = :income THEN monto ELSE -monto END), 0) FROM movimientos WHERE usuario_id = :user_id AND fecha < :from_date');
            $statement->execute(['income' => 'ingreso', 'user_id' => $userId, 'from_date' => $from]);
            $opening = (float) $statement->fetchColumn();
        }
        [$where, $params] = $this->period($userId, $from, $to, 'm');
        $statement = $this->db->prepare("SELECT m.fecha, COALESCE(SUM(CASE WHEN m.tipo = 'ingreso' THEN m.monto ELSE -m.monto END), 0) AS neto FROM movimientos m WHERE {$where} GROUP BY m.fecha ORDER BY m.fecha");
        $statement->execute($params);
        $rows = $statement->fetchAll();
        $balance = $opening;
        $result = [];
        foreach ($rows as $row) {
            $balance += (float) $row['neto'];
            $result[] = ['fecha' => $row['fecha'], 'saldo' => $balance];
        }
        return $result;
    }

    public function recentMovements(int $userId, ?string $from, ?string $to, int $limit = 8): array
    {
        [$where, $params] = $this->period($userId, $from, $to, 'm');
        $statement = $this->db->prepare("SELECT m.id, m.tipo, m.monto, m.descripcion, m.fecha, c.nombre AS categoria FROM movimientos m INNER JOIN categorias c ON c.id = m.categoria_id AND c.usuario_id = m.usuario_id WHERE {$where} ORDER BY m.fecha DESC, m.id DESC LIMIT {$limit}");
        $statement->execute($params);
        return $statement->fetchAll();
    }

    private function period(int $userId, ?string $from, ?string $to, string $alias = ''): array
    {
        $prefix = $alias === '' ? '' : $alias . '.';
        $where = $prefix . 'usuario_id = :user_id';
        $params = ['user_id' => $userId];
        if ($from !== null) { $where .= ' AND ' . $prefix . 'fecha >= :from_date'; $params['from_date'] = $from; }
        if ($to !== null) { $where .= ' AND ' . $prefix . 'fecha <= :to_date'; $params['to_date'] = $to; }
        return [$where, $params];
    }
}
