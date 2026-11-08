<?php

declare(strict_types=1);

/**
 * 在线演示数据集导出：在脱敏副本库上以真实控制器序列化响应，写成静态 JSON。
 *
 * 前置：先跑 demo_anonymize.php（生成脱敏副本库，默认 idc_test）。
 * 用法：cd backend && php scripts/demo_export.php [--caiwu-root=<path>]
 * 产物：<caiwu-root>/demo-data/{admin,console,www}.json
 *
 * 安全防呆：脚本启动后断言当前连接库名以 _test / _demo 结尾，否则拒绝执行。
 */

use App\Models\AdminUser;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;

$options = [];
foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--caiwu-root=')) {
        $options['caiwuRoot'] = substr($argument, strlen('--caiwu-root='));
    }
}

$caiwuRoot = $options['caiwuRoot'] ?? dirname(__DIR__, 2);
$outputDir = rtrim($caiwuRoot, '/\\').DIRECTORY_SEPARATOR.'demo-data';

// 让 Laravel 连到脱敏副本库：进程环境变量优先于 .env
putenv('DB_DATABASE=idc_test');
$_ENV['DB_DATABASE'] = 'idc_test';
$_SERVER['DB_DATABASE'] = 'idc_test';

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();

$dbName = (string) $app['db']->connection()->getDatabaseName();
if (! preg_match('/(_test|_demo)$/', $dbName)) {
    fwrite(STDERR, "当前连接库为 {$dbName}，不是 _test/_demo 副本库，拒绝导出。\n");
    exit(1);
}

$httpKernel = $app->make(HttpKernel::class);

$endpoints = json_decode((string) file_get_contents(__DIR__.'/demo_endpoints.json'), true);
if (! is_array($endpoints)) {
    fwrite(STDERR, "demo_endpoints.json 解析失败。\n");
    exit(1);
}

/** 用内部请求调用真实控制器，返回 JSON 信封中的 data。 */
function exportEndpoint(HttpKernel $kernel, string $method, string $path, ?string $bearer, array $query = [], ?string $body = null): array
{
    $server = [
        'HTTP_ACCEPT' => 'application/json',
        'CONTENT_TYPE' => 'application/json',
    ];
    if ($bearer) {
        $server['HTTP_AUTHORIZATION'] = 'Bearer '.$bearer;
    }

    $uri = '/api'.preg_replace('#^/api#', '', $path);
    $request = Request::create($uri, $method, $query, [], [], $server, $body);

    $response = $kernel->handle($request);
    $payload = json_decode((string) $response->getContent(), true);

    if (! is_array($payload) || ! array_key_exists('code', $payload)) {
        return ['_error' => 'non-envelope response', '_status' => $response->getStatusCode()];
    }
    if ((int) $payload['code'] !== 0) {
        return ['_error' => (string) ($payload['message'] ?? 'unknown'), '_code' => $payload['code']];
    }

    return is_array($payload['data'] ?? null) ? $payload['data'] : ['_value' => $payload['data'] ?? null];
}

/**
 * 后端序列化器会把 /media/xxx 拼成 API 绝对域名（UploadUrl::resolve）。
 * 在线演示不连后端，统一改写为站内相对路径 /demo/media/xxx。
 */
function rewriteMediaUrls(mixed $value): mixed
{
    if (is_array($value)) {
        return array_map('rewriteMediaUrls', $value);
    }

    if (is_string($value)) {
        return preg_replace(
            '#^https?://[^/\s"\\\']+/(media|uploads)/#',
            '/demo/media/',
            $value
        );
    }

    return $value;
}

/** 拆出 "METHOD path?query|{json body}" 三段。 */
function parseEndpointSpec(string $spec): array
{
    $body = null;
    if (str_contains($spec, '|')) {
        [$spec, $body] = explode('|', $spec, 2);
    }

    [$method, $path] = explode(' ', $spec, 2);

    $query = [];
    if (str_contains($path, '?')) {
        [$path, $queryString] = explode('?', $path, 2);
        parse_str($queryString, $query);
    }

    return [$method, $path, $query, $body];
}

/**
 * 把通配键里的 * 替换成一条真实记录 id。
 * 优先从同 app 已导出的列表响应里取第一个 id；取不到时按路径首段猜表名查 max(id)。
 */
