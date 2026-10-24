<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\IntegrationPlugin;
use App\Models\Role;
use App\Models\ScheduleRunLog;
use App\Models\ScheduleTaskRun;
use App\Models\Setting;
use App\Models\User;
use App\Services\Automation\Heartbeat\HeartbeatTaskRegistry;
use App\Services\Automation\ScheduleHookService;
use App\Services\Finance\AdminFinanceQueryService;
use App\Services\Finance\CouponService;
use App\Services\Finance\InvoiceService;
use App\Services\Finance\PaymentCallbackProjector;
use App\Services\Finance\TradeLifecycleService;
use App\Services\ProductCatalog\InstanceSpecCatalogService;
use App\Services\ProductCatalog\ProductDisplayNameResolver;
use App\Services\ProductCatalog\ProductFullPathResolver;
use App\Services\Referral\PromotionAmbassadorService;
use App\Services\Referral\ReferralService;
use App\Services\System\OperationLogService;
use App\Support\AdminPermissions;
use App\Support\SchemaMetadataCache;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use Tests\TestCase;
use Throwable;

/**
 * 数据库访问层查询放大回归断言。
 *
 * 每个用例都用 DB::listen 统计同一请求内的 SQL 条数，断言「与返回行数相关」的
 * 查询在修复后不再随行数增长。所有断言失败信息写明哪张表随行数增长、实测多少次。
 * 使用 DatabaseTransactions，测试结束回滚，不写生产库。
 */
class QueryAmplificationRegressionTest extends TestCase
{
    use DatabaseTransactions;

    /** @var list<string> */
    private array $sqls = [];

    private bool $capturing = false;

    private string $tag = '';

    private int $uid = 0;

