<?php

declare(strict_types=1);

$host = getenv('DB_HOST') ?: 'db';
$db = getenv('DB_NAME') ?: 'budget_manager';
$user = getenv('DB_USER') ?: 'budget';
$pass = getenv('DB_PASS') ?: '';

$pdo = new PDO("mysql:host={$host};dbname={$db};charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$adminCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
echo "users_count={$count}\n";
echo "admin_count={$adminCount}\n";
