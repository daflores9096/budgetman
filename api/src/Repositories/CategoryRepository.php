<?php

declare(strict_types=1);

namespace BudgetMan\Repositories;

use PDOException;

final class CategoryRepository extends BaseRepository
{
    public function listAll(): array
    {
        $stmt = $this->db()->query('SELECT id, name FROM categories ORDER BY name ASC');
        $items = $stmt->fetchAll();
        foreach ($items as &$r) {
            $r['id'] = (int) $r['id'];
            $r['name'] = (string) $r['name'];
        }
        unset($r);

        return $items;
    }

    public function existsByName(string $name): bool
    {
        $stmt = $this->db()->prepare('SELECT 1 FROM categories WHERE name = ?');
        $stmt->execute([$name]);

        return (bool) $stmt->fetchColumn();
    }

    public function findById(int $id): ?array
    {
        $row = $this->db()->prepare('SELECT id, name FROM categories WHERE id = ?');
        $row->execute([$id]);
        $existing = $row->fetch();

        return $existing ?: null;
    }

    public function create(string $name): int
    {
        $stmt = $this->db()->prepare('INSERT INTO categories (name) VALUES (?)');
        $stmt->execute([$name]);

        return (int) $this->db()->lastInsertId();
    }

    public function updateName(int $id, string $name): void
    {
        $upd = $this->db()->prepare('UPDATE categories SET name = ? WHERE id = ?');
        $upd->execute([$name, $id]);
    }

    public function reassignExpenses(string $from, string $to): void
    {
        $exp = $this->db()->prepare('UPDATE expenses SET category = ? WHERE category = ?');
        $exp->execute([$to, $from]);
    }

    public function ensureExists(string $name): void
    {
        $ensure = $this->db()->prepare('INSERT IGNORE INTO categories (name) VALUES (?)');
        $ensure->execute([$name]);
    }

    public function delete(int $id): void
    {
        $del = $this->db()->prepare('DELETE FROM categories WHERE id = ?');
        $del->execute([$id]);
    }

    public function isDuplicateKey(PDOException $e): bool
    {
        return (string) $e->getCode() === '23000';
    }
}
