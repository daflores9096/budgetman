<?php

declare(strict_types=1);

namespace BudgetMan\Services;

use BudgetMan\Core\AppConstants;
use BudgetMan\Core\Response;
use BudgetMan\Repositories\CategoryRepository;

final class CategoryService
{
    public function __construct(
        private readonly SchemaService $schema,
        private readonly CategoryRepository $categories,
    ) {
    }

    public function normalizeName(string $name): string
    {
        $name = preg_replace('/\s+/', ' ', $name);

        return $name === null ? '' : trim($name);
    }

    public function requireValid(string $category): void
    {
        $category = $this->normalizeName($category);
        if ($category === '') {
            Response::json(['error' => 'Categoría no válida'], 422);
            exit;
        }

        try {
            $this->schema->ensureDefaultCategoriesExist();
            if ($this->categories->existsByName($category)) {
                return;
            }
        } catch (\Throwable) {
            if (in_array($category, AppConstants::DEFAULT_CATEGORIES, true)) {
                return;
            }
        }

        Response::json(['error' => 'Categoría no válida'], 422);
        exit;
    }

    public function list(): array
    {
        $this->schema->ensureDefaultCategoriesExist();
        try {
            $items = $this->categories->listAll();
            $names = array_map(static fn(array $r): string => (string) $r['name'], $items);

            return ['categories' => $names, 'category_items' => $items];
        } catch (\Throwable) {
            return ['categories' => AppConstants::DEFAULT_CATEGORIES, 'category_items' => []];
        }
    }
}
