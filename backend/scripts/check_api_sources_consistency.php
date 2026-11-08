<?php

declare(strict_types=1);

/**
 * 校验两处接口真源是否一致，供 CI 与本地使用。
 *
 * 真源：
 *   1. Laravel 路由表            —— 唯一事实来源
 *   2. docs/自动生成/接口/后端API清单.md —— 由 export_api_inventory.php 生成（构建产物，不入库）
 *
 * Apifox 侧契约由 MCP 维护，不在本脚本校验范围内。
 *
 * 退出码：0 一致；1 存在漂移（打印差异明细）。
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;

define('LARAVEL_START', microtime(true));

$basePath = dirname(__DIR__);

require $basePath.'/vendor/autoload.php';

$app = require $basePath.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

/**
 * 归一化接口标识：路径统一去尾斜杠，方法统一大写。
 *
 * @return array{0: string, 1: string}
 */
function normalizeOperation(string $path, string $method): array
{
    $path = '/'.ltrim(trim($path), '/');
    $path = $path === '/' ? $path : rtrim($path, '/');

    return [$path, strtoupper(trim($method))];
}

// ---------- 源 1：路由表 ----------
$routeOps = [];
foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
    if (! $route instanceof Route) {
        continue;
    }
    if (! str_starts_with($route->uri(), 'api/')) {
        continue;
    }
    foreach ($route->methods() as $method) {
        if ($method === 'HEAD') {
            continue;
        }
        $routeOps[implode(' ', normalizeOperation($route->uri(), $method))] = true;
    }
}

// ---------- 源 2：后端 API 清单（构建产物，可能不存在） ----------
$inventoryPath = dirname($basePath).'/docs/自动生成/接口/后端API清单.md';
$inventoryOps = [];
if (is_file($inventoryPath)) {
    foreach (explode("\n", (string) file_get_contents($inventoryPath)) as $line) {
        if (! str_starts_with($line, '| ') || ! str_contains($line, '`')) {
            continue;
        }
        $cells = array_map('trim', explode('|', trim($line, '| ')));
        if (count($cells) !== 6 || ! str_starts_with($cells[1], '`')) {
            continue;
        }
        // 同一路由多方法在清单里合并成一行，如 `GET&#124;POST`。
        $methods = explode('&#124;', str_replace(['`', '|'], ['', '&#124;'], $cells[1]));
        $path = str_replace('`', '', $cells[2]);
        foreach ($methods as $method) {
            $method = trim($method);
            if ($method === '') {
                continue;
            }
            $inventoryOps[implode(' ', normalizeOperation($path, $method))] = true;
        }
    }
}

// ---------- 比对 ----------
$failures = 0;

/**
 * @param  array<string, true>  $left
 * @param  array<string, true>  $right
 * @return list<string>
 */
function diffOps(array $left, array $right): array
{
    return array_values(array_diff(array_keys($left), array_keys($right)));
}

if ($inventoryOps !== []) {
    $invMissing = diffOps($routeOps, $inventoryOps);
    $invExtra = diffOps($inventoryOps, $routeOps);

    if ($invMissing !== [] || $invExtra !== []) {
        $failures++;
        fwrite(STDERR, sprintf(
            '[后端API清单] 与路由表不一致：路由 %d 条，清单 %d 条。%s',
            count($routeOps),
            count($inventoryOps),
            PHP_EOL
        ));
        foreach (array_slice($invMissing, 0, 20) as $op) {
            fwrite(STDERR, '  路由有、清单缺：'.$op.PHP_EOL);
        }
        foreach (array_slice($invExtra, 0, 20) as $op) {
            fwrite(STDERR, '  清单有、路由无：'.$op.PHP_EOL);
        }
    } else {
        fwrite(STDOUT, sprintf('后端 API 清单与路由表一致（%d 条）。%s', count($inventoryOps), PHP_EOL));
    }
} else {
    fwrite(STDERR, '后端 API 清单不存在或为空，请先运行 composer api:inventory。'.PHP_EOL);
    $failures++;
}

if ($failures > 0) {
    fwrite(STDERR, '接口真源一致性校验失败。修复方式：重新生成对应产物后再提交。'.PHP_EOL);
    exit(1);
}

fwrite(STDOUT, '接口真源一致性校验通过。'.PHP_EOL);
exit(0);
