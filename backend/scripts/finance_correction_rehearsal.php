<?php

declare(strict_types=1);

/**
 * 财务存量订正——影子表演练（idc_test，自包含、零残留）
 * ------------------------------------------------------------------
 * 在 idc_test 中用【合成夹具】复现第 1 步诊断的 8 类异常，跑：
 *   ① 事务回滚演示（execute 后回滚，验证失败整体回滚能力）
 *   ② dry-run（仅计划，不落库）
 *   ③ execute（落库）
 *   ④ 再 execute（断言幂等：第二遍无新差异）
 * 断言：台账=余额、断链清零、校准分录合计=0、用户可用资金未被搬动。
 * 结束：删除全部合成夹具，校验 idc_test 恢复到演练前状态。
 *
 * ⚠ 只允许自造数据 + 彻底回滚；禁止 migrate:fresh / 清库。
 * 用法：php backend/scripts/finance_correction_rehearsal.php
 *      （可选 --db=idc_test --keep 保留夹具以便排查）
 */
define('FC_AS_LIBRARY', 1);
require __DIR__.'/finance_correction.php';

// ------------------------------------------------------------ 合成夹具定义

/** 保留的高位合成用户ID区间，避免与真实数据冲突 */
const REH_USERS = [8500001, 8500002, 8500003, 8500004, 8500005, 8500006, 8500007, 8500008, 8500009, 8500010];

$opts = ['db' => 'idc_test', 'keep' => false, 'csv_dir' => sys_get_temp_dir().'/fc_rehearsal_'.date('YmdHis')];
foreach (array_slice($argv, 1) as $a) {
    if ($a === '--keep') {
        $opts['keep'] = true;
    } elseif (strpos($a, '--db=') === 0) {
        $opts['db'] = substr($a, 5);
    } elseif (strpos($a, '--csv-dir=') === 0) {
        $opts['csv_dir'] = substr($a, 10);
    }
}

$BATCH = 'rehearsal-'.date('Ymd-His');
$pass = 0;
$fail = 0;
$log = [];

function step(string $msg): void
{
    echo $msg.PHP_EOL;
}
function check(string $name, bool $ok, string $detail = ''): void
{
    global $pass, $fail, $log;
    if ($ok) {
        $pass++;
        echo "  [PASS] {$name}".($detail ? "  ({$detail})" : '').PHP_EOL;
    } else {
        $fail++;
        echo "  [FAIL] {$name}".($detail ? "  ({$detail})" : '').PHP_EOL;
    }
    $log[] = ($ok ? 'PASS ' : 'FAIL ').$name.($detail ? " | {$detail}" : '');
}

try {
    $pdo = fc_connect(['connection' => $opts['db']]);
} catch (Throwable $e) {
    fwrite(STDERR, '连接失败：'.$e->getMessage().PHP_EOL);
    exit(1);
}

$db = fc_db_name($pdo);
$TABLES_ALL = ['users', 'user_accounts', 'account_transactions', 'recharge_records', 'invoices', 'orders', 'payments', 'refunds'];

// ------------------------------------------------------------ 清理器（幂等）

function rehe_cleanup(PDO $pdo, array $ids): void
{
    $in = implode(',', array_map('intval', $ids));
    // 删除顺序：子表 → 主表
    $pdo->exec("DELETE FROM recharge_records WHERE user_id IN ({$in})");
    $pdo->exec("DELETE FROM account_transactions WHERE user_id IN ({$in})");
    $pdo->exec("DELETE FROM invoices WHERE user_id IN ({$in})");
    $pdo->exec("DELETE FROM orders WHERE user_id IN ({$in})");
    $pdo->exec("DELETE FROM user_accounts WHERE user_id IN ({$in})");
    $pdo->exec("DELETE FROM users WHERE id IN ({$in})");
}

function rehe_counts(PDO $pdo, array $tables): array
{
    $out = [];
    foreach ($tables as $t) {
        try {
            $out[$t] = (int) $pdo->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn();
        } catch (Throwable $e) {
            $out[$t] = -1;
        }
    }

    return $out;
}

