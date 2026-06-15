<?php

declare(strict_types=1);

$newPassword = $argv[1] ?? 'Admin.00';
if (mb_strlen($newPassword) < 6) {
    fwrite(STDERR, "password must be at least 6 characters\n");
    exit(1);
}

$host = getenv('DB_HOST') ?: 'db';
$db = getenv('DB_NAME') ?: 'budget_manager';
$user = getenv('DB_USER') ?: 'budget';
$pass = getenv('DB_PASS') ?: '';

$pdo = new PDO("mysql:host={$host};dbname={$db};charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$hash = password_hash($newPassword, PASSWORD_DEFAULT);
$stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE username = 'admin' OR email = 'admin@localhost'");
$stmt->execute([$hash]);

echo 'updated_rows=' . $stmt->rowCount() . PHP_EOL;
echo 'new_password=' . $newPassword . PHP_EOL;
