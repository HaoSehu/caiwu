<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;

define('LARAVEL_START', microtime(true));

$basePath = dirname(__DIR__);

require $basePath.'/vendor/autoload.php';

$app = require $basePath.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$routes = collect(RouteFacade::getRoutes()->getRoutes())
    ->filter(fn (Route $route) => str_starts_with($route->uri(), 'api/'))
    ->map(function (Route $route): array {
        $methods = array_values(array_filter($route->methods(), fn (string $method) => $method !== 'HEAD'));
        $middleware = array_values(array_unique($route->middleware()));
        $uri = '/'.ltrim($route->uri(), '/');

        return [
            'group' => resolveGroup($uri),
            'methods' => implode('|', $methods),
            'path' => $uri,
            'action' => resolveAction($route),
            'auth' => resolveAuth($middleware),
            'middleware' => implode(', ', $middleware),
        ];
    })
    ->sortBy([
        ['group', 'asc'],
        ['path', 'asc'],
        ['methods', 'asc'],
    ])
    ->values();

$groupCounts = $routes
    ->countBy('group')
    ->map(fn (int $count, string $group): string => $group.'='.$count)
    ->implode(', ');

$lines = [
    '# 后端 API 清单',
    '',
    '- 生成时间: `'.date('Y-m-d H:i:s').'`',
    '- API 总数: `'.$routes->count().'`',
    '- 分组统计: `'.$groupCounts.'`',
    '',
    '> **自动生成**，由 `backend/scripts/export_api_inventory.php` 扫描 Laravel 路由表导出，**不要手工编辑**。',
    '>',
    '> 需要更新本文件时，直接在项目根目录执行：`php backend/scripts/export_api_inventory.php`。',
    '> 需要看业务分组、核心业务流程映射等人类可读导航，请查看 `文档/开发文档/后端/API清单导航.md`。',
    '',
    '| 分组 | 方法 | 路径 | 控制器动作 | 鉴权 | 中间件 |',
    '| --- | --- | --- | --- | --- | --- |',
];

foreach ($routes as $route) {
    $lines[] = sprintf(
        '| %s | `%s` | `%s` | `%s` | `%s` | `%s` |',
        escapeTableCell($route['group']),
        escapeTableCell($route['methods']),
        escapeTableCell($route['path']),
        escapeTableCell($route['action']),
        escapeTableCell($route['auth']),
        escapeTableCell($route['middleware'])
    );
}

$target = dirname($basePath).'/文档/开发文档/后端/后端API清单.md';
// Windows 下 PHP 文件系统 API 按系统 ANSI(GBK) 编码解析路径，
// UTF-8 中文路径需转 GBK 才能正确打开，否则报 "Failed to open stream: No such file or directory"。
$writeTarget = PHP_OS_FAMILY === 'Windows'
    ? mb_convert_encoding($target, 'GBK', 'UTF-8')
    : $target;
file_put_contents($writeTarget, implode("\n", $lines)."\n");

fwrite(STDOUT, sprintf(
    '已生成 API 清单: %s，接口数: %d%s',
    $target,
    $routes->count(),
    PHP_EOL
));

function resolveGroup(string $uri): string
{
    if (str_starts_with($uri, '/api/admin/')) {
        return 'admin';
    }

    if (str_starts_with($uri, '/api/client/')) {
        return 'client';
    }

    return 'site/public';
}

function resolveAction(Route $route): string
{
    $action = $route->getActionName();

    if ($action === 'Closure') {
        return 'Closure';
    }

    return $action;
}

/**
 * 中间件只从路由定义读取，不触发控制器实例化，避免导出文档时连接数据库。
 */
function resolveAuth(array $middleware): string
{
    $middlewareText = implode(',', $middleware);

    if (str_contains($middlewareText, 'ensure.admin')) {
        return 'admin';
    }

    if (str_contains($middlewareText, 'ensure.client')) {
        return 'client';
    }

    if (str_contains($middlewareText, 'auth:sanctum')) {
        return 'auth';
    }

    return 'public';
}

function escapeTableCell(string $value): string
{
    return str_replace('|', '&#124;', $value);
}
