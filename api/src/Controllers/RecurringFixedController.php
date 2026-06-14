<?php

declare(strict_types=1);

namespace BudgetMan\Controllers;

use BudgetMan\Core\Request;
use BudgetMan\Core\Response;
use BudgetMan\Middleware\AuthMiddleware;
use BudgetMan\Repositories\ExpenseRepository;
use BudgetMan\Repositories\RecurringFixedRepository;
use BudgetMan\Services\CategoryService;
use BudgetMan\Services\MonthService;
use BudgetMan\Services\SchemaService;

final class RecurringFixedController
{
    public function __construct(
        private readonly AuthMiddleware $auth,
        private readonly SchemaService $schema,
        private readonly CategoryService $categories,
        private readonly MonthService $months,
        private readonly RecurringFixedRepository $recurring,
        private readonly ExpenseRepository $expenses,
    ) {
    }

    public function handle(string $method, string $path): void
    {
        $this->schema->ensureRecurringFixedSchema();
        $this->schema->ensureLedgerUserSchema();

        if ($path === '/api/recurring-fixed/pending' && $method === 'GET') {
            $this->auth->requireAuth();
            $year = isset(Request::query()['year']) ? (int) Request::query()['year'] : 0;
            $month = isset(Request::query()['month']) ? (int) Request::query()['month'] : 0;
            $this->months->validateYearMonth($year, $month);
            Response::json([
                'pending' => $this->recurring->listPending($year, $month),
                'year' => $year,
                'month' => $month,
            ]);
            return;
        }

        if ($path === '/api/recurring-fixed' && $method === 'GET') {
            $this->auth->requireRole('admin');
            Response::json(['items' => $this->recurring->listAll()]);
            return;
        }

        if ($path === '/api/recurring-fixed' && $method === 'POST') {
            $this->auth->requireRole('admin');
            $body = Request::jsonBody();
            $title = trim((string) ($body['title'] ?? ''));
            $category = (string) ($body['category'] ?? 'Varios');
            $this->categories->requireValid($category);
            $expected = isset($body['expected_amount']) ? (float) $body['expected_amount'] : 0.0;
            if ($title === '' || mb_strlen($title) > 255) {
                Response::json(['error' => 'title inválido'], 422);
                return;
            }
            if ($expected < 0) {
                Response::json(['error' => 'expected_amount no puede ser negativo'], 422);
                return;
            }
            $id = $this->recurring->create($title, $expected, $category);
            Response::json(['id' => $id, 'title' => $title, 'expected_amount' => $expected, 'category' => $category], 201);
            return;
        }

        if (preg_match('#^/api/recurring-fixed/(\d+)/pay$#', $path, $m) && $method === 'POST') {
            $u = $this->auth->requireAuth();
            $rid = (int) $m[1];
            $tpl = $this->recurring->findById($rid);
            if (!$tpl) {
                Response::json(['error' => 'Plantilla no encontrada'], 404);
                return;
            }
            $body = Request::jsonBody();
            $date = (string) ($body['date'] ?? '');
            if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                Response::json(['error' => 'date inválida (YYYY-MM-DD)'], 422);
                return;
            }
            $actual = isset($body['actual']) ? (float) $body['actual'] : 0.0;
            if ($actual < 0) {
                Response::json(['error' => 'actual no puede ser negativo'], 422);
                return;
            }
            [$y, $mth] = $this->months->parseDateParts($date);
            if ($this->recurring->isPaidForMonth($rid, $y, $mth)) {
                Response::json(['error' => 'Este gasto fijo ya fue registrado para ese mes'], 409);
                return;
            }
            $category = (string) $tpl['category'];
            $this->categories->requireValid($category);
            $monthId = $this->months->getOrCreateId($y, $mth);
            $expenseId = $this->expenses->create(
                $monthId,
                'fixed',
                $date,
                (string) $tpl['title'],
                (float) $tpl['expected_amount'],
                $actual,
                $category,
                1,
                (int) $u['id'],
            );
            $this->recurring->linkMonthly($rid, $y, $mth, $expenseId);
            Response::json(['ok' => true, 'expense_id' => $expenseId]);
            return;
        }

        if (preg_match('#^/api/recurring-fixed/(\d+)$#', $path, $m)) {
            $id = (int) $m[1];

            if ($method === 'PATCH') {
                $this->auth->requireRole('admin');
                if (!$this->recurring->exists($id)) {
                    Response::json(['error' => 'Plantilla no encontrada'], 404);
                    return;
                }
                $body = Request::jsonBody();
                $fields = [];
                $params = [];
                if (array_key_exists('title', $body)) {
                    $t = trim((string) $body['title']);
                    if ($t === '' || mb_strlen($t) > 255) {
                        Response::json(['error' => 'title inválido'], 422);
                        return;
                    }
                    $fields[] = 'title = ?';
                    $params[] = $t;
                }
                if (array_key_exists('expected_amount', $body)) {
                    $ex = (float) $body['expected_amount'];
                    if ($ex < 0) {
                        Response::json(['error' => 'expected_amount no puede ser negativo'], 422);
                        return;
                    }
                    $fields[] = 'expected_amount = ?';
                    $params[] = $ex;
                }
                if (array_key_exists('category', $body)) {
                    $cat = (string) $body['category'];
                    $this->categories->requireValid($cat);
                    $fields[] = 'category = ?';
                    $params[] = $cat;
                }
                if ($fields === []) {
                    Response::json(['error' => 'Sin campos para actualizar'], 422);
                    return;
                }
                $this->recurring->update($id, $fields, $params);
                Response::json(['ok' => true]);
                return;
            }

            if ($method === 'DELETE') {
                $this->auth->requireRole('admin');
                $rows = $this->recurring->delete($id);
                if ($rows === 0) {
                    Response::json(['error' => 'Plantilla no encontrada'], 404);
                    return;
                }
                Response::json(['ok' => true]);
                return;
            }
        }

        Response::json(['error' => 'Ruta no encontrada'], 404);
    }
}
