<?php

declare(strict_types=1);

$host = getenv('DB_HOST') ?: 'db';
$db = getenv('DB_NAME') ?: 'budget_manager';
$user = getenv('DB_USER') ?: 'budget';
$pass = getenv('DB_PASS') ?: '';

$pdo = new PDO("mysql:host={$host};dbname={$db};charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$stmt = $pdo->prepare('SELECT id, username, email, role, disabled, password_hash FROM users WHERE username = ? OR email = ? LIMIT 1');
$stmt->execute(['admin', 'admin@localhost']);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    fwrite(STDERR, "admin user not found\n");
    exit(1);
}

echo 'id=' . $row['id'] . PHP_EOL;
echo 'username=' . $row['username'] . PHP_EOL;
echo 'email=' . $row['email'] . PHP_EOL;
echo 'role=' . $row['role'] . PHP_EOL;
echo 'disabled=' . ((int) $row['disabled'] ? 'yes' : 'no') . PHP_EOL;

$candidates = ['Admin.00', 'admin1234'];
foreach ($candidates as $candidate) {
    $ok = password_verify($candidate, (string) $row['password_hash']) ? 'ok' : 'fail';
    echo $candidate . '=' . $ok . PHP_EOL;
}
