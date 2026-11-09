<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ActivityLog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * activity-log:scrub-secrets 的 module 匹配（M-43 修复回归）。
 *
 * LogOperation 按请求路径首段取 module，管理端插件路由
 * /api/v2/admin/integration-plugins/... 实际落库值是 `integration-plugins`；
 * 修复前命令只查 `integration_plugin`，对真实数据永远 0 行。两种写法都必须命中。
 */
class FixScrubActivityLogModuleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_both_module_spellings_are_scrubbed(): void
    {
        $hyphenated = $this->makeLog('integration-plugins', 'MERCHANT-KEY-PLAIN');
        $underscored = $this->makeLog('integration_plugin', 'LEGACY-PRIVATE-PLAIN');
        $unrelated = $this->makeLog('system', 'SYSTEM-SECRET-PLAIN');

        $this->artisan('activity-log:scrub-secrets', ['--execute' => true])->assertExitCode(0);

        $hyphenated->refresh();
        $underscored->refresh();
        $unrelated->refresh();

        $this->assertSame(
            '[REDACTED]',
            (string) data_get((array) $hyphenated->context, 'params.config.merchant_private_key'),
            '真实写入路径的 module=integration-plugins 必须被清洗',
        );
        $this->assertSame(
            '[REDACTED]',
            (string) data_get((array) $underscored->context, 'params.config.merchant_private_key'),
            '历史下划线形式仍需兼容',
        );
        // 范围锁定：其它 module 的凭据不归本命令处理，保持原样。
        $this->assertSame(
            'SYSTEM-SECRET-PLAIN',
            (string) data_get((array) $unrelated->context, 'params.config.merchant_private_key'),
        );
    }

    public function test_dry_run_reports_matches_without_writing(): void
    {
        $log = $this->makeLog('integration-plugins', 'DRY-RUN-PLAIN');

        $this->artisan('activity-log:scrub-secrets', ['--json' => true])->assertExitCode(0);

        $log->refresh();
        $this->assertSame(
            'DRY-RUN-PLAIN',
            (string) data_get((array) $log->context, 'params.config.merchant_private_key'),
            '默认 dry-run 不得写库',
        );
    }

    private function makeLog(string $module, string $credentialValue): ActivityLog
    {
        return ActivityLog::query()->create([
            'actor_type' => 'admin',
            'actor_id' => 1,
            'actor_name' => 'admin',
            'module' => $module,
            'action' => $module.'.config.update',
            'description' => '['.$module.'] config update',
            'context' => [
                'params' => [
                    'config' => [
                        'merchant_private_key' => $credentialValue,
                        'app_id' => '2021000000',
                    ],
                ],
            ],
        ]);
    }
}
