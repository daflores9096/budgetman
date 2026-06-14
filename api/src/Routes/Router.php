<?php

declare(strict_types=1);

namespace BudgetMan\Routes;

use BudgetMan\Core\AppFactory;
use BudgetMan\Core\Request;
use BudgetMan\Core\Response;
use Throwable;

final class Router
{
    public static function dispatch(): void
    {
        $app = AppFactory::instance();
        $method = Request::method();
        $path = Request::path();

        try {
            if ($path === '/api/health') {
                $app->health->handle($method);
                return;
            }

            if (str_starts_with($path, '/api/auth')) {
                $app->authController->handle($method, $path);
                return;
            }

            if (str_starts_with($path, '/api/categories')) {
                $app->categories->handle($method, $path);
                return;
            }

            if (str_starts_with($path, '/api/recurring-fixed')) {
                $app->recurringFixed->handle($method, $path);
                return;
            }

            if (str_starts_with($path, '/api/users')) {
                $app->users->handle($method, $path);
                return;
            }

            if (str_starts_with($path, '/api/backups')) {
                $app->backups->handle($method, $path);
                return;
            }

            if (str_starts_with($path, '/api/')) {
                $app->schema->ensureLedgerUserSchema();
            }

            if ($path === '/api/transactions') {
                $app->transactions->handle($method, $path);
                return;
            }

            if (preg_match('#^/api/months/\d+/incomes$#', $path)) {
                $app->incomes->handle($method, $path);
                return;
            }

            if (preg_match('#^/api/months/\d+/expenses$#', $path)) {
                $app->expenses->handle($method, $path);
                return;
            }

            if (str_starts_with($path, '/api/months')) {
                $app->months->handle($method, $path);
                return;
            }

            if (str_starts_with($path, '/api/incomes')) {
                $app->incomes->handle($method, $path);
                return;
            }

            if (str_starts_with($path, '/api/expenses')) {
                $app->expenses->handle($method, $path);
                return;
            }

            Response::json(['error' => 'Ruta no encontrada'], 404);
        } catch (Throwable $e) {
            Response::json(['error' => 'Error del servidor', 'detail' => $e->getMessage()], 500);
        }
    }
}
