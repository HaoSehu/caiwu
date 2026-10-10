<?php

declare(strict_types=1);

/**
 * 财务存量订正脚本（演练 / 生产通用）
 * ------------------------------------------------------------------
 * 依据：docs/_local/财务存量订正-2026-10-10/只读诊断报告.md 与
 *       docs/_local/收敛决策材料-2026-10-10/财务存量订正专项演练方案.md
 *
 * 特性：
 *   - 默认 dry-run，仅检测与输出差异清单，不写库；
 *   - --execute 显式执行，事务包裹，失败整体回滚；
 *   - 幂等：重复执行不产生新分录 / 新差异（校准以批次号去重）；
 *   - 可审计：差异落 CSV，原值写入 remark；
 *   - 可限定用户范围 --users=1,2,3（演练用，避免影响他人）。
 *
 * 覆盖的“可自动项”：
 *   1) 现金台账 vs 账户余额不平 → 以余额为准，补记校准分录（event_type=correction）；
 *   2) balance_after 断链修正（口径：误写 balance_after=change_amount / 清零为 0）；
 *   3) account_type 双命名归一（cash_balance → cash）。
 * 其余项仅输出清单，不改数：trace_id NULL、负余额行、推荐口径、recharge_records 脱节、
 *   renew 无 service_id、订单↔账单错位 / 孤儿订单。
 *
 * 红线（沿用方案）：不物理删除 payments / account_transactions；订正=补记或订正特定列。
 *
 * 用法：
 *   php backend/scripts/finance_correction.php --connection=idc_test            # dry-run
 *   php backend/scripts/finance_correction.php --connection=idc_test --execute  # 执行
 *   php backend/scripts/finance_correction.php --users=73,137 --chain-mode=rebuild
 *   php backend/scripts/finance_correction.php --execute --batch=corr-20261010-01
 */
if (! defined('FC_AS_LIBRARY')) {
    fc_cli_main($argv);
}

// ============================================================ 常量

const FC_VERSION = '1.0.0';
/** 现金口径账户名（未归一 + 归一后） */
const FC_CASH_TYPES = ['cash', 'cash_balance'];
/** 台账校准分录事件类型（文档化新增枚举，待落 DATABASE.md 契约） */
const FC_EVENT_CORRECTION = 'correction';

// ============================================================ 参数解析

function fc_default_opts(): array
{
    return [
        'connection' => null,      // 数据库名（idc / idc_test），空则用 .env 的 DB_DATABASE
        'database' => null,      // 同 connection
        'host' => null,
        'port' => null,
        'user' => null,
        'pass' => null,
        'execute' => false,
        'batch' => null,
        'users' => [],        // [] = 全库
        'chain_mode' => 'local',   // local | rebuild
        'do_chain' => true,
        'do_calibration' => true,
        'do_account_type' => true,
        'csv_dir' => null,
        'env_file' => null,
        'quiet' => false,
    ];
}

function fc_parse_args(array $argv): array
{
    $opts = fc_default_opts();
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--execute') {
            $opts['execute'] = true;
        } elseif ($arg === '--quiet') {
            $opts['quiet'] = true;
        } elseif ($arg === '--no-chain') {
            $opts['do_chain'] = false;
        } elseif ($arg === '--no-calibration') {
            $opts['do_calibration'] = false;
        } elseif ($arg === '--no-account-type') {
            $opts['do_account_type'] = false;
        } elseif (strpos($arg, '--') === 0 && strpos($arg, '=') !== false) {
            [$k, $v] = explode('=', substr($arg, 2), 2);
            switch ($k) {
                case 'connection':
                case 'database':
                    $opts['connection'] = $v;
                    break;
                case 'host': $opts['host'] = $v;
                    break;
                case 'port': $opts['port'] = $v;
                    break;
                case 'user': $opts['user'] = $v;
                    break;
                case 'pass': $opts['pass'] = $v;
                    break;
                case 'batch': $opts['batch'] = $v;
                    break;
                case 'chain-mode': $opts['chain_mode'] = $v;
                    break;
                case 'csv-dir': $opts['csv_dir'] = $v;
                    break;
                case 'env-file': $opts['env_file'] = $v;
                    break;
                case 'users':
                    $opts['users'] = array_values(array_filter(array_map('intval', explode(',', $v))));
                    break;
                default:
                    fwrite(STDERR, "未知参数：--{$k}\n");
                    exit(2);
            }
        }
    }
    if (! in_array($opts['chain_mode'], ['local', 'rebuild'], true)) {
        fwrite(STDERR, "chain-mode 仅支持 local / rebuild\n");
        exit(2);
    }

    return $opts;
}