    private int $pid = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tag = 'q'.substr(uniqid(), -8);
        DB::listen(function ($query): void {
            if ($this->capturing) {
                $this->sqls[] = $query->sql;
            }
        });
    }

    /**
     * N1 管理端服务列表：products.custom_display_name 必须随商品投影一次取出。
     * 修复前：展示名解析在投影缺列时逐行单查 products，3 行实测 3 次。
     */
    public function test_admin_service_list_does_not_query_product_display_name_per_row(): void
    {
        $this->seedProductAndServices(3);
        $this->actingAsAdmin();

        $this->capture(fn () => $this->getJson('/api/v2/admin/services?page_size=50&keyword='.$this->tag)->assertOk());

        $count = $this->countSqlStartsWith('select `custom_display_name` from `products`');
        $this->assertSame(
            0,
            $count,
            sprintf('products 表 custom_display_name 兜底查询随服务行数增长：实测 %d 次（3 行数据，修复前 3 次），应在商品投影中一次取出。', $count)
        );
    }

    /**
     * N2 管理端推荐奖励列表：order.product 与三级分组必须预载。
     * 修复前：3 行实测 products 懒加载 3 次、third_product_groups 3 次。
     */
    public function test_admin_referral_rewards_do_not_lazy_load_product_per_row(): void
    {
        $this->seedReferralRewards(3);
        $this->actingAsAdmin();

        $this->capture(fn () => $this->getJson('/api/v2/admin/referral/rewards?page_size=50')->assertOk());

        $this->assertProductPathQueriesDoNotScale('管理端推荐奖励列表');
    }

    /**
     * N2 客户端推荐奖励列表：同上，客户端投影同样必须预载。
     */
    public function test_client_referral_rewards_do_not_lazy_load_product_per_row(): void
    {
        $this->seedReferralRewards(3);
        $this->actingAsClient();

        $this->capture(fn () => $this->getJson('/api/v2/client/referral/rewards?page_size=50')->assertOk());

        $this->assertProductPathQueriesDoNotScale('客户端推荐奖励列表');
    }

    /**
     * N3 管理端供应商列表：空 pluginBindings 预载关系不得 fall through 到逐行查询。
     * 修复前：3 个无绑定供应商实测 supplier_plugin_bindings 单行查询 3 次。
     */
    public function test_admin_supplier_list_does_not_query_binding_per_row(): void
    {
        $this->seedSuppliers(3);
        $this->actingAsAdmin();

        $this->capture(fn () => $this->getJson('/api/v2/admin/suppliers?page_size=50')->assertOk());

        $count = $this->countSqlContaining('from `supplier_plugin_bindings` where `supplier_id` =');
        $this->assertSame(
            0,
            $count,
            sprintf('supplier_plugin_bindings 表逐行查询随供应商行数增长：实测 %d 次（3 行数据，修复前 3 次），应直接用已预载关系判定。', $count)
        );
    }

    /**
     * N4 管理端用户账单子列表：退款汇总必须走 selectSub，不得逐行聚合。
     * 修复前：3 行实测 finance_refunds SUM 聚合 12 次（每行 4 次）。
     */
    public function test_admin_user_invoice_list_does_not_aggregate_refunds_per_row(): void
    {
        // 必须用独立商品造数：账单共用同一商品时 Product 实例唯一，商品级懒加载只会发生一次，
        // 逐行 N+1 测不出来（实测 3 张共用商品的账单即使去掉 with() 也只有 1 次分组查询）。
        $this->seedInvoicesWithPayments(3, true);
        $this->actingAsAdmin();

        $this->capture(fn () => $this->getJson('/api/v2/admin/users/'.$this->uid.'/invoices?page_size=50')->assertOk());

        $count = $this->countSqlContaining('select sum(`amount`) as aggregate from `finance_refunds`');
        $this->assertSame(
            0,
            $count,
            sprintf('finance_refunds 表逐行 SUM 聚合随账单行数增长：实测 %d 次（3 行数据，修复前 12 次），应由 invoices 主查询的 selectSub 一次算出。', $count)
        );

        // 账单行同时输出 product_full_path：商品三级分组缺预载时，InvoiceService::adminListItem()
        // 的 loadMissing 会按账单逐条补齐这三层关系（3 张账单实测每张各 1 次），应在账单查询的
        // with() 中一次性预载。这里断言「每张分组表在一次请求内只被查询 1 次」：预载是 1 次批量，
        // 逐行懒加载则会按账单条数递增。
        foreach (['third_product_groups', 'second_product_groups', 'first_product_groups'] as $table) {
            $count = $this->countSqlContaining('from `'.$table.'`');
            $this->assertSame(
                1,
                $count,
                sprintf('%s 表查询次数随账单行数增长：实测 %d 次（3 行数据；预载应恒为 1 次批量查询），应在账单查询的 with() 中预载商品三级分组。', $table, $count)
            );
        }
    }

    /**
     * N5 客户端账单列表：payments.callbacks 必须随 payments 一并预载。
     * 修复前：3 行实测 payment_callbacks 单 ID 延迟加载 3 次。
     */
    public function test_client_invoice_list_does_not_lazy_load_payment_callbacks_per_row(): void
    {
        $this->seedInvoicesWithPayments(3);
        $this->actingAsClient();

        $this->capture(fn () => $this->getJson('/api/v2/client/invoices?page_size=50')->assertOk());

        $count = $this->countSqlStartsWith('select * from `payment_callbacks`');
        $this->assertSame(
            0,
            $count,
            sprintf('payment_callbacks 表单 ID 延迟加载随账单行数增长：实测 %d 次（3 行数据，修复前 3 次），应随 payments 关系一次预载。', $count)
        );
    }

    /**
     * N7 管理端定时任务总览：每个任务的「最近一次运行/最近一次旧日志」必须批量预载。
     * 修复前：17 个任务实测 schedule_task_runs 单行查询 17 次、schedule_run_logs 单行查询 17 次。
     */
    public function test_admin_schedule_overview_does_not_query_last_run_per_task(): void
    {
        $tasks = app(HeartbeatTaskRegistry::class)->enabledTasks();
        $this->assertGreaterThanOrEqual(2, count($tasks), '定时任务注册表为空，查询计数断言无效。');

        $runTask = $tasks[0];
        ScheduleTaskRun::query()->create([
            'task_key' => $runTask->key(),
            'task_name' => $this->tag,
            'status' => ScheduleTaskRun::STATUS_SUCCESS,
            'source' => 'manual',
            'attempt' => 1,
            'duration_ms' => 12,
            'summary' => ['tag' => $this->tag],
            'queued_at' => now()->subMinutes(3),
            'started_at' => now()->subMinutes(2),
            'finished_at' => now()->subMinutes(1),
        ]);

        $legacyTask = $tasks[1];
        ScheduleRunLog::query()->create([
            'task_name' => $legacyTask->title(),
            'status' => 'success',
            'duration_ms' => 8,
            'summary' => ['tag' => $this->tag],
            'started_at' => now()->subMinutes(2),
            'finished_at' => now()->subMinutes(1),
        ]);

        $this->actingAsAdmin();

        $this->capture(fn () => $this->getJson('/api/v2/admin/schedules/overview')->assertOk());

        $runs = $this->countSqlContaining('from `schedule_task_runs` where `task_key` =');
        $this->assertSame(
            0,
            $runs,
            sprintf('schedule_task_runs 表单行查询随任务数增长：实测 %d 次（当前 %d 个任务），应一次分组查询预载最近运行记录。', $runs, count($tasks))
        );

        $logs = $this->countSqlContaining('from `schedule_run_logs` where `task_name` =');
        $this->assertSame(
            0,
            $logs,
            sprintf('schedule_run_logs 表单行查询随任务数增长：实测 %d 次（当前 %d 个任务），应对无运行记录的任务一次分组查询预载最近旧日志。', $logs, count($tasks))
        );

        // 防假绿：批量查询必须真的发生（每类恰好 1 次），否则上面的 0 次可能只是没查到数据。
        $this->assertSame(
            1,
            $this->countSqlContaining('from `schedule_task_runs` where `task_key` in ('),
            '定时任务总览未走 schedule_task_runs 批量预载，查询计数断言无效。'
        );
        $this->assertSame(
            1,
            $this->countSqlContaining('from `schedule_run_logs` where `task_name` in ('),
            '定时任务总览未走 schedule_run_logs 批量预载，查询计数断言无效。'
        );
    }

    /**
     * N11 客户端服务列表：商品级上游绑定与自定义展示名必须按页批量预载。
     *
     * 修复前：每行各查一次 product_upstream_bindings（can_manage 回退到商品绑定）
     * 与 products.custom_display_name（商品投影刻意不带该列，解析器回源取值）。
     * 造数让每行指向独立商品与独立绑定，按 id 记忆化的实现仍会逐行回源，只有批量预载能通过。
     */
    public function test_client_service_list_does_not_query_product_binding_or_display_name_per_row(): void
    {
        $this->seedClientServicesWithProductBindings(3);
        $this->actingAsClient();

        $this->capture(fn () => $this->getJson('/api/v2/client/services?page_size=50')->assertOk());

        // 单行与批量查询只差谓词 `=` 与 `in (`，且批量查询带 left join，
        // 因此按谓词片段计数，不按会被 join 打断的 `from 表 as 别名 where` 整串匹配。
        $bindings = $this->countSqlContaining('`pub`.`product_id` =');
        $this->assertSame(
            0,
            $bindings,
            sprintf('product_upstream_bindings 表逐行查询随服务行数增长：实测 %d 次（3 行数据，修复前 3 次），应按当页商品一次 whereIn 预载上游绑定。', $bindings)
        );

        $displayNames = $this->countSqlStartsWith('select `custom_display_name` from `products` where `products`.`id` =');
        $this->assertSame(
            0,
            $displayNames,
            sprintf('products.custom_display_name 逐行回源随服务行数增长：实测 %d 次（3 行数据，修复前 3 次），应在逐行解析前按当页商品批量预载该列。', $displayNames)
        );

        // 防假绿：两条批量预载查询都必须真的发生，否则上面的 0 次可能只是没走到对应分支。
        $this->assertSame(
            1,
            $this->countSqlContaining('`pub`.`product_id` in ('),
            '客户端服务列表未走商品上游绑定批量预载，查询计数断言无效。'
        );
        $this->assertSame(
            1,
            $this->countSqlStartsWith('select `custom_display_name`, `id` from `products` where `id` in ('),
            '客户端服务列表未走 custom_display_name 批量预载，查询计数断言无效。'
        );
    }

    /**
     * N11 客户端服务分组总览：卡片项的源商品与自定义展示名必须整批预载。
     *
     * 修复前：每个卡片项各查一次源商品（items 每桶至多 6 条，2/20/40 个服务实测源商品单查 4/12/12 次）
     * 与一次 custom_display_name 回源（2/6/6 次）。
     */
    public function test_client_service_grouped_overview_does_not_query_product_per_item(): void
    {
        $this->seedClientServicesWithProductBindings(3);
        $this->actingAsClient();

        $this->capture(fn () => $this->getJson('/api/v2/client/services/grouped-overview')->assertOk());

        $sourceLookups = $this->countSqlStartsWith('select `id`, `purchase_requires`, `config_options` from `products` where `products`.`id` =');
        $this->assertSame(
            0,
            $sourceLookups,
            sprintf('products 源商品逐行查询随卡片项数增长：实测 %d 次（3 行数据，修复前按卡片项各 2 次），应整批预载拆分商品的源商品。', $sourceLookups)
        );

        $displayNames = $this->countSqlStartsWith('select `custom_display_name` from `products` where `products`.`id` =');
        $this->assertSame(
            0,
            $displayNames,
            sprintf('products.custom_display_name 逐行回源随卡片项数增长：实测 %d 次（3 行数据，修复前按卡片项各 1 次），应整批预载该列。', $displayNames)
        );

        // 防假绿：分组总览的两条批量预载都必须真的发生（拆分源商品预载与自定义展示名预载各一次）。
        $this->assertSame(
            1,
            $this->countSqlStartsWith('select `id`, `purchase_requires`, `config_options` from `products` where `id` in ('),
            '客户端服务分组总览未走拆分源商品批量预载，查询计数断言测不到任何东西。'
        );
        $this->assertSame(
            1,
            $this->countSqlStartsWith('select `custom_display_name`, `id` from `products` where `id` in ('),
            '客户端服务分组总览未走 custom_display_name 批量预载，查询计数断言测不到任何东西。'
        );
    }

    /**
     * N8 管理端定时任务总览：integration_plugins 的表存在性探测与启用插件集合必须常量次。
     * 修复前：单次 GET 实测 information_schema 存在性探测 9 次、select * from integration_plugins 9 次
     * （8 个 legacy hook 任务与插件任务各自探测一次表是否存在，再各自重查一次启用插件集合）。
     * 修复后：各自 1 次，全部请求 SQL 从 36 条降到 20 条。
     */
    public function test_admin_schedule_overview_does_not_reprobe_integration_plugins_per_task(): void
    {
        $tasks = app(HeartbeatTaskRegistry::class)->enabledTasks();
        $this->assertGreaterThanOrEqual(2, count($tasks), '定时任务注册表为空，查询计数断言无效。');

        // 造数只为让响应带出本用例标记，capture() 用它确认请求真的命中了测试数据。
        ScheduleTaskRun::query()->create([
            'task_key' => $tasks[0]->key(),
            'task_name' => $this->tag,
            'status' => ScheduleTaskRun::STATUS_SUCCESS,
            'source' => 'manual',
            'attempt' => 1,
            'duration_ms' => 9,
            'summary' => ['tag' => $this->tag],
            'queued_at' => now()->subMinutes(3),
            'started_at' => now()->subMinutes(2),
            'finished_at' => now()->subMinutes(1),
        ]);

        $this->actingAsAdmin();

        // 修复后插件表存在性与启用集合会走进程内静态缓存与容器 scoped 缓存；
        // 用例开头的注册表调用已把它们预热，这里清空，保证本次请求是「首次查询」，
        // 否则缓存命中会让真实请求一次 SQL 都不发，防假绿断言会误报。
        SchemaMetadataCache::flush();
        $this->app->forgetScopedInstances();

        $this->capture(fn () => $this->getJson('/api/v2/admin/schedules/overview')->assertOk());

        $taskCount = count($tasks);

        // 防假绿：本次请求必须真的查过 integration_plugins，否则 0 次也能通过，测不到任何东西。
        $touched = $this->countSqlContaining('integration_plugins');
        $this->assertGreaterThan(
            0,
            $touched,
            '本次定时任务总览请求完全未查询 integration_plugins，查询计数断言测不到任何东西（0 次也能通过）。'
        );

        $probes = $this->countSqlContaining("information_schema.tables where table_schema = schema() and table_name = 'integration_plugins'");
        $this->assertLessThanOrEqual(
            1,
            $probes,
            sprintf('integration_plugins 存在性探测重复执行：实测 %d 次（当前 %d 个启用任务），应至多 1 次，说明每个任务都在重新探测插件表是否存在。', $probes, $taskCount)
        );

        $scans = $this->countSqlStartsWith('select * from `integration_plugins`');
        $this->assertLessThanOrEqual(
            2,
            $scans,
            sprintf('integration_plugins 启用插件集合重复查询：实测 %d 次（当前 %d 个启用任务），应至多 2 次，说明每个任务都在重新读取插件清单。', $scans, $taskCount)
        );
    }

    /**
     * P2-10 长驻进程缓存失效：纯 artisan 常驻进程的 request 只绑定一次且永不替换，
     * 仅按请求对象身份比较会让启用插件集合永久命中旧值。
     *
     * 修复前：槽只在「换了 request 实例」时失效，常驻进程内插件被停用后本进程直到重启前都读旧集合。
     * 修复后：写入时记录 stored_at，超过 ENABLED_PLUGINS_CACHE_TTL_SECONDS 即过期重查；
     * 同一作用域内仍然复用，单次请求的常量级去重能力不变。
     */
    public function test_schedule_hook_enabled_plugins_cache_expires_in_long_running_process(): void
    {
        $container = $this->app;

        // 先钉死前提：常驻进程里 request 只绑定一次，按身份比较永远相等，这正是 P2-10 的成因。
        $this->assertTrue($container->bound('request'), '测试容器未绑定 request，无法模拟常驻进程的固定作用域。');
        $this->assertSame(
            $container->make('request'),
            $container->make('request'),
            '测试容器的 request 不是同一实例，无法模拟常驻进程里恒等的作用域。'
        );
        $this->assertTrue(
            SchemaMetadataCache::hasTable('integration_plugins'),
            'integration_plugins 表不存在，缓存失效断言测不到任何东西。'
        );

        $container->forgetScopedInstances();

        // 同一作用域内必须继续命中：TTL 不能把单次请求内的常量级去重打回「每次调用都查库」。
        $cached = IntegrationPlugin::query()->whereKey(-1)->get();
        $slot = ScheduleHookService::enabledPluginsSlot($container);
        $slot->offsetSet('plugins', $cached);
        ScheduleHookService::markEnabledPluginsStored($slot);
        $reusedSlot = ScheduleHookService::enabledPluginsSlot($container);
        $this->assertTrue(
            $reusedSlot->offsetExists('plugins'),
            '同一作用域内第二次取槽未命中缓存，单次请求的常量级去重被破坏。'
        );
        $this->assertSame(
            $cached,
            $reusedSlot->offsetGet('plugins'),
            '同一作用域内第二次取槽拿到的不是同一份启用插件集合，单次请求的常量级去重被破坏。'
        );

        // 常驻进程里 request 恒等，只能靠 stored_at 的 TTL 兜底；三种非正常时间戳都必须判为过期。
        foreach ([
            '写入时间超过 TTL' => microtime(true) - ScheduleHookService::ENABLED_PLUGINS_CACHE_TTL_SECONDS - 1,
            '没有写入时间' => null,
            '写入时间不是浮点数' => 'not-a-float',
        ] as $scene => $storedAt) {
            $slot = ScheduleHookService::enabledPluginsSlot($container);
            $slot->exchangeArray([]);
            $slot->offsetSet('plugins', $cached);
            if ($storedAt === null) {
                $slot->offsetUnset('stored_at');
            } else {
                $slot->offsetSet('stored_at', $storedAt);
            }

            $refreshed = ScheduleHookService::enabledPluginsSlot($container);
            $this->assertFalse(
                $refreshed->offsetExists('plugins'),
                sprintf('%s 的启用插件缓存未失效：常驻进程会把插件启停前的旧集合一直读下去。', $scene)
            );
        }

        // 行为级：TTL 到期后必须真的重新查库，而不是只清空数组后又读到同一份旧集合。
        $container->forgetScopedInstances();
        SchemaMetadataCache::flush();
        $service = $container->make(ScheduleHookService::class);

        $this->sqls = [];
        $this->capturing = true;
        try {
            $service->hasListeners(ScheduleHookService::HOOK_BEFORE_CRON);
            $firstScans = $this->countSqlStartsWith('select * from `integration_plugins`');

            $service->hasListeners(ScheduleHookService::HOOK_BEFORE_CRON);
            $reusedScans = $this->countSqlStartsWith('select * from `integration_plugins`');

            ScheduleHookService::enabledPluginsSlot($container)->offsetSet(
                'stored_at',
                microtime(true) - ScheduleHookService::ENABLED_PLUGINS_CACHE_TTL_SECONDS - 1
            );
            $service->hasListeners(ScheduleHookService::HOOK_BEFORE_CRON);
            $expiredScans = $this->countSqlStartsWith('select * from `integration_plugins`');
        } finally {
            $this->capturing = false;
        }

        // 防假绿：首次必须真的查到插件表，否则后面两条计数断言测不到任何东西。
        $this->assertSame(
            1,
            $firstScans,
            '首次解析插件监听器未查询 integration_plugins，缓存失效断言测不到任何东西。'
        );
        $this->assertSame(
            1,
            $reusedScans,
            '同一请求内第二次解析插件监听器又查了一次启用插件集合，单次请求的常量级去重被破坏。'
        );
        $this->assertSame(
            2,
            $expiredScans,
            '缓存超过 TTL 后没有重新查询启用插件集合，常驻进程会一直读插件启停前的旧清单。'
        );
    }

    /**
     * N16 供应商绑定预载契约：预载关系一旦带 status 过滤，绑定会被误判为不存在。
     *
     * PluginBindingResolver::supplierBindingForSupplier() 在 relationLoaded('pluginBindings') 时以集合为准，
     * 空集合即「无绑定」而不再回退单行查询——这是 N3 修复的前提（避免列表页逐行查询）。
     * 代价是预载方必须整关系加载；本用例造「停用旧绑定 + 启用新绑定」两个供应商，
     * 断言列表接口仍能取到启用那条绑定（provider_key 与 status 均正确）。
     * 若将来有人在 with('pluginBindings') 上加了 status 过滤，集合会变空，
     * provider_key 会退化成空串、upstream_binding 变 null，本用例立即失败。
     */
    public function test_admin_supplier_list_keeps_binding_when_relation_has_disabled_rows(): void
    {
        // 唯一键是 (supplier_id, plugin_id, environment)，同一供应商要挂两条绑定必须用两个插件：
        // 停用插件（id 更小）在前、启用插件（id 更大）在后，用于验证取的是 status 最大的那条。
        $pluginIds = [];
        foreach (['disabled', 'enabled'] as $suffix) {
            $pluginIds[$suffix] = (int) DB::table('integration_plugins')->insertGetId([
                'domain' => 'upstream',
                'slug' => 'reg_n16_'.$suffix.'_'.$this->tag,
                'plugin_key' => 'reg_n16_'.$suffix.'_'.$this->tag,
                'name' => 'N16 契约插件'.$suffix.$this->tag,
                'entry_class' => 'Tests\\Fixtures\\DummyPlugin',
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $providerKeys = [];
        foreach ([0, 1] as $index) {
            $supplierId = (int) DB::table('suppliers')->insertGetId([
                'code' => $this->tag.'N16'.$index,
                'name' => 'N16 契约供应商'.$this->tag.$index,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('supplier_plugin_bindings')->insert([
                'supplier_id' => $supplierId,
                'plugin_id' => $pluginIds['disabled'],
                'provider_key' => 'disabled_'.$this->tag.$index,
                'environment' => 'production',
                'status' => 0,
                'priority' => 9,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $providerKeys[$supplierId] = 'enabled_'.$this->tag.$index;
            DB::table('supplier_plugin_bindings')->insert([
                'supplier_id' => $supplierId,
                'plugin_id' => $pluginIds['enabled'],
                'provider_key' => $providerKeys[$supplierId],
                'environment' => 'production',
                'status' => 1,
                'priority' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 第三个供应商只挂停用绑定：整关系预载能取到它（单行查询同样会返回停用行），
        // 但任何「预载时按 status 过滤」的写法都会把集合变成空，从而误判为「无绑定」。
        $disabledOnlySupplierId = (int) DB::table('suppliers')->insertGetId([
            'code' => $this->tag.'N16X',
            'name' => 'N16 契约停用绑定供应商'.$this->tag,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('supplier_plugin_bindings')->insert([
            'supplier_id' => $disabledOnlySupplierId,
            'plugin_id' => $pluginIds['disabled'],
            'provider_key' => 'disabled_only_'.$this->tag,
            'environment' => 'production',
            'status' => 0,
            'priority' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAsAdmin();

        $response = $this->getJson('/api/v2/admin/suppliers?page_size=50&keyword='.$this->tag)->assertOk();
        $items = collect((array) $response->json('data.list', []))->keyBy('id');

        $this->assertCount(3, $items, '供应商列表未命中本用例造的三行数据，契约断言无效。');

        foreach ($providerKeys as $supplierId => $expectedProviderKey) {
            $row = $items->get($supplierId);
            $this->assertNotNull($row, '供应商列表中缺少 id='.$supplierId.' 的行，契约断言无效。');
            $this->assertSame(
                $expectedProviderKey,
                (string) ($row['provider_key'] ?? ''),
                '供应商 '.$supplierId.' 的启用绑定被预载过滤掉了：supplierBindingForSupplier() 以已加载集合为准，'
                .'空集合即判为无绑定。预载 pluginBindings 时不得附加 status 过滤。'
            );
            $this->assertSame(
                1,
                (int) ($row['upstream_binding']['status'] ?? -1),
                '供应商 '.$supplierId.' 取到的不是启用那条绑定：应取 status 最大、priority 最大、id 最大的行。'
            );
        }

        // 只有停用绑定的供应商：整关系预载下仍应解析出那条绑定（与单行查询结果一致），
        // 一旦预载附加 status 过滤，这里会退化成空串。
        $disabledOnlyRow = $items->get($disabledOnlySupplierId);
        $this->assertNotNull($disabledOnlyRow, '供应商列表中缺少「只有停用绑定」的行，契约断言无效。');
        $this->assertSame(
            'disabled_only_'.$this->tag,
            (string) ($disabledOnlyRow['provider_key'] ?? ''),
            '只有停用绑定的供应商被预载过滤误判为「无绑定」：预载 pluginBindings 必须整关系加载，'
            .'不得附加 status 过滤，否则与单行查询 orderByDesc(status, priority, id)->first() 的语义不一致。'
        );
        $this->assertSame(
            0,
            (int) ($disabledOnlyRow['upstream_binding']['status'] ?? -1),
            '只有停用绑定的供应商，其绑定行 status 应为 0（取的是唯一那条停用绑定）。'
        );
    }

    /**
     * N17 展示名解析器兜底分支的单例一致性：预载必须写进容器单例，否则会被孤儿实例架空。
     *
     * ProductDisplayNameResolver / ProductFullPathResolver 在 AppServiceProvider 中注册为 singleton。
     * 两个 Service 的构造器都允许注入 null，此时兜底分支若写成 `?? new`，就会拿到一个脱离容器的孤儿实例：
     * 列表阶段把预载写进容器实例，逐行解析却发生在孤儿实例上，缓存不命中→仍按行回源。
     *
     * 注意：容器正常解析时依赖是注入进来的，兜底分支根本不会执行，所以这里必须显式构造
     * 「注入 null」的实例来覆盖该分支——直接 app() 取实例再断言是假绿（改回 `?? new` 也照样通过）。
     */
    public function test_product_display_name_resolver_fallbacks_reuse_container_singletons(): void
    {
        $referral = new ReferralService(
            app(PromotionAmbassadorService::class),
            app(OperationLogService::class),
            app(InvoiceService::class),
            null,
        );

        $referralResolver = new ReflectionMethod($referral, 'resolveProductDisplayNameResolver');
        $referralResolver->setAccessible(true);
        $this->assertSame(
            app(ProductDisplayNameResolver::class),
            $referralResolver->invoke($referral),
            'ReferralService 的兜底分支未取用容器内的 ProductDisplayNameResolver 单例：'
            .'预载写在容器实例上、解析发生在孤儿实例上，逐行解析仍会各发一次源商品查询。'
        );

        $finance = new AdminFinanceQueryService(
            app(ProductDisplayNameResolver::class),
            null,
        );

        $financeResolver = new ReflectionMethod($finance, 'productFullPathResolver');
        $financeResolver->setAccessible(true);
        $this->assertSame(
            app(ProductFullPathResolver::class),
            $financeResolver->invoke($finance),
            'AdminFinanceQueryService 的兜底分支未取用容器内的 ProductFullPathResolver 单例：'
            .'其内部持有的展示名解析器会与预载实例脱钩。'
        );

        // 账单列表与优惠券列表的预载同样写在解析器单例上：兜底新建实例会让整页预载变成孤儿缓存。
        $invoiceResolver = new ReflectionMethod(InvoiceService::class, 'resolveProductDisplayNameResolver');
        $invoiceResolver->setAccessible(true);
        $this->assertSame(
            app(ProductDisplayNameResolver::class),
            $invoiceResolver->invoke($this->newInvoiceServiceWithNullResolver()),
            'InvoiceService 的兜底分支未取用容器内的 ProductDisplayNameResolver 单例：'
            .'账单列表预载写在单例上、逐行解析落在孤儿实例上，源商品单查仍随行数增长。'
        );

        $couponResolver = new ReflectionMethod(CouponService::class, 'resolveProductDisplayNameResolver');
        $couponResolver->setAccessible(true);
        $this->assertSame(
            app(ProductDisplayNameResolver::class),
            $couponResolver->invoke($this->newCouponServiceWithNullResolver()),
            'CouponService 的兜底分支未取用容器内的 ProductDisplayNameResolver 单例：'
            .'券列表预载写在单例上、逐行解析落在孤儿实例上，自定义名单查仍随券行数增长。'
        );
    }

    /**
     * 构造「解析器为 null」的 InvoiceService：容器正常解析时走注入单例，兜底分支不执行，
     * 直接 app() 再断言是假绿，必须显式构造成兜底场景。
     */
    private function newInvoiceServiceWithNullResolver(): InvoiceService
    {
        return new InvoiceService(
            $this->app->make(PaymentCallbackProjector::class),
            $this->app->make(TradeLifecycleService::class),
            null,
        );
    }

    /**
     * 构造「解析器为 null」的 CouponService，同上。
     */
    private function newCouponServiceWithNullResolver(): CouponService
    {
        return new CouponService(null, null);
    }

    /**
     * N9 调度 Hook 降级契约：启用插件集合查询抛异常时必须静默降级，不得在调度主链路上抛出。
     *
     * enabledPlugins() 在「插件表不存在」与「查询抛异常」两条路径上都返回 null；
     * pluginListenersFor() 若漏掉 null 判空，就会在 null 上调用 each()，把 hasListeners()/run()/runMany()
     * 全部变成致命错误。而 ScheduleRunLogService::record() 调用 runMany 的位置在 try 之外，
     * 一旦这里抛出，所有调度任务都会停摆。本用例用 Connection::beforeExecuting 注入一次查询异常，锁死该契约。
     */
    public function test_schedule_hook_service_degrades_when_plugin_query_throws(): void
    {
        $service = app(ScheduleHookService::class);
        $connection = DB::connection();
        $property = new ReflectionProperty($connection, 'beforeExecutingCallbacks');
        $originalCallbacks = $property->getValue($connection);

        // Laravel 只提供 beforeExecuting() 追加、没有移除 API，这里直接接管回调表，并在 finally 中还原，
        // 避免注入泄漏到同一进程内其它测试。
        $thrown = 0;
        $property->setValue($connection, []);
        $connection->beforeExecuting(function (string $sql) use (&$thrown): void {
            // 只拦「启用插件集合」查询；表存在性探测（information_schema）与其它 SQL 一律放行。
            if (! str_contains($sql, 'integration_plugins') || ! str_contains($sql, 'order by `domain` asc')) {
                return;
            }

            $thrown++;
            throw new RuntimeException('模拟启用插件集合查询失败（N9 降级契约回归用例注入）');
        });

        try {
            SchemaMetadataCache::flush();
            $this->app->forgetScopedInstances();

            // 断言一：查询抛异常时 hasListeners() 返回 false，而不是在 null 插件集合上调用 each() 抛致命错误。
            try {
                $listened = $service->hasListeners(ScheduleHookService::HOOK_BEFORE_CRON);
            } catch (Throwable $exception) {
                $this->fail(sprintf(
                    '启用插件集合查询抛异常时，hasListeners() 未静默降级为 false 而是抛出 %s：%s。说明 pluginListenersFor() 没有在 null 插件集合上提前返回，而是在 null 上调用了 each()。',
                    $exception::class,
                    $exception->getMessage()
                ));
            }

            $this->assertFalse(
                $listened,
                '启用插件集合查询抛异常时，hasListeners() 未静默降级为 false：说明 pluginListenersFor() 没有在 null 插件集合上提前返回，而是在 null 上调用了 each()。'
            );
            // 防假绿：注入的异常必须真的发生过，否则「返回 false」可能只是没查插件表。
            $this->assertSame(
                1,
                $thrown,
                sprintf('注入的启用插件集合查询异常未被触发（实测 %d 次），hasListeners() 的降级断言测不到任何东西。', $thrown)
            );

            SchemaMetadataCache::flush();
            $this->app->forgetScopedInstances();
            $thrownBeforeMany = $thrown;
            try {
                $results = $service->runMany([
                    ScheduleHookService::HOOK_BEFORE_CRON,
                    ScheduleHookService::HOOK_AFTER_CRON,
                ], []);
            } catch (Throwable $exception) {
                $this->fail(sprintf(
                    '启用插件集合查询抛异常时，runMany() 未静默降级而是抛出 %s：%s。说明异常穿透了 pluginListenersFor()，ScheduleRunLogService::record() 在 try 之外调用 runMany() 会让所有调度任务停摆。',
                    $exception::class,
                    $exception->getMessage()
                ));
            }

            // 断言二：两个 hook 都必须静默降级为空数组，且 runMany() 自身不抛异常。
            $this->assertSame(
                [
                    ScheduleHookService::HOOK_BEFORE_CRON => [],
                    ScheduleHookService::HOOK_AFTER_CRON => [],
                ],
                $results,
                '启用插件集合查询抛异常时，runMany() 未为每个 hook 降级为空数组：说明异常穿透了 pluginListenersFor()，会让调度执行主链路直接抛出。'
            );
            $this->assertSame(
                2,
                $thrown - $thrownBeforeMany,
                sprintf('runMany() 只触发了 %d 次启用插件集合查询异常（应为 2 次），降级断言覆盖不完整。', $thrown - $thrownBeforeMany)
            );

            SchemaMetadataCache::flush();
            $this->app->forgetScopedInstances();
            $thrownBeforeRun = $thrown;

            // 断言三：单 hook 的 run() 同样返回空数组且不抛异常。
            try {
                $singleResults = $service->run(ScheduleHookService::HOOK_BEFORE_CRON, []);
            } catch (Throwable $exception) {
                $this->fail(sprintf(
                    '启用插件集合查询抛异常时，run() 未静默降级而是抛出 %s：%s。说明异常穿透了 pluginListenersFor()。',
                    $exception::class,
                    $exception->getMessage()
                ));
            }

            $this->assertSame(
                [],
                $singleResults,
                '启用插件集合查询抛异常时，run() 未降级为空数组：说明异常穿透了 pluginListenersFor()。'
            );
            $this->assertSame(
                1,
                $thrown - $thrownBeforeRun,
                sprintf('run() 只触发了 %d 次启用插件集合查询异常（应为 1 次），降级断言覆盖不完整。', $thrown - $thrownBeforeRun)
            );
        } finally {
            $property->setValue($connection, $originalCallbacks);
        }
    }

    /**
     * N10 管理端商品列表：拆分商品的展示名要回源商品取规格，源商品必须批量预载。
     * 修复前：3 行拆分商品实测 products 源商品单行查询 3 次（2 行 2 次、20 行 20 次、40 行 40 次）。
     * 造数刻意让每个拆分商品指向不同的源商品，按 id 记忆化的实现仍会逐行回源，只有批量预载能通过。
     */
    public function test_admin_product_list_does_not_query_split_source_product_per_row(): void
    {
        $this->seedSplitProducts(3);
        $this->actingAsAdmin();

        $this->capture(fn () => $this->getJson('/api/v2/admin/products?page_size=50')->assertOk());

        $lookups = $this->countSqlContaining('from `products` where `products`.`id` =');
        $this->assertSame(
            0,
            $lookups,
            sprintf('products 表源商品单行查询随商品行数增长：实测 %d 次（3 行拆分商品，修复前 3 次），应在返回前用一次 whereIn() 批量预载源商品。', $lookups)
        );

        // 防假绿：批量预载必须真的发生，否则上面的 0 次可能只是没走到拆分商品的展示名解析分支。
        $this->assertGreaterThanOrEqual(
            1,
            $this->countSqlContaining('from `products` where `id` in ('),
            '管理端商品列表未走源商品批量预载查询，查询计数断言测不到任何东西。'
        );
    }

    /**
     * N12 客户端订单列表：订单缺 product_spec_snapshot 时，展示名与商品全路径会逐行回源解析。
     * 修复前：3 行拆分商品且无快照，实测源商品单查 24 次、custom_display_name 单查 3 次；
     * 20 行时分别为 160 次与 20 次，随每页行数线性增长。
     * 造数刻意让每个订单指向不同的拆分商品与源商品，按 id 记忆化的实现仍会逐行回源。
     */
    public function test_client_order_list_does_not_query_product_display_name_per_row(): void
    {
        $this->seedClientOrdersWithSplitProducts(3);
        $this->actingAsClient();

        $this->capture(fn () => $this->getJson('/api/v2/client/orders?page_size=50')->assertOk());

        $sourceLookups = $this->countSqlStartsWith('select `id`, `purchase_requires`, `config_options` from `products` where `products`.`id` =');
        $this->assertSame(
            0,
            $sourceLookups,
            sprintf('products 源商品逐行查询随订单行数增长：实测 %d 次（3 行订单，修复前 24 次），应按当页商品的源商品一次 whereIn 预载。', $sourceLookups)
        );

        $displayNames = $this->countSqlStartsWith('select `custom_display_name` from `products` where `products`.`id` =');
        $this->assertSame(
            0,
            $displayNames,
            sprintf('products.custom_display_name 逐行回源随订单行数增长：实测 %d 次（3 行订单，修复前 3 次），应在逐行解析前按当页商品批量预载该列。', $displayNames)
        );

        // 防假绿：两条批量预载查询都必须真的发生，否则上面的 0 次可能只是没走到展示名解析分支。
        $this->assertSame(
            1,
            $this->countSqlStartsWith('select `id`, `purchase_requires`, `config_options` from `products` where `id` in ('),
            '客户端订单列表未走拆分商品源商品批量预载，查询计数断言测不到任何东西。'
        );
        $this->assertSame(
            1,
            $this->countSqlStartsWith('select `custom_display_name`, `id` from `products` where `id` in ('),
            '客户端订单列表未走 custom_display_name 批量预载，查询计数断言测不到任何东西。'
        );
    }

    /**
     * N13 客户端工单服务选项：服务行上的商品展示名、拆分源商品与插件投影都曾逐行回源。
     *
     * 修复前：3 行服务实测源商品单查 12 次、custom_display_name 单查 3 次，
     * 服务上游绑定 / 运行快照 / 连接快照各 3 次；20 行时分别为 80 / 20 / 20 / 20 / 20 次，
     * 随 limit 上限 50 线性增长。造数让每行指向独立商品与独立绑定，按 id 记忆化的实现仍会逐行回源。
     */
    public function test_client_ticket_service_options_does_not_query_per_row(): void
    {
        $this->seedClientServicesWithProductBindings(3);
        $this->actingAsClient();

        $this->capture(fn () => $this->getJson('/api/v2/client/tickets/service-options?limit=50')->assertOk());

        $sourceLookups = $this->countSqlStartsWith('select `id`, `purchase_requires`, `config_options` from `products` where `products`.`id` =');
        $this->assertSame(
            0,
            $sourceLookups,
            sprintf('products 源商品逐行查询随服务行数增长：实测 %d 次（3 行服务，修复前 12 次），应按当页商品一次 whereIn 预载。', $sourceLookups)
        );

        $displayNames = $this->countSqlStartsWith('select `custom_display_name` from `products` where `products`.`id` =');
        $this->assertSame(
            0,
            $displayNames,
            sprintf('products.custom_display_name 逐行回源随服务行数增长：实测 %d 次（3 行服务，修复前 3 次），应在逐行解析前批量预载该列。', $displayNames)
        );

        $bindings = $this->countSqlContaining('`sub`.`service_id` =');
        $this->assertSame(
            0,
            $bindings,
            sprintf('service_upstream_bindings 表逐行查询随服务行数增长：实测 %d 次（3 行服务，修复前 3 次），应按当页服务一次 whereIn 预载插件投影。', $bindings)
        );

        $runtimeSnapshots = $this->countSqlContaining('from `service_runtime_snapshots` where `service_id` =');
        $this->assertSame(
            0,
            $runtimeSnapshots,
            sprintf('service_runtime_snapshots 表逐行查询随服务行数增长：实测 %d 次（3 行服务，修复前 3 次），应按当页服务一次 whereIn 预载。', $runtimeSnapshots)
        );

        $connectionSnapshots = $this->countSqlContaining('from `service_connection_snapshots` where `service_id` =');
        $this->assertSame(
            0,
            $connectionSnapshots,
            sprintf('service_connection_snapshots 表逐行查询随服务行数增长：实测 %d 次（3 行服务，修复前 3 次），应按当页服务一次 whereIn 预载。', $connectionSnapshots)
        );

        // 防假绿：五条批量预载都必须真的发生，否则上面的 0 次可能只是没走到对应分支。
        $this->assertSame(
            1,
            $this->countSqlStartsWith('select `id`, `purchase_requires`, `config_options` from `products` where `id` in ('),
            '工单服务选项未走拆分源商品批量预载，查询计数断言测不到任何东西。'
        );
        $this->assertSame(
            1,
            $this->countSqlStartsWith('select `custom_display_name`, `id` from `products` where `id` in ('),
            '工单服务选项未走 custom_display_name 批量预载，查询计数断言测不到任何东西。'
        );
        $this->assertSame(
            1,
            $this->countSqlContaining('`sub`.`service_id` in ('),
            '工单服务选项未走服务上游绑定批量预载，查询计数断言测不到任何东西。'
        );
        $this->assertSame(
            1,
            $this->countSqlContaining('from `service_runtime_snapshots` where `service_id` in ('),
            '工单服务选项未走运行快照批量预载，查询计数断言测不到任何东西。'
        );
        $this->assertSame(
            1,
            $this->countSqlContaining('from `service_connection_snapshots` where `service_id` in ('),
            '工单服务选项未走连接快照批量预载，查询计数断言测不到任何东西。'
        );
    }

    /**
     * N14 管理端订单列表：订单展示名与商品全路径经 ProductFullPathResolver 回源到
     * ProductDisplayNameResolver，拆分商品的源商品曾按 purchase_requires.upstream_split.source_product_id
     * 逐行 find()。
     *
     * 修复前：3 行订单实测源商品单查 24 次（nameForOrder 与 pathForOrder 各触发一轮，内部多次单查），
     * 20 行 160 次，随每页行数线性增长。订单即使带 product_spec_snapshot 也会逐行回源：
     * nameForOrder 的 ?? 左侧是 display_product_name 访问器，求值即触发 product_name_snapshot 回源。
     */
    public function test_admin_order_list_does_not_query_split_source_product_per_row(): void
    {
        $this->seedClientOrdersWithSplitProducts(3, 'renew');
        $this->actingAsAdmin();

        $this->capture(fn () => $this->getJson('/api/v2/admin/orders?page_size=50')->assertOk());

        $sourceLookups = $this->countSqlStartsWith('select `id`, `purchase_requires`, `config_options` from `products` where `products`.`id` =');
        $this->assertSame(
            0,
            $sourceLookups,
            sprintf('products 源商品逐行查询随订单行数增长：实测 %d 次（3 行订单，修复前 24 次），应按当页商品的源商品一次 whereIn 预载。', $sourceLookups)
        );

        // 防假绿：批量预载必须真的发生，否则上面的 0 次可能只是没走到拆分商品的展示名解析分支。
        $this->assertSame(
            1,
            $this->countSqlStartsWith('select `id`, `purchase_requires`, `config_options` from `products` where `id` in ('),
            '管理端订单列表未走拆分商品源商品批量预载，查询计数断言测不到任何东西。'
        );
    }

    /**
     * N14 财务续费订单列表：与订单列表同一条回源链路，另外商品投影刻意未取 custom_display_name，
     * 解析时还会逐行补查该列。
     *
     * 修复前：3 行续费订单实测源商品单查 24 次、custom_display_name 单查 3 次；20 行分别为
     * 160 次与 20 次。订单不带 product_spec_snapshot 时才会走回源分支，造数刻意保持为空。
     */
    public function test_admin_finance_renewal_orders_do_not_query_product_display_name_per_row(): void
    {
        $this->seedClientOrdersWithSplitProducts(3, 'renew');
        $this->actingAsAdmin();

        $this->capture(fn () => $this->getJson('/api/v2/admin/finance/renewal-orders?page_size=50')->assertOk());

        $sourceLookups = $this->countSqlStartsWith('select `id`, `purchase_requires`, `config_options` from `products` where `products`.`id` =');
        $this->assertSame(
            0,
            $sourceLookups,
            sprintf('products 源商品逐行查询随续费订单行数增长：实测 %d 次（3 行订单，修复前 24 次），应按当页商品的源商品一次 whereIn 预载。', $sourceLookups)
        );

        $displayNames = $this->countSqlStartsWith('select `custom_display_name` from `products` where `products`.`id` =');
        $this->assertSame(
            0,
            $displayNames,
            sprintf('products.custom_display_name 逐行回源随续费订单行数增长：实测 %d 次（3 行订单，修复前 3 次），应在逐行解析前按当页商品批量预载该列。', $displayNames)
        );

        // 防假绿：两条批量预载都必须真的发生，否则上面的 0 次可能只是没走到展示名解析分支。
        $this->assertSame(
            1,
            $this->countSqlStartsWith('select `id`, `purchase_requires`, `config_options` from `products` where `id` in ('),
            '财务续费订单列表未走拆分商品源商品批量预载，查询计数断言测不到任何东西。'
        );
        $this->assertSame(
            1,
            $this->countSqlStartsWith('select `custom_display_name`, `id` from `products` where `id` in ('),
            '财务续费订单列表未走 custom_display_name 批量预载，查询计数断言测不到任何东西。'
        );
    }

    /**
     * N14 财务附加配置（升级）单列表：与续费列表同一条回源链路，商品三级分组曾逐行懒加载。
     *
     * 修复前：每出现一个新的商品，ProductFullPathResolver::segmentsForProduct() 就为该商品
     * 各发三次分组查询（3 行 3 个商品实测 third/second/first 各 3 次），随当页不同商品数增长。
     */
    public function test_admin_finance_upgrade_orders_do_not_lazy_load_product_groups_per_row(): void
    {
        $this->seedClientOrdersWithSplitProducts(3, 'upgrade');
        $this->actingAsAdmin();

        $this->capture(fn () => $this->getJson('/api/v2/admin/finance/upgrade-orders?page_size=50')->assertOk());

        foreach (['third_product_groups', 'second_product_groups', 'first_product_groups'] as $table) {
            $lazy = $this->countSqlStartsWith('select * from `'.$table.'`');
            $this->assertSame(
                0,
                $lazy,
                sprintf('%s 表逐行懒加载随附加配置行数增长：实测 %d 次（3 行数据，修复前每行一次），应在附加配置查询的 with() 中预载商品三级分组。', $table, $lazy)
            );

            // 防假绿：分组关系必须真的被预载过，否则上面的 0 次可能只是没走到 product_full_path 分支。
            $this->assertSame(
                1,
                $this->countSqlContaining('from `'.$table.'` where `'.$table.'`.`id` in ('),
                '附加配置列表未走商品三级分组批量预载，查询计数断言测不到任何东西。'
            );
        }
    }

    /**
     * N15 推荐奖励出现「发票来源」（order_id 为空）的行：没有订单可回溯全路径，展示名只能由
     * 奖励商品自身的拆分源商品解析。曾在此逐行调用 preloadSourceProducts()，该方法是「整批替换」
     * 语义，逐行调用会清空列表阶段建好的源商品映射，随后每行各发一次 whereIn + 一次单查。
     *
     * 修复前：3 行实测源商品单查 2 次、whereIn 5 次；20 行分别为 19 次与 22 次，随行数线性增长。
     */
    public function test_admin_referral_rewards_without_order_do_not_reload_source_products_per_row(): void
    {
        $this->seedReferralRewardsWithSplitProducts(3, false);
        $this->actingAsAdmin();

        $this->capture(fn () => $this->getJson('/api/v2/admin/referral/rewards?page_size=50')->assertOk());

        $sourceLookups = $this->countSqlStartsWith('select `id`, `purchase_requires`, `config_options` from `products` where `products`.`id` =');
        $this->assertSame(
            0,
            $sourceLookups,
            sprintf('products 源商品逐行查询随无订单奖励行数增长：实测 %d 次（3 行奖励，修复前每行多次回源），应在分页后按当页商品一次 whereIn 预载。', $sourceLookups)
        );

        $this->assertSame(
            1,
            $this->countSqlStartsWith('select `id`, `purchase_requires`, `config_options` from `products` where `id` in ('),
            '无订单奖励的源商品预载不是整批一次：实测 '.$this->countSqlStartsWith('select `id`, `purchase_requires`, `config_options` from `products` where `id` in (').' 次（3 行奖励），应为 1 次。'
        );
    }

    /**
     * N15 管理端推荐奖励列表：奖励行的展示名经 ProductFullPathResolver 回溯订单商品的源商品；
     * 商品投影刻意不含 custom_display_name，解析时还会逐行补查该列。
     *
     * 修复前：3 行奖励（无快照）实测源商品单查 24 次、custom_display_name 单查 3 次；
     * 20 行时分别为 160 次与 20 次，随每页行数线性增长。
     * 造数让每条奖励指向独立拆分商品与独立源商品，按 id 记忆化的实现仍会逐行回源。
     */
    public function test_admin_referral_rewards_do_not_query_source_product_or_custom_name_per_row(): void
    {
        $this->seedReferralRewardsWithSplitProducts(3);
        $this->actingAsAdmin();

        $this->capture(fn () => $this->getJson('/api/v2/admin/referral/rewards?page_size=50')->assertOk());

        $sourceLookups = $this->countSqlStartsWith('select `id`, `purchase_requires`, `config_options` from `products` where `products`.`id` =');
        $this->assertSame(
            0,
            $sourceLookups,
            sprintf('products 源商品逐行查询随奖励行数增长：实测 %d 次（3 行奖励；修复前每行多次回源，随每页行数线性增长），应按当页商品的源商品一次 whereIn 预载。', $sourceLookups)
        );

        $displayNames = $this->countSqlStartsWith('select `custom_display_name` from `products` where `products`.`id` =');
        $this->assertSame(
            0,
            $displayNames,
            sprintf('products.custom_display_name 逐行回源随奖励行数增长：实测 %d 次（3 行奖励；修复前每行一次独立回源），应在逐行解析前按当页商品批量预载该列。', $displayNames)
        );

        // 防假绿：两条批量预载都必须真的发生，否则上面的 0 次可能只是没走到展示名解析分支。
        // 必须是「恰好 1 次」：preloadSourceProducts() 是整批替换语义，逐行调用会把批次映射清空并
        // 每行各发一次 whereIn，这正是修复前 3 行实测 5 次、20 行 22 次 whereIn 的来源。
        $this->assertSame(
            1,
            $this->countSqlStartsWith('select `id`, `purchase_requires`, `config_options` from `products` where `id` in ('),
            '管理端推荐奖励列表的拆分商品源商品预载不是整批一次：实测 '.$this->countSqlStartsWith('select `id`, `purchase_requires`, `config_options` from `products` where `id` in (').' 次（3 行奖励），应在分页后一次性预载，禁止逐行调用。'
        );
        $this->assertSame(
            1,
            $this->countSqlStartsWith('select `custom_display_name`, `id` from `products` where `id` in ('),
            '管理端推荐奖励列表未走 custom_display_name 批量预载，查询计数断言测不到任何东西。'
        );
    }

    /**
     * N15 客户端推荐奖励列表：与客户端订单、工单同一条回源链路，奖励商品与订单商品各自解析一次展示名。
     *
     * 修复前：3 行奖励（无快照）实测源商品单查 24 次；20 行时 160 次。
     * 客户端投影里的 product.custom_display_name 直接读模型列（投影不带该列），不触发补查。
     */
    public function test_client_referral_rewards_do_not_query_source_product_per_row(): void
    {
        $this->seedReferralRewardsWithSplitProducts(3);
        $this->actingAsClient();

        $this->capture(fn () => $this->getJson('/api/v2/client/referral/rewards?page_size=50')->assertOk());

        $sourceLookups = $this->countSqlStartsWith('select `id`, `purchase_requires`, `config_options` from `products` where `products`.`id` =');
        $this->assertSame(
            0,
            $sourceLookups,
            sprintf('products 源商品逐行查询随奖励行数增长：实测 %d 次（3 行奖励；修复前每行多次回源，随每页行数线性增长），应按当页商品的源商品一次 whereIn 预载。', $sourceLookups)
        );

        $this->assertGreaterThanOrEqual(
            1,
            $this->countSqlStartsWith('select `id`, `purchase_requires`, `config_options` from `products` where `id` in ('),
            '客户端推荐奖励列表未走拆分商品源商品批量预载，查询计数断言测不到任何东西。'
        );
    }

    /**
     * 审查新发现 A：管理端商品列表带 keyword 时，展示名匹配会逐行回源自定义展示名。
     *
     * ProductAdminService::resolveDisplayNameMatchedProductIds() 的 chunkById 投影不含
     * custom_display_name，只预载源商品时不补这一列，解析器会退回每行一次
     * value('custom_display_name') 单查。修复前：20 个命中商品实测 20 次、60 个 60 次，
     * 随扫描行数线性增长。
     */
    public function test_admin_product_keyword_filter_does_not_query_custom_display_name_per_row(): void
    {
        $this->seedKeywordProducts(5, '关键词商品');
        $this->actingAsAdmin();

        $keyword = urlencode('关键词商品'.$this->tag);
        $this->capture(fn () => $this->getJson('/api/v2/admin/products?keyword='.$keyword)->assertOk());

        $displayNameLookups = $this->countSqlStartsWith('select `custom_display_name` from `products` where `products`.`id` =');
        $this->assertSame(
            0,
            $displayNameLookups,
            sprintf('products.custom_display_name 逐行回源随关键词命中行数增长：实测 %d 次（5 行命中商品；修复前每行一次单查），应在逐行解析前按批一次 whereIn 预载该列。', $displayNameLookups)
        );

        // 防假绿：批量预载必须真的发生，否则上面的 0 次可能只是没走到展示名解析分支。
        $this->assertGreaterThanOrEqual(
            1,
            $this->countSqlStartsWith('select `custom_display_name`, `id` from `products` where `id` in ('),
            '管理端商品列表的关键词匹配未走 custom_display_name 批量预载，查询计数断言测不到任何东西。'
        );
    }

    /**
     * 审查新发现 B：管理端推荐奖励带 keyword 时，关键词匹配的商品集合逐行回源自定义展示名。
     *
     * ReferralService::resolveProductIdsByKeyword() 的投影不含 custom_display_name，
     * 且原本没有任何预载，每扫描一个商品就发一次 value('custom_display_name') 单查。
     * 修复前：20 个商品实测 20 次、60 个 60 次，随商品数线性增长。
     */
    public function test_admin_referral_reward_keyword_filter_does_not_query_custom_display_name_per_row(): void
    {
        $this->seedKeywordRewards(5, '关键词奖励');
        $this->actingAsAdmin();

        $keyword = urlencode('关键词奖励'.$this->tag);
        $this->capture(fn () => $this->getJson('/api/v2/admin/referral/rewards?keyword='.$keyword)->assertOk());

        $displayNameLookups = $this->countSqlStartsWith('select `custom_display_name` from `products` where `products`.`id` =');
        $this->assertSame(
            0,
            $displayNameLookups,
            sprintf('products.custom_display_name 逐行回源随关键词匹配商品数增长：实测 %d 次（5 个候选商品；修复前每行一次单查），应在逐行解析前按批一次 whereIn 预载该列。', $displayNameLookups)
        );

        $this->assertGreaterThanOrEqual(
            1,
            $this->countSqlStartsWith('select `custom_display_name`, `id` from `products` where `id` in ('),
            '管理端推荐奖励的关键词匹配未走 custom_display_name 批量预载，查询计数断言测不到任何东西。'
        );
    }

    /**
     * 审查新发现 C：优惠券列表的适用范围商品名逐行回源自定义展示名。
     *
     * CouponService::resolveProductNameMapFromCoupons() 的投影不含 custom_display_name，
     * 且原本没有预载。修复前：20 张券（各绑 1 个独立商品）实测 20 次单查，随券行数线性增长。
     */
    public function test_admin_coupon_list_does_not_query_custom_display_name_per_row(): void
    {
        $this->seedCoupons(5);
        $this->actingAsAdmin();

        $this->capture(fn () => $this->getJson('/api/v2/admin/coupons?page_size=50')->assertOk());

        $displayNameLookups = $this->countSqlStartsWith('select `custom_display_name` from `products` where `products`.`id` =');
        $this->assertSame(
            0,
            $displayNameLookups,
            sprintf('products.custom_display_name 逐行回源随优惠券行数增长：实测 %d 次（5 张券各绑 1 个独立商品；修复前每张券一次单查），应在逐行解析前按批一次 whereIn 预载该列。', $displayNameLookups)
        );

        $this->assertGreaterThanOrEqual(
            1,
            $this->countSqlStartsWith('select `custom_display_name`, `id` from `products` where `id` in ('),
            '管理端优惠券列表未走 custom_display_name 批量预载，查询计数断言测不到任何东西。'
        );
    }

    /**
     * 审查新发现 D-1：管理端账单列表的展示名解析逐行回源拆分母商品。
     *
     * InvoiceService::adminList() 原本分页后直接逐行渲染，展示名解析会为每个拆分商品
     * 单查一次源商品。修复前：3 行实测 9 次（每行 3 次）、20 行 60 次，随当页行数线性增长。
     */
    public function test_admin_invoice_list_does_not_query_split_source_product_per_row(): void
    {
        $this->seedInvoicesWithSplitProducts(3);
        $this->actingAsAdmin();

        $this->capture(fn () => $this->getJson('/api/v2/admin/invoices?page_size=50')->assertOk());

        $sourceLookups = $this->countSqlStartsWith('select `id`, `purchase_requires`, `config_options` from `products` where `products`.`id` =');
        $this->assertSame(
            0,
            $sourceLookups,
            sprintf('products 源商品逐行查询随账单行数增长：实测 %d 次（3 行账单；修复前每行 3 次，随每页行数线性增长），应按当页商品的源商品一次 whereIn 预载。', $sourceLookups)
        );

        $this->assertGreaterThanOrEqual(
            1,
            $this->countSqlStartsWith('select `id`, `purchase_requires`, `config_options` from `products` where `id` in ('),
            '管理端账单列表未走拆分商品源商品批量预载，查询计数断言测不到任何东西。'
        );
    }

    /**
     * 审查新发现 D-2：客户端账单列表与 D-1 同一条回源链路。
     * 修复前：3 行实测源商品单查 9 次；20 行 60 次。
     */
    public function test_client_invoice_list_does_not_query_split_source_product_per_row(): void
    {
        $this->seedInvoicesWithSplitProducts(3);
        $this->actingAsClient();

        $this->capture(fn () => $this->getJson('/api/v2/client/invoices?page_size=50')->assertOk());

        $sourceLookups = $this->countSqlStartsWith('select `id`, `purchase_requires`, `config_options` from `products` where `products`.`id` =');
        $this->assertSame(
            0,
            $sourceLookups,
            sprintf('products 源商品逐行查询随账单行数增长：实测 %d 次（3 行账单；修复前每行 3 次，随每页行数线性增长），应按当页商品的源商品一次 whereIn 预载。', $sourceLookups)
        );

        $this->assertGreaterThanOrEqual(
            1,
            $this->countSqlStartsWith('select `id`, `purchase_requires`, `config_options` from `products` where `id` in ('),
            '客户端账单列表未走拆分商品源商品批量预载，查询计数断言测不到任何东西。'
        );
    }

    /**
     * N18 管理端 CPU 型号目录：绑定商品的展示名投影（purchase_requires/config_options/
     * custom_display_name）曾被 normalizeBindings 逐条 find()，目录绑定越多查询越多；
     * 拆分商品还会按 upstream_split.source_product_id 再逐条回源一次。
     *
     * 修复前：3 个绑定商品实测 products 单查 9 次（每商品 3 次），20 个绑定 60 次。
     */
    public function test_admin_cpu_model_catalog_does_not_query_product_per_binding_row(): void
    {
        $productIds = $this->seedCatalogSplitBindings(3);
        $this->writeCpuModelCatalog($productIds);
        $this->actingAsAdmin();

        $this->capture(fn () => $this->getJson('/api/v2/admin/cpu-model-catalog')->assertOk());

        $lookups = $this->countSqlContaining('from `products` where `products`.`id` =');
        $this->assertSame(
            0,
            $lookups,
            sprintf('CPU 型号目录按绑定商品逐条查询 products：实测 %d 次（3 个绑定商品，修复前 9 次），应在规范化前用一次 whereIn() 批量取回绑定商品。', $lookups)
        );

        // 防假绿：绑定商品批量投影必须真的发生。
        $this->assertGreaterThanOrEqual(
            1,
            $this->countSqlStartsWith('select `id`, `purchase_requires`, `config_options`, `custom_display_name` from `products` where `id` in ('),
            'CPU 型号目录未走绑定商品批量投影，查询计数断言测不到任何东西。'
        );

        // 拆分商品源商品必须整批预载，而不是逐条回源。
        $this->assertGreaterThanOrEqual(
            1,
            $this->countSqlStartsWith('select `id`, `purchase_requires`, `config_options` from `products` where `id` in ('),
            'CPU 型号目录未走拆分商品源商品批量预载，逐条回源仍会随绑定行数增长。'
        );
    }

    /**
     * N19 管理端实例规格目录：与 N18 同一条逐条回源链路（InstanceSpecCatalogService
     * 的 normalizeBindings → resolveBindingDisplayPayload）。
     *
     * 修复前：3 个绑定商品实测 products 单查 9 次（每商品 3 次），20 个绑定 60 次。
     */
    public function test_admin_instance_spec_catalog_does_not_query_product_per_binding_row(): void
    {
        $productIds = $this->seedCatalogSplitBindings(3);
        $this->writeInstanceSpecCatalog($productIds);
        $this->actingAsAdmin();

        $this->capture(fn () => $this->getJson('/api/v2/admin/instance-spec-catalog')->assertOk());

        $lookups = $this->countSqlContaining('from `products` where `products`.`id` =');
        $this->assertSame(
            0,
            $lookups,
            sprintf('实例规格目录按绑定商品逐条查询 products：实测 %d 次（3 个绑定商品，修复前 9 次），应在规范化前用一次 whereIn() 批量取回绑定商品。', $lookups)
        );

        $this->assertGreaterThanOrEqual(
            1,
            $this->countSqlStartsWith('select `id`, `purchase_requires`, `config_options`, `custom_display_name` from `products` where `id` in ('),
            '实例规格目录未走绑定商品批量投影，查询计数断言测不到任何东西。'
        );

        $this->assertGreaterThanOrEqual(
            1,
            $this->countSqlStartsWith('select `id`, `purchase_requires`, `config_options` from `products` where `id` in ('),
            '实例规格目录未走拆分商品源商品批量预载，逐条回源仍会随绑定行数增长。'
        );
    }

    // ---------------------------------------------------------------- helpers

    /**
     * 目录用例造数：每个「拆分商品」指向各自独立的源商品，用于同时击穿
     * 「按绑定商品逐条 find」与「按源商品逐条回源」两跳查询。
     *
     * @return list<int>
     */
    private function seedCatalogSplitBindings(int $rows): array
    {
        $productIds = [];

        for ($i = 0; $i < $rows; $i++) {
            $sourceId = (int) DB::table('products')->insertGetId([
                'product_type' => 'cloud_host',
                'service_type_code' => 'cloud_host',
                'custom_display_name' => '目录源商品'.$this->tag.'_'.$i,
                'pricing' => json_encode(['monthly' => '10.00']),
                'purchase_requires' => json_encode([
                    'upstream_default_config' => ['cpu' => '2核', 'memory' => '4G'],
                ]),
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $productIds[] = (int) DB::table('products')->insertGetId([
                'product_type' => 'cloud_host',
                'service_type_code' => 'cloud_host',
                'custom_display_name' => '目录绑定商品'.$this->tag.'_'.$i,
                'pricing' => json_encode(['monthly' => '20.00']),
                'purchase_requires' => json_encode([
                    'upstream_split' => [
                        'source_product_id' => $sourceId,
                        'source_product_name' => '目录源商品'.$this->tag.'_'.$i,
                        'variant_key' => 'v'.$i,
                    ],
                ]),
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $productIds;
    }

    /**
     * @param  list<int>  $productIds
     */
    private function writeCpuModelCatalog(array $productIds): void
    {
        $bindings = [];
        foreach ($productIds as $index => $productId) {
            $bindings[] = [
                'product_id' => $productId,
                'category_full_name' => '目录分类'.$this->tag.'_'.$index,
                'primary_price' => ['cycle' => 'month', 'amount' => '20.00'],
                'status' => 1,
            ];
        }

        Setting::setValue('product', 'cpu_model_catalog', json_encode([
            [
                'id' => 'group_'.$this->tag,
                'value' => 'group_'.$this->tag,
                'name' => '目录分组'.$this->tag,
                'models' => [
                    [
                        'id' => 'model_'.$this->tag,
                        'value' => 'model_'.$this->tag,
                        'name' => '目录型号'.$this->tag,
                        'base_frequency' => '2.0GHz',
                        'turbo_frequency' => '3.0GHz',
                        'bindings' => $bindings,
                    ],
                ],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param  list<int>  $productIds
     */
    private function writeInstanceSpecCatalog(array $productIds): void
    {
        $bindings = [];
        foreach ($productIds as $productId) {
            $bindings[] = [
                'product_id' => $productId,
                'status' => '启用',
                'primary_price' => ['cycle' => 'month', 'amount' => '20.00'],
            ];
        }

        Setting::setValue('product', 'instance_spec_catalog', json_encode([
            [
                'id' => 'spec_'.$this->tag,
                'value' => 'spec_'.$this->tag,
                'text' => '目录规格'.$this->tag,
                'alias' => '别名'.$this->tag,
                'note' => '备注'.$this->tag,
                'status' => '启用',
                'bindings' => $bindings,
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function assertProductPathQueriesDoNotScale(string $scene): void
    {
        $lazyProducts = $this->countSqlStartsWith('select * from `products` where `products`.`id` =');
        $this->assertSame(
            0,
            $lazyProducts,
            sprintf('%s：products 表逐行懒加载随奖励行数增长，实测 %d 次（3 行数据，修复前 3 次），应在 with() 中预载 order.product/product。', $scene, $lazyProducts)
        );

        foreach (['third_product_groups', 'second_product_groups', 'first_product_groups'] as $table) {
            $n = $this->countSqlStartsWith('select * from `'.$table.'`');
            $this->assertSame(
                0,
                $n,
                sprintf('%s：%s 表逐行懒加载随奖励行数增长，实测 %d 次（3 行数据，修复前 3 次），应预载商品三级分组。', $scene, $table, $n)
            );
        }
    }

    /**
     * @param  callable(): TestResponse  $request
     */
    private function capture(callable $request): TestResponse
    {
        foreach (['', 'localhost', '127.0.0.1'] as $domain) {
            RateLimiter::clear('api'.sha1($domain.'|127.0.0.1'));
            RateLimiter::clear('api'.sha1($domain.'|::1'));
        }

        $this->sqls = [];
        $this->capturing = true;

        try {
            $response = $request();
        } finally {
            $this->capturing = false;
        }

        // 防假绿：响应必须真的包含本用例造的数据，否则断言 0 次毫无意义。
        $this->assertStringContainsString(
            $this->tag,
            $response->getContent(),
            '响应中未出现本用例造数标记 '.$this->tag.'，说明请求未命中测试数据，查询计数断言无效。'
        );

        return $response;
    }

    private function countSqlStartsWith(string $prefix): int
    {
        return count(array_filter($this->sqls, static fn (string $sql): bool => str_starts_with($sql, $prefix)));
    }

    private function countSqlContaining(string $needle): int
    {
        return count(array_filter($this->sqls, static fn (string $sql): bool => str_contains($sql, $needle)));
    }

    private function actingAsAdmin(): void
    {
        $role = Role::query()->create([
            'name' => 'reg_role_'.$this->tag,
            'label' => '回归断言只读角色',
            'permissions' => [AdminPermissions::ALL],
        ]);

        $admin = AdminUser::query()->create([
            'username' => 'reg_admin_'.$this->tag,
            'password' => 'secret123',
            'nickname' => '回归断言管理员',
            'status' => 1,
            'role_id' => (int) $role->id,
        ]);

        Sanctum::actingAs($admin);
    }

    private function actingAsClient(): void
    {
        Sanctum::actingAs(User::query()->findOrFail($this->uid), ['*'], 'sanctum');
    }

    private function ensureUser(): void
    {
        if ($this->uid > 0) {
            return;
        }

        $this->uid = (int) DB::table('users')->insertGetId([
            'email' => $this->tag.'@regression.test',
            'password' => bcrypt('x'),
            'nickname' => '回归'.$this->tag,
            'status' => 1,
            'referral_code' => $this->tag,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('user_accounts')->insert([
            'user_id' => $this->uid,
            'cash_balance' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function ensureProduct(): void
    {
        if ($this->pid > 0) {
            return;
        }

        $fpg = DB::table('first_product_groups')->insertGetId([
            'name' => '一组'.$this->tag, 'code' => 'c1'.$this->tag,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $spg = DB::table('second_product_groups')->insertGetId([
            'first_product_group_id' => $fpg, 'name' => '二组'.$this->tag,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $tpg = DB::table('third_product_groups')->insertGetId([
            'second_product_group_id' => $spg, 'name' => '三组'.$this->tag,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->pid = (int) DB::table('products')->insertGetId([
            'product_type' => 'cloud_host',
            'service_type_code' => 'cloud_host',
            'custom_display_name' => '回归产品'.$this->tag,
            'product_group_id' => $tpg,
            'pricing' => json_encode(['monthly' => '35.00']),
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * 拆分商品造数：每个拆分商品指向各自独立的源商品。
     * 刻意不复用同一个源商品——按 id 进程内记忆化的实现仍会逐行回源，造数必须能击穿它。
     */
    private function seedSplitProducts(int $rows): void
    {
        $this->ensureUser();

        for ($i = 0; $i < $rows; $i++) {
            $sourceId = (int) DB::table('products')->insertGetId([
                'product_type' => 'cloud_host',
                'service_type_code' => 'cloud_host',
                'custom_display_name' => '回归源商品'.$this->tag.'_'.$i,
                'pricing' => json_encode(['monthly' => '10.00']),
                'purchase_requires' => json_encode([
                    'upstream_default_config' => ['cpu' => '2核', 'memory' => '4G'],
                ]),
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('products')->insert([
                'product_type' => 'cloud_host',
                'service_type_code' => 'cloud_host',
                'custom_display_name' => '回归拆分商品'.$this->tag.'_'.$i,
                'pricing' => json_encode(['monthly' => '20.00']),
                'purchase_requires' => json_encode([
                    'upstream_split' => [
                        'source_product_id' => $sourceId,
                        'source_product_name' => '回归源商品'.$this->tag.'_'.$i,
                        'variant_key' => 'v'.$i,
                    ],
                ]),
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * 客户端服务造数：每个服务配独立商品、独立拆分源商品、独立商品级上游绑定。
     * 刻意不复用商品与绑定——按 id 进程内记忆化的实现仍会逐行回源，造数必须能击穿它。
     */
    private function seedClientServicesWithProductBindings(int $rows): void
    {
        $this->ensureUser();

        $supplierId = (int) DB::table('suppliers')->insertGetId([
            'code' => $this->tag.'CS',
            'name' => '回归供应商'.$this->tag,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $pluginId = (int) DB::table('integration_plugins')->insertGetId([
            'domain' => 'servers',
            'slug' => 'n11'.$this->tag,
            'plugin_key' => 'n11'.$this->tag,
            'name' => '回归插件'.$this->tag,
            'version' => '1.0.0',
            'entry_class' => 'X',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $bindingId = (int) DB::table('supplier_plugin_bindings')->insertGetId([
            'supplier_id' => $supplierId,
            'plugin_id' => $pluginId,
            'provider_key' => 'zjmf',
            'status' => 1,
            'priority' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $fpg = DB::table('first_product_groups')->insertGetId([
            'name' => '一组'.$this->tag, 'code' => 'c1'.$this->tag,
            'is_visible' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $spg = DB::table('second_product_groups')->insertGetId([
            'first_product_group_id' => $fpg, 'name' => '二组'.$this->tag,
            'is_visible' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $tpg = DB::table('third_product_groups')->insertGetId([
            'second_product_group_id' => $spg, 'name' => '三组'.$this->tag,
            'is_visible' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        for ($i = 0; $i < $rows; $i++) {
            $sourceId = (int) DB::table('products')->insertGetId([
                'product_type' => 'cloud_server',
                'service_type_code' => 'cloud_server',
                'custom_display_name' => '回归源商品'.$this->tag.'_'.$i,
                'pricing' => json_encode(['monthly' => '10.00']),
                'purchase_requires' => json_encode([
                    'upstream_default_config' => ['cpu' => '2核', 'memory' => '4G'],
                ]),
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $productId = (int) DB::table('products')->insertGetId([
                'product_type' => 'cloud_server',
                'service_type_code' => 'cloud_server',
                'custom_display_name' => '回归拆'.$this->tag.'_'.$i,
                'product_group_id' => $tpg,
                'pricing' => json_encode(['monthly' => '20.00']),
                'purchase_requires' => json_encode([
                    'upstream_split' => [
                        'source_product_id' => $sourceId,
                        'source_product_name' => '回归源商品'.$this->tag.'_'.$i,
                        'variant_key' => 'v'.$i,
                    ],
                ]),
                'status' => 1,
                'sort_order' => $i,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('product_upstream_bindings')->insert([
                'product_id' => $productId,
                'supplier_plugin_binding_id' => $bindingId,
                'plugin_id' => $pluginId,
                'provider_key' => 'zjmf',
                'upstream_product_id' => 'up'.$i,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('services')->insert([
                'user_id' => $this->uid,
                'product_id' => $productId,
                'name' => '回归服务'.$this->tag.'_'.$i,
                'billing_cycle' => 'monthly',
                'amount' => 20,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * 订单造数：每条订单指向独立的拆分商品，该商品再指向独立的源商品，且订单不带 product_spec_snapshot。
     * 没有快照时展示名与商品全路径才会回源解析；每个 id 都不同才能击穿按 id 记忆化的实现。
     * $type 用于覆盖客户端订单列表（new）与管理端订单/财务续费列表（renew）各自的数据集合。
     */
    private function seedClientOrdersWithSplitProducts(int $rows, string $type = 'new'): void
    {
        $this->ensureUser();

        $fpg = DB::table('first_product_groups')->insertGetId([
            'name' => '一组'.$this->tag, 'code' => 'c1'.$this->tag,
            'is_visible' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $spg = DB::table('second_product_groups')->insertGetId([
            'first_product_group_id' => $fpg, 'name' => '二组'.$this->tag,
            'is_visible' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $tpg = DB::table('third_product_groups')->insertGetId([
            'second_product_group_id' => $spg, 'name' => '三组'.$this->tag,
            'is_visible' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        for ($i = 0; $i < $rows; $i++) {
            $sourceId = (int) DB::table('products')->insertGetId([
                'product_type' => 'cloud_server',
                'service_type_code' => 'cloud_server',
                'custom_display_name' => '回归订单源商品'.$this->tag.'_'.$i,
                'pricing' => json_encode(['monthly' => '10.00']),
                'purchase_requires' => json_encode([
                    'upstream_default_config' => ['cpu' => '2核', 'memory' => '4G'],
                ]),
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $productId = (int) DB::table('products')->insertGetId([
                'product_type' => 'cloud_server',
                'service_type_code' => 'cloud_server',
                'custom_display_name' => '回归订单拆商品'.$this->tag.'_'.$i,
                'product_group_id' => $tpg,
                'pricing' => json_encode(['monthly' => '20.00']),
                'purchase_requires' => json_encode([
                    'upstream_split' => [
                        'source_product_id' => $sourceId,
                        'source_product_name' => '回归订单源商品'.$this->tag.'_'.$i,
                        'variant_key' => 'v'.$i,
                    ],
                ]),
                'status' => 1,
                'sort_order' => $i,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('orders')->insert([
                'order_no' => $this->tag.'O'.$i,
                'user_id' => $this->uid,
                'product_id' => $productId,
                'type' => $type,
                'amount' => 20,
                'status' => 1,
                'config_snapshot' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function seedProductAndServices(int $rows): void
    {
        $this->ensureUser();
        $this->ensureProduct();

        for ($i = 0; $i < $rows; $i++) {
            DB::table('services')->insert([
                'user_id' => $this->uid,
                'product_id' => $this->pid,
                'name' => '回归服务'.$this->tag.'_'.$i,
                'domain' => 's'.$i.'.'.$this->tag.'.test',
                'billing_cycle' => 'monthly',
                'amount' => 35,
                'status' => 1,
                'auto_renew' => 1,
                'expires_at' => now()->addDays(30),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function seedReferralRewards(int $rows): void
    {
        $this->ensureUser();
        $this->ensureProduct();

        for ($i = 0; $i < $rows; $i++) {
            $orderId = DB::table('orders')->insertGetId([
                'order_no' => $this->tag.'O'.$i,
                'user_id' => $this->uid,
                'product_id' => $this->pid,
                'type' => 'new',
                'amount' => 35,
                'status' => 1,
                'config_snapshot' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('referral_rewards')->insert([
                'referrer_user_id' => $this->uid,
                'referred_user_id' => $this->uid,
                'order_id' => $orderId,
                'product_id' => $this->pid,
                'order_amount' => 35,
                'reward_rate' => 3,
                'reward_amount' => 1,
                'status' => 1,
                'rewarded_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * 推荐奖励造数（拆分商品版）：每条奖励指向独立的拆分商品与独立源商品，订单不带 product_spec_snapshot。
     * 无快照才会走 ProductFullPathResolver 的源商品回源分支；每个 id 都不同才能击穿按 id 记忆化的实现。
     */
    private function seedReferralRewardsWithSplitProducts(int $rows, bool $withOrder = true): void
    {
        $this->ensureUser();

        $fpg = DB::table('first_product_groups')->insertGetId([
            'name' => '一组'.$this->tag, 'code' => 'c1'.$this->tag,
            'is_visible' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $spg = DB::table('second_product_groups')->insertGetId([
            'first_product_group_id' => $fpg, 'name' => '二组'.$this->tag,
            'is_visible' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $tpg = DB::table('third_product_groups')->insertGetId([
            'second_product_group_id' => $spg, 'name' => '三组'.$this->tag,
            'is_visible' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        for ($i = 0; $i < $rows; $i++) {
            $sourceId = (int) DB::table('products')->insertGetId([
                'product_type' => 'cloud_server',
                'service_type_code' => 'cloud_server',
                'custom_display_name' => '回归奖励源商品'.$this->tag.'_'.$i,
                'pricing' => json_encode(['monthly' => '10.00']),
                'purchase_requires' => json_encode([
                    'upstream_default_config' => ['cpu' => '2核', 'memory' => '4G'],
                ]),
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $productId = (int) DB::table('products')->insertGetId([
                'product_type' => 'cloud_server',
                'service_type_code' => 'cloud_server',
                'custom_display_name' => '回归奖励拆商品'.$this->tag.'_'.$i,
                'product_group_id' => $tpg,
                'pricing' => json_encode(['monthly' => '20.00']),
                'purchase_requires' => json_encode([
                    'upstream_split' => [
                        'source_product_id' => $sourceId,
                        'source_product_name' => '回归奖励源商品'.$this->tag.'_'.$i,
                        'variant_key' => 'v'.$i,
                    ],
                ]),
                'status' => 1,
                'sort_order' => $i,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // $withOrder=false 造「发票来源」奖励：没有订单可回溯全路径，展示名只能走奖励商品自身，
            // 该分支正是逐行 preloadSourceProducts() 会破坏批次映射的路径。
            $orderId = null;
            if ($withOrder) {
                $orderId = DB::table('orders')->insertGetId([
                    'order_no' => $this->tag.'O'.$i,
                    'user_id' => $this->uid,
                    'product_id' => $productId,
                    'type' => 'new',
                    'amount' => 20,
                    'status' => 1,
                    'config_snapshot' => json_encode([]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('referral_rewards')->insert([
                'referrer_user_id' => $this->uid,
                'referred_user_id' => $this->uid,
                'order_id' => $orderId,
                'product_id' => $productId,
                'order_amount' => 20,
                'reward_rate' => 10,
                'reward_amount' => 2,
                'status' => 1,
                'rewarded_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function seedSuppliers(int $rows): void
    {
        for ($i = 0; $i < $rows; $i++) {
            DB::table('suppliers')->insert([
                'code' => $this->tag.'S'.$i,
                'name' => '回归供应商'.$this->tag.$i,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * 账单造数：默认所有账单共用 $this->pid；$distinctProducts=true 时每张账单各配独立商品。
     * 商品级懒加载（如 productGroup 三级链）只在「不同商品实例」之间重复，共用商品无法击穿按实例
     * 记忆的关系加载，因此需要测商品级 N+1 的场景必须用独立商品造数。
     */
    private function seedInvoicesWithPayments(int $rows, bool $distinctProducts = false): void
    {
        $this->ensureUser();
        $this->ensureProduct();

        for ($i = 0; $i < $rows; $i++) {
            $productId = $this->pid;
            if ($distinctProducts) {
                $productId = (int) DB::table('products')->insertGetId([
                    'product_type' => 'cloud_host',
                    'service_type_code' => 'cloud_host',
                    'custom_display_name' => '回归账单商品'.$this->tag.'_'.$i,
                    'product_group_id' => (int) DB::table('products')->where('id', $this->pid)->value('product_group_id'),
                    'pricing' => json_encode(['monthly' => '35.00']),
                    'status' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $orderId = DB::table('orders')->insertGetId([
                'order_no' => $this->tag.'O'.$i,
                'user_id' => $this->uid,
                'product_id' => $productId,
                'type' => 'new',
                'amount' => 35,
                'status' => 1,
                'config_snapshot' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $invoiceId = DB::table('invoices')->insertGetId([
                'invoice_no' => $this->tag.'I'.$i,
                'user_id' => $this->uid,
                'order_id' => $orderId,
                'product_id' => $productId,
                'type' => 'normal',
                'amount' => 35,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $paymentId = DB::table('payments')->insertGetId([
                'payment_no' => $this->tag.'P'.$i,
                'user_id' => $this->uid,
                'invoice_id' => $invoiceId,
                'amount' => 35,
                'status' => 1,
                'gateway_key' => 'alipay',
                'paid_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('payment_callbacks')->insert([
                'payment_id' => $paymentId,
                'callback_type' => 'notify',
                'payload_json' => json_encode(['out_trade_no' => $this->tag.'P'.$i]),
                'created_at' => now(),
            ]);
        }
    }

    /**
     * 关键词商品造数：一个三级分组 + 每行一个独立商品，自定义展示名都带关键词前缀。
     * 必须用独立商品：解析器的源商品/自定义名缓存按 id 记忆化，共用商品击穿不了逐行回源。
     */
    private function seedKeywordProducts(int $rows, string $namePrefix): void
    {
        $this->ensureUser();

        $fpg = DB::table('first_product_groups')->insertGetId([
            'name' => '一组'.$this->tag, 'code' => 'k1'.$this->tag,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $spg = DB::table('second_product_groups')->insertGetId([
            'first_product_group_id' => $fpg, 'name' => '二组'.$this->tag,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $tpg = DB::table('third_product_groups')->insertGetId([
            'second_product_group_id' => $spg, 'name' => '三组'.$this->tag,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        for ($i = 0; $i < $rows; $i++) {
            DB::table('products')->insert([
                'product_type' => 'cloud_host',
                'service_type_code' => 'cloud_host',
                'custom_display_name' => $namePrefix.$this->tag.'_'.$i,
                'product_group_id' => $tpg,
                'pricing' => json_encode(['monthly' => '10.00']),
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * 关键词推荐奖励造数：每行一个独立商品 + 一条挂在它上面的推荐奖励。
     * 关键词先命中商品、再 whereIn 到奖励表，因此奖励行数与商品行数一一对应。
     */
    private function seedKeywordRewards(int $rows, string $namePrefix): void
    {
        $this->seedKeywordProducts($rows, $namePrefix);

        $productIds = DB::table('products')
            ->where('custom_display_name', 'like', $namePrefix.$this->tag.'_%')
            ->orderBy('id')
            ->pluck('id');

        foreach ($productIds as $productId) {
            DB::table('referral_rewards')->insert([
                'referrer_user_id' => $this->uid,
                'referred_user_id' => $this->uid,
                'order_id' => null,
                'product_id' => (int) $productId,
                'order_amount' => 20,
                'reward_rate' => 10,
                'reward_amount' => 2,
                'status' => 1,
                'rewarded_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * 优惠券列表造数：每张券各绑一个独立商品，商品名全部带自定义展示名。
     * 券列表的适用范围商品名逐行解析，商品必须独立才能击穿按 id 记忆化。
     */
    private function seedCoupons(int $rows): void
    {
        $this->ensureUser();

        $fpg = DB::table('first_product_groups')->insertGetId([
            'name' => '一组'.$this->tag, 'code' => 'p1'.$this->tag,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $spg = DB::table('second_product_groups')->insertGetId([
            'first_product_group_id' => $fpg, 'name' => '二组'.$this->tag,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $tpg = DB::table('third_product_groups')->insertGetId([
            'second_product_group_id' => $spg, 'name' => '三组'.$this->tag,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        for ($i = 0; $i < $rows; $i++) {
            $productId = (int) DB::table('products')->insertGetId([
                'product_type' => 'cloud_host',
                'service_type_code' => 'cloud_host',
                'custom_display_name' => '回归券商品'.$this->tag.'_'.$i,
                'product_group_id' => $tpg,
                'pricing' => json_encode(['monthly' => '10.00']),
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('coupons')->insert([
                'name' => '回归优惠券'.$this->tag.'_'.$i,
                'code' => 'CPN'.$this->tag.$i,
                'distribution_type' => 'public',
                'discount_scope' => 'all',
                'discount_type' => 'fixed',
                'discount_value' => '1.00',
                'min_amount' => '0.00',
                'billing_cycles' => json_encode(['monthly']),
                'product_ids' => json_encode([$productId]),
                'first_order_only' => 0,
                'only_old_client' => 0,
                'is_discount' => 1,
                'used_count' => 0,
                'status' => 1,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * 账单造数（拆分商品版）：每行一个独立源商品 + 独立拆分商品 + 一张账单，账单不带订单。
     * 没有订单时展示名只能走「账单商品自身 → 源商品」这一条回源链路，正是逐行解析会放大的路径。
     */
    private function seedInvoicesWithSplitProducts(int $rows): void
    {
        $this->ensureUser();

        $fpg = DB::table('first_product_groups')->insertGetId([
            'name' => '一组'.$this->tag, 'code' => 'i1'.$this->tag,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $spg = DB::table('second_product_groups')->insertGetId([
            'first_product_group_id' => $fpg, 'name' => '二组'.$this->tag,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $tpg = DB::table('third_product_groups')->insertGetId([
            'second_product_group_id' => $spg, 'name' => '三组'.$this->tag,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        for ($i = 0; $i < $rows; $i++) {
            $sourceId = (int) DB::table('products')->insertGetId([
                'product_type' => 'cloud_host',
                'service_type_code' => 'cloud_host',
                'custom_display_name' => '回归账单源商品'.$this->tag.'_'.$i,
                'product_group_id' => $tpg,
                'pricing' => json_encode(['monthly' => '10.00']),
                'purchase_requires' => json_encode([
                    'upstream_default_config' => ['cpu' => '2核', 'memory' => '4G'],
                ]),
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $productId = (int) DB::table('products')->insertGetId([
                'product_type' => 'cloud_host',
                'service_type_code' => 'cloud_host',
                'custom_display_name' => '回归账单拆商品'.$this->tag.'_'.$i,
                'product_group_id' => $tpg,
                'pricing' => json_encode(['monthly' => '10.00']),
                'purchase_requires' => json_encode([
                    'upstream_split' => [
                        'source_product_id' => $sourceId,
                        'source_product_name' => '回归账单源商品'.$this->tag.'_'.$i,
                        'variant_key' => 'v'.$i,
                    ],
                ]),
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('invoices')->insert([
                'invoice_no' => $this->tag.'INV'.$i,
                'user_id' => $this->uid,
                'product_id' => $productId,
                'type' => 'service',
                'status' => 1,
                'amount' => '10.00',
                'paid_amount' => '0.00',
                'product_spec_snapshot' => '',
                'billing_cycle' => 'monthly',
                'quantity' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
