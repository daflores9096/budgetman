<?php

declare(strict_types=1);

namespace BudgetMan\Middleware;

use BudgetMan\Core\AppConstants;
use BudgetMan\Core\Request;
use BudgetMan\Core\Response;
use BudgetMan\Repositories\SessionRepository;
use BudgetMan\Services\SchemaService;

final class AuthMiddleware
{
    public function __construct(
        private readonly SchemaService $schema,
        private readonly SessionRepository $sessions,
        private readonly TokenServiceAdapter $tokens = new TokenServiceAdapter(),
    ) {
    }

    public function user(): ?array
    {
        $this->schema->ensureAuthSchema();

        $bearer = Request::bearerToken();
        $cookie = Request::cookie(AppConstants::SESSION_COOKIE);

        $u = $this->sessions->findUserByTokenHash($this->tokens->hash($bearer));
        if ($u !== null) {
            return $u;
        }

        if ($cookie !== '' && $cookie !== $bearer) {
            return $this->sessions->findUserByTokenHash($this->tokens->hash($cookie));
        }

        return null;
    }

    public function requireAuth(): array
    {
        $u = $this->user();
        if (!$u) {
            Response::json(['error' => 'No autorizado'], 401);
            exit;
        }

        return $u;
    }

    public function requireRole(string $role): array
    {
        $u = $this->requireAuth();
        if (($u['role'] ?? '') !== $role) {
            Response::json(['error' => 'Prohibido'], 403);
            exit;
        }

        return $u;
    }
}

/**
 * Thin adapter to avoid circular imports in middleware constructor defaults.
 */
final class TokenServiceAdapter
{
    public function hash(string $token): string
    {
        if ($token === '') {
            return '';
        }

        return hash('sha256', $token);
    }
}
