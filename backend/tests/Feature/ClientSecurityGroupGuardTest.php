<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\ServiceStatus;
use App\Exceptions\BusinessException;
use App\Models\Product;
use App\Models\Service;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ClientServiceConsole\ServiceDetailService;
use App\Services\ClientServiceConsole\ServiceNatService;
use App\Services\ClientServiceConsole\ServiceSecurityGroupService;
use App\Services\ClientServiceConsole\ServiceTransformService;
use App\Services\System\OperationLogService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

/**
 * 安全组越权与缓存守卫回归（D4A-01 / D4A-11 / D4B-02）。
 *
 * 上游替身走 custom 模式（不提供 getSecurityGroups），页面抓取与动作提交
 * 全部由内存替身承接，避免依赖真实上游网络。
 */
class ClientSecurityGroupGuardTest extends TestCase
{
    use DatabaseTransactions;

    private const SHARED_HOST_ID = 777;

    private ?Supplier $supplier = null;

    private ?FakeSecurityGroupRuntime $runtime = null;

    /**
     * D4A-01：owned 归属集合只统计「当前服务」自身的日志。
     * 同一上游主机被两个用户的服务绑定时，A 创建的安全组不得进入 B 的 owned 集合。
     */
    public function test_owned_bindings_only_count_logs_of_current_service(): void
    {
        [, $serviceA, , $serviceB] = $this->sharedHostFixture();
        $this->writeSecurityGroupCreateLog($serviceA, 101, 'g-101');

        $securityGroupService = $this->makeSecurityGroupService();
        $method = new \ReflectionMethod(ServiceSecurityGroupService::class, 'resolveOwnedSecurityGroupBindingsForService');
        $method->setAccessible(true);

        $ownedA = $method->invoke($securityGroupService, $serviceA);
        $this->assertContains(101, $ownedA['ids']);

        // 用户 B 的服务从未创建过 101：不得把同主机他人组的日志计入 owned（跨用户越权根因）
        $ownedB = $method->invoke($securityGroupService, $serviceB);
        $this->assertNotContains(101, $ownedB['ids']);
    }

    /**
     * D4A-01 端到端：用户 B 不能删除/应用用户 A 在同一上游主机上创建的安全组。
     */
    public function test_user_b_cannot_delete_security_group_created_by_user_a(): void
    {
        [, $serviceA, $userB, $serviceB] = $this->sharedHostFixture();
        $this->writeSecurityGroupCreateLog($serviceA, 101, 'g-101');
        // B 自己有一个组，保证 B 的 owned 集合非空并进入可见性过滤分支
        $this->writeSecurityGroupCreateLog($serviceB, 102, 'g-102');

        $securityGroupService = $this->makeSecurityGroupService();

        try {
            $securityGroupService->deleteSecurityGroupForUser($userB, (int) $serviceB->id, 101, []);
            $this->fail('跨用户删除安全组未被拦截');
        } catch (BusinessException $exception) {
            $this->assertSame(42200, $exception->getErrorCode());
            $this->assertSame('安全组不存在或不允许当前服务操作', $exception->getMessage());
        }

        // 越权请求不得触达上游删除动作
        $this->assertSame([], $this->runtime?->actions ?? []);
    }

    /**
     * D4A-01 正向对照：用户 A 删除自己创建的安全组应放行。
     */
    public function test_user_a_can_delete_own_security_group(): void
    {
        [$userA, $serviceA] = $this->sharedHostFixture();
        $this->writeSecurityGroupCreateLog($serviceA, 101, 'g-101');

        $securityGroupService = $this->makeSecurityGroupService();
        $result = $securityGroupService->deleteSecurityGroupForUser($userA, (int) $serviceA->id, 101, []);

        $this->assertSame('安全组已删除', $result['message']);
        $deleteActions = array_values(array_filter(
            $this->runtime?->actions ?? [],
            fn (array $payload): bool => ($payload['func'] ?? '') === 'delSecurityGroup'
        ));
        $this->assertCount(1, $deleteActions);
        $this->assertSame(101, $deleteActions[0]['id']);
    }

    /**
     * D4A-11：删除规则前必须断言 ruleId 属于 groupId。
     * 用户 B 用自有组 ID 携带他人组的 ruleId 请求时，按 404 拒绝。
     */
    public function test_delete_rule_rejects_rule_id_not_belonging_to_group(): void
    {
        [, , $userB, $serviceB] = $this->sharedHostFixture();
        $this->writeSecurityGroupCreateLog($serviceB, 102, 'g-102');
        $securityGroupService = $this->makeSecurityGroupService();
        // 上游 showSecurityRules(id=102) 只返回 B 自己组内的规则
        $this->runtime?->setGroupRules([['id' => 501], ['id' => 502]]);

        try {
            // 601 是同主机上他人组（A 的组 101）内的规则 ID
            $securityGroupService->deleteSecurityRuleForUser($userB, (int) $serviceB->id, 102, 601, []);
            $this->fail('不属于目标安全组的 ruleId 未被拦截');
        } catch (BusinessException $exception) {
            $this->assertSame(40400, $exception->getErrorCode());
        }

        // 归属断言失败时不得触达上游删除动作（showSecurityRules 属于断言本身，属预期）
        $deleteActions = array_values(array_filter(
            $this->runtime?->actions ?? [],
            fn (array $payload): bool => ($payload['func'] ?? '') === 'delSecurityRule'
        ));
        $this->assertSame([], $deleteActions);
    }

