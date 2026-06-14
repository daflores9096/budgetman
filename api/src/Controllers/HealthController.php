<?php

declare(strict_types=1);

namespace BudgetMan\Controllers;

use BudgetMan\Core\Config;
use BudgetMan\Core\Database;
use BudgetMan\Core\Request;
use BudgetMan\Core\Response;

final class HealthController
{
    public function handle(string $method): void
    {
        if ($method !== 'GET') {
            Response::json(['error' => 'Method not allowed'], 405);
            return;
        }

        Database::connection()->query('SELECT 1');
        $c = Config::load()['db'];
        $debug = isset(Request::query()['debug']) && Request::query()['debug'] === '1'
            && (Config::env('ALLOW_BOOTSTRAP') === '1' || Config::env('ALLOW_BOOTSTRAP') === 'true');

        $payload = ['ok' => true];
        if ($debug) {
            $payload['db'] = [
                'host' => $c['host'],
                'name' => $c['name'],
                'user' => $c['user'],
                'password_configured' => $c['pass'] !== '',
                'config_source' => is_file(dirname(__DIR__, 2) . '/config.local.php') ? 'config.local.php' : 'env',
            ];
        }

        Response::json($payload);
    }
}
