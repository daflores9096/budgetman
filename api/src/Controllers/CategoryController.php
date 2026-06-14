<?php

declare(strict_types=1);

namespace BudgetMan\Controllers;

use BudgetMan\Core\Request;
use BudgetMan\Core\Response;
use BudgetMan\Middleware\AuthMiddleware;
use BudgetMan\Repositories\CategoryRepository;
use BudgetMan\Services\CategoryService;
use PDOException;

final class CategoryController
{
    public function __construct(
        private readonly AuthMiddleware $auth,
        private readonly CategoryService $service,
        private readonly CategoryRepository $categories,
    ) {
    }

    public function handle(string $method, string $path): void
    {
        if ($path === '/api/categories' && $method === 'GET') {
            $this->auth->requireAuth();
            Response::json($this->service->list());
            return;
        }

        if ($path === '/api/categories' && $method === 'POST') {
            $this->auth->requireRole('admin');
            $body = Request::jsonBody();
            $name = $this->service->normalizeName((string) ($body['name'] ?? ''));
            if ($name === '' || mb_strlen($name) > 64) {
                Response::json(['error' => 'name inválido'], 422);
                return;
            }
            try {
                $id = $this->categories->create($name);
                Response::json(['id' => $id, 'name' => $name], 201);
            } catch (PDOException $e) {
                if ($this->categories->isDuplicateKey($e)) {
                    Response::json(['error' => 'Esa categoría ya existe'], 409);
                    return;
                }
                throw $e;
            }
            return;
        }

        if (preg_match('#^/api/categories/(\d+)$#', $path, $m)) {
            $id = (int) $m[1];

            if ($method === 'PATCH') {
                $this->auth->requireRole('admin');
                $body = Request::jsonBody();
                $name = $this->service->normalizeName((string) ($body['name'] ?? ''));
                if ($name === '' || mb_strlen($name) > 64) {
                    Response::json(['error' => 'name inválido'], 422);
                    return;
                }
                $existing = $this->categories->findById($id);
                if (!$existing) {
                    Response::json(['error' => 'Categoría no encontrada'], 404);
                    return;
                }
                try {
                    $this->categories->updateName($id, $name);
                } catch (PDOException $e) {
                    if ($this->categories->isDuplicateKey($e)) {
                        Response::json(['error' => 'Esa categoría ya existe'], 409);
                        return;
                    }
                    throw $e;
                }
                $this->categories->reassignExpenses((string) $existing['name'], $name);
                Response::json(['ok' => true, 'id' => $id, 'name' => $name]);
                return;
            }

            if ($method === 'DELETE') {
                $this->auth->requireRole('admin');
                $existing = $this->categories->findById($id);
                if (!$existing) {
                    Response::json(['error' => 'Categoría no encontrada'], 404);
                    return;
                }
                $name = (string) $existing['name'];
                if ($name === 'Varios') {
                    Response::json(['error' => 'No se puede eliminar la categoría Varios'], 422);
                    return;
                }
                $fallback = 'Varios';
                $this->categories->ensureExists($fallback);
                $this->categories->reassignExpenses($name, $fallback);
                $this->categories->delete($id);
                Response::json(['ok' => true]);
                return;
            }

            Response::json(['error' => 'Method not allowed'], 405);
            return;
        }
    }
}