// 兜底清理：任何异常退出都确保夹具被清除（除非 --keep）
register_shutdown_function(function () use ($pdo, $opts) {
    if ($opts['keep']) {
        return;
    }
    try {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $in = implode(',', REH_USERS);
        $exist = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE id IN ({$in})")->fetchColumn();
        if ($exist > 0) {
            $pdo->exec("DELETE FROM recharge_records WHERE user_id IN ({$in})");
            $pdo->exec("DELETE FROM account_transactions WHERE user_id IN ({$in})");
            $pdo->exec("DELETE FROM invoices WHERE user_id IN ({$in})");
            $pdo->exec("DELETE FROM orders WHERE user_id IN ({$in})");
            $pdo->exec("DELETE FROM user_accounts WHERE user_id IN ({$in})");
            $pdo->exec("DELETE FROM users WHERE id IN ({$in})");
        }
    } catch (Throwable $e) { /* 忽略 */
    }
});

// ------------------------------------------------------------ 前置：确认夹具ID空闲 + 基线快照

step('====================== 财务存量订正 · 影子表演练 ======================');
step("数据库={$db}  批次={$BATCH}  合成用户=".implode(',', REH_USERS));

$in = implode(',', REH_USERS);
$exist = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE id IN ({$in})")->fetchColumn();
if ($exist > 0) {
    fwrite(STDERR, "合成ID区间已被占用（{$exist} 行），终止以免影响并行任务。\n");
    exit(1);
}
$baseline = rehe_counts($pdo, $TABLES_ALL);
step('演练前基线行数：'.json_encode($baseline, JSON_UNESCAPED_UNICODE));

// ------------------------------------------------------------ 播种夹具

step("\n--- 播种合成夹具（复现 8 类异常）---");

