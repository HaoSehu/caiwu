<?php

declare(strict_types=1);

$sourcePath = __DIR__.'/idc-current-table-structure.md';
$currentDocPath = dirname(__DIR__).'/文档/开发文档/数据库/当前数据库结构.md';
$overviewDocPath = dirname(__DIR__).'/文档/开发文档/数据库/数据库文档.md';
$designDocPath = dirname(__DIR__).'/文档/开发文档/数据库/数据库设计整改方案.md';

$source = file_get_contents($sourcePath);
if ($source === false) {
    fwrite(STDERR, "cannot read source\n");
    exit(1);
}

/**
 * 按 Markdown 表格列拆分，保留注释中的 `\|` 字面竖线。
 *
 * @return list<string>
 */
function splitMarkdownTableRow(string $line): array
{
    $line = trim($line);
    if ($line === '' || ! str_starts_with($line, '|')) {
        return [];
    }

    // 保护转义竖线，避免被 explode 切断注释
    $sentinel = "\x01";
    $protected = str_replace('\\|', $sentinel, $line);
    $parts = explode('|', $protected);
    // 去掉首尾因行首/行尾 | 产生的空单元
    if ($parts !== [] && trim((string) $parts[0]) === '') {
        array_shift($parts);
    }
    if ($parts !== [] && trim((string) $parts[count($parts) - 1]) === '') {
        array_pop($parts);
    }

    return array_map(
        static fn (string $part): string => str_replace("\x01", '|', trim($part)),
        $parts
    );
}

$sections = preg_split('/^## `/m', $source);
array_shift($sections);

$parsed = [];
$fieldCount = 0;
$indexCount = 0;
$fkCount = 0;
$jsonFields = [];

foreach ($sections as $section) {
    if (! preg_match('/^([^`]+)`\s*\n(.*)$/s', $section, $sm)) {
        continue;
    }

    $name = $sm[1];
    $body = $sm[2];

    $engine = null;
    if (preg_match('/引擎：`([^`]+)`/', $body, $em)) {
        $engine = $em[1];
    }

    $fields = [];
    if (preg_match('/### 字段\s*\n\s*\n((?:\|.+\n)+)/u', $body, $fm)) {
        foreach (preg_split("/\r?\n/", trim($fm[1])) as $line) {
            if (! str_starts_with($line, '| `')) {
                continue;
            }
            $cols = splitMarkdownTableRow($line);
            if (count($cols) < 5) {
                continue;
            }
            $fieldName = trim($cols[0], '`');
            $type = trim($cols[1], '`');
            // 若注释里仍含未转义竖线，合并剩余列
            $comment = $cols[4];
            if (count($cols) > 5) {
                $comment = implode('|', array_slice($cols, 4));
            }
            $fields[] = [
                'name' => $fieldName,
                'type' => $type,
                'nullable' => $cols[2],
                'default' => $cols[3],
                'comment' => $comment,
            ];
            $fieldCount++;
            if (str_contains(strtolower($type), 'json')) {
                $jsonFields[] = $name.'.'.$fieldName;
            }
        }
    }

    $indexes = [];
    if (preg_match('/### 索引\s*\n\s*\n((?:\|.+\n)+)/u', $body, $im)) {
        foreach (preg_split("/\r?\n/", trim($im[1])) as $line) {
            if (! str_starts_with($line, '|')) {
                continue;
            }
            $cols = splitMarkdownTableRow($line);
            if (count($cols) < 3 || $cols[0] === '名称' || str_starts_with($cols[0], '---')) {
                continue;
            }
            $indexes[] = [
                'name' => trim($cols[0], '`'),
                'type' => $cols[1],
                'columns' => trim($cols[2], '`'),
            ];
            $indexCount++;
        }
    }

    $fks = [];
    if (preg_match('/### 外键\s*\n\s*\n((?:\|.+\n)+)/u', $body, $km)) {
        foreach (preg_split("/\r?\n/", trim($km[1])) as $line) {
            if (! str_starts_with($line, '| `')) {
                continue;
            }
            $cols = splitMarkdownTableRow($line);
            if (count($cols) < 5) {
                continue;
            }
            $fks[] = [
                'name' => trim($cols[0], '`'),
                'column' => trim($cols[1], '`'),
                'ref' => $cols[2],
                'on_delete' => $cols[3],
                'on_update' => $cols[4],
            ];
            $fkCount++;
        }
    }

    $parsed[$name] = [
        'engine' => $engine,
        'fields' => $fields,
        'indexes' => $indexes,
        'foreign_keys' => $fks,
    ];
}

