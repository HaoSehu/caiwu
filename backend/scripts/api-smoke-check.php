<?php

declare(strict_types=1);

use App\Models\AdminUser;
use App\Models\ContentArticle;
use App\Models\ContentCategory;
use App\Models\Coupon;
use App\Models\CouponCampaign;
use App\Models\Invoice;
use App\Models\MemberLevel;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ReferralWithdrawal;
use App\Models\Service;
use App\Models\Supplier;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Auth\AuthService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(HttpKernel::class);
$app->make(Kernel::class)->bootstrap();

final class ApiSmokeChecker
{
    private HttpKernel $kernel;

    private array $context = [];

    private array $createdTokenIds = [];

    public function __construct(HttpKernel $kernel)
    {
        $this->kernel = $kernel;
        $this->context = $this->loadContext();
    }

    public function run(): array
    {
        $startedAt = microtime(true);
        $routes = $this->collectRoutes();
        $results = [];

        foreach ($routes as $route) {
            $results[] = $this->probeRoute($route);
        }

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        $summary = $this->buildSummary($results, $durationMs);

        $this->cleanupTokens();

        return [
            'summary' => $summary,
            'results' => $results,
            'context' => $this->exportContext(),
        ];
    }

    private function collectRoutes(): array
    {
        /** @var Collection<int, Illuminate\Routing\Route> $routes */
        $routes = collect(Route::getRoutes()->getRoutes())
            ->map(function ($route): ?array {
                $uri = '/'.ltrim($route->uri(), '/');
                if (! str_starts_with($uri, '/api/')) {
                    return null;
                }

                $methods = array_values(array_filter(
                    $route->methods(),
                    static fn (string $method): bool => $method !== 'HEAD'
                ));

                if ($methods === []) {
                    return null;
                }

                return [
                    'uri' => $uri,
                    'method' => $methods[0],
                    'methods' => $methods,
                    'action' => ltrim((string) $route->getActionName(), '\\'),
                    'middleware' => $route->gatherMiddleware(),
                ];
            })
            ->filter()
            ->values();

        return $routes->all();
    }

