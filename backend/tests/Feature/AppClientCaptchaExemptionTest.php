<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Auth\GeeTestService;
use App\Services\Integrations\Plugins\IntegrationDriverBindingResolver;
use App\Services\Integrations\Plugins\PluginRuntimeRegistry;
use App\Support\ClientPlatform;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * 原生 App 发验证码免人机验证。
 *
 * App 只保留验证码登录（不提供密码入口），而 App 无法加载 Geetest SDK，
 * sendPhoneCode / sendEmailCode 原先的无条件校验会让 App 登录、注册与找回密码
 * 全部不可用。这里锁定两件事：
 * 1. 带 `X-Client-Platform: android` 的请求不再被 42210 拦下；
 * 2. 不带该请求头的请求保持原有强制校验（网页端行为零变化）。
 *
 * 免验的代价由既有防线承担：发送接口自带 throttle:3,1 与 MessageRateLimitService
 * 按 channel+目标+IP 限流，登录/重置场景对未注册账号静默不发码。
 */
class AppClientCaptchaExemptionTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    /**
     * 接口级用例需要真实表结构（settings/integration_plugins 等）。
     * 本机 idc_test 尚未迁移时显式跳过，而不是让整类用例红着冒充回归失败。
     */
    private function requireTestSchema(): void
    {
        if (! Schema::hasTable('settings')) {
            $this->markTestSkipped('idc_test 未迁移，接口级用例需先执行 php artisan migrate --database=mysql');
        }
    }

    public function test_app_phone_code_request_is_not_blocked_by_captcha(): void
    {
        $this->requireTestSchema();
        $this->fakeCaptchaEnabled();

        $response = $this->withHeader('X-Client-Platform', 'android')
            ->postJson('/api/v2/client/auth/phone-code', [
                'phone' => '13800000001',
                'purpose' => 'login',
            ]);

        $this->assertNotSame(42210, (int) $response->json('code'), 'App 请求不应再被行为验证拦下');
    }

    public function test_browser_phone_code_request_still_requires_captcha(): void
    {
        $this->requireTestSchema();
        $this->fakeCaptchaEnabled();

        $response = $this->postJson('/api/v2/client/auth/phone-code', [
            'phone' => '13800000001',
            'purpose' => 'login',
        ]);

        $response->assertStatus(422);
        $this->assertSame(42210, (int) $response->json('code'), '网页端必须完成行为验证');
    }

    public function test_app_email_code_request_is_not_blocked_by_captcha(): void
    {
        $this->requireTestSchema();
        $this->fakeCaptchaEnabled();

        $response = $this->withHeader('X-Client-Platform', 'android')
            ->postJson('/api/v2/client/auth/email-code', [
                'email' => 'app-captcha-test@example.com',
                'purpose' => 'login',
            ]);

        $this->assertNotSame(42210, (int) $response->json('code'), 'App 请求不应再被行为验证拦下');
    }

    public function test_client_platform_only_matches_android_header(): void
    {
        $app = Request::create('/api/v2/client/auth/phone-code', 'POST', [], [], [], [
            'HTTP_X_CLIENT_PLATFORM' => 'android',
        ]);
        $browser = Request::create('/api/v2/client/auth/phone-code', 'POST');
        $spoofed = Request::create('/api/v2/client/auth/phone-code', 'POST', [], [], [], [
            'HTTP_X_CLIENT_PLATFORM' => 'web',
        ]);

        $this->assertTrue(ClientPlatform::isApp($app));
        $this->assertFalse(ClientPlatform::isApp($browser), '无请求头即网页端');
        $this->assertFalse(ClientPlatform::isApp($spoofed), '其他取值不视为 App');
    }

    /**
     * 让 GeeTestService 认为人机验证已启用，并让任何校验都失败：
     * 这样只有「跳过了校验」的请求才能走到发送逻辑。
     */
    private function fakeCaptchaEnabled(): void
    {
        $bindingResolver = \Mockery::mock(IntegrationDriverBindingResolver::class);
        $bindingResolver->shouldReceive('captchaDriverKey')->andReturn('geetest');

        $runtime = \Mockery::mock(PluginRuntimeRegistry::class);
        $runtime->shouldIgnoreMissing();
        $runtime->shouldReceive('execute')->andReturnUsing(
            static function (string $domain, string $slug, string $action, array $payload = []): array {
                if ($action === 'captcha.config') {
                    return ['success' => true, 'data' => ['enabled' => true, 'captcha_id' => 'cid-test']];
                }

                if ($action === 'captcha.verify') {
                    return ['success' => false, 'message' => '行为验证未通过，请重试', 'data' => ['verified' => false]];
                }

                return ['success' => false, 'data' => []];
            }
        );

        $this->app->instance(IntegrationDriverBindingResolver::class, $bindingResolver);
        $this->app->instance(PluginRuntimeRegistry::class, $runtime);
        $this->app->forgetInstance(GeeTestService::class);
        $this->app->forgetInstance(LoginRiskControlService::class);
    }
}
