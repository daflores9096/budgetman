<?php

declare(strict_types=1);

namespace BudgetMan\Core;

final class Config
{
    public static function load(): array
    {
        $apiDir = dirname(__DIR__, 2);
        $local = $apiDir . DIRECTORY_SEPARATOR . 'config.local.php';
        $example = $apiDir . DIRECTORY_SEPARATOR . 'config.example.php';

        if (is_file($local)) {
            /** @var array $cfg */
            $cfg = require $local;
            $db = $cfg['db'] ?? [];

            return [
                'db' => [
                    'host' => (string) ($db['host'] ?? 'db'),
                    'port' => (int) ($db['port'] ?? 3306),
                    'name' => (string) ($db['name'] ?? 'budget_manager'),
                    'user' => (string) ($db['user'] ?? 'budget'),
                    'pass' => (string) ($db['pass'] ?? ''),
                    'charset' => (string) ($db['charset'] ?? 'utf8mb4'),
                ],
            ];
        }

        if (is_file($example)) {
            /** @var array $cfg */
            $cfg = require $example;
        } else {
            $cfg = [];
        }

        $db = $cfg['db'] ?? [];

        return [
            'db' => [
                'host' => self::env('DB_HOST', $db['host'] ?? '127.0.0.1'),
                'port' => (int) (self::env('DB_PORT', isset($db['port']) ? (string) $db['port'] : '3306')),
                'name' => self::env('DB_NAME', $db['name'] ?? 'budget_manager'),
                'user' => self::env('DB_USER', $db['user'] ?? 'root'),
                'pass' => self::env('DB_PASS', $db['pass'] ?? ''),
                'charset' => $db['charset'] ?? 'utf8mb4',
            ],
        ];
    }

    public static function env(string $key, ?string $default = null): ?string
    {
        $v = getenv($key);
        if ($v === false || $v === '') {
            return $default;
        }

        return $v;
    }
}
