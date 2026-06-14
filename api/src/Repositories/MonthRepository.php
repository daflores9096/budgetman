<?php

declare(strict_types=1);

namespace BudgetMan\Repositories;

use PDOException;

final class MonthRepository extends BaseRepository
{
    public function listWithTotals(): array
    {
        $stmt = $this->db()->query(
            'SELECT bm.id, bm.year, bm.month,
              (SELECT COALESCE(SUM(amount),0) FROM incomes WHERE budget_month_id = bm.id) AS total_income,
              (SELECT COALESCE(SUM(actual_amount),0) FROM expenses WHERE budget_month_id = bm.id) AS total_spent
             FROM budget_months bm
             ORDER BY bm.year ASC, bm.month ASC'
        );
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $r['id'] = (int) $r['id'];
            $r['year'] = (int) $r['year'];
            $r['month'] = (int) $r['month'];
            $r['total_income'] = (float) $r['total_income'];
            $r['total_spent'] = (float) $r['total_spent'];
            $r['remaining'] = $r['total_income'] - $r['total_spent'];
        }
        unset($r);

        return $rows;
    }

    public function create(int $year, int $month): int
    {
        $stmt = $this->db()->prepare('INSERT INTO budget_months (year, month) VALUES (?, ?)');
        $stmt->execute([$year, $month]);

        return (int) $this->db()->lastInsertId();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db()->prepare('SELECT id, year, month FROM budget_months WHERE id = ?');
        $stmt->execute([$id]);
        $bm = $stmt->fetch();

        return $bm ?: null;
    }

    public function delete(int $id): void
    {
        $stmt = $this->db()->prepare('DELETE FROM budget_months WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function getOrCreateId(int $year, int $month): int
    {
        $ins = $this->db()->prepare('INSERT IGNORE INTO budget_months (year, month) VALUES (?, ?)');
        $ins->execute([$year, $month]);
        $sel = $this->db()->prepare('SELECT id FROM budget_months WHERE year = ? AND month = ?');
        $sel->execute([$year, $month]);
        $row = $sel->fetch();

        return $row ? (int) $row['id'] : 0;
    }

    public function isDuplicateKey(PDOException $e): bool
    {
        return (string) $e->getCode() === '23000';
    }
}