    /**
     * D4A-11 正向对照：ruleId 确实属于目标组时放行，并把 (id, group) 传给上游。
     */
    public function test_delete_rule_allows_rule_belonging_to_group(): void
    {
        [, , $userB, $serviceB] = $this->sharedHostFixture();
        $this->writeSecurityGroupCreateLog($serviceB, 102, 'g-102');
        $this->makeSecurityGroupService();
        $this->runtime?->setGroupRules([['id' => 501], ['id' => 502]]);

        $securityGroupService = $this->makeSecurityGroupService();
        $result = $securityGroupService->deleteSecurityRuleForUser($userB, (int) $serviceB->id, 102, 501, []);

        $this->assertSame('规则已删除', $result['message']);
        $deleteActions = array_values(array_filter(
            $this->runtime?->actions ?? [],
            fn (array $payload): bool => ($payload['func'] ?? '') === 'delSecurityRule'
        ));
        $this->assertCount(1, $deleteActions);
        $this->assertSame(501, $deleteActions[0]['id']);
        $this->assertSame(102, $deleteActions[0]['group']);
    }

    /**
     * D4B-02：上下文缓存键必须携带供应商与主机维度，且缓存不落 JWT/整页 HTML；
     * 缓存命中后按需重新登录补齐凭据，页面不再重复抓取。
     */
    public function test_context_cache_key_has_dimensions_and_stores_no_jwt(): void
    {
        [, $serviceA] = $this->sharedHostFixture();
        $securityGroupService = $this->makeSecurityGroupService();
        $supplierId = (int) ($this->supplier?->id ?? 0);
        $cacheKey = 'sg_ctx:service:'.(int) $serviceA->id.':supplier:'.$supplierId.':host:'.self::SHARED_HOST_ID;

        $first = $securityGroupService->resolveSecurityGroupContext($serviceA, false);
        $this->assertSame('jwt-1', $first['jwt']);
        $this->assertSame(1, $this->runtime?->pageCount);

        $cached = Cache::get($cacheKey);
        $this->assertIsArray($cached);
        // JWT 与整页 HTML 不入缓存（D4B-02）
        $this->assertArrayNotHasKey('jwt', $cached);
        $this->assertArrayNotHasKey('html', $cached);
        $this->assertSame(self::SHARED_HOST_ID, (int) ($cached['host_id'] ?? 0));
        $this->assertSame($supplierId, (int) ($cached['supplier_id'] ?? 0));

        // 二次读取命中缓存：不再抓取页面，JWT 由重新登录补齐
        $second = $securityGroupService->resolveSecurityGroupContext($serviceA, false);
        $this->assertSame(1, $this->runtime?->pageCount);
        $this->assertSame('jwt-2', $second['jwt']);

        // 换绑到另一台上游主机（host 维度不同）后不得命中旧缓存
        Cache::forget($cacheKey);
        $otherKey = 'sg_ctx:service:'.(int) $serviceA->id.':supplier:'.$supplierId.':host:888';
        Cache::put($otherKey, ['mode' => 'custom', 'host_id' => 888], now()->addSeconds(60));
        $this->runtime?->resetHostOverride(888);
        $moved = $securityGroupService->resolveSecurityGroupContext($serviceA, false);
        $this->assertSame('custom', $moved['mode']);
        $this->assertSame(888, (int) ($moved['host_id'] ?? 0));
        $this->assertSame('jwt-3', $moved['jwt']);
    }

    // ── Fixture ──────────────────────────────────────────────────────────

    /**
     * 构造两个不同用户的服务绑定同一台上游主机的场景（D4A-01 的触发前提）。
     *
     * @return array{0: User, 1: Service, 2: User, 3: Service}
     */
    private function sharedHostFixture(): array
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $product = Product::query()->create([
            'product_type' => 'cloud_host',
            'status' => 1,
            'pricing' => ['monthly' => '35.00'],
        ]);
        $serviceA = Service::query()->create([
            'user_id' => $userA->id,
            'product_id' => $product->id,
            'billing_cycle' => 'monthly',
            'amount' => 35.00,
            'status' => ServiceStatus::ACTIVE,
            'expires_at' => now()->addMonth(),
        ]);
        $serviceB = Service::query()->create([
            'user_id' => $userB->id,
            'product_id' => $product->id,
            'billing_cycle' => 'monthly',
            'amount' => 35.00,
            'status' => ServiceStatus::ACTIVE,
            'expires_at' => now()->addMonth(),
        ]);

