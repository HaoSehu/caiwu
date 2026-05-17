<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

/** @var Collection<int, array<string, string>> $routes */
$routes = collect(app('router')->getRoutes()->getRoutes())
    ->filter(fn (Route $route): bool => Str::startsWith($route->uri(), 'api/'))
    ->map(function (Route $route): array {
        $uri = $route->uri();
        $methods = collect($route->methods())
            ->reject(fn (string $method): bool => $method === 'HEAD')
            ->values()
            ->all();
        $middleware = $route->gatherMiddleware();
        $area = Str::startsWith($uri, 'api/admin/')
            ? 'admin'
            : (Str::startsWith($uri, 'api/client/') ? 'client' : 'site/public');
        $auth = in_array('App\Http\Middleware\EnsureAdminAuthenticated', $middleware, true) || in_array('ensure.admin', $middleware, true)
            ? 'admin'
            : ((in_array('App\Http\Middleware\EnsureClientAuthenticated', $middleware, true) || in_array('ensure.client', $middleware, true)) ? 'client' : 'public');

        return [
            'area' => $area,
            'method' => implode('|', $methods),
            'uri' => '/'.ltrim($uri, '/'),
            'action' => ltrim($route->getActionName(), '\\'),
            'auth' => $auth,
            'middleware' => implode(', ', $middleware),
        ];
    })
    ->sortBy([
        ['area', 'asc'],
        ['uri', 'asc'],
        ['method', 'asc'],
    ])
    ->values();

$summary = $routes
    ->groupBy('area')
    ->map(fn (Collection $items): int => $items->count());

$projectRoot = dirname(__DIR__, 2);
$outputPath = $projectRoot.DIRECTORY_SEPARATOR.'文档'.DIRECTORY_SEPARATOR.'后端'.DIRECTORY_SEPARATOR.'后端API清单.md';

$lines = [
    '# 后端 API 清单',
    '',
    '- 生成时间: `'.now()->format('Y-m-d H:i:s').'`',
    '- API 总数: `'.$routes->count().'`',
    '- 分组统计: `'.$summary->map(fn (int $count, string $area): string => $area.'='.$count)->implode(', ').'`',
    '',
    '> **自动生成**，由 `backend/scripts/export_api_inventory.php` 扫描 Laravel 路由表导出，**不要手工编辑**。',
    '>',
    '> 需要更新本文件时，直接在项目根目录执行：`php backend/scripts/export_api_inventory.php`。',
    '> 需要看业务分组、核心业务流程映射等人类可读导航，请查看 `文档/后端/API清单导航.md`。',
    '',
    '| 分组 | 方法 | 路径 | 控制器动作 | 鉴权 | 中间件 |',
    '| --- | --- | --- | --- | --- | --- |',
];

foreach ($routes as $route) {
    $lines[] = sprintf(
        '| %s | `%s` | `%s` | `%s` | `%s` | `%s` |',
        $route['area'],
        $route['method'],
        str_replace('|', '\\|', $route['uri']),
        str_replace('|', '\\|', $route['action']),
        $route['auth'],
        str_replace('|', '\\|', $route['middleware'])
    );
}

file_put_contents($outputPath, implode(PHP_EOL, $lines).PHP_EOL);

echo 'Exported API inventory to: '.$outputPath.PHP_EOL;
