<?php

declare(strict_types=1);

namespace BudgetMan\Core;

final class AppConstants
{
    public const DEFAULT_CATEGORIES = [
        'Alimentación',
        'Salud',
        'Transporte',
        'Deudas/Créditos',
        'Mascotas',
        'Varios',
        'Servicios Hogar',
        'Entretenimiento',
    ];

    public const SESSION_COOKIE = 'bm_session';
    public const SESSION_TTL_SECONDS = 60 * 60 * 24 * 14;
}
