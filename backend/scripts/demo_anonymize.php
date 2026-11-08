<?php

declare(strict_types=1);

/**
 * 演练版数据脱敏：从本地 idc 库复制出 idc_demo 副本库，在副本上做脱敏。
 *
 * 原则：
 * - 只读 idc，只写 idc_demo；最后断言当前连接库名，防止误伤源库。
 * - 身份字段整体替换（不保留任何原值）；自由文本整体虚构化；
 *   密钥/凭据类整体清空；金额按固定系数缩放；时间列按整体平移，
 *   保证演练数据不会随时间全部过期。
 *
 * 用法：cd backend && php scripts/demo_anonymize.php
 */
$envPath = __DIR__.'/../.env';
$env = [];
foreach (file($envPath) ?: [] as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#' || ! str_contains($line, '=')) {
        continue;
    }
    [$key, $value] = explode('=', $line, 2);
    $env[trim($key)] = trim($value, "\"'");
}

$sourceDb = $env['DB_DATABASE'] ?? 'idc';
// 副本库必须是当前 MySQL 账号有权限的已存在库：idc 账号仅有 idc / idc_test 的授权。
// idc_test 是 phpunit 的空测试库，演练导出后不依赖它长期存在（JSON 已提交到官网仓库）。
$targetDb = $env['DEMO_DB_DATABASE'] ?? 'idc_test';

if ($sourceDb === $targetDb) {
    fwrite(STDERR, "源库与目标库同名（{$sourceDb}），拒绝执行。\n");
    exit(1);
}

