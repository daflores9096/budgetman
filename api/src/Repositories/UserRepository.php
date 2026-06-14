<?php

declare(strict_types=1);

namespace BudgetMan\Repositories;

use PDO;
use PDOException;

final class UserRepository extends BaseRepository
{
    public function findByLogin(string $login): ?array
    {
        $stmt = $this->db()->prepare('SELECT id, username, email, name, role, password_hash, disabled FROM users WHERE email = ? OR username = ? LIMIT 1');
        $stmt->execute([$login, $login]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db()->prepare('SELECT id, disabled FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function countAdmins(): int
    {
        return (int) $this->db()->query("SELECT COUNT(*) AS c FROM users WHERE role = 'admin'")->fetch()['c'];
    }

    public function listAll(): array
    {
        $stmt = $this->db()->query('SELECT id, username, email, name, role, disabled, created_at, updated_at FROM users ORDER BY id ASC');
        $rows = $stmt->fetchAll();
        foreach ($rows as &$u) {
            $u['id'] = (int) $u['id'];
            $u['username'] = (string) ($u['username'] ?? '');
            $u['email'] = (string) $u['email'];
            $u['name'] = (string) $u['name'];
            $u['role'] = (string) $u['role'];
            $u['disabled'] = (int) $u['disabled'] ? true : false;
        }
        unset($u);

        return $rows;
    }

    public function create(string $username, string $email, string $name, string $role, string $passwordHash): int
    {
        $ins = $this->db()->prepare('INSERT INTO users (username, email, name, role, password_hash) VALUES (?, ?, ?, ?, ?)');
        $ins->execute([$username, $email, $name, $role, $passwordHash]);

        return (int) $this->db()->lastInsertId();
    }

    public function createAdmin(string $username, string $email, string $name, string $passwordHash): int
    {
        $ins = $this->db()->prepare("INSERT INTO users (username, email, name, role, password_hash) VALUES (?, ?, ?, 'admin', ?)");
        $ins->execute([$username, $email, $name, $passwordHash]);

        return (int) $this->db()->lastInsertId();
    }

    public function exists(int $id): bool
    {
        $chk = $this->db()->prepare('SELECT 1 FROM users WHERE id = ?');
        $chk->execute([$id]);

        return (bool) $chk->fetchColumn();
    }

    public function update(int $id, array $fields, array $params): int
    {
        $params[] = $id;
        $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    public function updatePassword(int $id, string $passwordHash): void
    {
        $upd = $this->db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $upd->execute([$passwordHash, $id]);
    }

    public function delete(int $id): int
    {
        $del = $this->db()->prepare('DELETE FROM users WHERE id = ?');
        $del->execute([$id]);

        return $del->rowCount();
    }
}
