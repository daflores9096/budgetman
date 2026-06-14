<?php

declare(strict_types=1);

namespace BudgetMan\Core;

use BudgetMan\Middleware\AuthMiddleware;
use BudgetMan\Repositories\CategoryRepository;
use BudgetMan\Repositories\ExpenseRepository;
use BudgetMan\Repositories\IncomeRepository;
use BudgetMan\Repositories\MonthRepository;
use BudgetMan\Repositories\PasswordResetRepository;
use BudgetMan\Repositories\RecurringFixedRepository;
use BudgetMan\Repositories\SessionRepository;
use BudgetMan\Repositories\UserRepository;
use BudgetMan\Services\AuthService;
use BudgetMan\Services\BackupService;
use BudgetMan\Services\CategoryService;
use BudgetMan\Services\MonthService;
use BudgetMan\Services\SchemaService;
use BudgetMan\Services\TokenService;
use BudgetMan\Controllers\AuthController;
use BudgetMan\Controllers\BackupController;
use BudgetMan\Controllers\CategoryController;
use BudgetMan\Controllers\ExpenseController;
use BudgetMan\Controllers\HealthController;
use BudgetMan\Controllers\IncomeController;
use BudgetMan\Controllers\MonthController;
use BudgetMan\Controllers\RecurringFixedController;
use BudgetMan\Controllers\TransactionController;
use BudgetMan\Controllers\UserController;

final class AppFactory
{
    private static ?self $instance = null;

    public readonly SchemaService $schema;
    public readonly AuthMiddleware $auth;
    public readonly HealthController $health;
    public readonly AuthController $authController;
    public readonly CategoryController $categories;
    public readonly MonthController $months;
    public readonly TransactionController $transactions;
    public readonly IncomeController $incomes;
    public readonly ExpenseController $expenses;
    public readonly UserController $users;
    public readonly RecurringFixedController $recurringFixed;
    public readonly BackupController $backups;

    private function __construct()
    {
        $this->schema = new SchemaService();
        $tokens = new TokenService();
        $sessions = new SessionRepository();
        $users = new UserRepository();
        $this->auth = new AuthMiddleware($this->schema, $sessions);

        $categoryRepo = new CategoryRepository();
        $categoryService = new CategoryService($this->schema, $categoryRepo);
        $monthRepo = new MonthRepository();
        $monthService = new MonthService($monthRepo);
        $incomeRepo = new IncomeRepository();
        $expenseRepo = new ExpenseRepository();
        $recurringRepo = new RecurringFixedRepository();

        $this->health = new HealthController();
        $this->authController = new AuthController(
            $this->auth,
            new AuthService($this->schema, $users, $sessions, new PasswordResetRepository(), $tokens),
        );
        $this->categories = new CategoryController($this->auth, $categoryService, $categoryRepo);
        $this->months = new MonthController($this->auth, $monthService, $monthRepo, $incomeRepo, $expenseRepo);
        $this->transactions = new TransactionController($this->auth, $incomeRepo, $expenseRepo);
        $this->incomes = new IncomeController($this->auth, $monthService, $incomeRepo);
        $this->expenses = new ExpenseController($this->auth, $monthService, $categoryService, $expenseRepo);
        $this->users = new UserController($this->auth, $users, $sessions, $tokens);
        $this->recurringFixed = new RecurringFixedController(
            $this->auth,
            $this->schema,
            $categoryService,
            $monthService,
            $recurringRepo,
            $expenseRepo,
        );
        $this->backups = new BackupController($this->auth, new BackupService());
    }

    public static function instance(): self
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }
}