$dsn = sprintf('mysql:host=%s;port=%s', $env['DB_HOST'] ?? '127.0.0.1', $env['DB_PORT'] ?? '3306');
$pdo = new PDO($dsn, $env['DB_USERNAME'] ?? '', $env['DB_PASSWORD'] ?? '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

function quoteIdent(string $name): string
{
    return '`'.str_replace('`', '``', $name).'`';
}

/** 扫描当前库某表的列名（含生成列标记）。 */
function columnNames(PDO $pdo, string $schema, string $table): array
{
    $stmt = $pdo->prepare(
        'SELECT COLUMN_NAME, DATA_TYPE, EXTRA, IS_NULLABLE FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION'
    );
    $stmt->execute([$schema, $table]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** 可显式插入的列（排除生成列与自增列外的生成表达式）。 */
function insertableColumns(PDO $pdo, string $schema, string $table): array
{
    return array_values(array_map(
        static fn (array $column): string => $column['COLUMN_NAME'],
        array_filter(
            columnNames($pdo, $schema, $table),
            static fn (array $column): bool => ! str_contains(strtoupper((string) ($column['EXTRA'] ?? '')), 'GENERATED')
        )
    ));
}

/** 执行一条 SQL，失败时打印并退出（保留库名便于排查）。 */
function run(PDO $pdo, string $sql, string $db): void
{
    $pdo->exec("USE `{$db}`");
    $pdo->exec($sql);
}

echo "== 1/4 复制 {$sourceDb} -> {$targetDb}\n";
$pdo->exec("USE `{$targetDb}`");
// 目标库必须已存在（当前账号可能没有 CREATE DATABASE 权限）。
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
$existing = $pdo->query("SHOW TABLES FROM `{$targetDb}`")->fetchAll(PDO::FETCH_COLUMN);
foreach ($existing as $table) {
    $pdo->exec('DROP TABLE IF EXISTS `'.$targetDb.'`.'."`{$table}`");
}

$tables = $pdo->query("SHOW TABLES FROM `{$sourceDb}`")->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $table) {
    $pdo->exec("CREATE TABLE `{$targetDb}`.".quoteIdent($table)." LIKE `{$sourceDb}`.".quoteIdent($table));
    // 生成列不能出现在 INSERT 列表里，按非生成列显式复制
    $columns = insertableColumns($pdo, $sourceDb, $table);
    $columnList = implode(', ', array_map('quoteIdent', $columns));
    $pdo->exec(
        "INSERT INTO `{$targetDb}`.".quoteIdent($table)." ({$columnList})".
        " SELECT {$columnList} FROM `{$sourceDb}`.".quoteIdent($table)
    );
}
echo '   copied '.count($tables)." tables\n";
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

echo "== 2/4 身份字段与密钥脱敏\n";

$fakeHash = password_hash('demo-not-a-real-password', PASSWORD_BCRYPT);

// 用户与管理员：身份字段整体替换
// 注意唯一索引：phone/email 等可空唯一列统一置 NULL（MySQL 唯一索引允许多个 NULL），
// NOT NULL 的 qq/id_card 给逐行唯一的假值。
run($pdo, "UPDATE users SET
    email = CONCAT('demo-', id, '@example.test'),
    phone = NULL,
    qq = CONCAT('demo-qq-', id),
    nickname = CONCAT('演示用户', id),
    company = CONCAT('演示公司 ', id),
    real_name = CONCAT('演示用户', id),
    alipay_real_name = CONCAT('演示用户', id),
    alipay_account = NULL,
    id_card = CONCAT('DEMO-ID-', LPAD(id, 10, '0')),
    verification_certify_id = '',
    verification_message = '演练版示例',
    last_login_ip = '127.0.0.1',
    admin_note = NULL,
    password = ".$pdo->quote($fakeHash).'
', $targetDb);

// 自由文本：整体虚构化，不保留任何原文。
// 表结构随迁移演变，列不在时静默跳过（只处理存在的自由文本列），避免脚本因结构差异中断。
function scrubFreeText(PDO $pdo, string $schema, string $table, array $columns): void
{
    $columnMeta = columnNames($pdo, $schema, $table);
    $existing = array_column($columnMeta, 'COLUMN_NAME');
    $nullable = [];
    $numeric = [];
    foreach ($columnMeta as $meta) {
        $name = $meta['COLUMN_NAME'];
        $nullable[$name] = strtoupper((string) ($meta['IS_NULLABLE'] ?? '')) === 'YES';
        $numeric[$name] = (bool) preg_match(
            '/^(tinyint|smallint|mediumint|int|bigint|decimal|numeric|float|double|bit)/i',
            (string) $meta['DATA_TYPE']
        );
    }

    $sets = [];
    foreach ($columns as $column => $expression) {
        if (! in_array($column, $existing, true)) {
            continue;
        }
        // NOT NULL 列不接受 NULL：文本列清空落空串，数值列落 0（空串会被 MySQL 拒绝）
        if ($expression === 'NULL' && ! ($nullable[$column] ?? true)) {
            $expression = ($numeric[$column] ?? false) ? '0' : "''";
        }
        $sets[] = quoteIdent($column).' = '.$expression;
    }
    if (in_array('id', $existing, true) && $sets !== []) {
        run($pdo, 'UPDATE '.quoteIdent($table).' SET '.implode(', ', $sets), $schema);
    }
}

// 工单与消息
scrubFreeText($pdo, $targetDb, 'tickets', [
    'subject' => "CONCAT('演示工单 #', id)",
    'close_reason' => 'NULL',
]);
scrubFreeText($pdo, $targetDb, 'ticket_replies', [
    'content' => $pdo->quote('这是演练版中的示例回复，用于演示界面效果。'),
]);
scrubFreeText($pdo, $targetDb, 'message_logs', [
    'recipient' => "CONCAT('demo-', id, '@example.test')",
    'subject' => "CONCAT('演示消息 #', id)",
    'content' => $pdo->quote('这是演练版中的示例消息内容。'),
    'params_json' => 'NULL',
    'error_msg' => 'NULL',
]);
scrubFreeText($pdo, $targetDb, 'user_notifications', [
    'title' => "CONCAT('演示通知 #', id)",
    'content' => $pdo->quote('这是演练版中的示例通知。'),
    'data' => 'NULL',
]);
scrubFreeText($pdo, $targetDb, 'service_tracking_records', [
    'content' => $pdo->quote('演练版示例记录'),
    'images' => 'NULL',
]);
scrubFreeText($pdo, $targetDb, 'sms_provider_templates', [
    'content' => $pdo->quote('演练版示例短信模板'),
    'remark' => 'NULL',
    'raw_json' => 'NULL',
]);
scrubFreeText($pdo, $targetDb, 'notification_templates', [
    'subject' => "CONCAT('演示通知 ', id)",
    'content' => $pdo->quote('演练版示例通知模板'),
    'description' => 'NULL',
]);
scrubFreeText($pdo, $targetDb, 'activity_logs', [
    'description' => "CONCAT('演练版示例活动 #', id)",
    'actor_name' => "CONCAT('演示操作员', id)",
    'ip_address' => "'127.0.0.1'",
    'context' => 'NULL',
]);
scrubFreeText($pdo, $targetDb, 'schedule_run_logs', ['summary' => 'NULL', 'error_msg' => 'NULL']);
scrubFreeText($pdo, $targetDb, 'schedule_task_runs', [
    'summary' => 'NULL',
    'error_msg' => 'NULL',
    'rule_description' => 'NULL',
]);

// 内容文章：保留标题（官网演示需要真实文案感），正文换成示例段落
scrubFreeText($pdo, $targetDb, 'content_articles', [
    'summary' => 'NULL',
    'excerpt' => $pdo->quote('演练版示例摘要'),
    'content' => "CONCAT('<p>这是演练版中的示例内容 #', id, '。真实内容请访问生产站点。</p>')",
    'remark' => 'NULL',
    'operator' => 'NULL',
]);
scrubFreeText($pdo, $targetDb, 'content_categories', ['description' => "CONCAT('演练版示例分类 ', id)"]);

// 商品与营销
foreach (['first_product_groups', 'second_product_groups', 'third_product_groups', 'marketing_product_groups'] as $table) {
    scrubFreeText($pdo, $targetDb, $table, ['description' => 'NULL']);
}
scrubFreeText($pdo, $targetDb, 'coupon_campaigns', ['description' => 'NULL', 'remark' => 'NULL']);
scrubFreeText($pdo, $targetDb, 'coupons', ['description' => "CONCAT('演练版示例优惠券 ', id)", 'remark' => 'NULL']);
scrubFreeText($pdo, $targetDb, 'member_levels', ['remark' => 'NULL']);
scrubFreeText($pdo, $targetDb, 'promotion_ambassadors', ['remark' => 'NULL']);
scrubFreeText($pdo, $targetDb, 'user_coupons', ['remark' => 'NULL']);
scrubFreeText($pdo, $targetDb, 'products', ['remark' => "CONCAT('演练版示例商品 ', id)"]);

// 订单/账单/支付及财务
foreach (['orders', 'invoices', 'payments', 'recharge_records', 'refunds', 'finance_ledger_entries', 'referral_account_logs', 'referral_rewards', 'referral_withdrawals'] as $table) {
    scrubFreeText($pdo, $targetDb, $table, ['remark' => 'NULL']);
}
scrubFreeText($pdo, $targetDb, 'services', [
    'suspended_reason' => 'NULL',
    'remark' => 'NULL',
    'provision_data' => 'NULL',
    'domain' => "CONCAT('demo-', id, '.example.test')",
]);
scrubFreeText($pdo, $targetDb, 'payment_callbacks', [
    'payload_json' => "'{}'",
    'remark' => 'NULL',
    'gateway_trade_no' => "CONCAT('DEMO-TRADE-', id)",
]);

// 插件与上游连接：密钥、主机、IP 全部清空
scrubFreeText($pdo, $targetDb, 'integration_plugin_configs', [
    'secret_json' => 'NULL',
    'has_secret_json' => 'NULL',
    'config_json' => "'{}'",
]);
scrubFreeText($pdo, $targetDb, 'service_connection_snapshots', [
    'secret_json' => 'NULL',
    'has_secret_json' => 'NULL',
    'connection_json' => "'{}'",
    'hostname' => "CONCAT('192.0.2.', (id % 250) + 1)",
    'ip_address' => "CONCAT('192.0.2.', (id % 250) + 1)",
]);
scrubFreeText($pdo, $targetDb, 'service_upstream_bindings', [
    'connection_snapshot_json' => 'NULL',
    'runtime_snapshot_json' => 'NULL',
    'upstream_service_id' => "CONCAT('demo-upstream-', id)",
    'upstream_account_id' => "CONCAT('demo-account-', id)",
    'last_sync_error' => 'NULL',
]);
scrubFreeText($pdo, $targetDb, 'product_upstream_bindings', [
    'upstream_product_id' => "CONCAT('demo-product-', id)",
    'upstream_product_snapshot_json' => 'NULL',
    'last_sync_error' => 'NULL',
]);
scrubFreeText($pdo, $targetDb, 'service_runtime_snapshots', ['runtime_json' => 'NULL']);
scrubFreeText($pdo, $targetDb, 'service_provision_attempts', ['response_json' => 'NULL']);
scrubFreeText($pdo, $targetDb, 'integration_plugin_runtime_logs', ['context_json' => 'NULL']);
scrubFreeText($pdo, $targetDb, 'gateway_logs', ['payload_json' => 'NULL']);
scrubFreeText($pdo, $targetDb, 'archive_audit_logs', ['detail_json' => 'NULL']);
scrubFreeText($pdo, $targetDb, 'automation_logs', ['context' => 'NULL']);
scrubFreeText($pdo, $targetDb, 'schedule_ticks', ['context' => 'NULL']);
scrubFreeText($pdo, $targetDb, 'suppliers', [
    'contact_name' => "CONCAT('演示联系人', id)",
    'contact_phone' => "'13900000000'",
    'contact_email' => "CONCAT('supplier-', id, '@example.test')",
    'notes' => $pdo->quote('演练版示例供应商'),
    'website' => 'NULL',
]);
scrubFreeText($pdo, $targetDb, 'media_files', ['uploaded_by' => '0']);

// 消息投递目标的键里可能嵌有邮箱（admin:email:xxx / email:xxx），整体重写为演示值
scrubFreeText($pdo, $targetDb, 'message_recipients', [
    'target_key' => "CONCAT('user:', account_id)",
    'group_name' => 'NULL',
]);

// 内容封面图等图片地址指向上游/生产域名，统一改站内相对路径（导出时再按实际存在的文件筛）
scrubFreeText($pdo, $targetDb, 'content_articles', [
    'cover_image' => "CASE WHEN cover_image IS NULL OR cover_image = '' THEN cover_image ELSE CONCAT('/demo/media/', SUBSTRING_INDEX(cover_image, '/', -1)) END",
]);

// 用户与管理员（唯一索引：可空唯一列置 NULL，NOT NULL 列逐行唯一）
scrubFreeText($pdo, $targetDb, 'admin_users', [
    'username' => "CONCAT('demo-admin-', id)",
    'nickname' => "CONCAT('演示管理员', id)",
    'email' => "CONCAT('admin-', id, '@example.test')",
    'password' => $pdo->quote($fakeHash),
    'mfa_secret' => 'NULL',
    'mfa_recovery_codes' => 'NULL',
    'last_login_ip' => "'127.0.0.1'",
]);

// settings：敏感键清空，媒体地址改站内相对路径
run($pdo, "UPDATE settings SET item_value = 'demo-secret'
    WHERE item_key REGEXP 'password|secret|private_key|access_key|captcha_key|_key$' AND item_value <> ''", $targetDb);
run($pdo, "UPDATE settings SET item_value = REPLACE(REPLACE(item_value, 'https://api.coyjs.cn/media/', '/demo/media/'), 'https://www.coyjs.cn/media/', '/demo/media/')
    WHERE item_key IN ('site_logo','site_favicon','support_group_qr','client_console_icon')", $targetDb);
run($pdo, "UPDATE settings SET item_value = REPLACE(REPLACE(item_value, 'https://api.coyjs.cn/media/', '/demo/media/'), 'https://www.coyjs.cn/media/', '/demo/media/')
    WHERE group_key IN ('home_hero','site_home')", $targetDb);

// 会话与令牌类直接清空
foreach (['personal_access_tokens', 'sessions', 'failed_jobs', 'jobs', 'password_reset_tokens', 'verification_challenges'] as $table) {
    run($pdo, 'TRUNCATE TABLE '.quoteIdent($table), $targetDb);
}

// 媒体表：ftp 绝对地址改相对路径
run($pdo, "UPDATE media_files SET path = REPLACE(path, 'http://154.219.97.172', ''), url = REPLACE(url, 'http://154.219.97.172', '')", $targetDb);
run($pdo, "UPDATE media_files SET url = CONCAT('/demo/media/', filename) WHERE url LIKE 'http%'", $targetDb);
run($pdo, "UPDATE media_files SET url = CONCAT('/demo/media/', filename) WHERE disk = 'ftp'", $targetDb);

echo "== 3/4 金额缩放与时间平移\n";

// 金额列按 0.37 缩放，避免真实营收泄露
$moneyTables = [
    'orders' => ['amount', 'discount', 'paid_amount'],
    'invoices' => ['amount', 'paid_amount', 'refund_amount', 'discount_amount'],
    'invoice_items' => ['amount', 'unit_price', 'total'],
    'payments' => ['amount'],
    'recharge_records' => ['amount'],
    'user_accounts' => ['cash_balance', 'credit_limit', 'frozen_balance', 'total_recharge', 'total_consumption', 'total_refund'],
    'finance_accounts' => ['balance', 'credit_limit'],
    'finance_consumptions' => ['amount', 'balance_before', 'balance_after'],
    'finance_funding_lots' => ['amount', 'remaining_amount'],
    'finance_ledger_entries' => ['amount'],
    'account_transactions' => ['amount', 'balance_after'],
    'referral_account_logs' => ['amount', 'balance_after', 'balance_before', 'reward_amount'],
    'referral_rewards' => ['amount', 'reward_amount'],
    'referral_withdrawals' => ['amount'],
    'refunds' => ['amount'],
    'finance_refunds' => ['amount'],
    'finance_refund_allocations' => ['amount'],
    'users' => ['total_sales_amount', 'balance', 'credit_limit'],
    'coupons' => ['discount_value', 'min_amount'],
    'coupon_campaigns' => ['discount_value', 'min_amount'],
    'products' => ['setup_fee'],
];

foreach ($moneyTables as $table => $columns) {
    $existing = array_column(columnNames($pdo, $targetDb, $table), 'COLUMN_NAME');
    $sets = [];
    foreach ($columns as $column) {
        if (in_array($column, $existing, true)) {
            $sets[] = quoteIdent($column).' = ROUND('.quoteIdent($column).' * 0.37, 2)';
        }
    }
    if ($sets !== []) {
        run($pdo, "UPDATE {$table} SET ".implode(', ', $sets), $targetDb);
    }
}

// 时间列整体平移：把最新一条记录平移到「今天」，
// 保证仪表盘「今日收入 / 新增用户」等指标不为空，服务也不会全部过期。
$newest = $pdo->query("SELECT MAX(created_at) FROM `{$targetDb}`.orders")->fetchColumn();
$deltaDays = 0;
if ($newest) {
    $newestTs = strtotime((string) $newest);
    $deltaDays = (int) floor((time() - $newestTs) / 86400);
}

if ($deltaDays !== 0) {
    echo "   时间平移 {$deltaDays} 天\n";
    $timeColumnNames = ['created_at', 'updated_at', 'deleted_at', 'expires_at', 'verified_at', 'paid_at', 'finished_at', 'due_at', 'publish_at', 'received_at', 'checked_at', 'last_login_at', 'last_used_at', 'scheduled_at'];

    foreach ($tables as $table) {
        $existing = array_column(columnNames($pdo, $targetDb, $table), 'COLUMN_NAME');
        $sets = [];
        foreach ($timeColumnNames as $column) {
            if (in_array($column, $existing, true)) {
                $sets[] = quoteIdent($column).' = DATE_ADD('.quoteIdent($column).', INTERVAL '.$deltaDays.' DAY)';
            }
        }
        if ($sets !== []) {
            run($pdo, "UPDATE {$table} SET ".implode(', ', $sets), $targetDb);
        }
    }
}

echo "== 4/4 抽查\n";

// 防呆：确认当前连接的是副本库
$current = $pdo->query('SELECT DATABASE()')->fetchColumn();
if ($current !== $targetDb) {
    fwrite(STDERR, "当前连接库为 {$current}，不是 {$targetDb}，中止。\n");
    exit(1);
}

$checks = [
    'users 残留真实邮箱' => "SELECT COUNT(*) FROM `{$targetDb}`.users WHERE email IS NOT NULL AND email NOT LIKE '%@example.test'",
    'users 残留手机号' => "SELECT COUNT(*) FROM `{$targetDb}`.users WHERE phone IS NOT NULL",
    '工单残留原文' => "SELECT COUNT(*) FROM `{$targetDb}`.tickets WHERE subject NOT LIKE '演示工单%'",
    '密钥残留' => "SELECT COUNT(*) FROM `{$targetDb}`.settings WHERE item_value NOT IN ('', 'demo-secret') AND item_key REGEXP 'password|secret|private_key|access_key|captcha_key'",
    '令牌残留' => "SELECT COUNT(*) FROM `{$targetDb}`.personal_access_tokens",
    '投递目标含邮箱' => "SELECT COUNT(*) FROM `{$targetDb}`.message_recipients WHERE target_key LIKE '%@%'",
    '封面图外链' => "SELECT COUNT(*) FROM `{$targetDb}`.content_articles WHERE cover_image LIKE 'http%'",
];

$failed = false;
foreach ($checks as $label => $sql) {
    $count = (int) $pdo->query($sql)->fetchColumn();
    $status = $count === 0 ? 'OK' : 'FAIL';
    if ($count !== 0) {
        $failed = true;
    }
    echo "   [{$status}] {$label}: {$count}\n";
}

echo $failed ? "脱敏抽查未通过，禁止导出。\n" : "脱敏完成，可以导出。\n";
exit($failed ? 1 : 0);
