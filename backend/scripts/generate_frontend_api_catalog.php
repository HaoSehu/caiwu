<?php

declare(strict_types=1);

$backendDir = dirname(__DIR__);
$rootDir = dirname($backendDir);

// --check：只校验前端接口清单生成物是否与当前路由一致，不写文件。
// 供 CI 使用（backend.yml「接口清单一致性」步骤），路由变更后忘记重新生成时会失败。
$checkMode = in_array('--check', $argv ?? [], true);

chdir($backendDir);

$routeJson = shell_exec('php artisan route:list --json');
$routes = json_decode($routeJson ?: '[]', true);

if (! is_array($routes)) {
    fwrite(STDERR, "Failed to read Laravel route list.\n");
    exit(1);
}

$scopeLabels = [
    'admin' => '管理端',
    'client' => '客户端',
    'site' => '站点',
    'system' => '系统',
];

$sourceAppLabels = [
    'frontend-admin-v3' => '管理端前端',
    'frontend-user-v3-www' => '官网/用户入口',
    'frontend-user-v4-console' => '用户控制台',
];

$accessLabels = [
    'public' => '公开',
    'auth' => '仅登录',
    'permission' => '登录 + 权限',
];

$items = [];

foreach ($routes as $route) {
    $uri = '/'.ltrim((string) ($route['uri'] ?? ''), '/');
    if (preg_match('#^/api/(v2/(admin|client|site)|health)(/|$)#', $uri) !== 1) {
        continue;
    }

    $methods = array_values(array_filter(
        explode('|', str_replace('HEAD', '', (string) ($route['method'] ?? ''))),
        static fn (string $method): bool => $method !== ''
    ));
    if ($methods === []) {
        $methods = [(string) ($route['method'] ?? '')];
    }

    $scope = resolveScope($uri);
    $module = resolveModule($uri, $scope);
    $middleware = (array) ($route['middleware'] ?? []);
    $permission = resolvePermission($middleware);
    $access = $permission !== '' ? 'permission' : (isAuthenticatedRoute($middleware) ? 'auth' : 'public');

    $items[] = [
        'id' => implode('|', $methods).':'.$uri,
        'scope' => $scope,
        'scopeLabel' => $scopeLabels[$scope] ?? $scope,
        'module' => $module,
        'moduleLabel' => moduleLabel($module),
        'method' => implode(' / ', $methods),
        'methods' => $methods,
        'callPath' => preg_replace('#^/api/v2#', '', $uri),
        'backendPath' => $uri,
        'normalizedPath' => preg_replace(
            '#\{[^}/]+\}#',
            '{param}',
            (string) preg_replace('#^/api/v2/(admin|client|site)#', '/$1', $uri)
        ),
        'access' => $access,
        'accessLabel' => $accessLabels[$access] ?? $access,
        'permission' => $permission,
        'throttle' => resolveThrottle($middleware),
        'guards' => resolveGuards($middleware),
        'handler' => (string) ($route['action'] ?? ''),
        'sourceApps' => [],
        'sourceAppLabels' => [],
        'sourceFiles' => [],
    ];
}

usort(
    $items,
    static fn (array $left, array $right): int => [
        $left['scope'],
        $left['module'],
        $left['backendPath'],
        $left['method'],
    ] <=> [
        $right['scope'],
        $right['module'],
        $right['backendPath'],
        $right['method'],
    ]
);

$scopeCounts = [];
$accessCounts = [];
$moduleKeys = [];

foreach ($items as $item) {
    $scopeCounts[$item['scope']] = ($scopeCounts[$item['scope']] ?? 0) + 1;
    $accessCounts[$item['access']] = ($accessCounts[$item['access']] ?? 0) + 1;
    $moduleKeys[$item['scope'].':'.$item['module']] = true;
}

$payload = [
    'meta' => [
        'generatedAt' => date('Y-m-d H:i:s'),
        'total' => count($items),
        'baseURL' => '/api/v2',
        'dataSource' => 'php artisan route:list --json v2-only',
        'scopeLabels' => $scopeLabels,
        'sourceAppLabels' => $sourceAppLabels,
        'accessLabels' => $accessLabels,
        'scopeCounts' => $scopeCounts,
        'accessCounts' => $accessCounts,
        'moduleCount' => count($moduleKeys),
    ],
    'items' => $items,
];

$target = $rootDir.'/frontend-admin-v3/src/data/apiCatalog.generated.json';
$encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;

if ($checkMode) {
    // 只比对内容，忽略 meta.generatedAt（每次运行必然不同）。
    $existing = is_file($target) ? json_decode((string) file_get_contents($target), true) : null;
    $actual = is_array($existing) ? $existing : [];
    $expected = $payload;

    unset($actual['meta']['generatedAt'], $expected['meta']['generatedAt']);

    if ($actual !== $expected) {
        fwrite(STDERR, '接口清单生成物已过期：'.$target.PHP_EOL);
        fwrite(STDERR, '请运行 php backend/scripts/generate_frontend_api_catalog.php 重新生成并提交。'.PHP_EOL);

        $existingIds = array_column(is_array($actual['items'] ?? null) ? $actual['items'] : [], 'id');
        $expectedIds = array_column($expected['items'], 'id');
        $added = array_values(array_diff($expectedIds, $existingIds));
        $removed = array_values(array_diff($existingIds, $expectedIds));

        fwrite(STDERR, '新增 '.count($added).' 条，移除 '.count($removed).' 条。'.PHP_EOL);
        foreach (array_slice($added, 0, 10) as $id) {
            fwrite(STDERR, '  + '.$id.PHP_EOL);
        }
        foreach (array_slice($removed, 0, 10) as $id) {
            fwrite(STDERR, '  - '.$id.PHP_EOL);
        }

        exit(1);
    }

    echo '接口清单生成物与当前路由一致（'.count($items).' 条）。'.PHP_EOL;
    exit(0);
}