    private function probeRoute(array $route): array
    {
        app('auth')->forgetGuards();

        $plan = $this->buildRequestPlan($route);
        $server = $this->buildServerBag($plan);
        $parameters = $plan['query'];
        $bodyContent = null;

        if (($plan['json'] ?? false) === true) {
            $bodyContent = json_encode($plan['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $parameters = $plan['query'];
        } else {
            $parameters = array_merge($plan['query'], $plan['payload']);
        }

        $request = Request::create(
            $plan['uri'],
            $plan['method'],
            $parameters,
            [],
            [],
            $server,
            $bodyContent
        );

        $response = null;
        $exceptionMessage = '';

        try {
            $response = $this->kernel->handle($request);
        } catch (Throwable $exception) {
            $exceptionMessage = $exception->getMessage();
        }

        if ($response instanceof Response) {
            $this->kernel->terminate($request, $response);
        }

        return $this->normalizeResult($route, $plan, $response, $exceptionMessage);
    }

    private function buildRequestPlan(array $route): array
    {
        $scope = $this->resolveScope($route['uri']);
        $method = strtoupper((string) $route['method']);
        $placeholders = $this->extractPlaceholders($route['uri']);
        $isWrite = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
        $useSafeProbe = $this->shouldUseSafeProbe($route['uri'], $method);

        $uri = $route['uri'];
        foreach ($placeholders as $placeholder) {
            $replacement = $this->resolvePlaceholderValue($placeholder, $route['uri'], $isWrite || $useSafeProbe);
            $uri = str_replace('{'.$placeholder.'}', rawurlencode((string) $replacement), $uri);
        }

        $plan = [
            'scope' => $scope,
            'method' => $method,
            'uri' => $uri,
            'query' => [],
            'payload' => [],
            'headers' => [],
            'json' => false,
            'mode' => $isWrite ? '安全探测' : '真实读取',
        ];

        if ($scope === 'admin' || $scope === 'client') {
            $plan['headers']['Authorization'] = 'Bearer '.$this->resolveAuthToken($scope, $route['uri']);
        }

        if ($route['uri'] === '/api/admin/auth/logout') {
            $plan['headers']['Authorization'] = 'Bearer '.$this->createDisposableToken('admin');
            $plan['mode'] = '真实写请求';
        }

        if ($route['uri'] === '/api/client/auth/logout') {
            $plan['headers']['Authorization'] = 'Bearer '.$this->createDisposableToken('client');
            $plan['mode'] = '真实写请求';
        }

        if ($route['uri'] === '/api/admin/users/{user}/login-as') {
            $plan['mode'] = '真实写请求';
        }

        if ($route['uri'] === '/api/client/auth/login-as/exchange') {
            $plan['payload'] = [
                'code' => $this->issueLoginAsCode(),
            ];
            $plan['mode'] = '真实写请求';
        } elseif ($route['uri'] === '/api/site/products/{productId}/quote') {
            $plan['payload'] = $this->context['site_quote_payload'];
            $plan['mode'] = '真实写请求';
        } elseif ($isWrite) {
            $plan['payload'] = $this->buildSafeWritePayload($route['uri'], $scope);
        } else {
            $plan['query'] = $this->buildReadQuery($route['uri']);
        }

        if ($plan['payload'] !== [] && $this->shouldSendAsJson($route['uri'], $method)) {
            $plan['json'] = true;
            $plan['headers']['Content-Type'] = 'application/json';
        }

        return $plan;
    }

    private function buildSafeWritePayload(string $uri, string $scope): array
    {
        return match ($uri) {
            '/api/admin/auth/profile', '/api/client/auth/profile' => ['nickname' => ['invalid']],
            '/api/client/blackhole/query' => ['ip' => 'invalid-ip'],
            '/api/admin/settings' => ['settings' => 'invalid'],
            '/api/admin/schedules/trigger' => ['task' => ''],
            '/api/client/recharge' => ['amount' => 0],
            '/api/client/referral/withdrawals' => ['amount' => 0],
            '/api/client/tickets/upload-image' => [],
            '/api/client/payment/alipay/notify' => ['out_trade_no' => 'smoke-test'],
            '/api/client/verification/callback' => ['certify_id' => 'smoke'],
            default => $scope === 'admin' || $scope === 'client'
                ? ['__smoke_probe__' => '1']
                : [],
        };
    }

    private function buildReadQuery(string $uri): array
    {
        return match ($uri) {
            '/api/client/services/{id}/module-status' => ['type' => 'host'],
            '/api/client/services/{id}/monitor' => ['range' => '24h'],
            '/api/client/services/{id}/monitor/batch' => ['range' => '24h', 'limit' => 3],
            '/api/client/orders/{id}/pay/alipay/status' => [],
            '/api/client/recharge/{paymentNo}/status' => [],
            '/api/client/verification/status' => $this->context['verification_certify_id'] !== ''
                ? ['certify_id' => $this->context['verification_certify_id']]
                : [],
            '/api/client/verification/scan' => $this->context['verification_certify_id'] !== ''
                ? ['certify_id' => $this->context['verification_certify_id']]
                : [],
            '/api/admin/settings' => ['group' => 'system'],
            default => [],
        };
    }

    private function shouldSendAsJson(string $uri, string $method): bool
    {
        if (! in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return false;
        }

        if (str_contains($uri, '/upload-image')) {
            return false;
        }

        return true;
    }

    private function shouldUseSafeProbe(string $uri, string $method): bool
    {
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return true;
        }

        $keywords = [
            '/remote-status',
            '/module-status',
            '/reinstall/options',
            '/monitor',
            '/nat-forwardings',
            '/security-groups',
            '/vnc',
        ];

        foreach ($keywords as $keyword) {
            if (str_contains($uri, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function resolvePlaceholderValue(string $placeholder, string $uri, bool $preferInvalid): string|int
    {
        if ($placeholder === 'token') {
            return 'smoke-invalid-token';
        }

        if ($preferInvalid) {
            return match ($placeholder) {
                'paymentNo' => 'SMOKE-NOT-FOUND',
                default => 999999999,
            };
        }

        return match ($placeholder) {
            'user' => (int) $this->context['user_id'],
            'serviceId', 'id' => $this->resolveGenericIdByUri($uri),
            'invoice' => (int) $this->context['invoice_id'],
            'product' => (int) $this->context['product_id'],
            'productId' => (int) $this->context['product_id'],
            'supplier' => (int) $this->context['supplier_id'],
            'groupId' => (int) $this->context['public_group_id'],
            'article', 'articleId' => (int) $this->context['article_id'],
            'category' => (int) $this->context['content_category_id'],
            'ticket' => (int) $this->context['ticket_id'],
            'coupon' => (int) $this->context['coupon_id'],
            'couponCampaign' => (int) $this->context['coupon_campaign_id'],
            'memberLevel' => (int) $this->context['member_level_id'],
            'withdrawal' => (int) $this->context['withdrawal_id'],
            'paymentNo' => (string) $this->context['payment_no'],
            'forwardingId', 'groupId', 'ruleId', 'productCategory', 'productType' => 999999999,
            default => 999999999,
        };
    }

    private function resolveGenericIdByUri(string $uri): int
    {
        return match (true) {
            str_contains($uri, '/api/admin/orders/'),
            str_contains($uri, '/api/client/orders/') => (int) $this->context['order_id'],
            str_contains($uri, '/api/client/services/'),
            str_contains($uri, '/api/admin/users/{user}/services/') => (int) $this->context['service_id'],
            str_contains($uri, '/api/client/tickets/') => (int) $this->context['ticket_id'],
            default => 999999999,
        };
    }

    private function resolveScope(string $uri): string
    {
        return match (true) {
            str_starts_with($uri, '/api/admin/') => 'admin',
            str_starts_with($uri, '/api/client/') => 'client',
            default => 'public',
        };
    }

    private function resolveAuthToken(string $scope, string $uri): string
    {
        if ($scope === 'admin') {
            return (string) $this->context['admin_token'];
        }

        if ($scope === 'client') {
            return (string) $this->context['client_token'];
        }

        return '';
    }

    private function createDisposableToken(string $scope): string
    {
        if ($scope === 'admin') {
            /** @var AdminUser $admin */
            $admin = $this->context['admin_user'];
            $token = $admin->createToken('api-smoke-admin-disposable');
            $this->createdTokenIds[] = (int) $token->accessToken->id;

            return $token->plainTextToken;
        }

        /** @var User $user */
        $user = $this->context['client_user'];
        $token = $user->createToken('api-smoke-client-disposable');
        $this->createdTokenIds[] = (int) $token->accessToken->id;

        return $token->plainTextToken;
    }

    private function issueLoginAsCode(): string
    {
        /** @var User $user */
        $user = $this->context['client_user'];
        $codePayload = app(AuthService::class)->issueAdminLoginAsCode($user, [
            'admin_id' => (int) $this->context['admin_user']->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => (string) $this->context['default_user_agent'],
        ]);

        return (string) ($codePayload['login_code'] ?? '');
    }

    private function buildServerBag(array $plan): array
    {
        $server = [
            'HTTP_ACCEPT' => 'application/json',
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_USER_AGENT' => (string) $this->context['default_user_agent'],
        ];

        foreach ($plan['headers'] as $name => $value) {
            $serverKey = 'HTTP_'.strtoupper(str_replace('-', '_', $name));
            if (strtoupper($name) === 'CONTENT_TYPE') {
                $server['CONTENT_TYPE'] = $value;

                continue;
            }

            $server[$serverKey] = $value;
        }

        if (($plan['json'] ?? false) === true) {
            $server['CONTENT_TYPE'] = 'application/json';
        }

        return $server;
    }

    private function normalizeResult(array $route, array $plan, ?Response $response, string $exceptionMessage): array
    {
        if (! $response instanceof Response) {
            return [
                'method' => $route['method'],
                'uri' => $route['uri'],
                'action' => $route['action'],
                'mode' => $plan['mode'],
                'status' => 0,
                'outcome' => '异常',
                'content_type' => '',
                'response_code' => null,
                'message' => $exceptionMessage,
                'snippet' => $exceptionMessage,
            ];
        }

        $status = $response->getStatusCode();
        $contentType = trim((string) $response->headers->get('Content-Type', ''));
        $content = (string) $response->getContent();
        $decoded = null;

        if (str_contains(strtolower($contentType), 'json')) {
            $decoded = json_decode($content, true);
        }

        $snippet = $this->buildSnippet($decoded, $content);
        $outcome = $this->classifyOutcome($status, $decoded, $contentType);

        return [
            'method' => $route['method'],
            'uri' => $route['uri'],
            'action' => $route['action'],
            'mode' => $plan['mode'],
            'status' => $status,
            'outcome' => $outcome,
            'content_type' => $contentType,
            'response_code' => is_array($decoded) ? ($decoded['code'] ?? null) : null,
            'message' => is_array($decoded) ? (string) ($decoded['message'] ?? '') : '',
            'snippet' => $snippet,
        ];
    }

    private function classifyOutcome(int $status, mixed $decoded, string $contentType): string
    {
        if ($status >= 500 || $status === 0) {
            return '失败';
        }

        if ($status >= 200 && $status < 300) {
            return '通过';
        }

        if (in_array($status, [401, 403, 404, 409, 410, 422], true)) {
            return '预期拦截';
        }

        return '异常响应';
    }

    private function buildSnippet(mixed $decoded, string $content): string
    {
        if (is_array($decoded)) {
            $normalized = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return mb_substr((string) $normalized, 0, 220);
        }

        $trimmed = preg_replace('/\s+/u', ' ', trim($content)) ?? '';

        return mb_substr($trimmed, 0, 220);
    }

    private function buildSummary(array $results, int $durationMs): array
    {
        $collection = collect($results);

        return [
            'total' => $collection->count(),
            'passed' => $collection->where('outcome', '通过')->count(),
            'blocked_expected' => $collection->where('outcome', '预期拦截')->count(),
            'failed' => $collection->where('outcome', '失败')->count(),
            'unexpected' => $collection->where('outcome', '异常响应')->count(),
            'duration_ms' => $durationMs,
        ];
    }

    private function exportContext(): array
    {
        return [
            'admin_user_id' => (int) $this->context['admin_user']->id,
            'client_user_id' => (int) $this->context['client_user']->id,
            'service_id' => (int) $this->context['service_id'],
            'order_id' => (int) $this->context['order_id'],
            'invoice_id' => (int) $this->context['invoice_id'],
            'product_id' => (int) $this->context['product_id'],
        ];
    }

    private function cleanupTokens(): void
    {
        if ($this->createdTokenIds === []) {
            return;
        }

        PersonalAccessToken::query()
            ->whereIn('id', array_values(array_unique($this->createdTokenIds)))
            ->delete();
    }

    private function loadContext(): array
    {
        $admin = AdminUser::query()
            ->where('status', 1)
            ->get()
            ->sortByDesc(fn (AdminUser $item): int => count($item->resolvedPermissions()))
            ->firstOrFail();

        $client = User::query()
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->withCount(['orders', 'services', 'invoices', 'tickets'])
            ->get()
            ->sortByDesc(fn (User $item): int => (int) $item->orders_count + (int) $item->services_count + (int) $item->invoices_count + (int) $item->tickets_count)
            ->firstOrFail();

        $adminToken = $admin->createToken('api-smoke-admin');
        $clientToken = $client->createToken('api-smoke-client');

        $createdTokenIds = [
            (int) $adminToken->accessToken->id,
            (int) $clientToken->accessToken->id,
        ];

        $serviceId = (int) ($this->safeModelValue('services', fn () => Service::query()->where('user_id', $client->id)->value('id')) ?: $this->safeModelValue('services', fn () => Service::query()->value('id')) ?: 999999999);
        $orderId = (int) ($this->safeModelValue('orders', fn () => Order::query()->where('user_id', $client->id)->value('id')) ?: $this->safeModelValue('orders', fn () => Order::query()->value('id')) ?: 999999999);
        $invoiceId = (int) ($this->safeModelValue('invoices', fn () => Invoice::query()->where('user_id', $client->id)->value('id')) ?: $this->safeModelValue('invoices', fn () => Invoice::query()->value('id')) ?: 999999999);
        $ticketId = (int) ($this->safeModelValue('tickets', fn () => Ticket::query()->where('user_id', $client->id)->value('id')) ?: $this->safeModelValue('tickets', fn () => Ticket::query()->value('id')) ?: 999999999);
        $productId = (int) ($this->safeModelValue('products', fn () => Product::query()->value('id')) ?: 999999999);
        $supplierId = (int) ($this->safeModelValue('suppliers', fn () => Supplier::query()->value('id')) ?: 999999999);
        $userId = (int) ($this->safeModelValue('users', fn () => User::query()->value('id')) ?: 999999999);
        $articleId = (int) ($this->safeModelValue('content_articles', fn () => ContentArticle::query()->value('id')) ?: 999999999);
        $contentCategoryId = (int) ($this->safeModelValue('content_categories', fn () => ContentCategory::query()->value('id')) ?: 999999999);
        $couponId = (int) ($this->safeModelValue('coupons', fn () => Coupon::query()->value('id')) ?: 999999999);
        $couponCampaignId = (int) ($this->safeModelValue('coupon_campaigns', fn () => CouponCampaign::query()->value('id')) ?: 999999999);
        $memberLevelId = (int) ($this->safeModelValue('member_levels', fn () => MemberLevel::query()->value('id')) ?: 999999999);
        $withdrawalId = (int) ($this->safeModelValue('referral_withdrawals', fn () => ReferralWithdrawal::query()->value('id')) ?: 999999999);
        $paymentNo = (string) ($this->safeModelValue('payments', fn () => Payment::query()->where('user_id', $client->id)->value('payment_no')) ?: $this->safeModelValue('payments', fn () => Payment::query()->value('payment_no')) ?: 'SMOKE-NOT-FOUND');
        $rootGroup = Schema::hasTable('product_categories')
            ? ProductCategory::query()->whereNull('parent_id')->first()
            : null;
        $publicGroupId = (int) (($rootGroup?->legacy_group_id ?? 0) ?: ($rootGroup?->id ?? 999999999));
        $verificationCertifyId = trim((string) ($client->verification_certify_id ?? ''));

        $siteQuotePayload = [
            'billing_cycle' => $this->resolveQuoteCycle($productId),
            'config' => [],
            'quantity' => 1,
        ];

        $this->createdTokenIds = $createdTokenIds;

        return [
            'admin_user' => $admin,
            'client_user' => $client,
            'admin_token' => $adminToken->plainTextToken,
            'client_token' => $clientToken->plainTextToken,
            'user_id' => $userId,
            'service_id' => $serviceId,
            'order_id' => $orderId,
            'invoice_id' => $invoiceId,
            'ticket_id' => $ticketId,
            'product_id' => $productId,
            'supplier_id' => $supplierId,
            'article_id' => $articleId,
            'content_category_id' => $contentCategoryId,
            'coupon_id' => $couponId,
            'coupon_campaign_id' => $couponCampaignId,
            'member_level_id' => $memberLevelId,
            'withdrawal_id' => $withdrawalId,
            'payment_no' => $paymentNo,
            'public_group_id' => $publicGroupId,
            'verification_certify_id' => $verificationCertifyId,
            'site_quote_payload' => $siteQuotePayload,
            'default_user_agent' => 'Codex API Smoke Checker/1.0',
        ];
    }

    private function resolveQuoteCycle(int $productId): string
    {
        if (! Schema::hasTable('products')) {
            return 'monthly';
        }

        $rawPricing = Product::query()->whereKey($productId)->value('pricing');

        if (is_array($rawPricing)) {
            foreach ($rawPricing as $cycle => $amount) {
                if ((float) $amount > 0) {
                    return (string) $cycle;
                }
            }
        }

        if (is_string($rawPricing) && $rawPricing !== '') {
            $decoded = json_decode($rawPricing, true);
            if (is_array($decoded)) {
                foreach ($decoded as $cycle => $amount) {
                    if ((float) $amount > 0) {
                        return (string) $cycle;
                    }
                }
            }
        }

        return 'monthly';
    }

    private function safeModelValue(string $table, callable $resolver): mixed
    {
        if (! Schema::hasTable($table)) {
            return null;
        }

        try {
            return $resolver();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<int, string>
     */
    private function extractPlaceholders(string $uri): array
    {
        preg_match_all('/\{([^}]+)\}/', $uri, $matches);

        return array_values($matches[1] ?? []);
    }
}

$checker = new ApiSmokeChecker($kernel);
$payload = $checker->run();

$rootDir = dirname(__DIR__, 2);
$timestamp = date('Ymd-His');
$jsonFile = $rootDir.'/.review/api-smoke-'.$timestamp.'.json';
$markdownFile = $rootDir.'/.review/api-smoke-'.$timestamp.'.md';

if (! is_dir($rootDir.'/.review')) {
    mkdir($rootDir.'/.review', 0777, true);
}

file_put_contents(
    $jsonFile,
    json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
);

$lines = [];
$lines[] = '# API 动态冒烟结果';
$lines[] = '';
$lines[] = '- 时间: '.date('Y-m-d H:i:s');
$lines[] = '- 总数: '.$payload['summary']['total'];
$lines[] = '- 通过: '.$payload['summary']['passed'];
$lines[] = '- 预期拦截: '.$payload['summary']['blocked_expected'];
$lines[] = '- 失败: '.$payload['summary']['failed'];
$lines[] = '- 异常响应: '.$payload['summary']['unexpected'];
$lines[] = '- 耗时(ms): '.$payload['summary']['duration_ms'];
$lines[] = '';
$lines[] = '## 失败接口';
$lines[] = '';
$lines[] = '| 方法 | 路径 | 状态 | 结果 | 摘要 |';
$lines[] = '|---|---|---:|---|---|';

foreach ($payload['results'] as $result) {
    if (! in_array($result['outcome'], ['失败', '异常响应'], true)) {
        continue;
    }

    $summary = str_replace('|', '\\|', $result['snippet']);
    $lines[] = sprintf(
        '| %s | %s | %d | %s | %s |',
        $result['method'],
        $result['uri'],
        $result['status'],
        $result['outcome'],
        $summary
    );
}

$lines[] = '';
$lines[] = '## 全量明细';
$lines[] = '';
$lines[] = '| 方法 | 路径 | 模式 | 状态 | 结果 | 响应码 | 消息 |';
$lines[] = '|---|---|---|---:|---|---:|---|';

foreach ($payload['results'] as $result) {
    $message = str_replace('|', '\\|', $result['message'] ?: $result['snippet']);
    $lines[] = sprintf(
        '| %s | %s | %s | %d | %s | %s | %s |',
        $result['method'],
        $result['uri'],
        $result['mode'],
        $result['status'],
        $result['outcome'],
        $result['response_code'] === null ? '' : (string) $result['response_code'],
        $message
    );
}

file_put_contents($markdownFile, implode(PHP_EOL, $lines));

echo json_encode([
    'summary' => $payload['summary'],
    'json_file' => $jsonFile,
    'markdown_file' => $markdownFile,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
