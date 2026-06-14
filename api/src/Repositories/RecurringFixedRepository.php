<?php

declare(strict_types=1);

namespace BudgetMan\Repositories;

final class RecurringFixedRepository extends BaseRepository
{
    public function listPending(int $year, int $month): array
    {
        $stmt = $this->db()->prepare(
            'SELECT r.id, r.title, r.expected_amount, r.category
             FROM recurring_fixed_expenses r
             LEFT JOIN recurring_fixed_expense_monthly m
               ON m.recurring_fixed_expense_id = r.id AND m.year = ? AND m.month = ?
             WHERE m.recurring_fixed_expense_id IS NULL
             ORDER BY r.title ASC'
        );
        $stmt->execute([$year, $month]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $r['id'] = (int) $r['id'];
            $r['title'] = (string) $r['title'];
            $r['expected_amount'] = (float) $r['expected_amount'];
            $r['category'] = (string) $r['category'];
        }
        unset($r);

        return $rows;
    }

    public function listAll(): array
    {
        $stmt = $this->db()->query('SELECT id, title, expected_amount, category FROM recurring_fixed_expenses ORDER BY title ASC');
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $r['id'] = (int) $r['id'];
            $r['title'] = (string) $r['title'];
            $r['expected_amount'] = (float) $r['expected_amount'];
            $r['category'] = (string) $r['category'];
        }
        unset($r);

        return $rows;
    }

    public function findById(int $id): ?array
    {
        $row = $this->db()->prepare('SELECT id, title, expected_amount, category FROM recurring_fixed_expenses WHERE id = ?');
        $row->execute([$id]);
        $tpl = $row->fetch();

        return $tpl ?: null;
    }

    public function exists(int $id): bool
    {
        $exists = $this->db()->prepare('SELECT id FROM recurring_fixed_expenses WHERE id = ?');
        $exists->execute([$id]);

        return (bool) $exists->fetch();
    }

    public function create(string $title, float $expected, string $category): int
    {
        $ins = $this->db()->prepare('INSERT INTO recurring_fixed_expenses (title, expected_amount, category) VALUES (?,?,?)');
        $ins->execute([$title, $expected, $category]);

        return (int) $this->db()->lastInsertId();
    }

    public function update(int $id, array $fields, array $params): void
    {
        $params[] = $id;
        $sql = 'UPDATE recurring_fixed_expenses SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
    }

    public function delete(int $id): int
    {
        $del = $this->db()->prepare('DELETE FROM recurring_fixed_expenses WHERE id = ?');
        $del->execute([$id]);

        return $del->rowCount();
    }

    public function isPaidForMonth(int $recurringId, int $year, int $month): bool
    {
        $chk = $this->db()->prepare(
            'SELECT 1 FROM recurring_fixed_expense_monthly WHERE recurring_fixed_expense_id = ? AND year = ? AND month = ?'
        );
        $chk->execute([$recurringId, $year, $month]);

        return (bool) $chk->fetch();
    }

    public function linkMonthly(int $recurringId, int $year, int $month, int $expenseId): void
    {
        $link = $this->db()->prepare(
            'INSERT INTO recurring_fixed_expense_monthly (recurring_fixed_expense_id, year, month, expense_id) VALUES (?,?,?,?)'
        );
        $link->execute([$recurringId, $year, $month, $expenseId]);
    }
}
