<?php

declare(strict_types=1);

namespace BudgetMan\Controllers;

use BudgetMan\Core\Request;
use BudgetMan\Core\Response;
use BudgetMan\Middleware\AuthMiddleware;
use BudgetMan\Repositories\ExpenseRepository;
use BudgetMan\Repositories\IncomeRepository;
use BudgetMan\Repositories\MonthRepository;
use BudgetMan\Services\MonthService;
use PDOException;

final class MonthController
{
    public function __construct(
        private readonly AuthMiddleware $auth,
        private readonly MonthService $months,
        private readonly MonthRepository $monthRepo,
        private readonly IncomeRepository $incomes,
        private readonly ExpenseRepository $expenses,
    ) {
    }

    public function handle(string $method, string $path): void
    {
        if ($path === '/api/months' && $method === 'GET') {
            $this->auth->requireAuth();
            Response::json(['months' => $this->monthRepo->listWithTotals()]);
            return;
        }

        if ($path === '/api/months' && $method === 'POST') {
            $this->auth->requireAuth();
            $body = Request::jsonBody();
            $year = isset($body['year']) ? (int) $body['year'] : 0;
            $month = isset($body['month']) ? (int) $body['month'] : 0;
            $this->months->validateYearMonth($year, $month);
            try {
                $id = $this->monthRepo->create($year, $month);
                Response::json(['id' => $id, 'year' => $year, 'month' => $month], 201);
            } catch (PDOException $e) {
                if ($this->monthRepo->isDuplicateKey($e)) {
                    Response::json(['error' => 'Ese mes ya existe'], 409);
                    return;
                }
                throw $e;
            }
            return;
        }

        if (preg_match('#^/api/months/(\d+)$#', $path, $m)) {
            $monthId = (int) $m[1];
            $this->auth->requireAuth();

            if ($method === 'GET') {
                $bm = $this->monthRepo->findById($monthId);
                if (!$bm) {
                    Response::json(['error' => 'Mes no encontrado'], 404);
                    return;
                }
                $incomeRows = $this->incomes->listByMonth($monthId);
                $expenseRows = $this->expenses->listByMonth($monthId);
                $ti = array_sum(array_column($incomeRows, 'amount'));
                $ts = array_sum(array_column($expenseRows, 'actual'));
                Response::json([
                    'month' => [
                        'id' => (int) $bm['id'],
                        'year' => (int) $bm['year'],
                        'month' => (int) $bm['month'],
                    ],
                    'summary' => [
                        'total_income' => $ti,
                        'total_spent' => $ts,
                        'remaining' => $ti - $ts,
                    ],
                    'incomes' => $incomeRows,
                    'expenses' => $expenseRows,
                ]);
                return;
            }

            if ($method === 'DELETE') {
                $this->monthRepo->delete($monthId);
                Response::json(['ok' => true]);
                return;
            }
        }
    }
}
