<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\IntegrationPlugin;
use App\Models\IntegrationPluginRuntimeLog;
use App\Services\Integrations\Plugins\PluginConfigRepository;
use App\Services\Integrations\Plugins\PluginInstaller;
use App\Services\Integrations\Plugins\PluginRuntimeRegistry;
use App\Services\Integrations\Plugins\PluginScanner;
use App\Services\System\AdminLogV2QueryService;
use App\Support\AuditParamRedactor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

/**
 * 插件运行日志凭据脱敏（M-44 修复回归）。
 *
 * payload/context 里的 api_key / web_session_cookie 等价于账号密码，
 * 落库与详情读回都必须经过 AuditParamRedactor 掩码；visitor 默认角色带
 * log.list，不能靠「管理端才看日志」兜底。
 */
class FixPluginRuntimeLogRedactionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_credentials_in_context_are_masked_before_persisting(): void
    {
        $plugin = $this->enabledDemoPay();
        $apiKey = 'sk-live-1234567890abcdef';
        $cookie = 'sessionid=abcdef1234567890abcdef';

        $response = app(PluginRuntimeRegistry::class)->execute(
            'payment',
            'demo_pay',
            'payment.precreate',
            ['out_trade_no' => 'T-1', 'amount' => 1.0, 'subject' => 'test'],
            [
                'binding_id' => 77,
                'api_key' => $apiKey,
                'web_session_cookie' => $cookie,
                'supplier' => [
                    'name' => '供应商 A',
                    'api_key' => $apiKey,
                ],
            ],
        );

        $this->assertTrue((bool) ($response['success'] ?? false));

        $log = IntegrationPluginRuntimeLog::query()
            ->where('plugin_id', (int) $plugin->id)
            ->where('action', 'payment.precreate')
            ->first();
        $this->assertNotNull($log, '非探测动作必须落运行日志');

        $meta = (array) $log->request_meta_json;
        $context = (array) ($meta['context'] ?? []);

        $this->assertNotSame($apiKey, (string) ($context['api_key'] ?? ''), 'api_key 不得明文落库');
        $this->assertNotSame($cookie, (string) ($context['web_session_cookie'] ?? ''), 'web_session_cookie 不得明文落库');
        $this->assertStringStartsWith('sk-l', (string) $context['api_key'], '保留前 4 位便于定位');
        $this->assertStringEndsWith('cdef', (string) $context['api_key'], '保留后 4 位便于定位');
        $this->assertNotSame($apiKey, (string) data_get($context, 'supplier.api_key'), '嵌套凭据同样掩码');

        // 非凭据业务字段原样保留（日志仍需完整业务信息）。
        $this->assertSame(77, $context['binding_id']);
        $this->assertSame('供应商 A', (string) data_get($context, 'supplier.name'));
        $this->assertSame('T-1', (string) data_get($meta, 'payload.out_trade_no'));

        // 原始字符串已脱敏：整个库内 JSON 不含明文凭据。
        $raw = (string) DB::table('integration_plugin_runtime_logs')->where('id', $log->id)->value('request_meta_json');
        $this->assertStringNotContainsString($apiKey, $raw);
        $this->assertStringNotContainsString($cookie, $raw);
    }

    public function test_credentials_on_objects_are_masked_after_normalization(): void
    {
        $registry = app(PluginRuntimeRegistry::class);

        // 对象先由 jsonSafeValue 归一成 properties 快照，再脱敏；public 凭据属性必须被覆盖。
        $supplier = new \stdClass;
        $supplier->api_key = 'sk-object-1234567890abcd';
        $supplier->web_session_cookie = 'sid=object-cookie-abcdefgh';
        $supplier->name = '供应商 B';

        $method = new ReflectionMethod(PluginRuntimeRegistry::class, 'runtimeMeta');
        $method->setAccessible(true);

        $meta = $method->invoke($registry, [
            'payload' => ['out_trade_no' => 'T-3'],
            'context' => [
                'supplier' => $supplier,
                'binding' => ['web_session_cookie' => 'sid=binding-cookie-12345678', 'provider_key' => 'zjmf'],
            ],
        ], true);

        $encoded = (string) json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->assertStringNotContainsString('sk-object-1234567890abcd', $encoded, '对象快照里的凭据属性不得明文进入 meta');
        $this->assertStringNotContainsString('sid=object-cookie-abcdefgh', $encoded);
        $this->assertStringNotContainsString('sid=binding-cookie-12345678', $encoded, '嵌套数组里的凭据同样掩码');
        $this->assertStringContainsString('T-3', $encoded, '业务字段不得误伤');
        $this->assertStringContainsString('供应商 B', $encoded, '对象的普通属性原样保留');
    }

    public function test_runtime_detail_masks_legacy_plaintext_rows(): void
    {
        $plugin = $this->enabledDemoPay();
        $apiKey = 'legacy-api-key-plaintext-0001';

        // 模拟修复前的历史行：写库侧未脱敏。
        $log = IntegrationPluginRuntimeLog::query()->create([
            'trace_id' => 'trace-legacy',
            'domain' => 'payment',
            'plugin_id' => (int) $plugin->id,
            'plugin_key' => 'demo_pay',
            'slug' => 'demo_pay',
            'action' => 'payment.query',
            'status' => 'success',
            'duration_ms' => 5,
            'request_meta_json' => [
                'payload' => ['out_trade_no' => 'T-2'],
                'context' => [
                    'api_key' => $apiKey,
                    'web_session_cookie' => 'cookie-plaintext-value-0002',
                ],
            ],
            'created_at' => now(),
        ]);

        $detail = app(AdminLogV2QueryService::class)->detail('runtime', 'plugin-runtime-'.$log->id);

        $requestMeta = (array) ($detail['context']['request_meta'] ?? []);
        $context = (array) ($requestMeta['context'] ?? []);

        $this->assertNotSame($apiKey, (string) ($context['api_key'] ?? ''), '详情读回必须对历史明文再脱敏');
        $this->assertStringStartsWith('lega', (string) $context['api_key']);
        $this->assertStringEndsWith('0001', (string) $context['api_key']);
        $this->assertStringNotContainsString('cookie-plaintext-value-0002', (string) ($context['web_session_cookie'] ?? ''));
        $this->assertSame('T-2', (string) data_get($requestMeta, 'payload.out_trade_no'), '业务字段不得误伤');
    }

    public function test_redactor_keeps_business_fields_and_masks_credential_families(): void
    {
        $masked = AuditParamRedactor::maskCredentialFields([
            'api_key' => 'sk-abcdefghijklmn',
            'jwt_token' => 'eyJhbGciOiJIUzI1NiJ9.payload.sig',
            'web_session_cookie' => 'sid=abcdefghijklmn',
            'keyword' => '搜索关键词',
            'key_metric' => 'x',
            'short' => 'abc',
        ]);

        $this->assertStringStartsWith('sk-a', (string) $masked['api_key']);
        $this->assertStringEndsWith('klmn', (string) $masked['api_key']);
        $this->assertNotSame('eyJhbGciOiJIUzI1NiJ9.payload.sig', (string) $masked['jwt_token']);
        $this->assertStringStartsWith('eyJh', (string) $masked['jwt_token']);
        $this->assertStringEndsWith('.sig', (string) $masked['jwt_token']);
        // 业务字段不得误伤（与 AuditParamRedactor 既有口径一致）。
        $this->assertSame('搜索关键词', $masked['keyword']);
        $this->assertSame('x', $masked['key_metric']);
        $this->assertSame('abc', $masked['short']);
    }

    private function enabledDemoPay(): IntegrationPlugin
    {
        $installer = app(PluginInstaller::class);
        $scanner = app(PluginScanner::class);

        $plugin = $installer->install('payment', 'demo_pay', allowDemo: true);
        $manifest = $scanner->requireManifest('payment', 'demo_pay');
        app(PluginConfigRepository::class)->save($plugin, $manifest, ['merchant_id' => 'm-001']);
        $installer->enable($plugin->fresh() ?? $plugin, allowDemo: true);

        return $plugin->fresh() ?? $plugin;
    }
}