if ($parsed === []) {
    fwrite(STDERR, "no tables parsed from source\n");
    exit(1);
}

$now = (new DateTimeImmutable('now', new DateTimeZone('Asia/Shanghai')))->format('Y-m-d H:i:s P');
$tableCount = count($parsed);

$lines = [];
$lines[] = '# 当前数据库结构说明';
$lines[] = '';
$lines[] = '- 文档性质：参考资料 / 实库结构快照';
$lines[] = '- 生成时间：`'.$now.'`';
$lines[] = '- 数据来源：`migration-output/idc-current-table-structure.md`（实库 `idc` 元数据 DDL 导出）';
$lines[] = '- 数据库：`idc`';
$lines[] = '- 当前表数量：`'.$tableCount.'`';
$lines[] = '- 字段数量：`'.$fieldCount.'`';
$lines[] = '- 索引数量：`'.$indexCount.'`';
$lines[] = '- 外键约束数量：`'.$fkCount.'`';
$lines[] = '- 说明：';
$lines[] = '  - 本文由 `migration-output/idc-current-table-structure.md` 同步生成，不包含任何业务行数据。';
$lines[] = '  - 字段、索引、外键均来自该 DDL 快照，不以历史迁移文件推断。';
$lines[] = '  - 如需从实库重刷，可执行：`php backend/scripts/export_database_structure.php`；若要以 migration-output 快照为准，执行：`php migration-output/_sync_structure_docs.php`。';
$lines[] = '';
$lines[] = '> **当前基线**：以 `migration-output/idc-current-table-structure.md` 为准同步到本文件。';
$lines[] = '';
$lines[] = '## 1. 结构概览';
$lines[] = '';
$lines[] = '### 1.1 表清单';
$lines[] = '';
$lines[] = '| 表名 | 引擎 | 字段数 | 索引数 | 外键数 |';
$lines[] = '| --- | --- | ---: | ---: | ---: |';

foreach ($parsed as $name => $table) {
    $lines[] = '| `'.$name.'` | `'.($table['engine'] ?? 'InnoDB').'` | '.count($table['fields']).' | '.count($table['indexes']).' | '.count($table['foreign_keys']).' |';
}

$lines[] = '';
$lines[] = '### 1.2 JSON 字段';
$lines[] = '';
if ($jsonFields === []) {
    $lines[] = '- （无）';
} else {
    foreach ($jsonFields as $field) {
        $lines[] = '- `'.$field.'`';
    }
}

$lines[] = '';
$lines[] = '## 2. 表结构明细';
$lines[] = '';

$index = 1;
foreach ($parsed as $name => $table) {
    $lines[] = '### 2.'.$index.' `'.$name.'`';
    $lines[] = '';
    $lines[] = '- 引擎：`'.($table['engine'] ?? 'InnoDB').'`';
    $lines[] = '';
    $lines[] = '#### 字段';
    $lines[] = '';
    $lines[] = '| 字段 | 类型 | 可空 | 默认值 | 说明 |';
    $lines[] = '| --- | --- | --- | --- | --- |';
    foreach ($table['fields'] as $field) {
        $comment = str_replace('|', '\\|', (string) $field['comment']);
        $lines[] = '| `'.$field['name'].'` | `'.$field['type'].'` | '.$field['nullable'].' | '.$field['default'].' | '.$comment.' |';
    }
    $lines[] = '';
    $lines[] = '#### 索引';
    $lines[] = '';
    if ($table['indexes'] === []) {
        $lines[] = '无索引。';
        $lines[] = '';
    } else {
        $lines[] = '| 名称 | 类型 | 字段 |';
        $lines[] = '| --- | --- | --- |';
        foreach ($table['indexes'] as $idx) {
            $lines[] = '| `'.$idx['name'].'` | '.$idx['type'].' | `'.$idx['columns'].'` |';
        }
        $lines[] = '';
    }
    $lines[] = '#### 外键';
    $lines[] = '';
    if ($table['foreign_keys'] === []) {
        $lines[] = '无数据库级外键约束。';
        $lines[] = '';
    } else {
        $lines[] = '| 名称 | 字段 | 引用 | 删除规则 | 更新规则 |';
        $lines[] = '| --- | --- | --- | --- | --- |';
        foreach ($table['foreign_keys'] as $fk) {
            $lines[] = '| `'.$fk['name'].'` | `'.$fk['column'].'` | '.$fk['ref'].' | '.$fk['on_delete'].' | '.$fk['on_update'].' |';
        }
        $lines[] = '';
    }
    $index++;
}