$insUser = $pdo->prepare('INSERT INTO users (id, password, nickname, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())');
$insAcc = $pdo->prepare('INSERT INTO user_accounts
    (user_id, cash_balance, credit_limit, referral_frozen_balance, referral_available_balance,
     referral_pending_withdrawal_balance, referral_withdrawn_balance, version, created_at, updated_at)
    VALUES (?, ?, 0, ?, ?, 0, 0, 0, NOW(), NOW())');
$insTx = $pdo->prepare("INSERT INTO account_transactions
    (user_id, account_type, event_type, change_amount, currency, balance_after, source_type, remark, trace_id, created_at, updated_at)
    VALUES (?, ?, ?, ?, 'CNY', ?, ?, ?, ?, ?, NOW())");

$pdo->beginTransaction();
foreach (REH_USERS as $uid) {
    $insUser->execute([$uid, password_hash('rehearsal', PASSWORD_BCRYPT), "reh-{$uid}"]);
}
// 账户余额（余额为“真源”）
$acc = [
    8500001 => [0.00, 0.00, 0.00],       // 余额被清零
    8500002 => [100.10, 0.00, 0.00],     // 余额>台账（跳变型，无特判）
    8500003 => [80.00, 0.00, 0.00],      // 断链-误写
    8500004 => [95.49, 0.00, 0.00],      // 断链-清零
    8500005 => [50.00, 0.00, 0.00],      // account_type 双命名
    8500006 => [10.00, 0.00, 0.00],      // trace NULL
    8500007 => [0.00, 0.00, 0.00],       // 负余额过程行
    8500008 => [0.00, 0.00, 0.00],       // 推荐口径不平（仅清单）
    8500009 => [5.00, 0.00, 0.00],       // renew 无 service_id + recharge 脱节
    8500010 => [0.00, 0.00, 0.00],       // 订单↔账单错位 / 孤儿
];
foreach ($acc as $uid => [$cash, $rf, $ra]) {
    $insAcc->execute([$uid, $cash, $rf, $ra]);
}
// 现金流水
$tx = [
    // U1 余额清零：台账 +100，余额 0
    [8500001, 'cash', 'recharge', 100.00, 100.00, 'rehearsal:balance-zeroed'],
    // U2 余额>台账（跳变）
    [8500002, 'cash', 'recharge', 0.10, 0.10, 'rehearsal:jump-base'],
    // U3 断链-误写：+30 被写成 change_amount 本身
    [8500003, 'cash', 'recharge', 50.00, 50.00, 'rehearsal:ok'],
    [8500003, 'cash', 'recharge', 30.00, 30.00, 'rehearsal:miswrite'],   // 期望 80
    // U4 断链-清零：-4.51 后余额被清零
    [8500004, 'cash', 'recharge', 100.00, 100.00, 'rehearsal:ok'],
    [8500004, 'cash', 'consume', -4.51, 0.00, 'rehearsal:zeroed'],       // 期望 95.49
    // U5 双命名：cash_balance 旧命名
    [8500005, 'cash_balance', 'recharge', 40.00, 40.00, 'rehearsal:old-name'],
    [8500005, 'cash_balance', 'recharge', 10.00, 50.00, 'rehearsal:old-name'],
    // U6 trace NULL
    [8500006, 'cash', 'recharge', 10.00, 10.00, 'rehearsal:trace-null'],
    // U7 负余额过程行（链自洽）
    [8500007, 'cash', 'recharge', 30.00, 30.00, 'rehearsal:ok'],
    [8500007, 'cash', 'consume', -60.00, -30.00, 'rehearsal:negative'],
    [8500007, 'cash', 'refund', 30.00, 0.00, 'rehearsal:ok'],
    // U9 现金 +5（无 recharge_records → 展示表脱节）
    [8500009, 'cash', 'recharge', 5.00, 5.00, 'rehearsal:recharge-gap'],
];
foreach ($tx as [$u, $at, $et, $chg, $after, $remark]) {
    $trace = ($u === 8500006) ? null : "reh-trace-{$u}";
    $insTx->execute([$u, $at, $et, $chg, $after, 'rehearsal', $remark, $trace, '2026-09-01 10:00:00']);
}
// U8 推荐口径不平（available+frozen=0，台账 30）
$insTx->execute([8500008, 'referral', 'reward_frozen', 10.00, 10.00, 'rehearsal', 'rehearsal:referral', 'reh-trace-8500008', '2026-09-01 10:00:00']);
$insTx->execute([8500008, 'referral', 'reward_released', 10.00, 10.00, 'rehearsal', 'rehearsal:referral', 'reh-trace-8500008', '2026-09-01 10:01:00']);
$insTx->execute([8500008, 'referral', 'reward_frozen', 10.00, 20.00, 'rehearsal', 'rehearsal:referral', 'reh-trace-8500008', '2026-09-01 10:02:00']);
// U9 renew 无 service_id
$insInv = $pdo->prepare("INSERT INTO invoices (invoice_no, user_id, type, amount, status, service_id, created_at, updated_at)
    VALUES (?, ?, 'renew', ?, ?, NULL, NOW(), NOW())");
$insInv->execute(['REH-RENEW-1', 8500009, 5.00, 1]);
$insInv->execute(['REH-RENEW-2', 8500009, 9.90, 1]);
// U10 订单↔账单错位 + 孤儿订单
$insOrd = $pdo->prepare("INSERT INTO orders (order_no, user_id, type, amount, status, created_at, updated_at)
    VALUES (?, ?, 'new', ?, ?, NOW(), NOW())");
$insOrd->execute(['REH-ORD-1', 8500010, 20.00, 1]);
$ordId1 = (int) $pdo->lastInsertId();
$insOrd->execute(['REH-ORD-2', 8500010, 50.00, 1]); // 孤儿订单（无账单）
$insInv->execute(['REH-INV-1', 8500010, 20.00, 1]); // 金额 30 与 order 20 不符
$invId = (int) $pdo->lastInsertId();
$pdo->exec("UPDATE invoices SET order_id = {$ordId1}, amount = 30.00 WHERE id = {$invId}");
$pdo->commit();

step('夹具已播种并提交。');

// ------------------------------------------------------------ 演练前状态

function rehe_state(PDO $pdo, array $ids, string $mode): array
{
    $imb = fc_detect_imbalance($pdo, $ids);
    $chain = $mode === 'rebuild' ? fc_detect_chain_rebuild($pdo, $ids) : fc_detect_chain($pdo, $ids);

    return ['unbalanced' => count($imb), 'chain' => count($chain), 'imb' => $imb, 'chain_rows' => $chain];
}

$before = rehe_state($pdo, REH_USERS, 'local');
step("\n--- 演练前状态 ---");
step("  不平用户={$before['unbalanced']}  断链={$before['chain']}");
check('夹具复现“台账不平”', $before['unbalanced'] >= 2, "实际 {$before['unbalanced']}");
check('夹具复现“断链-误写/清零”', $before['chain'] >= 2, "实际 {$before['chain']}");

// ------------------------------------------------------------ ① 事务回滚演示

step("\n--- ① 事务回滚演示（execute 后 rollback）---");
$optsRollback = ['connection' => $opts['db'], 'users' => [8500001], 'execute' => true, 'batch' => $BATCH.'-rb', 'chain_mode' => 'local', 'do_chain' => false, 'do_account_type' => false, 'csv_dir' => $opts['csv_dir']];
$pdo->beginTransaction();
$s = fc_execute($pdo, $optsRollback);
$inTx = (int) $pdo->query("SELECT COUNT(*) FROM account_transactions WHERE user_id=8500001 AND event_type='correction'")->fetchColumn();
$pdo->rollBack();
$afterRollback = (int) $pdo->query("SELECT COUNT(*) FROM account_transactions WHERE user_id=8500001 AND event_type='correction'")->fetchColumn();
check('事务内可见校准分录', $inTx === 1, "in-tx={$inTx}");
check('回滚后校准分录消失', $afterRollback === 0, "after-rollback={$afterRollback}");

// ------------------------------------------------------------ ② dry-run

step("\n--- ② dry-run（只出计划，不落库）---");
$optsDry = [
    'connection' => $opts['db'], 'users' => REH_USERS, 'execute' => false, 'batch' => $BATCH,
    'chain_mode' => 'local', 'do_chain' => true, 'do_account_type' => true, 'csv_dir' => $opts['csv_dir'],
];
$dry = fc_execute($pdo, $optsDry);
step("  计划：account_type={$dry['planned']['account_type']} chain={$dry['planned']['chain']} calibration={$dry['planned']['calibration']}");
check('dry-run 有计划但零落库', $dry['applied']['calibration'] === 0 && $dry['applied']['chain'] === 0);
check('dry-run 检出校准=', $dry['planned']['calibration'] >= 2, "实际 {$dry['planned']['calibration']}");
check('dry-run 检出断链订正=', $dry['planned']['chain'] >= 2, "实际 {$dry['planned']['chain']}");
$stillUnbalanced = count(fc_detect_imbalance($pdo, REH_USERS));
check('dry-run 未改动数据（不平仍存在）', $stillUnbalanced === $before['unbalanced'], "现在 {$stillUnbalanced}");

// ------------------------------------------------------------ ③ execute

step("\n--- ③ execute（落库）---");
$optsExec = $optsDry;
$optsExec['execute'] = true;
$pdo->beginTransaction();
$exec = fc_execute($pdo, $optsExec);
$pdo->commit();
step("  已落库：account_type={$exec['applied']['account_type']} chain={$exec['applied']['chain']} calibration={$exec['applied']['calibration']}");

$after = fc_verify($pdo, REH_USERS, 'local');
step('  校验：不平用户='.count($after['unbalanced']).' 断链='.count($after['chain_breaks']).' 校准分录合计='.fc_money($after['calibration_sum']));
$calSum = (float) $pdo->query("SELECT COALESCE(SUM(change_amount),0) FROM account_transactions
    WHERE event_type='correction' AND user_id IN (".implode(',', REH_USERS).')')->fetchColumn();

check('订正后“台账=余额”全部达成', count($after['unbalanced']) === 0, '残留 '.count($after['unbalanced']));
check('订正后“断链清零”', count($after['chain_breaks']) === 0, '残留 '.count($after['chain_breaks']));
check('校准分录合计=0', fc_eq($calSum, 0.0), '合计='.fc_money($calSum));

// 逐用户守恒断言
$rows = $pdo->query("SELECT a.user_id, a.cash_balance, COALESCE(SUM(t.change_amount),0) ledger
    FROM user_accounts a LEFT JOIN account_transactions t
      ON t.user_id=a.user_id AND t.account_type IN ('cash','cash_balance')
    WHERE a.user_id IN (".implode(',', REH_USERS).') GROUP BY a.user_id, a.cash_balance')->fetchAll();
$allBalanced = true;
foreach ($rows as $r) {
    if (! fc_eq((float) $r['cash_balance'], (float) $r['ledger'])) {
        $allBalanced = false;
    }
}
check('逐用户 台账合计=账户余额', $allBalanced);

// 用户可用资金未被搬动
$accAfter = $pdo->query('SELECT user_id, cash_balance FROM user_accounts WHERE user_id IN ('.implode(',', REH_USERS).')')->fetchAll(PDO::FETCH_KEY_PAIR);
$fundsSame = true;
foreach ($acc as $uid => [$cash]) {
    if (! fc_eq((float) $accAfter[$uid], (float) $cash)) {
        $fundsSame = false;
    }
}
check('账户余额（用户资金）未被改动', $fundsSame);

$m = $exec['manual'];
check('仅清单项齐全（trace/负/推荐/充值/renew/错位/孤儿）',
    $m['trace_null'] >= 1 && $m['negative'] >= 1 && $m['referral_imbalance'] >= 1
    && $m['recharge_gap'] >= 1 && $m['renew_no_service'] >= 2
    && $m['order_invoice_mismatch'] >= 1 && $m['orphan_order'] >= 1,
    json_encode($m, JSON_UNESCAPED_UNICODE));

// ------------------------------------------------------------ ④ 幂等：再 execute

step("\n--- ④ 幂等：第二次 execute ---");
$pdo->beginTransaction();
$exec2 = fc_execute($pdo, $optsExec);
$pdo->commit();
step("  第二遍计划：account_type={$exec2['planned']['account_type']} chain={$exec2['planned']['chain']} calibration={$exec2['planned']['calibration']}");
check('第二遍无新差异（幂等）',
    $exec2['planned']['account_type'] === 0 && $exec2['planned']['chain'] === 0 && $exec2['planned']['calibration'] === 0,
    json_encode($exec2['planned'], JSON_UNESCAPED_UNICODE));
check('第二遍未新增落库', $exec2['applied']['calibration'] === 0 && $exec2['applied']['chain'] === 0);

// 链口径 rebuild 幂等复测
$optsReb = $optsExec;
$optsReb['chain_mode'] = 'rebuild';
$optsReb['batch'] = $BATCH.'-reb';
$pdo->beginTransaction();
$reb = fc_execute($pdo, $optsReb);
$pdo->commit();
check('rebuild 口径幂等（无新差异）', $reb['planned']['chain'] === 0 && $reb['planned']['calibration'] === 0,
    json_encode($reb['planned'], JSON_UNESCAPED_UNICODE));

// 落盘演练证据（临时目录）
$files = fc_write_report($pdo, $optsExec, $exec);
step("\n--- 演练差异与清单已落盘（临时目录）: ".$opts['csv_dir'].' ---');
foreach ($files as $k => $f) {
    step("  {$k}: ".basename($f));
}

// ------------------------------------------------------------ 清理 + 恢复校验

step("\n--- 清理合成夹具并校验零残留 ---");
$pdo->beginTransaction();
rehe_cleanup($pdo, REH_USERS);
$pdo->commit();
$afterCounts = rehe_counts($pdo, $TABLES_ALL);
step('演练后行数：'.json_encode($afterCounts, JSON_UNESCAPED_UNICODE));
check('idc_test 恢复到演练前状态（全表行数一致）', $afterCounts === $baseline,
    'before='.json_encode($baseline).' after='.json_encode($afterCounts));
$left = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE id IN ('.implode(',', REH_USERS).')')->fetchColumn();
check('合成用户已全部清除', $left === 0, "残留 {$left}");

// ------------------------------------------------------------ 汇总

step("\n====================== 演练汇总 ======================");
step("PASS={$pass}  FAIL={$fail}");
if ($opts['csv_dir'] && is_dir($opts['csv_dir']) && ! $opts['keep']) {
    // 临时证据目录清理（内容已打印/留档至文档）
    foreach (glob($opts['csv_dir'].'/*') as $f) {
        @unlink($f);
    }
    @rmdir($opts['csv_dir']);
}
exit($fail === 0 ? 0 : 1);
