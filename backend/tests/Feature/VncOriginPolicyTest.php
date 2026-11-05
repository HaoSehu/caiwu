<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\ClientServiceConsole\VncOriginPolicy;
use App\Support\PublicUrl;
use Tests\TestCase;

/**
 * VNC Relay Origin 判定（VncOriginPolicy）：
 * - 浏览器端只能使用 token 签发时固化的 console 域；
 * - 移动端 App 使用 allowed_app_origins 白名单（含裸值 caiwu-app 的 scheme 补齐）；
 * - 其它 Origin 一律拒绝；非法输入不抛异常。
 */
class VncOriginPolicyTest extends TestCase
{
    private VncOriginPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new VncOriginPolicy;
    }

    public function test_console_origin_matches_token_payload(): void
    {
        $params = ['allowed_origin' => 'https://console.example.com'];

        $this->assertTrue($this->policy->isAllowed('https://console.example.com', $params));
        // 大小写不敏感
        $this->assertTrue($this->policy->isAllowed('HTTPS://Console.Example.com', $params));
    }

    public function test_default_console_origin_used_when_payload_missing(): void
    {
        $console = rtrim((string) PublicUrl::console(), '/');

        $this->assertTrue($this->policy->isAllowed($console, []));
    }

    public function test_app_origin_allowed_from_token_payload(): void
    {
        $params = [
            'allowed_origin' => 'https://console.example.com',
            'allowed_app_origins' => ['caiwu-app'],
        ];

        // 裸值配置补 https scheme 后放行
        $this->assertTrue($this->policy->isAllowed('https://caiwu-app', $params));
        // 浏览器 console 域仍然放行
        $this->assertTrue($this->policy->isAllowed('https://console.example.com', $params));
    }

    public function test_app_origin_from_config_when_payload_missing(): void
    {
        config(['idc.vnc_relay.app_origins' => ['caiwu-app']]);

        $this->assertTrue($this->policy->isAllowed('https://caiwu-app', [
            'allowed_origin' => 'https://console.example.com',
        ]));
    }

    public function test_app_origin_with_explicit_scheme_and_port(): void
    {
        $params = [
            'allowed_origin' => 'https://console.example.com',
            'allowed_app_origins' => ['https://app.example.com:8443'],
        ];

        $this->assertTrue($this->policy->isAllowed('https://app.example.com:8443', $params));
        // 端口不一致必须拒绝
        $this->assertFalse($this->policy->isAllowed('https://app.example.com', $params));
    }

    public function test_unknown_origin_is_rejected(): void
    {
        $params = [
            'allowed_origin' => 'https://console.example.com',
            'allowed_app_origins' => ['caiwu-app'],
        ];

        $this->assertFalse($this->policy->isAllowed('https://evil.example.com', $params));
        $this->assertFalse($this->policy->isAllowed('', $params));
        $this->assertFalse($this->policy->isAllowed('not-a-origin', $params));
    }

    public function test_missing_origin_header_is_rejected(): void
    {
        $this->assertFalse($this->policy->isAllowed('', ['allowed_origin' => 'https://console.example.com']));
        $this->assertFalse($this->policy->isAllowed('   ', ['allowed_origin' => 'https://console.example.com']));
    }

    public function test_normalize_origin_strips_default_ports(): void
    {
        $this->assertSame('https://console.example.com', $this->policy->normalizeOrigin('https://console.example.com:443'));
        $this->assertSame('http://console.example.com', $this->policy->normalizeOrigin('http://console.example.com:80'));
        $this->assertSame('https://console.example.com:8443', $this->policy->normalizeOrigin('https://console.example.com:8443'));
        $this->assertSame('', $this->policy->normalizeOrigin('console.example.com'));
    }
}
