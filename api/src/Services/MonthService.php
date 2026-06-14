<?php

declare(strict_types=1);

namespace BudgetMan\Services;

use BudgetMan\Core\Response;
use BudgetMan\Repositories\MonthRepository;

final class MonthService
{
    public function __construct(private readonly MonthRepository $months)
    {
    }

    public function validateYearMonth(int $year, int $month): void
    {
        if ($year < 2000 || $year > 2100 || $month < 1 || $month > 12) {
            Response::json(['error' => 'year o month inválidos'], 422);
            exit;
        }
    }

    public function ensureExists(int $monthId): void
    {
        if (!$this->months->findById($monthId)) {
            Response::json(['error' => 'Mes no encontrado'], 404);
            exit;
        }
    }

    public function getOrCreateId(int $year, int $month): int
    {
        $this->validateYearMonth($year, $month);
        $id = $this->months->getOrCreateId($year, $month);
        if ($id <= 0) {
            Response::json(['error' => 'No se pudo crear el mes'], 500);
            exit;
        }

        return $id;
    }

    public function parseDateParts(string $date): array
    {
        return array_map('intval', explode('-', $date, 3));
    }
}
