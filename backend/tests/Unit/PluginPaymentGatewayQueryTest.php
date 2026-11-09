<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Integrations\Plugins\Adapters\PluginPaymentGateway;
use App\Services\Integrations\Plugins\PluginManifest;
use App\Services\Integrations\Plugins\PluginRuntimeRegistry;
use Mockery;
use Tests\TestCase;

class PluginPaymentGatewayQueryTest extends TestCase
{
    public function test_query_propagates_verified_flag_from_plugin_payload(): void
    {
        $gateway = $this->gatewayWithQueryData('PAY-1', [
            'trade_status' => 'TRADE_SUCCESS',
            'trade_no' => 'ALI-1',
            'out_trade_no' => 'PAY-1',
            'total_amount' => '10.00',
            'verified' => true,
            'raw' => ['code' => '10000'],
        ]);

        $result = $gateway->query('PAY-1');

        $this->assertTrue($result->verified, '插件返回的 verified 必须由适配层透传');
        $this->assertTrue($result->toArray()['verified']);
    }

    public function test_query_defaults_to_unverified_when_plugin_omits_flag(): void
    {
        $gateway = $this->gatewayWithQueryData('PAY-2', [
            'trade_status' => 'TRADE_SUCCESS',
            'trade_no' => 'ALI-2',
            'out_trade_no' => 'PAY-2',
            'total_amount' => '10.00',
            'raw' => ['code' => '10000'],
        ]);

        $result = $gateway->query('PAY-2');

        $this->assertFalse($result->verified, '插件未声明 verified 时必须按未验签处理');
        $this->assertFalse($result->toArray()['verified']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function gatewayWithQueryData(string $outTradeNo, array $data): PluginPaymentGateway
    {
        $runtime = Mockery::mock(PluginRuntimeRegistry::class);
        $runtime->shouldReceive('execute')
            ->once()
            ->with('payment', 'alipay', 'payment.query', ['out_trade_no' => $outTradeNo])
            ->andReturn(['success' => true, 'data' => $data]);

        $manifest = new PluginManifest(
            domain: 'payment',
            slug: 'alipay',
            key: 'alipay',
            name: '支付宝',
            version: '1.0.0',
            entryClass: 'Stub',
            providerClass: null,
            capabilities: [],
            configSchema: [],
            basePath: '',
        );

        return new PluginPaymentGateway($runtime, $manifest);
    }
}