// ============================================================ 连接

function fc_read_env(string $file): array
{
    $env = [];
    if (! is_file($file)) {
        return $env;
    }
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $env[trim($k)] = trim($v, " \t\"'");
    }

    return $env;
}

function fc_connect(array $opts): PDO
{
    $opts = array_merge(fc_default_opts(), $opts);
    $envFile = $opts['env_file'] ?: __DIR__.'/../.env';
    $env = fc_read_env($envFile);
    $host = $opts['host'] ?: ($env['DB_HOST'] ?? '127.0.0.1');
    $port = $opts['port'] ?: ($env['DB_PORT'] ?? '3306');
    $user = $opts['user'] ?: ($env['DB_USERNAME'] ?? 'root');
    $pass = $opts['pass'] !== null ? $opts['pass'] : ($env['DB_PASSWORD'] ?? '');
    $db = $opts['connection'] ?: $opts['database'] ?: ($env['DB_DATABASE'] ?? 'idc');
    $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

function fc_db_name(PDO $pdo): string
{
    return (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
}

// ============================================================ 工具

function fc_money(float $v): string
{
    return number_format($v, 2, '.', '');
}

function fc_eq(float $a, float $b): bool
{
    return abs($a - $b) < 0.0001;
}

/** 用户范围 SQL 片段（安全：仅拼接整数 id） */
function fc_scope_clause(array $users, string $col = 'user_id'): array
{
    if (! $users) {
        return ['', []];
    }
    $ids = array_map('intval', $users);

    return [" AND {$col} IN (".implode(',', $ids).')', []];
}

function fc_ensure_dir(string $dir): void
{
    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
}

// ============================================================ 检测

/** 现金台账 vs 余额不平（与第 1 步诊断同口径：仅现金账户） */
function fc_detect_imbalance(PDO $pdo, array $users): array
{
    [$clause] = fc_scope_clause($users, 'a.user_id');
    $sql = "SELECT a.user_id,
                   a.cash_balance,
                   COALESCE(SUM(t.change_amount), 0) AS ledger_sum
            FROM user_accounts a
            LEFT JOIN account_transactions t
                   ON t.user_id = a.user_id
                  AND t.account_type IN ('cash', 'cash_balance')
            WHERE 1 = 1 {$clause}
            GROUP BY a.user_id, a.cash_balance
            HAVING ABS(a.cash_balance - COALESCE(SUM(t.change_amount), 0)) > 0.0001
            ORDER BY a.user_id";
    $out = [];
    foreach ($pdo->query($sql)->fetchAll() as $r) {
        $out[] = [
            'user_id' => (int) $r['user_id'],
            'cash_balance' => (float) $r['cash_balance'],
            'ledger_sum' => (float) $r['ledger_sum'],
            'delta' => round((float) $r['cash_balance'] - (float) $r['ledger_sum'], 2),
        ];
    }

    return $out;
}

/**
 * balance_after 断链检测（现金账户，按“上一条记录值”局部口径）。
 * 类别：miswrite（balance_after 恰等于 change_amount）/ zeroed（balance_after 归零）/ jump（未解释跳变）。
 * 返回：每个用户一行 [id,user_id,change_amount,prev_after,expected,recorded,class,created_at]
 */
function fc_detect_chain(PDO $pdo, array $users): array
{
    [$clause] = fc_scope_clause($users, 'user_id');
    $sql = "SELECT id, user_id, account_type, event_type, change_amount, balance_after, created_at
            FROM account_transactions
            WHERE account_type IN ('cash', 'cash_balance') {$clause}
            ORDER BY user_id, created_at, id";
    $rows = $pdo->query($sql)->fetchAll();

    $breaks = [];
    $prevByUser = [];
    foreach ($rows as $r) {
        $u = (int) $r['user_id'];
        $chg = (float) $r['change_amount'];
        $after = (float) $r['balance_after'];
        if (! array_key_exists($u, $prevByUser)) {
            $prevByUser[$u] = null;
        }
        $prev = $prevByUser[$u];
        if ($prev !== null) {
            $exp = round($prev + $chg, 2);
            if (! fc_eq($exp, $after)) {
                $cls = 'jump';
                if (fc_eq($after, $chg)) {
                    $cls = 'miswrite';           // 误写为 change_amount 本身
                } elseif (fc_eq($after, 0.0)) {
                    $cls = 'zeroed';             // 归零
                }
                $breaks[] = [
                    'id' => (int) $r['id'],
                    'user_id' => $u,
                    'change_amount' => $chg,
                    'prev_after' => round($prev, 2),
                    'expected' => $exp,
                    'recorded' => $after,
                    'class' => $cls,
                    'created_at' => $r['created_at'],
                ];
            }
        }
        $prevByUser[$u] = $after;
    }

    return $breaks;
}

/** 前向重建口径：累计值与记录值不符的行 */
function fc_detect_chain_rebuild(PDO $pdo, array $users): array
{
    [$clause] = fc_scope_clause($users, 'user_id');
    $sql = "SELECT id, user_id, change_amount, balance_after, created_at
            FROM account_transactions
            WHERE account_type IN ('cash', 'cash_balance') {$clause}
            ORDER BY user_id, created_at, id";
    $rows = $pdo->query($sql)->fetchAll();

    $diffs = [];
    $cum = [];
    foreach ($rows as $r) {
        $u = (int) $r['user_id'];
        $chg = (float) $r['change_amount'];
        $after = (float) $r['balance_after'];
        $cum[$u] = round(($cum[$u] ?? 0.0) + $chg, 2);
        if (! fc_eq($cum[$u], $after)) {
            $diffs[] = [
                'id' => (int) $r['id'],
                'user_id' => $u,
                'expected' => $cum[$u],
                'recorded' => $after,
                'created_at' => $r['created_at'],
            ];
        }
    }

    return $diffs;
}

function fc_detect_account_type(PDO $pdo, array $users): array
{
    [$clause] = fc_scope_clause($users, 'user_id');
    $sql = "SELECT account_type, COUNT(*) AS c FROM account_transactions WHERE 1=1 {$clause}
            GROUP BY account_type ORDER BY c DESC";

    return $pdo->query($sql)->fetchAll();
}

// ---- 仅清单项（不改数） ----

function fc_list_trace_null(PDO $pdo, array $users): array
{
    [$clause] = fc_scope_clause($users, 'user_id');

    return $pdo->query("SELECT id,user_id,account_type,event_type,change_amount,created_at
        FROM account_transactions WHERE trace_id IS NULL {$clause} ORDER BY id")->fetchAll();
}

function fc_list_negative(PDO $pdo, array $users): array
{
    [$clause] = fc_scope_clause($users, 'user_id');

    return $pdo->query("SELECT id,user_id,account_type,event_type,change_amount,balance_after,created_at
        FROM account_transactions WHERE balance_after < 0 {$clause} ORDER BY id")->fetchAll();
}

function fc_list_referral_imbalance(PDO $pdo, array $users): array
{
    [$clause] = fc_scope_clause($users, 'a.user_id');
    $sql = "SELECT a.user_id,
                   a.referral_available_balance,
                   a.referral_frozen_balance,
                   COALESCE(SUM(t.change_amount), 0) AS ledger_sum
            FROM user_accounts a
            LEFT JOIN account_transactions t
                   ON t.user_id = a.user_id
                  AND t.account_type IN ('referral', 'referral_available', 'referral_frozen')
            WHERE 1 = 1 {$clause}
            GROUP BY a.user_id, a.referral_available_balance, a.referral_frozen_balance
            HAVING ABS(a.referral_available_balance + a.referral_frozen_balance
                       - COALESCE(SUM(t.change_amount), 0)) > 0.0001
            ORDER BY a.user_id";

    return $pdo->query($sql)->fetchAll();
}

function fc_list_recharge_gap(PDO $pdo, array $users): array
{
    [$clause] = fc_scope_clause($users, 't.user_id');
    $sql = "SELECT t.id AS account_transaction_id, t.user_id, t.event_type, t.change_amount, t.created_at
            FROM account_transactions t
            LEFT JOIN recharge_records rr ON rr.account_transaction_id = t.id
            WHERE t.event_type IN ('recharge', 'manual_recharge')
              AND rr.id IS NULL {$clause}
            ORDER BY t.id";

    return $pdo->query($sql)->fetchAll();
}

function fc_list_renew_no_service(PDO $pdo, array $users): array
{
    [$clause] = fc_scope_clause($users, 'user_id');
    $sql = "SELECT id, invoice_no, user_id, order_id, service_id, status, paid_amount, paid_at, created_at
            FROM invoices
            WHERE type = 'renew' AND (service_id IS NULL OR service_id = 0) {$clause}
            ORDER BY id";
    try {
        return $pdo->query($sql)->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function fc_list_order_invoice(PDO $pdo, array $users): array
{
    [$clauseO] = fc_scope_clause($users, 'o.user_id');
    try {
        $mismatch = $pdo->query("SELECT o.id AS order_id, o.order_no, o.user_id, o.status AS o_status,
                   o.amount AS o_amount, i.id AS invoice_id, i.status AS i_status, i.amount AS i_amount
            FROM orders o JOIN invoices i ON i.order_id = o.id
            WHERE (o.amount <> i.amount OR o.status <> i.status) {$clauseO}
            ORDER BY o.id")->fetchAll();
    } catch (Throwable $e) {
        $mismatch = [];
    }
    [$clauseO2] = fc_scope_clause($users, 'o.user_id');
    try {
        $orphan = $pdo->query("SELECT o.id AS order_id, o.order_no, o.user_id, o.status, o.created_at
            FROM orders o LEFT JOIN invoices i ON i.order_id = o.id
            WHERE i.id IS NULL {$clauseO2} ORDER BY o.id")->fetchAll();
    } catch (Throwable $e) {
        $orphan = [];
    }

    return ['mismatch' => $mismatch, 'orphan' => $orphan];
}

// ============================================================ 执行

/**
 * 检测 + （可选）落库。不做事务控制，由调用方 begin/commit/rollback。
 *
 * @return array 汇总（含 diffs 供 CSV 落盘）
 */
function fc_execute(PDO $pdo, array $opts): array
{
    $opts = array_merge(fc_default_opts(), $opts);
    $batch = $opts['batch'] ?: ('corr-'.date('Ymd').'-01');
    $users = $opts['users'];
    $execute = (bool) $opts['execute'];

    $summary = [
        'batch' => $batch,
        'db' => fc_db_name($pdo),
        'mode' => $execute ? 'execute' : 'dry-run',
        'chain_mode' => $opts['chain_mode'],
        'scope_users' => $users,
        'account_type_dist' => fc_detect_account_type($pdo, $users),
        'imbalance' => fc_detect_imbalance($pdo, $users),
        'chain_breaks' => fc_detect_chain($pdo, $users),
        'diffs' => [],
        'applied' => ['account_type' => 0, 'chain' => 0, 'calibration' => 0],
        'manual' => [],
    ];

    // ---- 1) account_type 归一 cash_balance -> cash ----
    if ($opts['do_account_type']) {
        [$clause] = fc_scope_clause($users, 'user_id');
        $sql = "SELECT id, user_id, account_type FROM account_transactions
                WHERE account_type = 'cash_balance' {$clause} ORDER BY id";
        $targets = $pdo->query($sql)->fetchAll();
        foreach ($targets as $t) {
            $summary['diffs'][] = [
                'item' => 'account_type', 'action' => 'normalize', 'table' => 'account_transactions',
                'ref_id' => $t['id'], 'user_id' => $t['user_id'],
                'field' => 'account_type', 'old' => 'cash_balance', 'new' => 'cash',
                'note' => '双命名归一',
            ];
        }
        if ($execute && $targets) {
            $upd = $pdo->prepare("UPDATE account_transactions
                SET account_type = 'cash',
                    remark = CONCAT(COALESCE(remark,''), ?),
                    updated_at = NOW()
                WHERE id = ? AND account_type = 'cash_balance'");
            foreach ($targets as $t) {
                // 批次号来自 CLI 输入，必须参数绑定，禁止拼接进 SQL 文本
                $upd->execute([" [corr:{$batch} orig_account_type=cash_balance]", $t['id']]);
                $summary['applied']['account_type']++;
            }
            // 归一后重新检测断链/不平（口径基于归一后的现金账户）
            $summary['imbalance'] = fc_detect_imbalance($pdo, $users);
            $summary['chain_breaks'] = fc_detect_chain($pdo, $users);
        }
    }

    // ---- 2) balance_after 断链修正 ----
    $chainPlan = [];
    if ($opts['do_chain']) {
        if ($opts['chain_mode'] === 'local') {
            foreach ($summary['chain_breaks'] as $b) {
                if ($b['class'] === 'miswrite' || $b['class'] === 'zeroed') {
                    $chainPlan[] = $b; // 仅订正“误写/清零”两类；jump 留人工
                }
            }
        } else { // rebuild
            foreach (fc_detect_chain_rebuild($pdo, $users) as $d) {
                $chainPlan[] = [
                    'id' => $d['id'], 'user_id' => $d['user_id'],
                    'expected' => $d['expected'], 'recorded' => $d['recorded'],
                    'class' => 'rebuild',
                ];
            }
        }
        foreach ($chainPlan as $p) {
            $summary['diffs'][] = [
                'item' => 'chain', 'action' => 'fix_balance_after', 'table' => 'account_transactions',
                'ref_id' => $p['id'], 'user_id' => $p['user_id'],
                'field' => 'balance_after', 'old' => fc_money($p['recorded']), 'new' => fc_money($p['expected']),
                'note' => '断链'.$p['class'],
            ];
        }
        if ($execute && $chainPlan) {
            $upd = $pdo->prepare("UPDATE account_transactions
                SET balance_after = ?,
                    remark = CONCAT(COALESCE(remark,''), ?),
                    updated_at = NOW()
                WHERE id = ?");
            foreach ($chainPlan as $p) {
                $marker = " [corr:{$batch} orig_balance_after=".fc_money($p['recorded']).']';
                $upd->execute([fc_money($p['expected']), $marker, $p['id']]);
                $summary['applied']['chain']++;
            }
        }
    }

    // ---- 3) 现金台账不平 -> 以余额为准补校准分录 ----
    if ($opts['do_calibration'] && $execute) {
        // 校准前重算不平（若断链已订正，现金台账合计不变，余额也不变，故不平集合稳定）
        $imbalance = fc_detect_imbalance($pdo, $users);
    } else {
        $imbalance = $summary['imbalance'];
    }
    $calPlan = [];
    foreach ($imbalance as $im) {
        $delta = $im['delta'];
        if (fc_eq($delta, 0.0)) {
            continue;
        }
        // 该用户最后一条现金流水的 balance_after（校准行延续局部链）
        $prev = $pdo->prepare("SELECT balance_after FROM account_transactions
            WHERE user_id = ? AND account_type IN ('cash','cash_balance')
            ORDER BY created_at DESC, id DESC LIMIT 1");
        $prev->execute([$im['user_id']]);
        $lastAfter = (float) ($prev->fetchColumn() ?: 0.0);
        $newAfter = round($lastAfter + $delta, 2);
        $calPlan[] = [
            'user_id' => $im['user_id'], 'delta' => $delta,
            'balance_after' => $newAfter, 'cash_balance' => $im['cash_balance'],
            'ledger_sum' => $im['ledger_sum'],
        ];
    }
    foreach ($calPlan as $c) {
        $summary['diffs'][] = [
            'item' => 'calibration', 'action' => 'insert_ledger', 'table' => 'account_transactions',
            'ref_id' => '', 'user_id' => $c['user_id'],
            'field' => 'change_amount', 'old' => '(无)', 'new' => fc_money($c['delta']),
            'note' => '余额校准：台账'.fc_money($c['ledger_sum']).'→余额'.fc_money($c['cash_balance']),
        ];
    }
    if ($execute && $calPlan) {
        $ins = $pdo->prepare("INSERT INTO account_transactions
            (user_id, account_type, event_type, change_amount, currency, balance_after,
             source_type, source_id, origin_type, origin_id, remark, operator, trace_id, created_at, updated_at)
            VALUES (?, 'cash', ?, ?, 'CNY', ?, 'correction', NULL, NULL, NULL, ?, 'finance-correction', NULL, NOW(), NOW())");
        $exists = $pdo->prepare('SELECT COUNT(*) FROM account_transactions
            WHERE user_id = ? AND event_type = ? AND remark LIKE ?');
        foreach ($calPlan as $c) {
            // 幂等：同批次已有校准分录则跳过
            $exists->execute([$c['user_id'], FC_EVENT_CORRECTION, "%[corr:{$batch}]%"]);
            if ((int) $exists->fetchColumn() > 0) {
                continue;
            }
            $remark = "[corr:{$batch}] 余额校准：台账".fc_money($c['ledger_sum'])
                .'→余额'.fc_money($c['cash_balance']);
            $ins->execute([$c['user_id'], FC_EVENT_CORRECTION, fc_money($c['delta']), fc_money($c['balance_after']), $remark]);
            $summary['applied']['calibration']++;
        }
    }

    // ---- 4) 仅清单项 ----
    $oi = fc_list_order_invoice($pdo, $users);
    $summary['manual'] = [
        'trace_null' => count(fc_list_trace_null($pdo, $users)),
        'negative' => count(fc_list_negative($pdo, $users)),
        'referral_imbalance' => count(fc_list_referral_imbalance($pdo, $users)),
        'recharge_gap' => count(fc_list_recharge_gap($pdo, $users)),
        'renew_no_service' => count(fc_list_renew_no_service($pdo, $users)),
        'order_invoice_mismatch' => count($oi['mismatch']),
        'orphan_order' => count($oi['orphan']),
    ];
    $summary['_manual_rows'] = [
        'trace_null' => fc_list_trace_null($pdo, $users),
        'negative' => fc_list_negative($pdo, $users),
        'referral_imbalance' => fc_list_referral_imbalance($pdo, $users),
        'recharge_gap' => fc_list_recharge_gap($pdo, $users),
        'renew_no_service' => fc_list_renew_no_service($pdo, $users),
        'order_invoice_mismatch' => $oi['mismatch'],
        'orphan_order' => $oi['orphan'],
    ];
    $summary['calibration_plan'] = $calPlan;
    // 计划变更量（统计差异清单，dry-run 与 execute 均可观测）
    $planned = ['account_type' => 0, 'chain' => 0, 'calibration' => 0];
    foreach ($summary['diffs'] as $d) {
        if (isset($planned[$d['item']])) {
            $planned[$d['item']]++;
        }
    }
    $summary['planned'] = $planned;

    return $summary;
}

// ============================================================ 断言/校验

/** 返回 [unbalanced_users, chain_breaks]（用于订正后校验，0 即达标） */
function fc_verify(PDO $pdo, array $users, string $chainMode = 'local'): array
{
    $unbalanced = fc_detect_imbalance($pdo, $users);
    $breaks = $chainMode === 'rebuild'
        ? fc_detect_chain_rebuild($pdo, $users)
        : fc_detect_chain($pdo, $users);
    [$clause] = fc_scope_clause($users, 'user_id');

    return [
        'unbalanced' => $unbalanced,
        'chain_breaks' => $breaks,
        'calibration_sum' => (float) $pdo->query(
            "SELECT COALESCE(SUM(change_amount),0) FROM account_transactions
             WHERE event_type = '".FC_EVENT_CORRECTION."' {$clause}"
        )->fetchColumn(),
    ];
}

// ============================================================ CSV 落盘

function fc_write_csv(string $path, array $headers, array $rows): int
{
    $fp = fopen($path, 'w');
    fwrite($fp, "\xEF\xBB\xBF"); // BOM，便于 Excel
    fputcsv($fp, $headers);
    $n = 0;
    foreach ($rows as $r) {
        fputcsv($fp, array_map(static fn ($v) => is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : $v, $r));
        $n++;
    }
    fclose($fp);

    return $n;
}

function fc_write_report(PDO $pdo, array $opts, array $summary): array
{
    $opts = array_merge(fc_default_opts(), $opts);
    $dir = $opts['csv_dir'] ?: (__DIR__.'/../../docs/_local/财务存量订正-2026-10-10');
    fc_ensure_dir($dir);
    $stamp = $summary['batch'].'_'.($opts['execute'] ? 'execute' : 'dryrun');
    $files = [];

    // 差异主表
    $diffRows = array_map(static fn ($d) => [
        $d['item'], $d['action'], $d['table'], $d['ref_id'], $d['user_id'], $d['field'], $d['old'], $d['new'], $d['note'],
    ], $summary['diffs']);
    $files['diff'] = $dir."/correction_diff_{$stamp}.csv";
    fc_write_csv($files['diff'], ['item', 'action', 'table', 'ref_id', 'user_id', 'field', 'old', 'new', 'note'], $diffRows);

    // 待人工清单
    $m = $summary['_manual_rows'];
    $files['trace_null'] = $dir."/manual_trace_null_{$stamp}.csv";
    fc_write_csv($files['trace_null'], ['id', 'user_id', 'account_type', 'event_type', 'change_amount', 'created_at'], $m['trace_null']);

    $files['negative'] = $dir."/manual_negative_{$stamp}.csv";
    fc_write_csv($files['negative'], ['id', 'user_id', 'account_type', 'event_type', 'change_amount', 'balance_after', 'created_at'], $m['negative']);

    $files['referral'] = $dir."/manual_referral_{$stamp}.csv";
    fc_write_csv($files['referral'], ['user_id', 'referral_available_balance', 'referral_frozen_balance', 'ledger_sum'], $m['referral_imbalance']);

    $files['recharge_gap'] = $dir."/manual_recharge_gap_{$stamp}.csv";
    fc_write_csv($files['recharge_gap'], ['account_transaction_id', 'user_id', 'event_type', 'change_amount', 'created_at'], $m['recharge_gap']);

    $files['renew'] = $dir."/manual_renew_no_service_{$stamp}.csv";
    fc_write_csv($files['renew'], ['id', 'invoice_no', 'user_id', 'order_id', 'service_id', 'status', 'paid_amount', 'paid_at', 'created_at'], $m['renew_no_service']);

    $files['order_invoice'] = $dir."/manual_order_invoice_{$stamp}.csv";
    fc_write_csv($files['order_invoice'], ['order_id', 'order_no', 'user_id', 'o_status', 'o_amount', 'invoice_id', 'i_status', 'i_amount'], $m['order_invoice_mismatch']);

    $files['orphan'] = $dir."/manual_orphan_order_{$stamp}.csv";
    fc_write_csv($files['orphan'], ['order_id', 'order_no', 'user_id', 'status', 'created_at'], $m['orphan_order']);

    return $files;
}

// ============================================================ 打印

function fc_print_summary(array $summary, array $files): void
{
    $mode = $summary['mode'];
    echo "==================== 财务存量订正 ({$mode}) ====================".PHP_EOL;
    echo "批次     : {$summary['batch']}".PHP_EOL;
    echo "数据库   : {$summary['db']}".PHP_EOL;
    echo "链口径   : {$summary['chain_mode']}".PHP_EOL;
    echo '用户范围 : '.($summary['scope_users'] ? implode(',', $summary['scope_users']) : '全库').PHP_EOL;
    echo '账号类型分布: ';
    $parts = [];
    foreach ($summary['account_type_dist'] as $d) {
        $parts[] = "{$d['account_type']}={$d['c']}";
    }
    echo implode('  ', $parts).PHP_EOL;

    echo '--- 现金台账不平（订正前）: '.count($summary['imbalance']).' 用户'.PHP_EOL;
    echo '--- 断链（局部口径）: '.count($summary['chain_breaks']).' 行'.PHP_EOL;
    echo "--- 计划变更：account_type={$summary['planned']['account_type']}"
        ."  chain={$summary['planned']['chain']}"
        ."  calibration={$summary['planned']['calibration']}".PHP_EOL;
    if ($summary['mode'] === 'execute') {
        echo "--- 已落库：account_type={$summary['applied']['account_type']}"
            ."  chain={$summary['applied']['chain']}"
            ."  calibration={$summary['applied']['calibration']}".PHP_EOL;
    }
    echo '--- 仅清单（不改数）: '.json_encode($summary['manual'], JSON_UNESCAPED_UNICODE).PHP_EOL;
    echo '--- 差异 CSV: '.count($summary['diffs'])." 行 -> {$files['diff']}".PHP_EOL;
}

// ============================================================ CLI

function fc_cli_main(array $argv): void
{
    $opts = fc_parse_args($argv);
    try {
        $pdo = fc_connect($opts);
    } catch (Throwable $e) {
        fwrite(STDERR, '数据库连接失败：'.$e->getMessage().PHP_EOL);
        exit(1);
    }

    if (! empty($opts['users'])) {
        echo '提示：已限定用户范围 '.implode(',', $opts['users']).'（演练用途）'.PHP_EOL;
    }

    try {
        $pdo->beginTransaction();
        $summary = fc_execute($pdo, $opts);
        if ($opts['execute']) {
            $pdo->commit();
        } else {
            $pdo->rollBack(); // dry-run：绝不落库
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        fwrite(STDERR, '执行失败，已整体回滚：'.$e->getMessage().PHP_EOL);
        exit(1);
    }

    $files = fc_write_report($pdo, $opts, $summary);
    fc_print_summary($summary, $files);
    if ($opts['execute']) {
        $v = fc_verify($pdo, $opts['users'], $opts['chain_mode']);
        echo '--- 校验：不平用户='.count($v['unbalanced'])
            .'  断链='.count($v['chain_breaks'])
            .'  校准分录合计='.fc_money($v['calibration_sum']).PHP_EOL;
    }
}
