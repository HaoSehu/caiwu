<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Support\AuditParamRedactor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 审计落库前的凭据剔除（B-07 修复回归）：
 * 网关/插件配置里的私钥、公钥、密钥必须与密码同等级剔除，
 * 业务字段（app_id / notify_url / nickname）原样保留——与「日志不脱敏」不冲突。
 */
class AuditParamRedactorTest extends TestCase
{
    use DatabaseTransactions;

    public function test_gateway_credentials_are_redacted_at_any_depth(): void
    {
        $input = [
            'merchant_private_key' => 'MIIEvPLAINTEXT',
            'platform_public_key' => 'PUBKEY',
            'config' => [
                'app_id' => '2021000000',
                'secret_key' => 'SK',
                'wx_secret_v3' => 'V3',
                'sign_pem' => 'PEM',
                'notify_url' => 'https://example.test/notify',
            ],
            'nickname' => '正常业务字段',
        ];

        $out = AuditParamRedactor::redact($input, 'integration_plugin');

        $this->assertSame('[REDACTED]', $out['merchant_private_key']);
        $this->assertSame('[REDACTED]', $out['platform_public_key']);
        $this->assertSame('[REDACTED]', $out['config']['secret_key']);
        $this->assertSame('[REDACTED]', $out['config']['wx_secret_v3']);
        $this->assertSame('[REDACTED]', $out['config']['sign_pem']);

        // 业务字段不得误伤。
        $this->assertSame('2021000000', $out['config']['app_id']);
        $this->assertSame('https://example.test/notify', $out['config']['notify_url']);
        $this->assertSame('正常业务字段', $out['nickname']);
    }

    public function test_business_fields_containing_key_or_secret_are_not_redacted(): void
    {
        // 词根按「下划线段」判定，不做任意子串包含：secretary / keyword / monkey 不得误伤。
        $out = AuditParamRedactor::redact([
            'keyword' => '搜索关键词',
            'monkey' => 'abc',
            'key_metric' => 'x',
            'secretary' => 'name',
            'hotkey' => 'F5',
        ], 'product');

        $this->assertSame('搜索关键词', $out['keyword']);
        $this->assertSame('abc', $out['monkey']);
        $this->assertSame('x', $out['key_metric']);
        $this->assertSame('name', $out['secretary']);
        $this->assertSame('F5', $out['hotkey']);
    }

    public function test_scrub_command_redacts_legacy_rows_without_deleting_them(): void
    {
        $context = [
            'params' => [
                'config' => [
                    'merchant_private_key' => 'LEGACYPRIVATE',
                    'app_id' => '2021000000',
                ],
            ],
        ];

        $log = ActivityLog::query()->create([
            'actor_type' => 'admin',
            'actor_id' => 1,
            'actor_name' => 'admin',
            'module' => 'integration_plugin',
            'action' => 'integration_plugin.config.update',
            'description' => '[integration_plugin] integration_plugin.config.update',
            'context' => $context,
        ]);

        $this->artisan('activity-log:scrub-secrets', ['--execute' => true])->assertExitCode(0);

        $log->refresh();
        $this->assertSame('2021000000', (string) data_get((array) $log->context, 'params.config.app_id'));
        $this->assertSame('[REDACTED]', (string) data_get((array) $log->context, 'params.config.merchant_private_key'));
        // 不删行、不改其它字段。
        $this->assertSame('[integration_plugin] integration_plugin.config.update', (string) $log->description);
    }
}
