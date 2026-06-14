<?php

declare(strict_types=1);

namespace BudgetMan\Services;

final class TokenService
{
    public function base64urlEncode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    public function randomToken(int $bytes = 32): string
    {
        return $this->base64urlEncode(random_bytes($bytes));
    }

    public function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
