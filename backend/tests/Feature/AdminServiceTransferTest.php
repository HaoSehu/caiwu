<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Constants\ServiceStatus;
use App\Jobs\SendTemplateEmailJob;
use App\Models\AdminUser;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use App\Services\ClientServiceConsole\ServiceDetailService;
use App\Services\System\ActivityLogService;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

/**
 * 管理端「转移实例归属」（POST /api/v2/admin/users/{user}/services/{service}/transfers）：
 * 1. 只有 USER_MANAGE 能转移，USER_DETAIL 一律 403；
 * 2. 转移后 services.user_id 落到接收方，原持有者再访问该实例 404，接收方可正常读取；
 * 3. 实例必须属于路由上的用户，越权访问 404 且不泄露存在性；
 * 4. 接收方必须是未软删且启用中的用户，同用户无需转移，参数校验先于业务；
 * 5. 状态无关：运行中 / 暂停 / 已删除 / 异常 / 已到期都可转移，任何状态都不构成拒绝理由；
 * 6. 账务闸门：存在待支付账单、或存在已支付但未履约完成的账单时拒绝。requires_refund=1
 *    不豁免——钱要退回但履约仍未结束，转移后新持有人可再次下单造成双扣；
 * 7. 锁内复检：事务内重锁 services 行后重新校验账单（对 invoice 行加锁），
 *    窗口内新落库的账单不会漏放行；
 * 8. 转移即把 auto_renew 重置为 0：自动续费按当前持有者扣款，不能继承原用户授权；
 * 9. 审计写 activity_logs：actor_type=admin，保留 from/to 用户与备注；客户侧两条流水
 *    显式带 actor_type=client 且不带运营身份/IP；管理员代客操作可按 from/to 用户查到；
 * 10. 双方各收到一封归属变更通知（模板 100030）；
 * 11. 强审计：活动日志写入失败时整笔转移回滚（writeStrict），不留「改了归属却查不到人」；
 * 12. 并发：锁内发现归属已被他人改动时回 409/40900，不覆盖他人转移结果；
 * 13. 缓存：新旧两套详情/配置缓存键都要失效，并把转移记进实例操作日志详情。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class AdminServiceTransferTest extends TestCase
{
    use DatabaseTransactions;

    public function test_transfer_moves_ownership_and_writes_audit_log(): void
    {
        $admin = $this->makeAdmin([AdminPermissions::USER_MANAGE]);
        $fromUser = $this->makeUser('transfer-from');
        $toUser = $this->makeUser('transfer-to');
        $service = $this->makeService($fromUser, '待转移实例');
        Sanctum::actingAs($admin);

        $this->postJson("/api/v2/admin/users/{$fromUser->id}/services/{$service->id}/transfers", [
            'target_user_id' => (int) $toUser->id,
            'remark' => '客户要求把实例转到自己名下',
        ])
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('message', '实例归属已转移')
            ->assertJsonPath('data.service.id', (int) $service->id)
            ->assertJsonPath('data.service.name', '待转移实例');

        $this->assertSame((int) $toUser->id, (int) $service->fresh()->user_id);

        // 归属同步生效：原持有者控制台读不到，接收方可以读到。
        $this->getJson("/api/v2/admin/users/{$fromUser->id}/services/{$service->id}?sync_remote=0")
            ->assertStatus(404)
            ->assertJsonPath('code', 40400);

        $this->getJson("/api/v2/admin/users/{$toUser->id}/services/{$service->id}?sync_remote=0")
            ->assertOk()
            ->assertJsonPath('data.service.id', (int) $service->id);

        // 接收方实例列表也包含该实例，原持有者列表为空。
        $this->getJson("/api/v2/admin/users/{$toUser->id}/services?page_size=50")
            ->assertOk()
            ->assertJsonPath('data.total', 1);

        $this->getJson("/api/v2/admin/users/{$fromUser->id}/services?page_size=50")
            ->assertOk()
            ->assertJsonPath('data.total', 0);

        // 审计口径：服务操作日志的管理员主体不能被回落到新持有者。
        $consoleLog = DB::table('activity_logs')
            ->where('action', 'service.console.transfer')
            ->where('subject_id', (int) $service->id)
            ->first();

        $this->assertNotNull($consoleLog);
        $this->assertSame('admin', (string) $consoleLog->actor_type);
        $this->assertSame((int) $admin->id, (int) $consoleLog->actor_id);
        $this->assertSame('service', (string) $consoleLog->module);

        $context = json_decode((string) $consoleLog->context, true);
        $this->assertSame((int) $fromUser->id, (int) ($context['from_user_id'] ?? 0));
        $this->assertSame((int) $toUser->id, (int) ($context['to_user_id'] ?? 0));
        $this->assertSame('客户要求把实例转到自己名下', (string) ($context['remark'] ?? ''));

        // 双方各留一条客户侧流水的痕迹，便于对账追溯。
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'service.record.transferred_out',
            'actor_id' => (int) $fromUser->id,
            'subject_id' => (int) $service->id,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'service.record.transferred_in',
            'actor_id' => (int) $toUser->id,
            'subject_id' => (int) $service->id,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'user.service.record.transferred',
            'actor_type' => 'admin',
            'subject_id' => (int) $service->id,
        ]);

        // 客户侧流水会进双方实例操作日志：不得带上运营账号名与来源 IP。
        $clientLog = DB::table('activity_logs')
            ->where('action', 'service.record.transferred_in')
            ->where('subject_id', (int) $service->id)
            ->first();

        $this->assertNotNull($clientLog);
        $this->assertNull($clientLog->ip_address);

        $clientContext = json_decode((string) $clientLog->context, true);
        $this->assertArrayNotHasKey('operator_name', $clientContext);
        $this->assertArrayNotHasKey('operator_id', $clientContext);
        $this->assertArrayNotHasKey('trace_id', $clientContext);
        // 归属信息仍保留，客户侧可看懂发生了什么。
        $this->assertSame((int) $toUser->id, (int) ($clientContext['to_user_id'] ?? 0));
    }

    public function test_transfer_forgets_detail_and_both_overview_caches(): void
    {
        $admin = $this->makeAdmin([AdminPermissions::USER_MANAGE]);
        $fromUser = $this->makeUser('transfer-cache-from');
        $toUser = $this->makeUser('transfer-cache-to');
        $service = $this->makeService($fromUser, '缓存实例');
        Sanctum::actingAs($admin);

        $detailService = app(ServiceDetailService::class);
        $oldDetailKey = $detailService->buildDetailResponseCacheKey($service);
        $oldConfigKey = $detailService->buildServiceConfigCacheKey($service);
        $fromOverviewKey = 'client_service_console:grouped_overview:'.(int) $fromUser->id;
        $toOverviewKey = 'client_service_console:grouped_overview:'.(int) $toUser->id;

        Cache::put($oldDetailKey, ['warm' => true], 120);
        Cache::put($oldConfigKey, ['warm' => true], 120);
        Cache::put($fromOverviewKey, ['warm' => true], 120);
        Cache::put($toOverviewKey, ['warm' => true], 120);

        $this->postJson("/api/v2/admin/users/{$fromUser->id}/services/{$service->id}/transfers", [
            'target_user_id' => (int) $toUser->id,
        ])->assertOk()->assertJsonPath('code', 0);

        // 旧持有者的详情/配置缓存键含 user_id + updated_at，必须在归属变更后立刻失效。
        $this->assertFalse(Cache::has($oldDetailKey));
        $this->assertFalse(Cache::has($oldConfigKey));

        // 概览按用户分组缓存，新旧两个用户都要失效，否则列表仍显示旧归属。
        $this->assertFalse(Cache::has($fromOverviewKey));
        $this->assertFalse(Cache::has($toOverviewKey));
    }

    /**
     * 缓存键含 user_id 与 updated_at：转移后会生成一套全新键。
     * 若忘了用事务内刷新后的实例清新键，新持有者看到过期的旧快照；第二次转移起就会漏失效。
     */
    public function test_transfer_forgets_new_holder_detail_and_config_cache_keys(): void
    {
        $admin = $this->makeAdmin([AdminPermissions::USER_MANAGE]);
        $fromUser = $this->makeUser('transfer-cache-old');
        $toUser = $this->makeUser('transfer-cache-new');
        $thirdUser = $this->makeUser('transfer-cache-third');
        $service = $this->makeService($fromUser, '二次转移缓存实例');
        $service->loadMissing('product');
        Sanctum::actingAs($admin);

        $detailService = app(ServiceDetailService::class);
        $oldDetailKey = $detailService->buildDetailResponseCacheKey($service);
        $oldConfigKey = $detailService->buildServiceConfigCacheKey($service);

        $this->postJson("/api/v2/admin/users/{$fromUser->id}/services/{$service->id}/transfers", [
            'target_user_id' => (int) $toUser->id,
        ])->assertOk()->assertJsonPath('code', 0);

        // 新持有者打开控制台，会把详情/配置缓存按「新 user_id + 新 updated_at」重新写满。
        $afterFirstTransfer = Service::query()->with('product')->findOrFail((int) $service->id);
        $newDetailKey = $detailService->buildDetailResponseCacheKey($afterFirstTransfer);
        $newConfigKey = $detailService->buildServiceConfigCacheKey($afterFirstTransfer);

        // 键必须真的换了，否则下面的断言会退化成「同一个键被清过一次」。
        $this->assertNotSame($oldDetailKey, $newDetailKey);
        $this->assertNotSame($oldConfigKey, $newConfigKey);

        Cache::put($newDetailKey, ['warm' => true], 120);
        Cache::put($newConfigKey, ['warm' => true], 120);

        $this->postJson("/api/v2/admin/users/{$toUser->id}/services/{$service->id}/transfers", [
            'target_user_id' => (int) $thirdUser->id,
        ])->assertOk()->assertJsonPath('code', 0);

        // 第二次转移同样要用「转移前快照」和「转移后实例」各清一次新键。
        $this->assertFalse(Cache::has($newDetailKey));
        $this->assertFalse(Cache::has($newConfigKey));
    }

    /**
     * 归属变更必须能在实例操作日志里说清「从谁到谁」。
     */
    public function test_transfer_operation_log_exposes_transfer_detail_items(): void
    {
        $admin = $this->makeAdmin([AdminPermissions::USER_MANAGE]);
        $fromUser = $this->makeUser('transfer-log-from');
        $toUser = $this->makeUser('transfer-log-to');
        $service = $this->makeService($fromUser, '日志详情实例');
        Sanctum::actingAs($admin);

        $this->postJson("/api/v2/admin/users/{$fromUser->id}/services/{$service->id}/transfers", [
            'target_user_id' => (int) $toUser->id,
            'remark' => '日志详情校验',
        ])->assertOk()->assertJsonPath('code', 0);

        // 管理端口径的实例操作日志包含管理员操作，接收方名下也能查到这次转移。
        $response = $this->getJson("/api/v2/admin/users/{$toUser->id}/services/{$service->id}/operation-logs?page_size=50")
            ->assertOk()
            ->assertJsonPath('code', 0);

        $transferLog = collect((array) $response->json('data.list'))
            ->firstWhere('action', 'service.console.transfer');

        $this->assertIsArray($transferLog);
        $this->assertSame('实例归属已转移给'.$toUser->email, $transferLog['summary']);
        $this->assertSame('转移归属', $transferLog['action_label']);
        $this->assertSame('service', $transferLog['category']);
        $this->assertSame('实例变更', $transferLog['category_label']);
        $this->assertSame('admin', $transferLog['actor_type']);
        $this->assertSame('管理员', $transferLog['actor_label']);

        $items = collect((array) $transferLog['detail_items'])
            ->mapWithKeys(fn (array $item): array => [(string) $item['label'] => (string) $item['value']])
            ->all();

        $this->assertSame('实例归属已转移给'.$toUser->email, $items['执行说明'] ?? null);
        $this->assertSame('转移归属', $items['操作类型'] ?? null);
        $this->assertSame('日志详情实例', $items['实例名称'] ?? null);
        $this->assertSame($fromUser->email, $items['原归属用户'] ?? null);
        $this->assertSame($toUser->email, $items['接收方用户'] ?? null);
        $this->assertSame('日志详情校验', $items['备注'] ?? null);
        // auto_renew 原本就是 0，未重置时不展示这一项，避免日志虚报动作。
        $this->assertArrayNotHasKey('自动续费已重置', $items);
    }

    public function test_transfer_blocks_paid_unfulfilled_invoice_owned_by_previous_holder(): void
    {
        $fromUser = $this->makeUser('transfer-cross-from');
        $toUser = $this->makeUser('transfer-cross-to');
        $service = $this->makeService($fromUser, '跨用户账务实例');
        // 账单归属与实例持有者不同：模拟归属已变更过一次、旧账仍挂在历史用户名下的情况。
        $historicalUser = $this->makeUser('transfer-cross-history');
        $invoice = $this->makeInvoice($fromUser, (int) $service->id, InvoiceStatus::PAID, [
            'fulfillment_pending' => true,
            'requires_refund' => false,
        ], (int) $historicalUser->id);

        Sanctum::actingAs($this->makeAdmin([AdminPermissions::USER_MANAGE]));

        $this->postJson("/api/v2/admin/users/{$fromUser->id}/services/{$service->id}/transfers", [
            'target_user_id' => (int) $toUser->id,
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 42200)
            ->assertJsonPath('message', '该实例存在已支付但尚未履约完成的账单（'.$invoice->invoice_no.'），请待履约完成或退款到账后再转移');

        $this->assertSame((int) $fromUser->id, (int) $service->fresh()->user_id);
    }

    public function test_transfer_rechecks_billing_after_acquiring_lock(): void
    {
        $fromUser = $this->makeUser('transfer-race-from');
        $toUser = $this->makeUser('transfer-race-to');
        $service = $this->makeService($fromUser, '并发建单实例');
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::USER_MANAGE]));

        // 事务外的快照校验必然通过（此刻无账单）；在锁内重查实例的瞬间插入一笔待支付账单，
        // 模拟「校验通过 → 续费建单落地」的窗口，验证锁内复检能拦住。
        $retrievals = 0;
        $racingInvoice = null;
        Service::retrieved(function (Service $model) use (&$retrievals, &$racingInvoice, $service, $fromUser): void {
            if ((int) $model->id !== (int) $service->id) {
                return;
            }

            $retrievals++;
            if ($retrievals !== 2 || $racingInvoice instanceof Invoice) {
                return;
            }

            $racingInvoice = $this->makeInvoice($fromUser, (int) $service->id, InvoiceStatus::UNPAID);
        });

        try {
            $this->postJson("/api/v2/admin/users/{$fromUser->id}/services/{$service->id}/transfers", [
                'target_user_id' => (int) $toUser->id,
            ])
                ->assertStatus(422)
                ->assertJsonPath('code', 42200);
        } finally {
            Service::getEventDispatcher()?->forget('eloquent.retrieved: '.Service::class);
        }

        $this->assertInstanceOf(Invoice::class, $racingInvoice);

        // 归属未被改变：窗口内的账单没有放行转移。
        $this->assertSame((int) $fromUser->id, (int) $service->fresh()->user_id);
    }

    /**
     * 并发：锁内查到归属已被他人改动时必须 409/40900，不能覆盖他人刚做完的转移。
     */
    public function test_transfer_rejects_when_ownership_changed_inside_lock(): void
    {
        $fromUser = $this->makeUser('transfer-conflict-from');
        $toUser = $this->makeUser('transfer-conflict-to');
        $thirdUser = $this->makeUser('transfer-conflict-third');
        $service = $this->makeService($fromUser, '并发归属实例');
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::USER_MANAGE]));

        // 第一次查询是锁外定位，仍然属于原持有者；第二次查询发生在 lockForUpdate 之后，
        // 把它改成第三个人，等价于「另一个管理员刚刚把这台机器转走了」。
        $retrievals = 0;
        Service::retrieved(function (Service $model) use (&$retrievals, $service, $thirdUser): void {
            if ((int) $model->id !== (int) $service->id) {
                return;
            }

            $retrievals++;
            if ($retrievals === 2) {
                $model->setAttribute('user_id', (int) $thirdUser->id);
            }
        });

        try {
            $this->postJson("/api/v2/admin/users/{$fromUser->id}/services/{$service->id}/transfers", [
                'target_user_id' => (int) $toUser->id,
            ])
                ->assertStatus(409)
                ->assertJsonPath('code', 40900)
                ->assertJsonPath('message', '实例归属已被其他操作变更，请刷新后重试');
        } finally {
            Service::getEventDispatcher()?->forget('eloquent.retrieved: '.Service::class);
        }

        $this->assertSame(2, $retrievals);
        // 没有写入：既没有转给接收方，也没有被这次请求改回原持有者。
        $this->assertSame((int) $fromUser->id, (int) $service->fresh()->user_id);
        $this->assertDatabaseMissing('activity_logs', [
            'action' => 'service.console.transfer',
            'subject_id' => (int) $service->id,
        ]);
    }

    /**
     * 强审计：activity_logs 写不进去时整笔转移必须回滚，不能留下「归属改了却查不到是谁改的」。
     */
    public function test_transfer_rolls_back_when_audit_log_write_fails(): void
    {
        $fromUser = $this->makeUser('transfer-audit-from');
        $toUser = $this->makeUser('transfer-audit-to');
        $service = $this->makeService($fromUser, '强审计回滚实例');
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::USER_MANAGE]));

        // 唯一落库入口 throw：writeStrict()/strict 模式必须让异常穿透，把业务事务一起回滚。
        $this->app->instance(ActivityLogService::class, new class extends ActivityLogService
        {
            public function record(array $attributes): void
            {
                throw new RuntimeException('activity_logs 写入失败（测试注入）');
            }
        });

        $this->postJson("/api/v2/admin/users/{$fromUser->id}/services/{$service->id}/transfers", [
            'target_user_id' => (int) $toUser->id,
        ])
            ->assertStatus(500)
            ->assertJsonPath('code', 50000);

        // 归属保持原样，且一条审计流水都没落库。
        $this->assertSame((int) $fromUser->id, (int) $service->fresh()->user_id);
        $this->assertDatabaseMissing('activity_logs', [
            'subject_id' => (int) $service->id,
        ]);
    }

    public function test_transfer_requires_user_manage_permission(): void
    {
        $fromUser = $this->makeUser('transfer-rbac-from');
        $toUser = $this->makeUser('transfer-rbac-to');
        $service = $this->makeService($fromUser, '越权转移实例');

        // 仅持 USER_DETAIL（USER_MANAGE 的 implied 子权限）不得转移。
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::USER_DETAIL]));

        $this->postJson("/api/v2/admin/users/{$fromUser->id}/services/{$service->id}/transfers", [
            'target_user_id' => (int) $toUser->id,
        ])
            ->assertStatus(403)
            ->assertJsonPath('code', 40300);

        $this->assertSame((int) $fromUser->id, (int) $service->fresh()->user_id);
    }

    public function test_transfer_rejects_service_not_owned_by_route_user(): void
    {
        $owner = $this->makeUser('transfer-owner');
        $other = $this->makeUser('transfer-other');
        $target = $this->makeUser('transfer-target');
        $service = $this->makeService($owner, '归属他人实例');
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::USER_MANAGE]));

        // 路由上的 user 不是真实持有者：按 user_id 定位后必然 404，不能借路由越权转移。
        $this->postJson("/api/v2/admin/users/{$other->id}/services/{$service->id}/transfers", [
            'target_user_id' => (int) $target->id,
        ])
            ->assertStatus(404)
            ->assertJsonPath('code', 40400);

        $this->assertSame((int) $owner->id, (int) $service->fresh()->user_id);
    }

    public function test_transfer_rejects_invalid_target_user(): void
    {
        $fromUser = $this->makeUser('transfer-target-from');
        $service = $this->makeService($fromUser, '接收方校验实例');
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::USER_MANAGE]));

        // 不存在
        $this->postJson("/api/v2/admin/users/{$fromUser->id}/services/{$service->id}/transfers", [
            'target_user_id' => 999999999,
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 42200)
            ->assertJsonPath('message', '接收方用户不存在或已被禁用');

        // 禁用账号：仍会被自动续费扣款，但不能承接新资产。
        $disabled = $this->makeUser('transfer-disabled');
        $disabled->forceFill(['status' => 0])->save();

        $this->postJson("/api/v2/admin/users/{$fromUser->id}/services/{$service->id}/transfers", [
            'target_user_id' => (int) $disabled->id,
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 42200)
            ->assertJsonPath('message', '接收方用户不存在或已被禁用');

        // 软删账号
        $deleted = $this->makeUser('transfer-deleted');
        $deleted->delete();

        $this->postJson("/api/v2/admin/users/{$fromUser->id}/services/{$service->id}/transfers", [
            'target_user_id' => (int) $deleted->id,
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 42200);

        $this->assertSame((int) $fromUser->id, (int) $service->fresh()->user_id);
    }

    public function test_transfer_rejects_same_owner(): void
    {
        $fromUser = $this->makeUser('transfer-same');
        $service = $this->makeService($fromUser, '同归属实例');
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::USER_MANAGE]));

        $this->postJson("/api/v2/admin/users/{$fromUser->id}/services/{$service->id}/transfers", [
            'target_user_id' => (int) $fromUser->id,
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 42200)
            ->assertJsonPath('message', '接收方与当前归属用户相同，无需转移');
    }

    public function test_transfer_validates_payload(): void
    {
        $fromUser = $this->makeUser('transfer-validate');
        $service = $this->makeService($fromUser, '参数校验实例');
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::USER_MANAGE]));

        $this->postJson("/api/v2/admin/users/{$fromUser->id}/services/{$service->id}/transfers", [])
            ->assertStatus(422)
            ->assertJsonPath('code', 42200);

        $this->postJson("/api/v2/admin/users/{$fromUser->id}/services/{$service->id}/transfers", [
            'target_user_id' => 0,
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 42200);

        $this->postJson("/api/v2/admin/users/{$fromUser->id}/services/{$service->id}/transfers", [
            'target_user_id' => (int) $fromUser->id + 1,
            'remark' => str_repeat('备', 201),
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 42200);
    }

    public function test_transfer_allows_pending_and_terminated_service(): void
    {
        $fromUser = $this->makeUser('transfer-status-from');
        $toUser = $this->makeUser('transfer-status-to');
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::USER_MANAGE]));

        // 开通中：实例还没交付，但归属本身可以先落到最终持有者名下。
        $pending = $this->makeService($fromUser, '开通中实例');
        $pending->forceFill(['status' => ServiceStatus::PENDING])->save();

        $this->postJson("/api/v2/admin/users/{$fromUser->id}/services/{$pending->id}/transfers", [
            'target_user_id' => (int) $toUser->id,
        ])
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('message', '实例归属已转移');

        // 已删除：实例不再可用，但归属仍需可纠正（删错的实例必须先能挂回正确账号）。
        $terminated = $this->makeService($fromUser, '已删除实例');
        $terminated->forceFill(['status' => ServiceStatus::TERMINATED])->save();

        $this->postJson("/api/v2/admin/users/{$fromUser->id}/services/{$terminated->id}/transfers", [
            'target_user_id' => (int) $toUser->id,
        ])
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('message', '实例归属已转移');

        $this->assertSame((int) $toUser->id, (int) $pending->fresh()->user_id);
        $this->assertSame((int) $toUser->id, (int) $terminated->fresh()->user_id);
    }

    public function test_transfer_rejects_unpaid_invoice(): void
    {
        $fromUser = $this->makeUser('transfer-unpaid-from');
        $toUser = $this->makeUser('transfer-unpaid-to');
        $service = $this->makeService($fromUser, '欠费实例');
        $invoice = $this->makeInvoice($fromUser, (int) $service->id, InvoiceStatus::UNPAID);
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::USER_MANAGE]));

        $this->postJson("/api/v2/admin/users/{$fromUser->id}/services/{$service->id}/transfers", [
            'target_user_id' => (int) $toUser->id,
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 42200)
            ->assertJsonPath('message', '该实例存在待支付账单（'.$invoice->invoice_no.'），请先结清或作废后再转移');

        // 拦截发生在写库前：归属不变，不产生部分生效的数据。
        $this->assertSame((int) $fromUser->id, (int) $service->fresh()->user_id);
    }

    public function test_transfer_rejects_paid_unfulfilled_invoice_even_when_refund_flagged(): void
    {
        $fromUser = $this->makeUser('transfer-paid-from');
        $toUser = $this->makeUser('transfer-paid-to');
        $service = $this->makeService($fromUser, '待履约实例');
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::USER_MANAGE]));

        $pending = $this->makeInvoice($fromUser, (int) $service->id, InvoiceStatus::PAID, [
            'fulfillment_pending' => true,
            'requires_refund' => false,
        ]);

        $this->postJson("/api/v2/admin/users/{$fromUser->id}/services/{$service->id}/transfers", [
            'target_user_id' => (int) $toUser->id,
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 42200)
            ->assertJsonPath('message', '该实例存在已支付但尚未履约完成的账单（'.$pending->invoice_no.'），请待履约完成或退款到账后再转移');

        $this->assertSame((int) $fromUser->id, (int) $service->fresh()->user_id);

        // 已标记人工退款（requires_refund=1）只是「钱要退回」，钱与权益都还没结清：
        // 履约仍然 pending，转移后新持有者可再次下单造成双扣，因此同样必须拦截。
        $pending->forceFill(['requires_refund' => true])->save();

        $this->postJson("/api/v2/admin/users/{$fromUser->id}/services/{$service->id}/transfers", [
            'target_user_id' => (int) $toUser->id,
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 42200)
            ->assertJsonPath('message', '该实例存在已支付但尚未履约完成的账单（'.$pending->invoice_no.'），请待履约完成或退款到账后再转移');

        $this->assertSame((int) $fromUser->id, (int) $service->fresh()->user_id);
    }

    public function test_transfer_allows_suspended_abnormal_and_expired_service(): void
    {
        $fromUser = $this->makeUser('transfer-status-extra-from');
        $toUser = $this->makeUser('transfer-status-extra-to');
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::USER_MANAGE]));

        // 手动暂停：停机责任跟着实例走，不需要先恢复运行。
        $suspended = $this->makeService($fromUser, '暂停实例');
        $suspended->forceFill(['status' => ServiceStatus::SUSPENDED, 'suspended_reason' => 'manual'])->save();

        $this->postJson("/api/v2/admin/users/{$fromUser->id}/services/{$suspended->id}/transfers", [
            'target_user_id' => (int) $toUser->id,
        ])
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('message', '实例归属已转移');

        // 到期停机（suspended_reason=expired）：与普通暂停不再区分指引。
        $expired = $this->makeService($fromUser, '到期实例');
        $expired->forceFill(['status' => ServiceStatus::SUSPENDED, 'suspended_reason' => 'expired'])->save();

        $this->postJson("/api/v2/admin/users/{$fromUser->id}/services/{$expired->id}/transfers", [
            'target_user_id' => (int) $toUser->id,
        ])
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('message', '实例归属已转移');

        // 异常：带故障的实例同样可以换归属，故障处理与归属是两个维度。
        $abnormal = $this->makeService($fromUser, '异常实例');
        $abnormal->forceFill(['status' => ServiceStatus::ABNORMAL])->save();

        $this->postJson("/api/v2/admin/users/{$fromUser->id}/services/{$abnormal->id}/transfers", [
            'target_user_id' => (int) $toUser->id,
        ])
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('message', '实例归属已转移');

        $this->assertSame((int) $toUser->id, (int) $suspended->fresh()->user_id);
        $this->assertSame((int) $toUser->id, (int) $expired->fresh()->user_id);
        $this->assertSame((int) $toUser->id, (int) $abnormal->fresh()->user_id);
    }

    public function test_transfer_allows_expired_active_service(): void
    {
        $fromUser = $this->makeUser('transfer-expired-from');
        $toUser = $this->makeUser('transfer-expired-to');
        $service = $this->makeService($fromUser, '已过期但未停机实例');
        $service->forceFill(['expires_at' => now()->subDay()])->save();
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::USER_MANAGE]));

        // 到期只影响「能不能继续用」，不再是转移门槛：待续费实例也要能换归属。
        $this->postJson("/api/v2/admin/users/{$fromUser->id}/services/{$service->id}/transfers", [
            'target_user_id' => (int) $toUser->id,
        ])
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('message', '实例归属已转移');

        $this->assertSame((int) $toUser->id, (int) $service->fresh()->user_id);
    }

    public function test_transfer_still_blocks_billing_on_non_active_service(): void
    {
        $fromUser = $this->makeUser('transfer-status-billing-from');
        $toUser = $this->makeUser('transfer-status-billing-to');
        $service = $this->makeService($fromUser, '已删除欠费实例');
        $service->forceFill(['status' => ServiceStatus::TERMINATED])->save();
        $invoice = $this->makeInvoice($fromUser, (int) $service->id, InvoiceStatus::UNPAID);
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::USER_MANAGE]));

        // 松开状态限制不等于松开账务闸门：状态无关，账单照拦。
        $this->postJson("/api/v2/admin/users/{$fromUser->id}/services/{$service->id}/transfers", [
            'target_user_id' => (int) $toUser->id,
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 42200)
            ->assertJsonPath('message', '该实例存在待支付账单（'.$invoice->invoice_no.'），请先结清或作废后再转移');

        $this->assertSame((int) $fromUser->id, (int) $service->fresh()->user_id);
    }

    public function test_transfer_resets_auto_renew_to_protect_new_holder(): void
    {
        $fromUser = $this->makeUser('transfer-autorenew-from');
        $toUser = $this->makeUser('transfer-autorenew-to');
        $service = $this->makeService($fromUser, '自动续费实例');
        $service->forceFill(['auto_renew' => 1])->save();
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::USER_MANAGE]));

        $this->postJson("/api/v2/admin/users/{$fromUser->id}/services/{$service->id}/transfers", [
            'target_user_id' => (int) $toUser->id,
        ])
            ->assertOk()
            ->assertJsonPath('code', 0);

        $fresh = $service->fresh();
        $this->assertSame((int) $toUser->id, (int) $fresh->user_id);
        // 自动续费按当前持有者扣款：转移后必须由新持有者显式开启，不能继承原用户的授权。
        $this->assertSame(0, (int) $fresh->auto_renew);
    }

    public function test_transfer_notifies_both_parties(): void
    {
        Queue::fake();

        $fromUser = $this->makeUser('transfer-notify-from');
        $toUser = $this->makeUser('transfer-notify-to');
        $service = $this->makeService($fromUser, '通知实例');
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::USER_MANAGE]));

        $this->postJson("/api/v2/admin/users/{$fromUser->id}/services/{$service->id}/transfers", [
            'target_user_id' => (int) $toUser->id,
        ])
            ->assertOk()
            ->assertJsonPath('code', 0);

        Queue::assertPushed(SendTemplateEmailJob::class, 2);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function makeAdmin(array $permissions = [AdminPermissions::USER_MANAGE]): AdminUser
    {
        $role = Role::query()->create([
            'name' => 'role_'.uniqid(),
            'label' => '服务转移测试角色',
            'permissions' => $permissions,
        ]);

        return AdminUser::query()->create([
            'username' => 'admin_'.uniqid(),
            'password' => 'secret123',
            'nickname' => '管理员',
            'status' => 1,
            'role_id' => $role->id,
        ]);
    }

    private function makeUser(string $prefix): User
    {
        return User::query()->create([
            'email' => $prefix.'-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => $prefix.'-tester',
            'total_sales_amount' => 0,
        ]);
    }

    private function makeService(User $user, string $name): Service
    {
        $product = Product::query()->create([
            'product_group_id' => null,
            'service_type_code' => 'test',
            'product_type' => 'other',
            'pricing' => ['monthly' => 100.00],
            'setup_fee' => 0,
            'status' => 1,
            'sort_order' => 0,
        ]);

        return Service::query()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'name' => $name,
            'billing_cycle' => 'monthly',
            'amount' => 100.00,
            'status' => 1,
            'expires_at' => now()->addMonth(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function makeInvoice(User $user, int $serviceId, int $status, array $extra = [], ?int $invoiceUserId = null): Invoice
    {
        return Invoice::query()->create([
            'invoice_no' => 'SI'.date('YmdHis').mt_rand(100000, 999999),
            // 账单归属用户默认与实例持有者一致；跨用户场景（转移后旧账不随实例走）显式传入。
            'user_id' => $invoiceUserId ?? $user->id,
            'service_id' => $serviceId,
            'type' => 'renew',
            'amount' => 100.00,
            'paid_amount' => $status === InvoiceStatus::PAID ? 100.00 : 0,
            'billing_cycle' => 'monthly',
            'status' => $status,
            ...$extra,
        ]);
    }
}
