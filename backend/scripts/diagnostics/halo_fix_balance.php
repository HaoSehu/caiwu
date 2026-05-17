<?php

$out = 'c:/Users/cloud_user/Desktop/caiwu/backend/_halo_fix_balance_result.txt';
file_put_contents($out, "start\n");

// 读取 .env
$env = [];
foreach (file(__DIR__.'/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with(trim($line), '#') || ! str_contains($line, '=')) {
        continue;
    }
    [$k, $v] = explode('=', $line, 2);
    $env[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
}

$host = $env['DB_HOST'] ?? '127.0.0.1';
$port = $env['DB_PORT'] ?? '3306';
$db = $env['DB_DATABASE'] ?? 'idc';
$user = $env['DB_USERNAME'] ?? 'idc';
$pass = $env['DB_PASSWORD'] ?? '';

file_put_contents($out, "connecting to {$host}:{$port}/{$db}\n", FILE_APPEND);

try {
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    file_put_contents($out, "connected\n", FILE_APPEND);

    $affected = $pdo->exec('
        UPDATE user_accounts ua
        JOIN users u ON u.id = ua.user_id
        SET ua.cash_balance = u.balance
        WHERE u.deleted_at IS NULL
          AND u.balance != ua.cash_balance
    ');
    file_put_contents($out, "rows_updated={$affected}\n", FILE_APPEND);

    $stmt = $pdo->query('SELECT COUNT(*) FROM users u JOIN user_accounts ua ON ua.user_id=u.id WHERE u.deleted_at IS NULL AND u.balance!=ua.cash_balance');
    $remaining = $stmt->fetchColumn();
    file_put_contents($out, "mismatch_after={$remaining}\n", FILE_APPEND);

    // 注册迁移记录，避免下次 migrate 重复执行
    $batch = $pdo->query('SELECT MAX(batch) FROM migrations')->fetchColumn();
    $migration = '2026_04_21_000001_sync_user_accounts_cash_balance_from_users_balance';
    $exists = $pdo->query("SELECT COUNT(*) FROM migrations WHERE migration='{$migration}'")->fetchColumn();
    if (! $exists) {
        $pdo->exec("INSERT INTO migrations (migration, batch) VALUES ('{$migration}', ".((int) $batch + 1).')');
        file_put_contents($out, "migration_recorded\n", FILE_APPEND);
    } else {
        file_put_contents($out, "migration_already_recorded\n", FILE_APPEND);
    }

    file_put_contents($out, "done\n", FILE_APPEND);
} catch (Throwable $e) {
    file_put_contents($out, 'error: '.$e->getMessage()."\n", FILE_APPEND);
}
