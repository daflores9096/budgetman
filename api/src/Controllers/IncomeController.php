<?php

declare(strict_types=1);

namespace BudgetMan\Controllers;

use BudgetMan\Core\Request;
use BudgetMan\Core\Response;
use BudgetMan\Middleware\AuthMiddleware;
use BudgetMan\Repositories\IncomeRepository;
use BudgetMan\Services\MonthService;

final class IncomeController
{
    public function __construct(
        private readonly AuthMiddleware $auth,
        private readonly MonthService $months,
        private readonly IncomeRepository $incomes,
    ) {
    }

    public function handle(string $method, string $path): void
    {
        if (preg_match('#^/api/months/(\d+)/incomes$#', $path, $m) && $method === 'POST') {
            $u = $this->auth->requireAuth();
            $monthId = (int) $m[1];
            $this->months->ensureExists($monthId);
            $body = Request::jsonBody();
            $id = $this->createIncome($body, $monthId, (int) $u['id']);
            Response::json(['id' => $id], 201);
            return;
        }

        if ($path === '/api/incomes' && $method === 'POST') {
            $u = $this->auth->requireAuth();
            $body = Request::jsonBody();
            $date = (string) ($body['date'] ?? '');
            [$y, $mth] = $this->months->parseDateParts($date);
            $monthId = $this->months->getOrCreateId($y, $mth);
            $id = $this->createIncome($body, $monthId, (int) $u['id']);
            Response::json(['id' => $id], 201);
            return;
        }

        if (preg_match('#^/api/incomes/(\d+)$#', $path, $m)) {
            $this->auth->requireAuth();
            $id = (int) $m[1];

            if ($method === 'PATCH') {
                $body = Request::jsonBody();
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
                if (array_key_exists('amount', $body)) {
                    $a = (float) $body['amount'];
                    if ($a <= 0) {
                        Response::json(['error' => 'amount debe ser > 0'], 422);
                        return;
                    }
                    $fields[] = 'amount = ?';
                    $params[] = $a;
                }
                if ($fields === []) {
                    Response::json(['error' => 'Sin campos para actualizar'], 422);
                    return;
                }
                $this->incomes->update($id, $fields, $params);
                Response::json(['ok' => true]);
                return;
            }

            if ($method === 'DELETE') {
                $this->incomes->delete($id);
                Response::json(['ok' => true]);
                return;
            }
        }
    }

    private function createIncome(array $body, int $monthId, int $userId): int
    {
        $date = (string) ($body['date'] ?? '');
        $description = trim((string) ($body['description'] ?? ''));
        $amount = isset($body['amount']) ? (float) $body['amount'] : 0.0;
        if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            Response::json(['error' => 'date inválida (YYYY-MM-DD)'], 422);
            exit;
        }
        if ($amount <= 0) {
            Response::json(['error' => 'amount debe ser > 0'], 422);
            exit;
        }

        return $this->incomes->create($monthId, $date, $description, $amount, $userId);
    }
}
