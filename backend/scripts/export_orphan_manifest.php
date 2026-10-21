<?php

declare(strict_types=1);

/**
 * 生成迁移孤儿白名单 database/migrations-orphans.json。
 *
 * 用途：升级守卫（db:upgrade）需要区分「已知历史缺失」与「误删」。本脚本从真实
 * 结构来源计算差集，避免人工抄写产生偏差；生成物仍需人工 review 后才可提交。
 *
 * 用法（backend 目录）：
 *   php scripts/export_orphan_manifest.php
 *   php scripts/export_orphan_manifest.php --dry-run
 *
 * 数据来源优先级：实库 migrations 表 > schema baseline 中的 INSERT 记录。
 */

use Illuminate\Contracts\Console\Kernel;

define('LARAVEL_START', microtime(true));

$basePath = dirname(__DIR__);

require $basePath.'/vendor/autoload.php';

$app = require $basePath.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$options = getopt('', ['dry-run']);
$dryRun = array_key_exists('dry-run', $options);
$target = $basePath.'/database/migrations-orphans.json';

$recorded = DB::table('migrations')->orderBy('id')->pluck('migration')
    ->map(static fn (mixed $name): string => (string) $name)
    ->all();

$files = [];
foreach ([
    $basePath.'/database/migrations/*.php',
    $basePath.'/database/_archive/migrations/*.php',
    $basePath.'/database/_archive/migrations_devin/*.php',
] as $pattern) {
    foreach (glob($pattern) ?: [] as $file) {
        $files[] = basename($file, '.php');
    }
}

$orphans = array_values(array_diff($recorded, $files));
sort($orphans);

$payload = [
    'description' => '迁移账本中保留、但代码包中已无对应文件的已知历史迁移。'
        .'这些是 2026-04 至 2026-08 期间被直接删除且未归档的历史迁移文件，'
        .'在 idc / idc_test 的 migrations 表里仍保留执行记录。'
        .'只允许人工确认后追加，禁止脚本自动写入；新增条目会削弱升级守卫的拦截能力。',
    'generated_by' => 'backend/scripts/export_orphan_manifest.php',
    'recorded_at' => date('Y-m-d'),
    'orphans' => array_map(static fn (string $name): array => [
        'migration' => $name,
        'reason' => '历史迁移文件已删除且未归档，属于固定存量事实',
        'recorded_at' => date('Y-m-d'),
    ], $orphans),
];

$json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;

if (! $dryRun) {
    file_put_contents($target, $json);
}

fwrite(STDOUT, sprintf(
    '%s 迁移孤儿白名单: %s（记录 %d / 文件 %d / 孤儿 %d）%s',
    $dryRun ? 'Dry-run' : 'Generated',
    $target,
    count($recorded),
    count($files),
    count($orphans),
    PHP_EOL,
));