file_put_contents($target, $encoded);

echo 'Generated '.count($items).' API catalog entries at '.$target.PHP_EOL;

function resolveScope(string $uri): string
{
    if (str_starts_with($uri, '/api/v2/admin')) {
        return 'admin';
    }

    if (str_starts_with($uri, '/api/v2/client')) {
        return 'client';
    }

    if (str_starts_with($uri, '/api/v2/site')) {
        return 'site';
    }

    return 'system';
}

function resolveModule(string $uri, string $scope): string
{
    if ($scope === 'system') {
        return 'health';
    }

    $path = trim((string) preg_replace('#^/api/v2/(admin|client|site)/?#', '', $uri), '/');
    $module = explode('/', $path)[0] ?? 'root';

    return $module !== '' ? $module : 'root';
}

function moduleLabel(string $module): string
{
    $labels = [
        'auth' => '认证',
        'balance-logs' => '余额日志',
        'content' => '内容',
        'coupons' => '优惠券',
        'dashboard' => '仪表盘',
        'finance' => '财务',
        'health' => '健康检查',
        'invoices' => '账单',
        'logs' => '日志',
        'media-files' => '媒体文件',
        'notifications' => '通知',
        'orders' => '订单',
        'payments' => '支付',
        'product-groups' => '产品分组',
        'product-types' => '产品类型',
        'products' => '产品',
        'recharge' => '充值',
        'referral' => '分销',
        'services' => '服务',
        'settings' => '设置',
        'site' => '站点',
        'staff' => '员工',
        'suppliers' => '供应商',
        'tickets' => '工单',
        'users' => '用户',
        'verification' => '实名',
    ];

    return $labels[$module] ?? ucwords(str_replace(['-', '_'], ' ', $module));
}

/**
 * 归一化中间件条目：拆出「类名 basename」与「: 之后的参数」。
 *
 * `artisan route:list --json` 输出的是 FQCN 形态（如
 * App\Http\Middleware\CheckPermission:product.manage、
 * Illuminate\Routing\Middleware\ThrottleRequests:10,1），而路由文件与内核也允许短别名
 * （permission:xxx、throttle:10,1）。只保留 basename 与参数，两种形态即可统一比较。
 *
 * @return array{name: string, params: string}
 */
function normalizeMiddleware(string $middleware): array
{
    $name = trim($middleware);
    $params = '';

    if (($separator = strpos($name, ':')) !== false) {
        $params = substr($name, $separator + 1);
        $name = substr($name, 0, $separator);
    }

    if (($namespaceSeparator = strrpos($name, '\\')) !== false) {
        $name = substr($name, $namespaceSeparator + 1);
    }

    return ['name' => $name, 'params' => $params];
}

/**
 * 查找第一个命中的中间件（类名 basename 或路由短别名均可）。
 *
 * @param  list<string>  $middleware
 * @param  list<string>  $names
 * @return array{name: string, params: string}|null
 */
function findMiddleware(array $middleware, array $names): ?array
{
    foreach ($middleware as $item) {
        $normalized = normalizeMiddleware((string) $item);
        if (in_array($normalized['name'], $names, true)) {
            return $normalized;
        }
    }

    return null;
}

/**
 * @param  list<string>  $middleware
 */
function resolvePermission(array $middleware): string
{
    $matched = findMiddleware($middleware, ['CheckPermission', 'permission']);

    return $matched !== null ? $matched['params'] : '';
}

/**
 * @param  list<string>  $middleware
 */
function isAuthenticatedRoute(array $middleware): bool
{
    return findMiddleware($middleware, [
        'Authenticate',
        'auth',
        'EnsureAdminAuthenticated',
        'ensure.admin',
        'admin.auth',
        'EnsureClientAuthenticated',
        'ensure.client',
        'client.auth',
    ]) !== null;
}

/**
 * @param  list<string>  $middleware
 */
function resolveThrottle(array $middleware): string
{
    $matched = findMiddleware($middleware, ['ThrottleRequests', 'throttle']);

    return $matched !== null ? $matched['params'] : '';
}

/**
 * @param  list<string>  $middleware
 * @return list<string>
 */
function resolveGuards(array $middleware): array
{
    $guards = [];
    foreach ($middleware as $item) {
        $normalized = normalizeMiddleware((string) $item);
        if (in_array($normalized['name'], ['ThrottleRequests', 'throttle'], true)) {
            $guards[] = '限流 '.$normalized['params'];
        }
        if (in_array($normalized['name'], ['VerifyCallbackSignature', 'verify.callback', 'VerifyAlipayCallbackSignature', 'verify.alipay.callback', 'VerifyPaymentCallbackSignature', 'verify.payment.callback'], true)) {
            $guards[] = '签名校验';
        }
    }

    return $guards;
}
