<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

// 一次性脚本：同步 users.balance → user_accounts.cash_balance
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$pdo = DB::getPdo();
$pdo->exec('
    UPDATE user_accounts ua
    JOIN users u ON u.id = ua.user_id
    SET ua.cash_balance = u.balance
    WHERE u.deleted_at IS NULL
      AND u.balance != ua.cash_balance
');
$affected = $pdo->exec('SELECT ROW_COUNT()');

$check = DB::select(
    'SELECT COUNT(*) as mismatch FROM users u JOIN user_accounts ua ON ua.user_id = u.id WHERE u.deleted_at IS NULL AND u.balance != ua.cash_balance'
);

$result = 'mismatch_after='.($check[0]->mismatch ?? '?').PHP_EOL;
file_put_contents(__DIR__.'/_halo_sync_balance_result.txt', $result);
echo $result;
