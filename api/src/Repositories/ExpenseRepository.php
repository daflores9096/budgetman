<?php

declare(strict_types=1);

namespace BudgetMan\Repositories;

final class ExpenseRepository extends BaseRepository
{
    public function listByMonth(int $monthId): array
    {
        $exp = $this->db()->prepare(
            'SELECT id, expense_type AS type, entry_date AS date, description,
                    expected_amount AS expected, actual_amount AS actual, category, paid
             FROM expenses WHERE budget_month_id = ? ORDER BY entry_date ASC, id ASC'
        );
        $exp->execute([$monthId]);
        return $this->normalizeRows($exp->fetchAll());
    }

    public function listByDateRange(string $start, string $end): array
    {
        $exp = $this->db()->prepare(
            'SELECT e.id, e.expense_type AS type, e.entry_date AS date, e.description,
                    e.expected_amount AS expected, e.actual_amount AS actual, e.category, e.paid,
                    u.username AS username
             FROM expenses e
             LEFT JOIN users u ON u.id = e.user_id
             WHERE e.entry_date BETWEEN ? AND ?
             ORDER BY e.entry_date ASC, e.id ASC'
        );
        $exp->execute([$start, $end]);
        return $this->normalizeRows($exp->fetchAll());
    }

    public function findType(int $id): ?string
    {
        $row = $this->db()->prepare('SELECT expense_type FROM expenses WHERE id = ?');
        $row->execute([$id]);
        $existing = $row->fetch();

        return $existing ? (string) $existing['expense_type'] : null;
    }

    public function create(
        int $monthId,
        string $type,
        string $date,
        string $description,
        ?float $expected,
        float $actual,
        string $category,
        int $paid,
        int $userId
    ): int {
        $stmt = $this->db()->prepare(
            'INSERT INTO expenses (budget_month_id, expense_type, entry_date, description, expected_amount, actual_amount, category, paid, user_id)
             VALUES (?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([$monthId, $type, $date, $description, $expected, $actual, $category, $paid, $userId]);

        return (int) $this->db()->lastInsertId();
    }

    public function update(int $id, array $fields, array $params): void
    {
        $params[] = $id;
        $sql = 'UPDATE expenses SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db()->prepare('DELETE FROM expenses WHERE id = ?');
        $stmt->execute([$id]);
    }

    private function normalizeRows(array $rows): array
    {
        foreach ($rows as &$e) {
            $e['id'] = (int) $e['id'];
            $e['expected'] = $e['expected'] === null ? null : (float) $e['expected'];
            $e['actual'] = (float) $e['actual'];
            $e['paid'] = (bool) (int) $e['paid'];
        }
        unset($e);

        return $rows;
    }
}
