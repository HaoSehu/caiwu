<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * 客户端 API 全局限流回归（付款阻断事故）。
 *
 * 事故现象：用户点任何支付方式（余额/支付宝/微信/组合）都被 429
 * 「请求过于频繁，请稍后再试」拒绝。
 *
 * 根因：bootstrap/app.php 的 $middleware->throttleApi() 把全部
 * /api/v2/client/* 压到同一个 `api` 限流桶（120 次/分钟按 IP）。收银台
 * 每 5 秒轮询支付状态、充值页同频轮询、实名认证每 3 秒轮询，叠加账单列表/
 * 详情/资产等常规请求，一个正常用户 3–5 分钟就会打满 120，之后连支付请求
 * 一起被拦截——四种支付方式走的是同一个全局桶，所以「全部被拒」。
 *
 * 修复口径（AppServiceProvider::'api' limiter）：
 * - 已登录（认证已解析出用户）：600/分钟，按用户 id 计数；
 * - 匿名：120/分钟，按 IP 计数（公开端点防刷口径不变）。
 *
 * 本测试锁定：
 * 1. 已登录用户远超 120 次的轮询不会 429；
 * 2. 支付请求不被轮询连坐；
 * 3. 已登录额度确实是 600/分钟且按用户隔离，不是无限；
 * 4. 匿名请求仍按 IP 120/分钟限流。
 *
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class ClientApiGlobalRateLimitTest extends TestCase
{
    use DatabaseTransactions;

    private const AUTH_LIMIT_KEY = 'authenticated-api:user:';

    private const ANON_LIMIT_KEY = 'api:127.0.0.1';

    protected function setUp(): void
    {
        parent::setUp();

        // 限流计数存放在缓存里，不随数据库事务回滚；同一进程内多个用例会累积，
        // 不清空会让前面的用例把后面的额度吃光（表现为「第 1 次请求就 429」）。
        RateLimiter::clear(self::ANON_LIMIT_KEY);
    }

    public function test_已登录用户轮询不会触发全局接口限流(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);
        RateLimiter::clear(self::AUTH_LIMIT_KEY.$user->id);

        // 收银台轮询 5 分钟 ≈ 60 次，加上列表/详情/资产刷新，取 180 次覆盖真实用量
        for ($i = 1; $i <= 180; $i++) {
            $response = $this->getJson('/api/v2/client/invoices/summary');

            $this->assertNotSame(
                429,
                $response->getStatusCode(),
                "第 {$i} 次已登录客户端请求被限流：正常轮询不应触发全局 429"
            );
        }

        $this->assertSame(
            0,
            RateLimiter::attempts(self::ANON_LIMIT_KEY),
            '已登录请求不应消耗匿名 IP 桶'
        );
    }

    public function test_持续轮询后支付接口仍可用(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);
        RateLimiter::clear(self::AUTH_LIMIT_KEY.$user->id);

        for ($i = 1; $i <= 150; $i++) {
            $this->getJson('/api/v2/client/invoices/summary')->assertStatus(200);
        }

        // 支付请求本身不该被轮询连坐；账单不存在应为 404 而不是 429
        $response = $this->postJson('/api/v2/client/invoices/999999999/pay/balance', [
            'payment_session_token' => str_repeat('a', 40),
        ]);

        $this->assertNotSame(
            429,
            $response->getStatusCode(),
            '持续轮询后支付接口被全局限流连坐，用户将无法付款'
        );
    }

    public function test_已登录额度按用户隔离且上限生效(): void
    {
        $first = $this->makeUser();
        $second = $this->makeUser();
        RateLimiter::clear(self::AUTH_LIMIT_KEY.$first->id);
        RateLimiter::clear(self::AUTH_LIMIT_KEY.$second->id);

        // 用户 A 真实打满 600 次额度（限流器内部计数只有经过中间件才会累加，
        // 手工 hit 写不到同一个计数口径）
        Sanctum::actingAs($first);
        for ($i = 1; $i <= 600; $i++) {
            $this->getJson('/api/v2/client/invoices/summary')->assertStatus(200);
        }

        $this->assertSame(
            429,
            $this->getJson('/api/v2/client/invoices/summary')->getStatusCode(),
            '额度耗尽后仍应被限流'
        );

        // 用户 B 不受 A 的额度影响——按用户计数而不是按 IP
        Sanctum::actingAs($second);
        $this->assertNotSame(
            429,
            $this->getJson('/api/v2/client/invoices/summary')->getStatusCode(),
            '其他用户不应被同一 IP 下的用户 A 连坐'
        );
    }

    public function test_匿名限流器仍按_ip_计数(): void
    {
        RateLimiter::clear(self::ANON_LIMIT_KEY);

        // 匿名入口由 api 组的 throttle:api 按 IP 计数（未登录请求会被认证中间件
        // 先拒绝，因此这里直接断言限流器的键与额度，而不是走 HTTP）。
        $request = Request::create('/api/v2/client/invoices/summary', 'GET');
        $request->setUserResolver(static fn () => null);

        $limit = app(\Illuminate\Cache\RateLimiter::class)->limiter('api')($request);

        $this->assertSame(self::ANON_LIMIT_KEY, $limit->key, '匿名请求必须按 IP 计数');
        $this->assertSame(120, $limit->maxAttempts, '匿名入口额度必须保持 120/分钟');
    }

    /**
     * 修复成立的前提：限流中间件执行时用户必须已解析。
     *
     * throttle:api 在 api 组声明顺序上排在 auth:sanctum 之前，但 Laravel 的
     * $middlewarePriority 会把 AuthenticatesRequests 提到 ThrottleRequests 之前。
     * 这个隐式顺序一旦被框架升级或自定义中间件打破，限流器里的 $request->user()
     * 会恒为 null，所有登录用户退回共享 IP 桶（付款阻断复发），而其他用例仍会通过。
     */
    public function test_认证中间件必须排在限流之前(): void
    {
        $kernel = app(Kernel::class);
        $priority = (new \ReflectionClass($kernel))->getProperty('middlewarePriority');
        $priority->setAccessible(true);
        $list = $priority->getValue($kernel);

        $authIndex = array_search(AuthenticatesRequests::class, $list, true);
        $throttleIndex = array_search(ThrottleRequests::class, $list, true);

        $this->assertNotFalse($authIndex, '中间件优先级表缺少 AuthenticatesRequests');
        $this->assertNotFalse($throttleIndex, '中间件优先级表缺少 ThrottleRequests');
        $this->assertLessThan(
            $throttleIndex,
            $authIndex,
            'AuthenticatesRequests 必须排在 ThrottleRequests 之前，否则限流器读不到当前用户，已登录用户会退回共享 IP 桶'
        );
    }

    private function makeUser(): User
    {
        return User::query()->create([
            'email' => 'ratelimit-'.uniqid('', true).'@example.test',
            'password' => bcrypt('secret'),
            'status' => 1,
        ]);
    }
}
