<?php

declare(strict_types=1);

namespace BudgetMan\Controllers;

use BudgetMan\Core\Request;
use BudgetMan\Core\Response;
use BudgetMan\Middleware\AuthMiddleware;
use BudgetMan\Repositories\ExpenseRepository;
use BudgetMan\Services\CategoryService;
use BudgetMan\Services\MonthService;

final class ExpenseController
{
    public function __construct(
        private readonly AuthMiddleware $auth,
        private readonly MonthService $months,
        private readonly CategoryService $categories,
        private readonly ExpenseRepository $expenses,
    ) {
    }

    public function handle(string $method, string $path): void
    {
        if (preg_match('#^/api/months/(\d+)/expenses$#', $path, $m) && $method === 'POST') {
            $u = $this->auth->requireAuth();
            $monthId = (int) $m[1];
            $this->months->ensureExists($monthId);
            $id = $this->createExpense(Request::jsonBody(), $monthId, (int) $u['id'], false);
            Response::json(['id' => $id], 201);
            return;
        }

        if ($path === '/api/expenses' && $method === 'POST') {
            $u = $this->auth->requireAuth();
            $body = Request::jsonBody();
            $date = (string) ($body['date'] ?? '');
            [$y, $mth] = $this->months->parseDateParts($date);
            $monthId = $this->months->getOrCreateId($y, $mth);
            $id = $this->createExpense($body, $monthId, (int) $u['id'], true);
            Response::json(['id' => $id], 201);
            return;
        }

        if (preg_match('#^/api/expenses/(\d+)$#', $path, $m)) {
            $this->auth->requireAuth();
            $id = (int) $m[1];

            if ($method === 'PATCH') {
                $body = Request::jsonBody();
                $type = $this->expenses->findType($id);
                if ($type === null) {
                    Response::json(['error' => 'Gasto no encontrado'], 404);
                    return;
                }

                $fields = [];
                $params = [];
                if (array_key_exists('date', $body)) {
                    $d = (string) $body['date'];
                    if ($d === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
                        Response::json(['error' => 'date inválida'], 422);
                        return;
                    }
                    $fields[] = 'entry_date = ?';
                    $params[] = $d;
                }
                if (array_key_exists('description', $body)) {
                    $fields[] = 'description = ?';
                    $params[] = trim((string) $body['description']);
                }
                if (array_key_exists('category', $body)) {
                    $cat = (string) $body['category'];
                    $this->categories->requireValid($cat);
                    $fields[] = 'category = ?';
                    $params[] = $cat;
                }
                if ($type === 'fixed' && array_key_exists('expected', $body)) {
                    if ($body['expected'] === null || $body['expected'] === '') {
                        $fields[] = 'expected_amount = NULL';
                    } else {
                        $fields[] = 'expected_amount = ?';
                        $params[] = (float) $body['expected'];
                    }
                }
                if (array_key_exists('actual', $body)) {
                    $fields[] = 'actual_amount = ?';
                    $params[] = (float) $body['actual'];
                }
                if (array_key_exists('paid', $body)) {
                    $fields[] = 'paid = ?';
                    $params[] = !empty($body['paid']) ? 1 : 0;
                }
                if ($fields === []) {
                    Response::json(['error' => 'Sin campos para actualizar'], 422);
                    return;
                }
                $this->expenses->update($id, $fields, $params);
                Response::json(['ok' => true]);
                return;
            }

            if ($method === 'DELETE') {
                $this->expenses->delete($id);
                Response::json(['ok' => true]);
                return;
            }
        }
    }

    private function createExpense(array $body, int $monthId, int $userId, bool $quickEntry): int
    {
        $type = (string) ($body['type'] ?? 'variable');
        if ($type !== 'fixed' && $type !== 'variable') {
            Response::json(['error' => 'type debe ser fixed o variable'], 422);
            exit;
        }
        $date = (string) ($body['date'] ?? '');
        if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            Response::json(['error' => 'date inválida (YYYY-MM-DD)'], 422);
            exit;
        }
        $description = trim((string) ($body['description'] ?? ''));
        $category = (string) ($body['category'] ?? 'Varios');
        $this->categories->requireValid($category);

        $expected = null;
        if (array_key_exists('expected', $body) && $body['expected'] !== null && $body['expected'] !== '') {
            $expected = (float) $body['expected'];
        }
        $actual = isset($body['actual']) ? (float) $body['actual'] : 0.0;
        if ($actual < 0) {
            Response::json(['error' => 'actual no puede ser negativo'], 422);
            exit;
        }
        $paid = !empty($body['paid']) ? 1 : 0;
        if ($type === 'variable') {
            $expected = null;
            if ($quickEntry) {
                $paid = 0;
            }
        }

        return $this->expenses->create($monthId, $type, $date, $description, $expected, $actual, $category, $paid, $userId);
    }
}
