<?php

declare(strict_types=1);

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'bootstrap.php';

use BudgetMan\Core\Cors;
use BudgetMan\Routes\Router;

Cors::apply();
Cors::handlePreflight();

Router::dispatch();
