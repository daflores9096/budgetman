<?php

declare(strict_types=1);

namespace BudgetMan\Controllers;

use BudgetMan\Middleware\AuthMiddleware;
use BudgetMan\Services\BackupService;

final class BackupController
{
    public function __construct(
        private readonly AuthMiddleware $auth,
        private readonly BackupService $backups,
    ) {
    }

    public function handle(string $method, string $path): void
    {
        $this->auth->requireRole('admin');

        if ($path === '/api/backups' && $method === 'GET') {
            $this->backups->download();
            return;
        }

        if ($path === '/api/backups/restore' && $method === 'POST') {
            $this->backups->restore();
            return;
        }
    }
}
