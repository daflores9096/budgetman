<?php

declare(strict_types=1);

namespace BudgetMan\Repositories;

use BudgetMan\Core\Database;
use PDO;

abstract class BaseRepository
{
    protected function db(): PDO
    {
        return Database::connection();
    }
}