file_put_contents($currentDocPath, implode("\n", $lines)."\n");

$has = static fn (string $t): bool => isset($parsed[$t]);
$domains = [
    '身份与权限' => array_values(array_filter([
        'users', 'user_accounts', 'admin_users', 'admin_user_roles', 'roles', 'member_levels',
        'personal_access_tokens', 'password_reset_tokens', 'sessions', 'verification_histories',
    ], $has)),
    '商品与供应商' => array_values(array_filter([
        'products', 'first_product_groups', 'second_product_groups', 'third_product_groups',
        'suppliers', 'supplier_plugin_bindings', 'product_upstream_bindings',
    ], $has)),
    '交易与账单' => array_values(array_filter([
        'orders', 'invoices', 'invoice_items', 'payments', 'payment_callbacks', 'gateway_logs',
    ], $has)),
    '账户与流水' => array_values(array_filter([
        'account_transactions', 'referral_account_logs',
    ], $has)),
    '服务实例' => array_values(array_filter([
        'services', 'service_upstream_bindings', 'service_runtime_snapshots',
        'service_connection_snapshots', 'service_provision_attempts',
    ], $has)),
    '优惠券' => array_values(array_filter([
        'coupon_campaigns', 'coupons', 'user_coupons',
    ], $has)),
    '返佣' => array_values(array_filter([
        'referral_rewards', 'referral_withdrawals',
    ], $has)),
    '工单' => array_values(array_filter([
        'tickets', 'ticket_replies',
    ], $has)),
    '内容与媒体' => array_values(array_filter([
        'content_articles', 'content_categories', 'media_files', 'notice_reads',
    ], $has)),
    '通知与日志' => array_values(array_filter([
        'message_logs', 'notification_templates', 'user_notifications', 'operation_logs',
        'activity_logs', 'automation_logs', 'schedule_run_logs', 'schedule_ticks',
        'schedule_task_runs', 'archive_audit_logs', 'failed_jobs', 'jobs',
    ], $has)),
    '插件系统' => array_values(array_filter([
        'integration_plugins', 'integration_plugin_configs', 'integration_plugin_bindings',
        'integration_plugin_runtime_logs',
    ], $has)),
    '代理/插件扩展' => array_values(array_filter([
        'agent_applications',
    ], $has)),
    '系统配置' => array_values(array_filter([
        'settings', 'migrations',
    ], $has)),
];

$domainLines = [];
foreach ($domains as $domain => $tableNames) {
    if ($tableNames === []) {
        continue;
    }
    $domainLines[] = '| '.$domain.' | `'.implode('`、`', $tableNames).'` |';
}

