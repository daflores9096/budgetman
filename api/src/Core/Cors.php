<?php

declare(strict_types=1);

namespace BudgetMan\Core;

final class Cors
{
    public static function apply(): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
    }

    public static function handlePreflight(): void
    {
        if (Request::method() === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}
