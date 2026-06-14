<?php

declare(strict_types=1);

namespace BudgetMan\Controllers;

use BudgetMan\Core\Request;
use BudgetMan\Core\Response;
use BudgetMan\Middleware\AuthMiddleware;
use BudgetMan\Repositories\SessionRepository;
use BudgetMan\Repositories\UserRepository;
use BudgetMan\Services\TokenService;
use PDOException;

final class UserController
{
    public function __construct(
        private readonly AuthMiddleware $auth,
        private readonly UserRepository $users,
        private readonly SessionRepository $sessions,
        private readonly TokenService $tokens,
    ) {
    }

    public function handle(string $method, string $path): void
    {
        $this->auth->requireRole('admin');

        if ($path === '/api/users' && $method === 'GET') {
            Response::json(['users' => $this->users->listAll()]);
            return;
        }

        if ($path === '/api/users' && $method === 'POST') {
            $body = Request::jsonBody();
            $username = $this->normalizeUsername((string) ($body['username'] ?? ''));
            $email = strtolower(trim((string) ($body['email'] ?? '')));
            $name = trim((string) ($body['name'] ?? ''));
            $role = (string) ($body['role'] ?? 'appuser');
            $password = (string) ($body['password'] ?? '');
            if ($username === '' || mb_strlen($username) < 3 || mb_strlen($username) > 64) {
                Response::json(['error' => 'username inválido'], 422);
                return;
            }
            if ($email === '' || mb_strlen($email) > 190) {
                Response::json(['error' => 'email inválido'], 422);
                return;
            }
            if ($role !== 'admin' && $role !== 'appuser') {
                Response::json(['error' => 'role inválido'], 422);
                return;
            }
            if ($password === '') {
                $password = $this->tokens->randomToken(9);
            }
            if (mb_strlen($password) < 6) {
                Response::json(['error' => 'password muy corto'], 422);
                return;
            }
            try {
                $id = $this->users->create($username, $email, $name, $role, password_hash($password, PASSWORD_DEFAULT));
                Response::json([
                    'id' => $id,
                    'username' => $username,
                    'email' => $email,
                    'name' => $name,
                    'role' => $role,
                    'temp_password' => $password,
                ], 201);
            } catch (PDOException $e) {
                if ((string) $e->getCode() === '23000') {
                    Response::json(['error' => 'Ese email o username ya existe'], 409);
                    return;
                }
                throw $e;
            }
            return;
        }

        if (preg_match('#^/api/users/(\d+)$#', $path, $m)) {
            $id = (int) $m[1];

            if ($method === 'PATCH') {
                $body = Request::jsonBody();
                $fields = [];
                $params = [];

                if (array_key_exists('username', $body)) {
                    $username = $this->normalizeUsername((string) $body['username']);
                    if ($username === '' || mb_strlen($username) < 3 || mb_strlen($username) > 64) {
                        Response::json(['error' => 'username inválido'], 422);
                        return;
                    }
                    $fields[] = 'username = ?';
                    $params[] = $username;
                }
                if (array_key_exists('email', $body)) {
                    $email = strtolower(trim((string) $body['email']));
                    if ($email === '' || mb_strlen($email) > 190) {
                        Response::json(['error' => 'email inválido'], 422);
                        return;
                    }
                    $fields[] = 'email = ?';
                    $params[] = $email;
                }
                if (array_key_exists('name', $body)) {
                    $name = trim((string) $body['name']);
                    if (mb_strlen($name) > 120) {
                        Response::json(['error' => 'name inválido'], 422);
                        return;
                    }
                    $fields[] = 'name = ?';
                    $params[] = $name;
                }
                if (array_key_exists('role', $body)) {
                    $role = (string) $body['role'];
                    if ($role !== 'admin' && $role !== 'appuser') {
                        Response::json(['error' => 'role inválido'], 422);
                        return;
                    }
                    $fields[] = 'role = ?';
                    $params[] = $role;
                }
                if (array_key_exists('disabled', $body)) {
                    $fields[] = 'disabled = ?';
                    $params[] = !empty($body['disabled']) ? 1 : 0;
                }
                if (array_key_exists('password', $body)) {
                    $pw = (string) $body['password'];
                    if (mb_strlen($pw) < 6) {
                        Response::json(['error' => 'password muy corto'], 422);
                        return;
                    }
                    $fields[] = 'password_hash = ?';
                    $params[] = password_hash($pw, PASSWORD_DEFAULT);
                }
                if ($fields === []) {
                    Response::json(['error' => 'Sin campos para actualizar'], 422);
                    return;
                }
                try {
                    $rows = $this->users->update($id, $fields, $params);
                    if ($rows === 0 && !$this->users->exists($id)) {
                        Response::json(['error' => 'Usuario no encontrado'], 404);
                        return;
                    }
                } catch (PDOException $e) {
                    if ((string) $e->getCode() === '23000') {
                        Response::json(['error' => 'Ese email o username ya existe'], 409);
                        return;
                    }
                    throw $e;
                }
                Response::json(['ok' => true]);
                return;
            }

            if ($method === 'DELETE') {
                $rows = $this->users->delete($id);
                if ($rows === 0) {
                    Response::json(['error' => 'Usuario no encontrado'], 404);
                    return;
                }
                Response::json(['ok' => true]);
                return;
            }
        }

        if (preg_match('#^/api/users/(\d+)/reset-password$#', $path, $m) && $method === 'POST') {
            $id = (int) $m[1];
            $body = Request::jsonBody();
            $pw = (string) ($body['password'] ?? '');
            if ($pw === '') {
                $pw = $this->tokens->randomToken(9);
            }
            if (mb_strlen($pw) < 6) {
                Response::json(['error' => 'password muy corto'], 422);
                return;
            }
            if (!$this->users->exists($id)) {
                Response::json(['error' => 'Usuario no encontrado'], 404);
                return;
            }
            $this->users->updatePassword($id, password_hash($pw, PASSWORD_DEFAULT));
            $this->sessions->deleteByUserId($id);
            Response::json(['ok' => true, 'temp_password' => $pw]);
            return;
        }

        Response::json(['error' => 'Ruta no encontrada'], 404);
    }

    private function normalizeUsername(string $v): string
    {
        $v = trim(strtolower($v));
        $v = preg_replace('/[^a-z0-9_.-]+/', '', $v);

        return $v ?? '';
    }
}
