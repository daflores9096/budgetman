<?php

declare(strict_types=1);

namespace BudgetMan\Services;

use BudgetMan\Core\AppConstants;
use BudgetMan\Core\Database;
use PDO;
use Throwable;

final class SchemaService
{
    private static bool $authDone = false;
    private static bool $ledgerDone = false;
    private static bool $recurringDone = false;
    private static bool $categoriesDone = false;

    private function db(): PDO
    {
        return Database::connection();
    }

    public function ensureAuthSchema(): void
    {
        if (self::$authDone) {
            return;
        }
        self::$authDone = true;

        $this->db()->exec(
            "CREATE TABLE IF NOT EXISTS users (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(64) NOT NULL,
                email VARCHAR(190) NOT NULL,
                name VARCHAR(120) NOT NULL DEFAULT '',
                role ENUM('admin','appuser') NOT NULL DEFAULT 'appuser',
                password_hash VARCHAR(255) NOT NULL,
                disabled TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_users_username (username),
                UNIQUE KEY uq_users_email (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"
        );

        try {
            $this->db()->exec("ALTER TABLE users ADD COLUMN username VARCHAR(64) NOT NULL DEFAULT ''");
        } catch (Throwable) {
        }
        try {
            $this->db()->exec("UPDATE users SET username = CONCAT('user', id) WHERE username = '' OR username IS NULL");
        } catch (Throwable) {
        }
        try {
            $this->db()->exec("ALTER TABLE users ADD UNIQUE KEY uq_users_username (username)");
        } catch (Throwable) {
        }

        $this->db()->exec(
            "CREATE TABLE IF NOT EXISTS sessions (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                token_hash CHAR(64) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_seen_at TIMESTAMP NULL DEFAULT NULL,
                expires_at TIMESTAMP NOT NULL,
                UNIQUE KEY uq_sessions_token_hash (token_hash),
                KEY idx_sessions_user (user_id),
                CONSTRAINT fk_sessions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"
        );

        $this->db()->exec(
            "CREATE TABLE IF NOT EXISTS password_resets (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                token_hash CHAR(64) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                expires_at TIMESTAMP NOT NULL,
                used_at TIMESTAMP NULL DEFAULT NULL,
                UNIQUE KEY uq_password_resets_token_hash (token_hash),
                KEY idx_password_resets_user (user_id),
                CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"
        );
    }

    public function ensureLedgerUserSchema(): void
    {
        if (self::$ledgerDone) {
            return;
        }
        self::$ledgerDone = true;

        try {
            $cols = $this->db()->query("SELECT TABLE_NAME, COLUMN_NAME
              FROM INFORMATION_SCHEMA.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('expenses','incomes')")->fetchAll();
            $has = [];
            foreach ($cols as $c) {
                $has[strtolower((string) $c['TABLE_NAME']) . '.' . strtolower((string) $c['COLUMN_NAME'])] = true;
            }

            if (empty($has['expenses.user_id'])) {
                $this->db()->exec('ALTER TABLE expenses ADD COLUMN user_id INT UNSIGNED NULL');
                $this->db()->exec('ALTER TABLE expenses ADD KEY idx_expenses_user (user_id)');
            }
            if (empty($has['incomes.user_id'])) {
                $this->db()->exec('ALTER TABLE incomes ADD COLUMN user_id INT UNSIGNED NULL');
                $this->db()->exec('ALTER TABLE incomes ADD KEY idx_incomes_user (user_id)');
            }
        } catch (Throwable) {
        }
    }

    public function ensureRecurringFixedSchema(): void
    {
        if (self::$recurringDone) {
            return;
        }
        self::$recurringDone = true;

        $this->db()->exec(
            'CREATE TABLE IF NOT EXISTS recurring_fixed_expenses (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL DEFAULT \'\',
                expected_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                category VARCHAR(64) NOT NULL DEFAULT \'Varios\',
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->db()->exec(
            'CREATE TABLE IF NOT EXISTS recurring_fixed_expense_monthly (
                recurring_fixed_expense_id INT UNSIGNED NOT NULL,
                year SMALLINT UNSIGNED NOT NULL,
                month TINYINT UNSIGNED NOT NULL,
                expense_id INT UNSIGNED NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (recurring_fixed_expense_id, year, month),
                KEY idx_rfxm_expense (expense_id),
                CONSTRAINT fk_rfxm_template FOREIGN KEY (recurring_fixed_expense_id)
                    REFERENCES recurring_fixed_expenses (id) ON DELETE CASCADE,
                CONSTRAINT fk_rfxm_expense FOREIGN KEY (expense_id)
                    REFERENCES expenses (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function ensureDefaultCategoriesExist(): void
    {
        if (self::$categoriesDone) {
            return;
        }
        self::$categoriesDone = true;

        try {
            $stmt = $this->db()->query('SELECT COUNT(*) AS c FROM categories');
            $row = $stmt->fetch();
            $count = isset($row['c']) ? (int) $row['c'] : 0;
            if ($count > 0) {
                return;
            }
            $ins = $this->db()->prepare('INSERT IGNORE INTO categories (name) VALUES (?)');
            foreach (AppConstants::DEFAULT_CATEGORIES as $name) {
                $ins->execute([$name]);
            }
        } catch (Throwable) {
        }
    }
}
