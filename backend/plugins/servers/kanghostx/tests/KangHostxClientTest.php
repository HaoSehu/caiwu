<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\BusinessException;
use App\Models\Supplier;
use Caiwu\Plugins\Servers\KangHostx\Lib\KangHostxClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

// 插件类由运行时的 PluginFileLoader 按 require 加载，测试中需手动引入
require_once __DIR__.'/../lib/KangHostxClient.php';

/**
 * 康乐面板（kanghostx）客户端契约回归。
 *
 * 面板调用最容易出的两类事故：
 * 1. 签名错（c/a/r/s 四参数任一漏拼）会被面板当成未授权，但错误文案只报「失败」；
 * 2. 面板地址或访问密钥缺失时如果直接发请求，会打到空的 host 上并超时。
 * 这里把签名算法、请求拼装、异常分支与成功判定全部钉住。
 */
class KangHostxClientTest extends TestCase
{
    private function supplier(array $attributes = []): Supplier
    {
        $supplier = new Supplier;
        // api_url / api_username / api_key 不是表列，由绑定解析器运行时注入
        $supplier->forceFill(array_merge([
            'id' => 7,
            'api_url' => 'https://panel.example.test',
            'api_key' => 'kang-hostx-access-hash',
        ], $attributes));

        return $supplier;
    }

    public function test_sign_is_md5_of_action_hash_and_nonce(): void
    {
        $client = new KangHostxClient;

        $this->assertSame(
            md5('info'.'kang-hostx-access-hash'.'123456'),
            $client->sign('info', 'kang-hostx-access-hash', '123456')
        );
    }

    public function test_info_request_contains_signed_query(): void
    {
        Http::fake([
            'panel.example.test/*' => Http::response(['result' => 200, 'msg' => 'ok'], 200),
        ]);

        $response = (new KangHostxClient)->info($this->supplier());

        $this->assertSame(200, $response['result']);

        Http::assertSent(function (Request $request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return $request->method() === 'GET'
                && $request->url() !== ''
                && ($query['c'] ?? '') === 'whm'
                && ($query['a'] ?? '') === 'info'
                && ($query['json'] ?? '') === '1'
                && ($query['s'] ?? '') === md5('info'.'kang-hostx-access-hash'.(string) ($query['r'] ?? ''));
        });
    }

    public function test_missing_access_hash_is_rejected_before_request(): void
    {
        Http::fake();

        try {
            (new KangHostxClient)->info($this->supplier(['api_key' => null]));
            $this->fail('缺少访问密钥时应当拒绝');
        } catch (BusinessException $exception) {
            $this->assertSame('康乐访问密钥未配置', $exception->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_missing_api_url_falls_back_to_provider_config(): void
    {
        Http::fake([
            'panel.example.test/*' => Http::response(['result' => 200], 200),
        ]);

        $supplier = $this->supplier(['api_url' => null, 'provider_config' => ['api_url' => 'https://panel.example.test']]);

        $this->assertSame(200, (new KangHostxClient)->info($supplier)['result']);
    }

    public function test_invalid_api_url_throws_business_exception(): void
    {
        Http::fake();

        try {
            (new KangHostxClient)->info($this->supplier(['api_url' => 'not-a-url']));
            $this->fail('非法面板地址应当拒绝');
        } catch (BusinessException $exception) {
            $this->assertSame('康乐面板接口地址未配置或格式不正确', $exception->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_non_http_scheme_is_rejected(): void
    {
        Http::fake();

        try {
            (new KangHostxClient)->info($this->supplier(['api_url' => 'ftp://panel.example.test']));
            $this->fail('非 HTTP(S) 协议应当拒绝');
        } catch (BusinessException $exception) {
            $this->assertSame('康乐面板接口地址仅支持 HTTP 或 HTTPS', $exception->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_http_error_status_throws_business_exception(): void
    {
        Http::fake([
            'panel.example.test/*' => Http::response('bad gateway', 502),
        ]);

        try {
            (new KangHostxClient)->info($this->supplier());
            $this->fail('非 2xx 应当抛异常');
        } catch (BusinessException $exception) {
            $this->assertSame('康乐接口请求失败，请检查面板地址和网络连通性', $exception->getMessage());
        }
    }

    public function test_non_json_body_throws_business_exception(): void
    {
        Http::fake([
            'panel.example.test/*' => Http::response('<html>fatal</html>', 200),
        ]);

        try {
            (new KangHostxClient)->info($this->supplier());
            $this->fail('非 JSON 响应应当抛异常');
        } catch (BusinessException $exception) {
            $this->assertSame('康乐接口返回异常，未解析到有效数据', $exception->getMessage());
        }
    }

    public function test_bom_prefixed_json_body_is_decoded(): void
    {
        Http::fake([
            'panel.example.test/*' => Http::response("\xEF\xBB\xBF".json_encode(['result' => 200]), 200),
        ]);

        $this->assertSame(200, (new KangHostxClient)->info($this->supplier())['result']);
    }

    public function test_endpoint_appends_api_index_php_once(): void
    {
        Http::fake([
            'panel.example.test/*' => Http::response(['result' => 200], 200),
        ]);

        (new KangHostxClient)->info($this->supplier(['api_url' => 'https://panel.example.test/api/index.php']));

        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://panel.example.test/api/index.php?'));
    }

    public function test_assert_success_accepts_result_or_status_or_code(): void
    {
        $client = new KangHostxClient;

        $client->assertSuccess(['result' => 200], '开通');
        $client->assertSuccess(['status' => 200], '开通');
        $client->assertSuccess(['code' => 200], '开通');

        $this->expectNotToPerformAssertions();
    }

    public function test_assert_success_throws_with_upstream_message(): void
    {
        try {
            (new KangHostxClient)->assertSuccess(['result' => 500, 'msg' => '  主机名已存在  '], '开通主机');
            $this->fail('失败码应当抛异常');
        } catch (BusinessException $exception) {
            $this->assertSame('开通主机失败：主机名已存在', $exception->getMessage());
        }
    }

    public function test_assert_success_uses_fallback_message_for_empty_500(): void
    {
        try {
            (new KangHostxClient)->assertSuccess(['result' => 500], '开通主机');
            $this->fail('失败码应当抛异常');
        } catch (BusinessException $exception) {
            $this->assertSame('开通主机失败：主机名已存在或参数不正确', $exception->getMessage());
        }
    }

    public function test_assert_success_serializes_array_message(): void
    {
        try {
            (new KangHostxClient)->assertSuccess(['result' => 400, 'msg' => ['error' => '参数缺失']], '开通主机');
            $this->fail('失败码应当抛异常');
        } catch (BusinessException $exception) {
            $this->assertStringContainsString('参数缺失', $exception->getMessage());
        }
    }

    public function test_panel_login_url_uses_root_only(): void
    {
        $client = new KangHostxClient;

        $this->assertSame(
            'https://panel.example.test/vhost/index.php?c=session&a=login',
            $client->panelLoginUrl($this->supplier(['api_url' => 'https://panel.example.test/api/index.php']))
        );
        $this->assertSame(
            'https://panel.example.test:8443/vhost/index.php?c=session&a=login',
            $client->panelLoginUrl($this->supplier(['api_url' => 'https://panel.example.test:8443/api/index.php']))
        );
    }

    public function test_empty_action_is_rejected(): void
    {
        Http::fake();

        try {
            (new KangHostxClient)->request($this->supplier(), '  ');
            $this->fail('空动作应当拒绝');
        } catch (BusinessException $exception) {
            $this->assertSame('康乐接口动作不能为空', $exception->getMessage());
        }

        Http::assertNothingSent();
    }
}
