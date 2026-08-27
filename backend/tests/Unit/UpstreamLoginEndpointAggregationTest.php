<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Upstream\Contracts\ProvidesUpstreamLoginEndpoints;
use App\Services\Upstream\Drivers\HostingPanelApi\HostingPanelApiTransport;
use ReflectionMethod;
use Tests\TestCase;

/**
 * 上游插件登录端点声明聚合的契约回归：
 * 纯 tag 绑定不出现在 bound()，聚合助手必须直接消费 tagged()，
 * 防止守卫写法把声明集合静默清空（历史缺陷见 ZJMF 解耦迁移工件）。
 */
class UpstreamLoginEndpointAggregationTest extends TestCase
{
    public function test_policy_contract_is_satisfied_by_fake_declaration(): void
    {
        $policy = new class implements ProvidesUpstreamLoginEndpoints
        {
            /**
             * @return list<string>
             */
            public function loginEndpointUris(): array
            {
                return ['/fake_login_api', '  '];
            }
        };

        $this->app->tag([$policy::class], 'upstream.login_endpoints');
        $this->app->instance($policy::class, $policy);

        $transport = new HostingPanelApiTransport;
        $method = new ReflectionMethod($transport, 'declaredUpstreamLoginEndpoints');
        $method->setAccessible(true);

        $this->assertSame(['/fake_login_api'], $method->invoke($transport));
    }

    public function test_unregistered_tag_yields_empty_endpoint_set(): void
    {
        $transport = new HostingPanelApiTransport;
        $method = new ReflectionMethod($transport, 'isLoginEndpoint');
        $method->setAccessible(true);

        // 未登记任何插件声明时仅保留传输层自有登录端点，其余一律不放行
        $this->assertTrue($method->invoke($transport, '/v1/login_api'));
        $this->assertFalse($method->invoke($transport, '/other_plugin_login'));
    }

    public function test_duplicate_declarations_are_deduplicated(): void
    {
        $first = new class implements ProvidesUpstreamLoginEndpoints
        {
            public function loginEndpointUris(): array
            {
                return ['/fake_login_api', '/Shared_Login_Api'];
            }
        };
        $second = new class implements ProvidesUpstreamLoginEndpoints
        {
            public function loginEndpointUris(): array
            {
                return [' /shared_login_api ', '/fake_login_api '];
            }
        };

        $this->app->tag([$first::class, $second::class], 'upstream.login_endpoints');
        $this->app->instance($first::class, $first);
        $this->app->instance($second::class, $second);

        $this->assertSame(
            ['/fake_login_api', '/shared_login_api'],
            $this->invokeAggregation()
        );
    }

    public function test_non_contract_implementations_are_skipped(): void
    {
        $policy = new class implements ProvidesUpstreamLoginEndpoints
        {
            public function loginEndpointUris(): array
            {
                return ['/fake_login_api'];
            }
        };
        $stray = new class
        {
            public function loginEndpointUris(): array
            {
                return ['/must_not_be_collected'];
            }
        };

        $this->app->tag([$policy::class, $stray::class], 'upstream.login_endpoints');
        $this->app->instance($policy::class, $policy);
        $this->app->instance($stray::class, $stray);

        $this->assertSame(['/fake_login_api'], $this->invokeAggregation());
    }

    public function test_failing_policy_is_skipped_without_breaking_others(): void
    {
        $healthy = new class implements ProvidesUpstreamLoginEndpoints
        {
            public function loginEndpointUris(): array
            {
                return ['/healthy_login_api'];
            }
        };
        $broken = new class implements ProvidesUpstreamLoginEndpoints
        {
            public function loginEndpointUris(): array
            {
                throw new \RuntimeException('policy declaration unavailable');
            }
        };

        $this->app->tag([$broken::class, $healthy::class], 'upstream.login_endpoints');
        $this->app->instance($broken::class, $broken);
        $this->app->instance($healthy::class, $healthy);

        // 单个 policy 声明异常只跳过该 policy，不得打断其余声明与上游请求
        $this->assertSame(['/healthy_login_api'], $this->invokeAggregation());
    }

    /**
     * @return list<string>
     */
    private function invokeAggregation(): array
    {
        $transport = new HostingPanelApiTransport;
        $method = new ReflectionMethod($transport, 'declaredUpstreamLoginEndpoints');
        $method->setAccessible(true);

        return $method->invoke($transport);
    }
}
