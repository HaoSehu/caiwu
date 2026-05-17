<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientBlackholeQueryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_client_blackhole_query_returns_partial_result_when_region_sources_partially_unavailable(): void
    {
        Http::fake([
            'http://160.202.238.2:90/blackhole/blackholeapi.php*' => Http::response([], 500),
            'http://160.202.238.2:90/use/find.php*' => Http::response([], 500),
            'http://160.202.238.2:90/through/through.php' => Http::response('', 500),
            'http://160.202.238.2:90/flow/flowapi.php*' => Http::response([], 500),
            'http://160.202.238.2:81/api/blackhole.php*' => Http::response([], 500),
            'https://mianban.288cloud.com/ddos/api/*' => Http::response($this->hongkongEmptyResponse(), 200),
        ]);

        Sanctum::actingAs($this->createClientUser());

        $response = $this->postJson('/api/client/blackhole/query', [
            'ip' => '154.40.43.100',
        ]);

        $response->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.ip', '154.40.43.100')
            ->assertJsonPath('data.blackholed', false)
            ->assertJsonPath('data.overall_status', 'partial')
            ->assertJsonPath('data.sources.hongkong_blackhole.status', 'normal')
            ->assertJsonPath('data.sources.shiyan_blackhole.status', 'unavailable')
            ->assertJsonPath('data.sources.ningbo_blackhole.status', 'unavailable')
            ->assertJsonPath('data.sources.us1_traffic.image_url', 'https://do.yazzi.net/index/history?logo=0&ip=154.40.43.100');

        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/addons'));
    }

    public function test_client_blackhole_query_aggregates_current_81_and_90_region_sources(): void
    {
        Http::fake([
            'http://160.202.238.2:90/blackhole/blackholeapi.php*' => Http::response([
                'found' => true,
                'count' => 2,
                'records' => [[
                    'id' => 20795,
                    'host_id' => 46000,
                    'product_id' => 117,
                    'client_id' => 12227,
                    'host_ip' => '160.202.238.52',
                    'start_time' => 1761827751,
                    'end_time' => 1761828354,
                    'create_time' => 1761828957,
                    'update_time' => 0,
                    'host_name' => 'FX-SY-YG-2#-2F-B-3C-A11',
                    'username' => '深圳市华瑞云网络科技有限公司',
                    'product_name' => '十堰电信机柜B套餐',
                    'domainstatus' => 'Active',
                ]],
            ]),
            'http://160.202.238.2:90/use/find.php*' => Http::response([
                'status' => 200,
                'msg' => '请求成功',
                'data' => [
                    'list' => [
                        [
                            'id' => 61,
                            'name' => 'TCP_syn_10s_10c',
                            'description' => 'TCP 攻击频率限制',
                            'upstream_set_id' => '674c5569e13823a6ae23c2e1',
                            'create_time' => 1772468158,
                            'default_port' => '0-0',
                            'port' => '',
                            'status' => 1,
                            'port_list' => [],
                        ],
                        [
                            'id' => 28,
                            'name' => 'Syn_60s_60c',
                            'description' => 'TCP 攻击限制',
                            'upstream_set_id' => '661f230b8bbb202971f5b21c',
                            'create_time' => 1772468190,
                            'default_port' => '0-0',
                            'port' => '',
                            'status' => 0,
                            'port_list' => [],
                        ],
                    ],
                    'count' => 2,
                    'app_enabled_count' => 1,
                    'app_max' => 5,
                    'apply_rule_id' => 3057,
                    'pass_through' => 0,
                ],
            ]),
            'http://160.202.238.2:90/through/through.php' => Http::response(
                $this->shiyanLayer4Html(),
                200,
                ['Content-Type' => 'text/html; charset=UTF-8']
            ),
            'http://160.202.238.2:90/flow/flowapi.php*' => Http::response([
                'status' => 200,
                'msg' => '请求成功',
                'data' => [
                    'info' => [
                        'username' => '深圳市华瑞云网络科技有限公司',
                        'product_name' => '十堰电信机柜B套餐',
                        'host_ip' => '160.202.238.52',
                        'host_id' => 46000,
                        'client_id' => 12227,
                        'product_id' => 117,
                        'host_name' => 'FX-SY-YG-2#-2F-B-3C-A11',
                        'active_time' => 1751266598,
                    ],
                    'list' => [[
                        'hour' => '09',
                        'minute' => '30',
                        'in_size' => 25000000,
                        'in_drop_size' => 5000000,
                    ]],
                ],
            ]),
            'http://160.202.238.2:81/api/blackhole.php*' => Http::response([
                'code' => 200,
                'message' => '查询成功',
                'data' => [],
            ]),
            'https://mianban.288cloud.com/ddos/api/*' => Http::response($this->hongkongEmptyResponse(), 200),
        ]);

        Sanctum::actingAs($this->createClientUser());

        $response = $this->postJson('/api/client/blackhole/query', [
            'ip' => '160.202.238.52',
        ]);

        $response->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.ip', '160.202.238.52')
            ->assertJsonPath('data.blackholed', true)
            ->assertJsonPath('data.overall_status', 'blackholed')
            ->assertJsonPath('data.sources.shiyan_blackhole.count', 2)
            ->assertJsonPath('data.sources.shiyan_blackhole.records.0.host_ip', '160.202.238.52')
            ->assertJsonPath('data.sources.shiyan_layer7.enabled_count', 1)
            ->assertJsonPath('data.sources.shiyan_layer4.count', 1)
            ->assertJsonPath('data.sources.shiyan_layer4.columns.0', '规则 ID')
            ->assertJsonPath('data.sources.shiyan_flow.metrics.sample_count', 1)
            ->assertJsonPath('data.sources.shiyan_flow.samples.0.before_mbps', 200)
            ->assertJsonPath('data.sources.shiyan_flow.samples.0.after_mbps', 160)
            ->assertJsonPath('data.sources.ningbo_blackhole.status', 'normal')
            ->assertJsonPath('data.sources.hongkong_blackhole.status', 'normal');

        $payload = $response->json();
        $this->assertSame('屏蔽 UDP', data_get($payload, 'data.sources.shiyan_layer4.list.0.规则名称'));

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'POST'
                && $request->url() === 'http://160.202.238.2:90/through/through.php'
                && str_contains($request->body(), 'action=search')
                && str_contains($request->body(), 'search_ip=160.202.238.52');
        });

        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/addons'));
    }

    public function test_client_can_submit_ningbo_whitelist_request(): void
    {
        Http::fake([
            'http://160.202.238.2:81/api/gb.php*' => Http::response([
                'code' => 200,
                'message' => '过白成功',
                'data' => null,
            ]),
        ]);

        Sanctum::actingAs($this->createClientUser());

        $response = $this->postJson('/api/client/blackhole/ningbo/whitelist', [
            'ip' => '114.66.31.123',
            'domain' => 'example.com',
        ]);

        $response->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('message', '过白成功')
            ->assertJsonPath('data.success', true)
            ->assertJsonPath('data.business_code', 200);

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'GET'
                && str_starts_with($request->url(), 'http://160.202.238.2:81/api/gb.php')
                && $request['ip'] === '114.66.31.123'
                && $request['name'] === 'example.com';
        });
    }

    public function test_client_can_toggle_shiyan_layer7_rule(): void
    {
        Http::fake([
            'http://160.202.238.2:90/use/request.php*' => Http::response([
                'status' => 200,
                'msg' => '请求成功',
                'data' => null,
            ]),
        ]);

        Sanctum::actingAs($this->createClientUser());

        $response = $this->postJson('/api/client/blackhole/shiyan/layer7/toggle', [
            'ip' => '160.202.238.52',
            'rule_id' => 61,
            'enabled' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('message', '请求成功')
            ->assertJsonPath('data.success', true)
            ->assertJsonPath('data.rule_id', 61)
            ->assertJsonPath('data.enabled', true);

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'GET'
                && str_starts_with($request->url(), 'http://160.202.238.2:90/use/request.php')
                && $request['ip'] === '160.202.238.52'
                && (string) $request['id'] === '61'
                && (string) $request['status'] === '1';
        });
    }

    public function test_client_can_add_shiyan_layer4_rule(): void
    {
        Http::fake([
            'http://160.202.238.2:90/through/through.php' => Http::response(
                '<html><body><div class="feedback ok">新增成功</div></body></html>',
                200,
                ['Content-Type' => 'text/html; charset=UTF-8']
            ),
        ]);

        Sanctum::actingAs($this->createClientUser());

        $response = $this->postJson('/api/client/blackhole/shiyan/layer4/add', [
            'ip' => '160.202.238.52',
            'mode' => 1,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('message', '新增成功')
            ->assertJsonPath('data.success', true)
            ->assertJsonPath('data.mode', 1);

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'POST'
                && $request->url() === 'http://160.202.238.2:90/through/through.php'
                && str_contains($request->body(), 'action=add')
                && str_contains($request->body(), 'ip=160.202.238.52')
                && str_contains($request->body(), 'mode=1');
        });
    }

    public function test_client_can_delete_shiyan_layer4_rule(): void
    {
        Http::fake([
            'http://160.202.238.2:90/through/through.php' => Http::response(
                '<html><body><div class="feedback ok">删除成功</div></body></html>',
                200,
                ['Content-Type' => 'text/html; charset=UTF-8']
            ),
        ]);

        Sanctum::actingAs($this->createClientUser());

        $response = $this->postJson('/api/client/blackhole/shiyan/layer4/delete', [
            'ip' => '160.202.238.52',
            'rule_id' => '1001',
        ]);

        $response->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('message', '删除成功')
            ->assertJsonPath('data.success', true)
            ->assertJsonPath('data.rule_id', '1001');

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'POST'
                && $request->url() === 'http://160.202.238.2:90/through/through.php'
                && str_contains($request->body(), 'action=delete')
                && str_contains($request->body(), 'ip=160.202.238.52')
                && str_contains($request->body(), 'id=1001');
        });
    }

    private function hongkongEmptyResponse(): array
    {
        return [
            'status' => 200,
            'msg' => '暂无黑洞记录',
            'data' => [],
            'table' => '',
        ];
    }

    private function shiyanLayer4Html(): string
    {
        return <<<'HTML'
        <!DOCTYPE html>
        <html lang="zh-CN">
          <body>
            <div>查询成功</div>
            <div>共 1 条记录</div>
            <table>
              <thead>
                <tr>
                  <th>规则 ID</th>
                  <th>规则名称</th>
                  <th>类型</th>
                  <th>动作</th>
                  <th>方向</th>
                  <th>状态</th>
                  <th>主机 IP</th>
                  <th>操作</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td>1001</td>
                  <td>屏蔽 UDP</td>
                  <td>UDP</td>
                  <td>屏蔽</td>
                  <td>入向</td>
                  <td>启用</td>
                  <td>160.202.238.52</td>
                  <td>删除</td>
                </tr>
              </tbody>
            </table>
          </body>
        </html>
        HTML;
    }

    private function createClientUser(): User
    {
        return User::query()->create([
            'email' => 'blackhole-client-'.bin2hex(random_bytes(4)).'@example.com',
            'password' => 'Temp@123456',
            'phone' => '13'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'status' => 1,
            'nickname' => 'Blackhole Client',
            'real_name' => '',
            'id_card' => '',
            'verification_status' => 0,
            'verification_message' => '',
            'verification_certify_id' => null,
            'member_level_id' => null,
            'total_sales_amount' => '0.00',
            'referrer_user_id' => null,
            'verified_at' => null,
        ]);
    }
}
