<?php

declare(strict_types=1);

namespace BudgetMan\Repositories;

use BudgetMan\Core\AppConstants;
use PDO;
use Throwable;

final class SessionRepository extends BaseRepository
{
    public function create(int $userId, string $tokenHash): void
    {
        $ins = $this->db()->prepare('INSERT INTO sessions (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 14 DAY))');
        $ins->execute([$userId, $tokenHash]);
    }

    public function deleteByHash(string $tokenHash): void
    {
        try {
            $del = $this->db()->prepare('DELETE FROM sessions WHERE token_hash = ?');
            $del->execute([$tokenHash]);
        } catch (Throwable) {
        }
    }

    public function deleteByUserId(int $userId): void
    {
        try {
            $del = $this->db()->prepare('DELETE FROM sessions WHERE user_id = ?');
            $del->execute([$userId]);
        } catch (Throwable) {
        }
    }

    public function touch(string $tokenHash): void
    {
        try {
            $touch = $this->db()->prepare('UPDATE sessions SET last_seen_at = NOW() WHERE token_hash = ?');
            $touch->execute([$tokenHash]);
        } catch (Throwable) {
        }
    }

    public function findUserByTokenHash(string $tokenHash): ?array
    {
        $stmt = $this->db()->prepare(
            "SELECT u.id, u.username, u.email, u.name, u.role, u.disabled
             FROM sessions s
             JOIN users u ON u.id = s.user_id
             WHERE s.token_hash = ? AND s.expires_at > NOW()
             LIMIT 1"
        );
        $stmt->execute([$tokenHash]);
        $u = $stmt->fetch();
        if (!$u || !empty($u['disabled'])) {
            return null;
        }

        $this->touch($tokenHash);

        return [
            'id' => (int) $u['id'],
            'username' => isset($u['username']) ? (string) $u['username'] : '',
            'email' => (string) $u['email'],
            'name' => (string) $u['name'],
            'role' => (string) $u['role'],
        ];
    }

    public function setCookie(string $token): void
    {
        $secure = (getenv('COOKIE_SECURE') ?: '') === '1';
        setcookie(AppConstants::SESSION_COOKIE, $token, [
            'expires' => time() + AppConstants::SESSION_TTL_SECONDS,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public function clearCookie(): void
    {
        setcookie(AppConstants::SESSION_COOKIE, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => false,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
