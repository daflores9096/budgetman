<?php

declare(strict_types=1);

namespace BudgetMan\Repositories;

final class IncomeRepository extends BaseRepository
{
    public function listByMonth(int $monthId): array
    {
        $inc = $this->db()->prepare(
            'SELECT id, entry_date AS date, description, amount
             FROM incomes WHERE budget_month_id = ? ORDER BY entry_date ASC, id ASC'
        );
        $inc->execute([$monthId]);
        $incomes = $inc->fetchAll();
        foreach ($incomes as &$i) {
            $i['id'] = (int) $i['id'];
            $i['amount'] = (float) $i['amount'];
        }
        unset($i);

        return $incomes;
    }

    public function listByDateRange(string $start, string $end): array
    {
        $inc = $this->db()->prepare(
            'SELECT i.id, i.entry_date AS date, i.description, i.amount
             FROM incomes i
             WHERE i.entry_date BETWEEN ? AND ?
             ORDER BY i.entry_date ASC, i.id ASC'
        );
        $inc->execute([$start, $end]);
        $incomes = $inc->fetchAll();
        foreach ($incomes as &$i) {
            $i['id'] = (int) $i['id'];
            $i['amount'] = (float) $i['amount'];
        }
        unset($i);

        return $incomes;
    }

    public function create(int $monthId, string $date, string $description, float $amount, int $userId): int
    {
        $stmt = $this->db()->prepare(
            'INSERT INTO incomes (budget_month_id, entry_date, description, amount, user_id) VALUES (?,?,?,?,?)'
        );
        $stmt->execute([$monthId, $date, $description, $amount, $userId]);

        return (int) $this->db()->lastInsertId();
    }

    public function update(int $id, array $fields, array $params): void
    {
        $params[] = $id;
        $sql = 'UPDATE incomes SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db()->prepare('DELETE FROM incomes WHERE id = ?');
        $stmt->execute([$id]);
    }
}