        return [$userA, $serviceA, $userB, $serviceB];
    }

    private function writeSecurityGroupCreateLog(Service $service, int $groupId, string $groupName): void
    {
        app(OperationLogService::class)->writeServiceConsoleLog($service, 'service.console.security_group.create', [
            'category' => 'security_group',
            'summary' => '创建安全组',
            'host_id' => self::SHARED_HOST_ID,
            'group_id' => $groupId,
            'group_name' => $groupName,
            'message' => '安全组创建成功',
        ], ['actor_type' => 'client']);
    }

    private function makeSecurityGroupService(): ServiceSecurityGroupService
    {
        if (! $this->supplier instanceof Supplier) {
            $this->supplier = Supplier::query()->create([
                'name' => '测试供应商',
                'code' => 'sg-guard-test-'.bin2hex(random_bytes(4)),
                'status' => 1,
            ]);
        }
        $this->runtime ??= new FakeSecurityGroupRuntime;

        /** @var ServiceDetailService&Mockery\MockInterface $detailService */
        $detailService = Mockery::mock(ServiceDetailService::class);
        $detailService->shouldReceive('findUserService')->andReturnUsing(
            fn (User $user, int $serviceId): Service => Service::query()->where('user_id', $user->id)->findOrFail($serviceId)
        );
        $detailService->shouldReceive('resolveManagedSupplierAndHost')->andReturnUsing(
            fn (Service $service): array => [$this->supplier, $this->runtime->currentHostId ?? self::SHARED_HOST_ID]
        );
        $detailService->shouldReceive('resolveRuntimeCapabilityForSupplier')->andReturn($this->runtime);
        // 上下文解析的模块发现经 detailService 中转，直接转发给上游替身
        $detailService->shouldReceive('fetchSupportedModules')->andReturnUsing(
            fn (): array => $this->runtime->fetchSupportedModules($this->supplier, self::SHARED_HOST_ID, '', false)
        );
        $detailService->shouldReceive('extractPayload')->andReturnUsing(
            static fn (array $response): array => is_array($response['data'] ?? null) ? $response['data'] : $response
        );
        $detailService->shouldReceive('assertSuccess')->andReturnNull();

        return new ServiceSecurityGroupService(
            app(OperationLogService::class),
            $detailService,
            app(ServiceTransformService::class),
            new ServiceNatService(app(OperationLogService::class), $detailService, app(ServiceTransformService::class)),
        );
    }
}

/**
 * 安全组上游运行时替身：custom 模式（不提供 getSecurityGroups），
 * 记录 login/页面抓取次数与提交的动作载荷，页面 HTML 固定包含
 * 用户 A 的组 101 与用户 B 的组 102。
 */
final class FakeSecurityGroupRuntime
{
    public int $loginCount = 0;

    public int $pageCount = 0;

    /** @var int|null 强制覆盖 resolveManagedSupplierAndHost 返回的主机 ID */
    public ?int $currentHostId = null;

    /** @var array<int, array<string, mixed>> */
    public array $actions = [];

    /** @var array<int, array<string, mixed>> */
    private array $groupRules = [];

    public function setGroupRules(array $rules): void
    {
        $this->groupRules = $rules;
    }

    public function resetHostOverride(?int $hostId): void
    {
        $this->currentHostId = $hostId;
    }

    public function login(Supplier $supplier): string
    {
        $this->loginCount++;

        return 'jwt-'.$this->loginCount;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchSupportedModules(Supplier $supplier, int $hostId, string $jwt, bool $fresh = false): array
    {
        return [
            ['type' => 'custom', 'function' => 'secgroup', 'name' => '安全组'],
        ];
    }

    public function fetchCustomModulePage(Supplier $supplier, int $hostId, string $moduleKey, string $jwt): string
    {
        $this->pageCount++;

        return <<<'HTML'
<html><body>
<div class="table-responsive"><table><tbody>
<tr>
<td>g-101</td><td>用户A的组</td>
<td><a class="trview" data-id="101">查看规则</a><a class="deletegroup" data-id="101">删除</a><a class="apply" data-id="101">应用</a></td>
</tr>
<tr>
<td>g-102</td><td>用户B的组</td>
<td><a class="trview" data-id="102">查看规则</a><a class="deletegroup" data-id="102">删除</a><a class="apply" data-id="102">应用</a></td>
</tr>
</tbody></table></div>
</body></html>
HTML;
    }

    public function getCustomModuleActionEndpoint(Supplier $supplier, int $hostId): string
    {
        return 'https://upstream.test/provision/custom/'.$hostId;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function submitCustomModuleAction(Supplier $supplier, string $endpoint, array $payload, string $jwt): array
    {
        $this->actions[] = $payload;

        if ((string) ($payload['func'] ?? '') === 'showSecurityRules') {
            return ['status' => 200, 'data' => ['list' => $this->groupRules]];
        }

        return ['status' => 200, 'msg' => ''];
    }
}