$overview = [];
$overview[] = '# 数据库文档';
$overview[] = '';
$overview[] = '- 文档性质：当前数据库业务说明 / 结构索引';
$overview[] = '- 对齐时间：`'.$now.'`';
$overview[] = '- 数据来源：`migration-output/idc-current-table-structure.md`（实库 `idc` DDL 快照）+ Laravel 当前代码';
$overview[] = '- 完整字段与索引明细：见 `文档/开发文档/数据库/当前数据库结构.md`';
$overview[] = '';
$overview[] = '## 1. 概览';
$overview[] = '';
$overview[] = '| 项 | 值 |';
$overview[] = '| --- | --- |';
$overview[] = '| 数据库 | `idc` |';
$overview[] = '| 结构对象数量 | `'.$tableCount.'`（本快照全部按 BASE TABLE 记录） |';
$overview[] = '| 字段数量 | `'.$fieldCount.'` |';
$overview[] = '| 索引数量 | `'.$indexCount.'` |';
$overview[] = '| 数据库级外键 | `'.$fkCount.'` |';
$overview[] = '| 默认存储引擎 | `InnoDB` |';
$overview[] = '| 业务表主要排序规则 | `utf8mb4_unicode_ci` |';
$overview[] = '';
$overview[] = '## 2. 业务域分组';
$overview[] = '';
$overview[] = '| 业务域 | 表 |';
$overview[] = '| --- | --- |';
$overview = array_merge($overview, $domainLines);
$overview[] = '';
$overview[] = '## 3. 核心关系';
$overview[] = '';
$overview[] = '```mermaid';
$overview[] = 'erDiagram';
$overview[] = '    users ||--o| user_accounts : owns';
$overview[] = '    users ||--o{ invoices : creates';
$overview[] = '    users ||--o{ orders : creates';
$overview[] = '    users ||--o{ payments : pays';
$overview[] = '    users ||--o{ services : owns';
$overview[] = '    products ||--o{ invoices : billed';
$overview[] = '    products ||--o{ orders : ordered';
$overview[] = '    products ||--o{ services : provisioned';
$overview[] = '    first_product_groups ||--o{ second_product_groups : contains';
$overview[] = '    second_product_groups ||--o{ third_product_groups : contains';
$overview[] = '    third_product_groups ||--o{ products : classifies';
$overview[] = '    suppliers ||--o{ supplier_plugin_bindings : binds';
$overview[] = '    supplier_plugin_bindings ||--o{ product_upstream_bindings : maps';
$overview[] = '    product_upstream_bindings ||--o{ service_upstream_bindings : provisions';
$overview[] = '    services ||--o{ service_runtime_snapshots : snapshots';
$overview[] = '    services ||--o{ service_connection_snapshots : connections';
$overview[] = '    services ||--o{ service_provision_attempts : attempts';
$overview[] = '    integration_plugins ||--o{ integration_plugin_configs : configures';
$overview[] = '    integration_plugins ||--o{ integration_plugin_bindings : binds';
$overview[] = '    integration_plugins ||--o{ integration_plugin_runtime_logs : runs';
$overview[] = '    integration_plugins ||--o{ message_logs : sends';
$overview[] = '    invoices ||--o{ invoice_items : contains';
$overview[] = '    invoices ||--o{ payments : paid_by';
$overview[] = '    payments ||--o{ payment_callbacks : has';
$overview[] = '    orders ||--o| invoices : projected_to';
$overview[] = '    orders ||--o| services : provisions';
$overview[] = '    services }o--|| invoices : source_invoice';
$overview[] = '    tickets ||--o{ ticket_replies : contains';
$overview[] = '    coupons ||--o{ user_coupons : assigned';
$overview[] = '```';
$overview[] = '';
$overview[] = '说明：';
$overview[] = '';
$overview[] = '- 图中只表达核心业务关系。真实库当前有 `'.$fkCount.'` 个数据库级外键，更多关系由 Eloquent 关系、业务代码和索引约定维护。';
$overview[] = '- 本轮文档基线来自 `migration-output/idc-current-table-structure.md`：商品分组仍为 `first_product_groups` / `second_product_groups` / `third_product_groups` 三张物理表，**未包含** `product_groups` 自引用表。';
$overview[] = '- `orders` 正在退为内部 shadow Order，用户侧新购链路已由 `CheckoutService` 以 Invoice-first 创建账单，再创建内部 Order 供开通/退款链路兼容。';
$overview[] = '- `payments` 只表达第三方真实资金流入和退款状态，不记录余额/免费/手工开服。';
$overview[] = '- 上游供应商、商品、服务运行态由绑定/快照表承载：`supplier_plugin_bindings`、`product_upstream_bindings`、`service_upstream_bindings`、`service_runtime_snapshots`、`service_connection_snapshots`。';
$overview[] = '- `agent_applications` 属于 ZJMF Bridge 插件扩展表，随插件迁移存在。';
$overview[] = '';
$overview[] = '## 4. 结构特征';
$overview[] = '';
$overview[] = '- 所有业务表均为 `InnoDB`。';
$overview[] = '- JSON 字段集中在商品配置、订单/账单快照、支付回调、通知模板、插件配置、上游绑定快照、服务运行/连接快照、调度任务运行摘要和日志上下文。';
$overview[] = '- 金额字段大多为 `decimal(12,2)`。';
$overview[] = '- `user_accounts` 为余额唯一真源；`account_transactions` 为账户流水真源。';
$overview[] = '- `referral_account_logs` 仍在本快照中存在，但业务写入路径已主要收敛到 `account_transactions`（推荐流水查询读 AccountTransaction）。';
$overview[] = '- `operation_logs` 与 `activity_logs` 仍处于过渡期双写：`OperationLogService` 同时写入两表。';
$overview[] = '- `message_logs` 统一承接邮件/短信发送日志，通过 `channel` 区分渠道。';
$overview[] = '- `services.provision_data` 与服务上游绑定/快照表并存，运行时由投影层合并读取。';
$overview[] = '- 完整字段、索引、外键明细不在本文重复维护，统一以：';
$overview[] = '  - `文档/开发文档/数据库/当前数据库结构.md`';
$overview[] = '  - 源快照 `migration-output/idc-current-table-structure.md`';
$overview[] = '';
$overview[] = '## 5. 刷新方式';
$overview[] = '';
$overview[] = '```bash';
$overview[] = '# 方式 A：从 migration-output 快照同步到项目文档';
$overview[] = 'php migration-output/_sync_structure_docs.php';
$overview[] = '';
$overview[] = '# 方式 B：直接从实库 information_schema 重刷结构明细';
$overview[] = 'php backend/scripts/export_database_structure.php';
$overview[] = '```';
$overview[] = '';
$overview[] = '> 注意：方式 B 会按实库实时状态覆盖 `当前数据库结构.md` 的格式与统计；若要以 migration-output 快照为准，使用方式 A。';
$overview[] = '';

