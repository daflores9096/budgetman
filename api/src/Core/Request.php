<?php

declare(strict_types=1);

namespace BudgetMan\Core;

final class Request
{
    public static function method(): string
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }

    public static function path(): string
    {
        return parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    }

    public static function query(): array
    {
        return $_GET ?? [];
    }

    public static function jsonBody(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    public static function bearerToken(): string
    {
        $hdr = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (is_string($hdr) && preg_match('/^Bearer\s+(.+)$/i', trim($hdr), $m)) {
            return trim((string) $m[1]);
        }

        return '';
    }

    public static function cookie(string $name): string
    {
        return (string) ($_COOKIE[$name] ?? '');
    }
}
