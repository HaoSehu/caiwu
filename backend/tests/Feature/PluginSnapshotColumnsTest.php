<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\PaymentGatewayCode;
use App\Models\GatewayLog;
use App\Models\Payment;
use App\Models\User;
use App\Models\VerificationHistory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 插件数据链快照列（2026_10_26_100000）的落地契约：
 * 迁移必须真的建出快照列，模型必须能读写这些列，投影必须带出插件身份。
 *
 * 使用 DatabaseTransactions：测试库 idc_test 已由 test:prepare 预置并应用最新迁移。
 */
class PluginSnapshotColumnsTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @return list<string>
     */
    private function columns(string $table): array
    {
        $rows = DB::select(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table]
        );

        return array_map(static fn (object $row): string => (string) $row->COLUMN_NAME, $rows);
    }

    #[Test]
    public function payments_has_plugin_and_channel_snapshot_columns(): void
    {
        $columns = $this->columns('payments');

        foreach (['plugin_key', 'plugin_name', 'payment_type', 'option_key', 'option_name'] as $column) {
            $this->assertContains($column, $columns, 'payments 缺少快照列 '.$column);
        }
    }

    #[Test]
    public function refund_verification_and_gateway_log_tables_have_snapshot_columns(): void
    {
        foreach (['plugin_id', 'plugin_key', 'plugin_name', 'gateway_key', 'payment_type'] as $column) {
            $this->assertContains($column, $this->columns('finance_refunds'), 'finance_refunds 缺少快照列 '.$column);
        }

        foreach (['plugin_id', 'plugin_key', 'plugin_name', 'driver_key'] as $column) {
            $this->assertContains($column, $this->columns('verification_histories'), 'verification_histories 缺少快照列 '.$column);
        }

        $this->assertContains('plugin_key', $this->columns('gateway_logs'));
    }

    #[Test]
    public function payment_projection_carries_plugin_identity(): void
    {
        $columns = Payment::gatewayProjectionColumns(['id', 'payment_no', 'gateway']);

        $this->assertContains('gateway_key', $columns);
        foreach (['plugin_id', 'plugin_key', 'plugin_name', 'payment_type', 'option_key', 'option_name'] as $column) {
            $this->assertContains($column, $columns, 'gatewayProjectionColumns 必须带出 '.$column);
        }
        $this->assertNotContains('gateway', $columns);
    }

    #[Test]
    public function payment_persists_channel_snapshot(): void
    {
        $user = User::query()->create([
            'email' => 'plugin-snapshot-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'plugin-snapshot-tester',
        ]);

        $payment = Payment::query()->create([
            'payment_no' => 'PY'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => $user->id,
            'gateway' => PaymentGatewayCode::YIPAY,
            'plugin_key' => 'yipay',
            'plugin_name' => '易支付',
            'payment_type' => 'wxpay',
            'option_key' => 'yipay:wxpay',
            'option_name' => '易支付 - 微信',
            'amount' => 1.00,
            'status' => 0,
        ]);

        $fresh = $payment->fresh();
        $this->assertInstanceOf(Payment::class, $fresh);
        $this->assertSame('yipay', $fresh->plugin_key);
        $this->assertSame('易支付', $fresh->plugin_name);
        $this->assertSame('wxpay', $fresh->payment_type);
        $this->assertSame('yipay:wxpay', $fresh->option_key);
        $this->assertSame('易支付 - 微信', $fresh->option_name);

        // 投影必须能直接还原「哪个插件、哪条通道」，无需再回查 integration_plugins。
        $projected = Payment::query()
            ->whereKey($fresh->id)
            ->first(Payment::gatewayProjectionColumns(['id', 'gateway']));
        $this->assertInstanceOf(Payment::class, $projected);
        $this->assertSame('易支付 - 微信', $projected->option_name);
    }

    #[Test]
    public function verification_history_persists_plugin_snapshot(): void
    {
        $user = User::query()->create([
            'email' => 'verification-snapshot-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'verification-snapshot-tester',
        ]);

        $history = VerificationHistory::query()->create([
            'user_id' => $user->id,
            'real_name' => '快照测试',
            'id_card' => '110101199001011234',
            'verification_status' => 1,
            'verification_biz_code' => 'FACE',
            'verification_type' => 'personal',
            'plugin_id' => 15,
            'plugin_key' => 'baidu_face',
            'plugin_name' => '百度智能云人脸实名认证',
            'driver_key' => 'baidu_face',
            'submitted_at' => now(),
        ]);

        $fresh = $history->fresh();
        $this->assertInstanceOf(VerificationHistory::class, $fresh);
        $this->assertSame('baidu_face', $fresh->plugin_key);
        $this->assertSame('百度智能云人脸实名认证', $fresh->plugin_name);
        $this->assertSame('baidu_face', $fresh->driver_key);
    }

    #[Test]
    public function gateway_log_persists_plugin_key_snapshot(): void
    {
        $log = GatewayLog::query()->create([
            'gateway' => 'alipay_f2f',
            'plugin_key' => 'alipay',
            'action' => 'precreate',
            'out_trade_no' => 'SNAPSHOT'.uniqid(),
            'result_status' => 'success',
        ]);

        $fresh = $log->fresh();
        $this->assertInstanceOf(GatewayLog::class, $fresh);
        $this->assertSame('alipay', $fresh->plugin_key);
        // gateway 仍是插件上报原文，不做归一化（P-10 的语义分工）。
        $this->assertSame('alipay_f2f', $fresh->gateway);
    }

    #[Test]
    public function verification_history_plugin_index_exists(): void
    {
        $rows = DB::select(
            "SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'verification_histories' AND INDEX_NAME = ?",
            ['verification_histories_plugin_submitted_idx']
        );

        $this->assertNotEmpty($rows, 'verification_histories 缺少 (plugin_id, submitted_at) 索引');
    }

    #[Test]
    public function gateway_log_plugin_key_index_exists(): void
    {
        $rows = DB::select(
            "SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gateway_logs' AND INDEX_NAME = ?",
            ['gateway_logs_plugin_key_created_idx']
        );

        $this->assertNotEmpty($rows, 'gateway_logs 缺少 (plugin_key, created_at) 索引');
    }
}
