<?php

declare(strict_types=1);

namespace BudgetMan\Controllers;

use BudgetMan\Core\Request;
use BudgetMan\Core\Response;
use BudgetMan\Middleware\AuthMiddleware;
use BudgetMan\Repositories\ExpenseRepository;
use BudgetMan\Repositories\IncomeRepository;

final class TransactionController
{
    public function __construct(
        private readonly AuthMiddleware $auth,
        private readonly IncomeRepository $incomes,
        private readonly ExpenseRepository $expenses,
    ) {
    }

    public function handle(string $method, string $path): void
    {
        if ($path !== '/api/transactions' || $method !== 'GET') {
            return;
        }

        $this->auth->requireAuth();
        $qs = Request::query();
        $start = (string) ($qs['start'] ?? '');
        $end = (string) ($qs['end'] ?? '');
        if ($start === '' || $end === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
            Response::json(['error' => 'start y end requeridos (YYYY-MM-DD)'], 422);
            return;
        }
        if ($start > $end) {
            Response::json(['error' => 'start no puede ser mayor que end'], 422);
            return;
        }

        $incomeRows = $this->incomes->listByDateRange($start, $end);
        $expenseRows = $this->expenses->listByDateRange($start, $end);
        $ti = array_sum(array_column($incomeRows, 'amount'));
        $ts = array_sum(array_column($expenseRows, 'actual'));

        Response::json([
            'range' => ['start' => $start, 'end' => $end],
            'summary' => [
                'total_income' => $ti,
                'total_spent' => $ts,
                'remaining' => $ti - $ts,
            ],
            'incomes' => $incomeRows,
            'expenses' => $expenseRows,
        ]);
    }
}
