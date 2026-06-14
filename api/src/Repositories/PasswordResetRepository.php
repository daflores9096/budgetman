<?php

declare(strict_types=1);

namespace BudgetMan\Repositories;

final class PasswordResetRepository extends BaseRepository
{
    public function create(int $userId, string $tokenHash): void
    {
        $ins = $this->db()->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 60 MINUTE))');
        $ins->execute([$userId, $tokenHash]);
    }

    public function findValid(string $tokenHash): ?array
    {
        $stmt = $this->db()->prepare(
            'SELECT id, user_id
             FROM password_resets
             WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute([$tokenHash]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function markUsed(int $id): void
    {
        $mark = $this->db()->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?');
        $mark->execute([$id]);
    }
}