function resolveWildcard(string $templateKey, array $exported, PDO $pdo): ?string
{
    if (! str_contains($templateKey, '*')) {
        return $templateKey;
    }

    [$method, $path] = explode(' ', $templateKey, 2);
    $querySuffix = '';
    if (str_contains($path, '?')) {
        [$path, $queryString] = explode('?', $path, 2);
        $querySuffix = '?'.$queryString;
    }
    $segments = explode('/', trim($path, '/'));
    $starIndex = array_search('*', $segments, true);
    if ($starIndex === false) {
        return null;
    }

    // 同名 GET 列表键（去掉 * 及其后的段）里找 id
    $listPrefix = array_slice($segments, 0, (int) $starIndex);
    $listKey = 'GET /'.implode('/', $listPrefix);

    $candidates = [];
    if (isset($exported[$listKey]) && is_array($exported[$listKey])) {
        $candidates = $exported[$listKey]['list'] ?? $exported[$listKey];
    }

    $id = null;
    if (is_array($candidates)) {
        foreach ($candidates as $row) {
            if (is_array($row) && isset($row['id']) && (int) $row['id'] > 0) {
                $id = (string) (int) $row['id'];
                break;
            }
        }
    }

    // 回退：按路径首段猜表名（/v2/admin/orders/* -> orders；/v2/site/products/* -> products）
    if ($id === null && count($segments) > 2) {
        $table = $segments[2];
        $table = rtrim($table, 's');
        $tableMap = [
            'order' => 'orders', 'invoice' => 'invoices', 'product' => 'products',
            'ticket' => 'tickets', 'user' => 'users', 'service' => 'services',
            'notice' => 'content_articles', 'help-article' => 'content_articles',
            'product-group' => 'first_product_groups', 'coupon' => 'coupons',
            'member-level' => 'member_levels', 'supplier' => 'suppliers',
        ];
        $table = $tableMap[$table] ?? $table.'s';
        try {
            $found = $pdo->query('SELECT MAX(id) FROM `'.$table.'`')->fetchColumn();
            if ($found) {
                $id = (string) (int) $found;
            }
        } catch (Throwable) {
            $id = null;
        }
    }

    if ($id === null) {
        return null;
    }

    $segments[$starIndex] = $id;

    return $method.' /'.implode('/', $segments).$querySuffix;
}

$admin = AdminUser::query()->where('status', 1)->orderBy('id')->first();
$user = User::query()->whereNull('deleted_at')->orderBy('id')->first();

if (! $admin || ! $user) {
    fwrite(STDERR, "副本库缺少可用的管理员或用户记录，无法创建导出令牌。\n");
    exit(1);
}

$adminToken = $admin->createToken('demo-export')->plainTextToken;
$userToken = $user->createToken('demo-export')->plainTextToken;
$tokens = ['admin' => $adminToken, 'console' => $userToken, 'www' => null];

if (! is_dir($outputDir)) {
    mkdir($outputDir, 0775, true);
}

$exportedAt = date('c');
$summary = [];
$pdo = $app['db']->connection()->getPdo();

foreach (['admin', 'console', 'www'] as $appKey) {
    $keys = $endpoints[$appKey] ?? [];
    $exported = [];
    $failures = [];

    foreach ($keys as $templateKey) {
        // 通配键依赖列表先导出：按清单顺序处理即可与 resolveWildcard 的 exported 对齐
        $resolved = resolveWildcard($templateKey, $exported, $pdo);
        if ($resolved === null) {
            $failures[$templateKey] = '无法解析通配 id';

            continue;
        }

        [$method, $path, $query, $body] = parseEndpointSpec($resolved);
        $data = exportEndpoint($httpKernel, $method, $path, $tokens[$appKey], $query, $body);

        if (isset($data['_error'])) {
            $failures[$templateKey] = $data['_error'];

            continue;
        }

        $exported[$templateKey] = $data;

        // 进程内复用内核会话会缓存上一个请求的 auth guard 用户（admin token 串到 client
        // 请求，报「仅允许客户访问」）；每个请求后清掉，下一个请求重新解析自己的 token。
        $app['auth']->forgetGuards();
        $app->forgetInstance('request');
        Facade::clearResolvedInstance('request');
    }

    $payload = [
        'meta' => ['app' => $appKey, 'exported_at' => $exportedAt],
        'endpoints' => $exported,
    ];
    file_put_contents(
        $outputDir.DIRECTORY_SEPARATOR.$appKey.'.json',
        json_encode(
            rewriteMediaUrls($payload),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
        )
    );

    $summary[$appKey] = ['ok' => count($exported), 'failed' => $failures];
    echo "[{$appKey}] 导出 ".count($exported).' 个接口，失败 '.count($failures)."\n";
    foreach ($failures as $key => $reason) {
        echo "   失败 {$key}: {$reason}\n";
    }
}

// 清理临时令牌
$admin->tokens()->where('name', 'demo-export')->delete();
$user->tokens()->where('name', 'demo-export')->delete();

echo "输出目录: {$outputDir}\n";
exit(0);
