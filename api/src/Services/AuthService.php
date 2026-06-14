<?php

declare(strict_types=1);

namespace BudgetMan\Services;

use BudgetMan\Core\AppConstants;
use BudgetMan\Core\Config;
use BudgetMan\Core\Request;
use BudgetMan\Core\Response;
use BudgetMan\Repositories\PasswordResetRepository;
use BudgetMan\Repositories\SessionRepository;
use BudgetMan\Repositories\UserRepository;

final class AuthService
{
    public function __construct(
        private readonly SchemaService $schema,
        private readonly UserRepository $users,
        private readonly SessionRepository $sessions,
        private readonly PasswordResetRepository $resets,
        private readonly TokenService $tokens,
    ) {
    }

    public function me(): void
    {
        $this->schema->ensureAuthSchema();
        // AuthMiddleware handles user lookup in controller
    }

    public function login(array $body): array
    {
        $login = strtolower(trim((string) ($body['email'] ?? $body['login'] ?? $body['username'] ?? '')));
        $password = (string) ($body['password'] ?? '');
        if ($login === '' || $password === '' || mb_strlen($login) > 190) {
            Response::json(['error' => 'email/password inválidos'], 422);
            exit;
        }

        $row = $this->users->findByLogin($login);
        if (!$row || !empty($row['disabled']) || !password_verify($password, (string) $row['password_hash'])) {
            Response::json(['error' => 'Credenciales inválidas'], 401);
            exit;
        }

        $token = $this->tokens->randomToken(32);
        $this->sessions->create((int) $row['id'], $this->tokens->hash($token));
        $this->sessions->setCookie($token);

        return [
            'user' => [
                'id' => (int) $row['id'],
                'username' => (string) ($row['username'] ?? ''),
                'email' => (string) $row['email'],
                'name' => (string) $row['name'],
                'role' => (string) $row['role'],
            ],
            'access_token' => $token,
        ];
    }

    public function logout(): void
    {
        $token = Request::bearerToken();
        if ($token === '') {
            $token = Request::cookie(AppConstants::SESSION_COOKIE);
        }
        if ($token !== '') {
            $this->sessions->deleteByHash($this->tokens->hash($token));
        }
        $this->sessions->clearCookie();
    }

    public function forgot(array $body): array
    {
        $email = strtolower(trim((string) ($body['email'] ?? '')));
        if ($email === '' || mb_strlen($email) > 190) {
            Response::json(['error' => 'email inválido'], 422);
            exit;
        }

        $row = $this->users->findByEmail($email);
        $resetLink = null;
        if ($row && empty($row['disabled'])) {
            $token = $this->tokens->randomToken(32);
            $this->resets->create((int) $row['id'], $this->tokens->hash($token));
            $base = Config::env('APP_BASE_URL') ?: 'http://localhost:48080';
            $resetLink = rtrim($base, '/') . '/reset-password?token=' . urlencode($token);
        }

        return ['ok' => true, 'reset_link' => $resetLink];
    }

    public function reset(array $body): void
    {
        $token = (string) ($body['token'] ?? '');
        $newPassword = (string) ($body['new_password'] ?? '');
        if ($token === '' || mb_strlen($token) > 512) {
            Response::json(['error' => 'token inválido'], 422);
            exit;
        }
        if (mb_strlen($newPassword) < 6) {
            Response::json(['error' => 'La contraseña debe tener al menos 6 caracteres'], 422);
            exit;
        }

        $row = $this->resets->findValid($this->tokens->hash($token));
        if (!$row) {
            Response::json(['error' => 'Token inválido o expirado'], 422);
            exit;
        }

        $this->users->updatePassword((int) $row['user_id'], password_hash($newPassword, PASSWORD_DEFAULT));
        $this->resets->markUsed((int) $row['id']);
        $this->sessions->deleteByUserId((int) $row['user_id']);
        $this->sessions->clearCookie();
    }

    public function bootstrap(array $body): array
    {
        $allow = (Config::env('ALLOW_BOOTSTRAP') ?: '1') === '1';
        if (!$allow) {
            Response::json(['error' => 'bootstrap disabled'], 403);
            exit;
        }
        if ($this->users->countAdmins() > 0) {
            Response::json(['error' => 'admin already exists'], 409);
            exit;
        }

        $username = $this->normalizeUsername((string) ($body['username'] ?? 'admin'));
        $email = strtolower(trim((string) ($body['email'] ?? 'admin@example.com')));
        $name = trim((string) ($body['name'] ?? 'Admin'));
        $password = (string) ($body['password'] ?? '');
        if ($username === '' || mb_strlen($username) < 3 || mb_strlen($username) > 64) {
            Response::json(['error' => 'username inválido'], 422);
            exit;
        }
        if ($password === '') {
            $password = $this->tokens->randomToken(9);
        }

        $id = $this->users->createAdmin($username, $email, $name, password_hash($password, PASSWORD_DEFAULT));

        return [
            'ok' => true,
            'username' => $username,
            'email' => $email,
            'temp_password' => $password,
            'id' => $id,
        ];
    }

    public function normalizeUsername(string $v): string
    {
        $v = trim(strtolower($v));
        $v = preg_replace('/[^a-z0-9_.-]+/', '', $v);

        return $v ?? '';
    }
}