file_put_contents($overviewDocPath, implode("\n", $overview));

if (is_file($designDocPath)) {
    $design = file_get_contents($designDocPath);
    if ($design !== false) {
        $design = preg_replace(
            '/- 当前基线：MySQL `idc`，.*?结构快照见 `文档\/开发文档\/数据库\/当前数据库结构\.md`/u',
            '- 当前基线：MySQL `idc`，'.$tableCount.' 张表，'.$fkCount.' 个数据库级外键（对齐 `migration-output/idc-current-table-structure.md`，同步时间 '.$now.'），结构快照见 `文档/开发文档/数据库/当前数据库结构.md`',
            $design,
            1
        );

        $oldStage4 = <<<'TXT'
阶段 4 已将商品分组结构真源收敛到 `product_groups`。当前 `product_groups.parent_id` 自引用表达层级，`products.product_group_id` 作为商品唯一结构归属并通过数据库外键指向 `product_groups.id`。

阶段结果：
- 旧 `first_product_groups`、`second_product_groups`、`third_product_groups` 物理表已删除，当前同名对象仅为兼容视图。
- `products.first_product_group_id`、`products.second_product_group_id`、`products.third_product_group_id` 物理列已删除。
- 当前前端和商品管理仍消费一、二、三级投影字段，这些字段由后端模型/Resource 和兼容视图从 `product_groups` 递归关系计算，不再作为结构真源。
TXT;
        $newStage4 = <<<'TXT'
阶段 4 原计划将商品分组结构真源收敛到 `product_groups`。但当前文档基线（`migration-output/idc-current-table-structure.md`）显示：

- 商品分组仍为 `first_product_groups` / `second_product_groups` / `third_product_groups` 三张物理表。
- 本快照**未包含** `product_groups` 自引用表。
- 以本快照为准更新项目文档后，后续若实库完成自引用重构，需重新导出并再同步文档。
TXT;
        if (str_contains($design, '阶段 4 已将商品分组结构真源收敛到 `product_groups`')) {
            $design = str_replace($oldStage4, $newStage4, $design);
        }

        file_put_contents($designDocPath, $design);
    }
}

echo json_encode([
    'source' => $sourcePath,
    'tables' => $tableCount,
    'fields' => $fieldCount,
    'indexes' => $indexCount,
    'foreign_keys' => $fkCount,
    'has_product_groups' => isset($parsed['product_groups']),
    'has_first_product_groups' => isset($parsed['first_product_groups']),
    'has_agent_applications' => isset($parsed['agent_applications']),
    'updated' => [
        $currentDocPath,
        $overviewDocPath,
        $designDocPath,
    ],
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
