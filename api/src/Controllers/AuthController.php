<?php

declare(strict_types=1);

namespace BudgetMan\Controllers;

use BudgetMan\Core\Request;
use BudgetMan\Core\Response;
use BudgetMan\Middleware\AuthMiddleware;
use BudgetMan\Services\AuthService;

final class AuthController
{
    public function __construct(
        private readonly AuthMiddleware $auth,
        private readonly AuthService $service,
    ) {
    }

    public function handle(string $method, string $path): void
    {
        if ($path === '/api/auth/me' && $method === 'GET') {
            $u = $this->auth->user();
            Response::json(['user' => $u], 200);
            return;
        }

        if ($path === '/api/auth/login' && $method === 'POST') {
            Response::json($this->service->login(Request::jsonBody()));
            return;
        }

        if ($path === '/api/auth/logout' && $method === 'POST') {
            $this->service->logout();
            Response::json(['ok' => true]);
            return;
        }

        if ($path === '/api/auth/forgot' && $method === 'POST') {
            Response::json($this->service->forgot(Request::jsonBody()));
            return;
        }

        if ($path === '/api/auth/reset' && $method === 'POST') {
            $this->service->reset(Request::jsonBody());
            Response::json(['ok' => true]);
            return;
        }

        if ($path === '/api/auth/bootstrap' && $method === 'POST') {
            $result = $this->service->bootstrap(Request::jsonBody());
            Response::json($result, 201);
            return;
        }

        Response::json(['error' => 'Ruta no encontrada'], 404);
    }
}
