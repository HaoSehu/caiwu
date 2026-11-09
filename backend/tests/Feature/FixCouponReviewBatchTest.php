<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Constants\UserCouponStatus;
use App\Exceptions\BusinessException;
use App\Models\AdminUser;
use App\Models\Coupon;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Models\UserCoupon;
use App\Services\Finance\CouponCampaignService;
use App\Services\Finance\CouponService;
use App\Support\AdminPermissions;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * 财务审查 2026-11-09 优惠券批次修复回归（M-85 / M-82 / M-86 / M-87 / M-88 / M-1）。
 *
 * 使用 DatabaseTransactions：所有写入在测试结束后回滚，不污染 idc_test 现有数据。
 */
class FixCouponReviewBatchTest extends TestCase
{
    use DatabaseTransactions;

    // ------------------------------------------------------------------ M-85

    public function test_admin_list_with_product_ids_returns_ok(): void
    {
        $product = $this->makeProduct('活动商品');
        $campaign = $this->campaignService()->createCampaign(
            $this->campaignPayload(['product_ids' => [(int) $product->id]]),
            ['operator' => 'tester']
        );

        // create 的返回值同样要走商品名解析：旧实现按 products.name 取值，先落库再 500
        $this->assertSame([(int) $product->id], $campaign['product_ids']);
        $this->assertStringContainsString('活动商品', (string) $campaign['product_scope_text']);

        $listed = $this->campaignService()->adminList(['keyword' => (string) $campaign['name']]);
        $row = collect($listed->items())->firstWhere('id', (int) $campaign['id']);

        $this->assertIsArray($row, '含 product_ids 的活动必须能在管理端列表返回');
        $this->assertStringContainsString('活动商品', (string) $row['product_scope_text']);

        // 端到端：管理端活动列表接口必须返回 200（M-85 的故障面是整页 500）
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::COUPON_LIST]));
        $this->getJson('/api/v2/admin/coupon-campaigns?keyword='.urlencode((string) $campaign['name']))->assertOk();
    }

    public function test_product_name_falls_back_when_display_name_unavailable(): void
    {
        // 商品行已不存在（活动常驻、商品被物理清理）：只能落到「商品#id」兜底，不得抛异常
        $campaign = $this->campaignService()->createCampaign(
            $this->campaignPayload(['product_ids' => [999999]]),
            ['operator' => 'tester']
        );

        $this->assertSame([999999], $campaign['product_ids']);
        $this->assertSame('商品#999999', (string) $campaign['product_scope_text']);
    }

    // ------------------------------------------------------------------ M-82

    public function test_store_campaign_rejects_out_of_range_trigger_time(): void
    {
        $base = $this->campaignPayload();
        $base['weekdays'] = [1];

        foreach (['99:99', '25:00', '24:00', '12:60', '12:00:60'] as $badTime) {
            try {
                $this->campaignService()->createCampaign(
                    array_merge($base, ['trigger_time' => $badTime]),
                    ['operator' => 'tester']
                );
                $this->fail('非法触发时间必须被拒绝：'.$badTime);
            } catch (BusinessException $exception) {
                $this->assertSame('触发时间不合法', $exception->getMessage(), '非法触发时间：'.$badTime);
            }
        }

        // 合法边界值照常通过
        foreach (['00:00', '23:59', '23:59:59'] as $goodTime) {
            $campaign = $this->campaignService()->createCampaign(
                array_merge($base, ['trigger_time' => $goodTime, 'name' => '边界活动'.$goodTime.uniqid()]),
                ['operator' => 'tester']
            );
            $this->assertNotSame('', (string) $campaign['trigger_time']);
        }
    }

    public function test_store_campaign_request_rejects_99_99(): void
    {
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::COUPON_MANAGE]));

        $this->postJson('/api/v2/admin/coupon-campaigns', array_merge($this->campaignPayload(), [
            'trigger_time' => '99:99',
        ]))->assertStatus(422);

        $this->postJson('/api/v2/admin/coupon-campaigns', array_merge($this->campaignPayload(), [
            'trigger_time' => '09:30',
            'name' => '合法时间活动'.uniqid(),
        ]))->assertOk();
    }

    public function test_dispatch_and_list_survive_legacy_invalid_trigger_time(): void
    {
        // 模拟历史坏数据：直接写库绕过保存校验。sort_order 拉高使其排在正常活动之前，
        // 复现「坏活动在遍历中先出现、抛异常中断整批」的原始缺陷路径。
        $badName = '坏时间活动'.uniqid();
        $badId = (int) DB::table('coupon_campaigns')->insertGetId([
            'name' => $badName,
            'weekdays' => json_encode([(int) now()->dayOfWeek]),
            'trigger_time' => '99:99:00',
            'issue_quantity' => 1,
            'discount_type' => 'fixed',
            'discount_value' => 5,
            'status' => 1,
            'sort_order' => 999,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $goodCampaign = $this->campaignService()->createCampaign(
            $this->campaignPayload(['trigger_time' => '00:00']),
            ['operator' => 'tester']
        );

        // 调度不得因单个坏活动整体失败：坏活动跳过，后续活动照常发放
        $summary = $this->campaignService()->dispatchDueCampaigns(CarbonImmutable::now(config('app.timezone')), ['operator' => 'tester']);

        $this->assertGreaterThanOrEqual(1, (int) $summary['triggered']);
        $this->assertSame(
            1,
            Coupon::query()->where('coupon_campaign_id', (int) $goodCampaign['id'])->count(),
            '坏活动不得阻断其后的活动发放'
        );
        $this->assertSame(
            0,
            Coupon::query()->where('coupon_campaign_id', $badId)->count(),
            '坏 trigger_time 的活动应被跳过而非发放'
        );

        // 管理端列表也不得整页 500：坏配置降级为空串展示
        $items = collect($this->campaignService()->adminList(['keyword' => $badName])->items());
        $badRow = $items->firstWhere('id', $badId);
        $this->assertIsArray($badRow, '含坏 trigger_time 的活动必须能在列表返回');
        $this->assertSame('', (string) $badRow['trigger_time']);
        $this->assertNull($badRow['next_run_at']);

        // 端到端：列表接口必须返回 200（M-82 的故障面是整页 500）
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::COUPON_LIST]));
        $this->getJson('/api/v2/admin/coupon-campaigns')->assertOk();
    }

    // ------------------------------------------------------------------ M-86

    public function test_replace_replay_keeps_used_coupon_used(): void
    {
        $user = User::factory()->create();
        $service = app(CouponService::class);

        $coupon = $service->createCoupon([
            'name' => '重放保护券'.uniqid(),
            'code' => 'RP'.uniqid(),
            'distribution_type' => 'private',
            'user_ids' => [(int) $user->id],
            'discount_type' => 'fixed',
            'discount_scope' => 'first_month',
            'discount_value' => 5,
            'status' => 1,
        ], ['operator' => 'tester']);

        $userCoupon = UserCoupon::query()
            ->where('coupon_id', (int) $coupon['id'])
            ->where('user_id', (int) $user->id)
            ->firstOrFail();
        $userCoupon->forceFill(['status' => UserCouponStatus::USED, 'used_at' => now(), 'last_used_at' => now()])->save();

        // replace 重放（同 user_ids 重新提交）：已核销行不得被重置回 OWNED 再次抵扣
        $service->updateCoupon(Coupon::query()->findOrFail((int) $coupon['id']), [
            'name' => (string) $coupon['name'],
            'distribution_type' => 'private',
            'discount_type' => 'fixed',
            'discount_scope' => 'first_month',
            'discount_value' => 5,
            'user_ids' => [(int) $user->id],
        ], ['operator' => 'tester']);

        $userCoupon->refresh();
        $this->assertSame(UserCouponStatus::USED, (int) $userCoupon->status, '已核销券不得因 replace 重放复活');
        $this->assertNotNull($userCoupon->used_at);
    }

    // ------------------------------------------------------------------ M-87

    public function test_campaign_dispatch_truncates_overlong_coupon_name(): void
    {
        // 活动名 120 字符（请求层上限）+ 批次后缀必然超 coupons.name(120)
        $longName = str_repeat('券', 120);
        $campaign = $this->campaignService()->createCampaign(
            $this->campaignPayload(['name' => $longName, 'trigger_time' => '00:00']),
            ['operator' => 'tester']
        );

        $summary = $this->campaignService()->dispatchDueCampaigns(CarbonImmutable::now(config('app.timezone')), ['operator' => 'tester']);
        $this->assertGreaterThanOrEqual(1, (int) $summary['triggered']);

        $coupon = Coupon::query()->where('coupon_campaign_id', (int) $campaign['id'])->firstOrFail();
        $this->assertLessThanOrEqual(120, mb_strlen((string) $coupon->name), 'coupons.name 必须按字符截断到 120');
    }

    // ------------------------------------------------------------------ M-88

    public function test_preview_returns_null_when_coupon_has_pending_invoice(): void
    {
        [$user, $userCoupon, $product] = $this->grantedCouponFixture();
        $this->createInvoice($user, $userCoupon, $product, InvoiceStatus::UNPAID);

        // 同券已有待支付账单：预览不得再给出折扣价（reserve 会拒绝，报价与落单必须同判据）
        $this->assertNull(app(CouponService::class)->previewOwnedCoupon(
            (int) $userCoupon->id,
            (int) $user->id,
            $product,
            'monthly',
            100.0,
            'new'
        ));

        // 续费预览例外：续费复用对账依赖 preview 不被自身待支付账单拒绝
        $this->assertIsArray(app(CouponService::class)->previewOwnedCoupon(
            (int) $userCoupon->id,
            (int) $user->id,
            $product,
            'monthly',
            100.0,
            'renew'
        ));
    }

    public function test_preview_returns_null_when_coupon_already_used(): void
    {
        [$user, $userCoupon, $product] = $this->grantedCouponFixture();
        $this->createInvoice($user, $userCoupon, $product, InvoiceStatus::PAID);

        // 已支付=已消耗（不是占用）：新购与续费预览都必须拒绝，返回 null 而非折扣价
        $this->assertNull(app(CouponService::class)->previewOwnedCoupon(
            (int) $userCoupon->id,
            (int) $user->id,
            $product,
            'monthly',
            100.0,
            'new'
        ));

        $this->assertNull(app(CouponService::class)->previewOwnedCoupon(
            (int) $userCoupon->id,
            (int) $user->id,
            $product,
            'monthly',
            100.0,
            'renew'
        ));
    }

    public function test_preview_allows_coupon_without_blocking_invoices(): void
    {
        [$user, $userCoupon, $product] = $this->grantedCouponFixture();

        // 无占用账单：预览照常返回折扣
        $payload = app(CouponService::class)->previewOwnedCoupon(
            (int) $userCoupon->id,
            (int) $user->id,
            $product,
            'monthly',
            100.0,
            'new'
        );

        $this->assertIsArray($payload);
        $this->assertSame('5.00', (string) $payload['discount_amount']);
    }

    // ------------------------------------------------------------------- M-1

    public function test_admin_without_grant_permission_cannot_create_private_coupon(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::COUPON_MANAGE]));

        $this->postJson('/api/v2/admin/coupons', [
            'name' => '定向券'.uniqid(),
            'distribution_type' => 'private',
            'user_ids' => [(int) $user->id],
            'discount_type' => 'fixed',
            'discount_scope' => 'first_month',
            'discount_value' => 5,
        ])->assertStatus(403)
            ->assertJsonPath('code', 40300);
    }

    public function test_admin_with_grant_permission_can_create_private_coupon(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::COUPON_MANAGE, AdminPermissions::COUPON_GRANT]));

        $this->postJson('/api/v2/admin/coupons', [
            'name' => '定向券'.uniqid(),
            'distribution_type' => 'private',
            'user_ids' => [(int) $user->id],
            'discount_type' => 'fixed',
            'discount_scope' => 'first_month',
            'discount_value' => 5,
        ])->assertOk();
    }

    public function test_high_value_coupon_requires_high_value_grant_permission(): void
    {
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::COUPON_MANAGE]));

        $this->postJson('/api/v2/admin/coupons', [
            'name' => '高额券'.uniqid(),
            'distribution_type' => 'public',
            'discount_type' => 'fixed',
            'discount_scope' => 'first_month',
            'discount_value' => 1500,
        ])->assertStatus(403)
            ->assertJsonPath('code', 40300);

        // 阈值以内的券不需要高额权限
        $this->postJson('/api/v2/admin/coupons', [
            'name' => '普通券'.uniqid(),
            'distribution_type' => 'public',
            'discount_type' => 'fixed',
            'discount_scope' => 'first_month',
            'discount_value' => 999,
        ])->assertOk();
    }

    public function test_high_value_coupon_passes_with_high_value_grant_permission(): void
    {
        Sanctum::actingAs($this->makeAdmin([
            AdminPermissions::COUPON_MANAGE,
            AdminPermissions::COUPON_HIGH_VALUE_GRANT,
        ]));

        $this->postJson('/api/v2/admin/coupons', [
            'name' => '高额券'.uniqid(),
            'distribution_type' => 'public',
            'discount_type' => 'fixed',
            'discount_scope' => 'first_month',
            'discount_value' => 1500,
        ])->assertOk();
    }

    public function test_admin_without_grant_permission_cannot_update_to_private_coupon(): void
    {
        $user = User::factory()->create();
        $coupon = app(CouponService::class)->createCoupon([
            'name' => '公开券'.uniqid(),
            'code' => 'PUB'.uniqid(),
            'distribution_type' => 'public',
            'discount_type' => 'fixed',
            'discount_scope' => 'first_month',
            'discount_value' => 5,
            'status' => 1,
        ], ['operator' => 'tester']);

        Sanctum::actingAs($this->makeAdmin([AdminPermissions::COUPON_MANAGE]));

        $this->putJson('/api/v2/admin/coupons/'.(int) $coupon['id'], [
            'name' => (string) $coupon['name'],
            'distribution_type' => 'private',
            'user_ids' => [(int) $user->id],
            'discount_type' => 'fixed',
            'discount_scope' => 'first_month',
            'discount_value' => 5,
        ])->assertStatus(403)
            ->assertJsonPath('code', 40300);
    }

    // --------------------------------------------------------------- helpers

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: User, 1: UserCoupon, 2: Product}
     */
    private function grantedCouponFixture(array $overrides = []): array
    {
        $user = User::factory()->create();
        $product = $this->makeProduct('预览券商品');

        $coupon = app(CouponService::class)->createCoupon(array_merge([
            'name' => '预览券'.uniqid(),
            'code' => 'PV'.uniqid(),
            'distribution_type' => 'private',
            'user_ids' => [(int) $user->id],
            'discount_type' => 'fixed',
            // 持续优惠：新购与续费两种 orderType 都允许，便于覆盖续费预览豁免
            'discount_scope' => 'recurring',
            'discount_value' => 5,
            'min_amount' => 0,
            'status' => 1,
        ], $overrides), ['operator' => 'tester']);

        $userCoupon = UserCoupon::query()
            ->where('user_id', (int) $user->id)
            ->where('coupon_id', (int) $coupon['id'])
            ->firstOrFail();

        return [$user, $userCoupon, $product];
    }

    private function makeProduct(string $name): Product
    {
        return Product::query()->create([
            'name' => $name.uniqid(),
            'custom_display_name' => $name,
            'product_type' => 'cloud_host',
            'status' => 1,
            'pricing' => ['monthly' => '100.00'],
            'setup_fee' => '0.00',
            'config_options' => [],
            'purchase_requires' => [],
            'stock' => -1,
            'auto_setup' => 0,
        ]);
    }

    private function createInvoice(User $user, UserCoupon $userCoupon, Product $product, int $status): Invoice
    {
        return Invoice::query()->create([
            'invoice_no' => Invoice::generateInvoiceNo(),
            'user_id' => (int) $user->id,
            'product_id' => (int) $product->id,
            'coupon_id' => (int) $userCoupon->coupon_id,
            'user_coupon_id' => (int) $userCoupon->id,
            'type' => 'new',
            'amount' => '100.00',
            'paid_amount' => $status === InvoiceStatus::PAID ? '100.00' : '0.00',
            'billing_cycle' => 'monthly',
            'status' => $status,
            'paid_at' => $status === InvoiceStatus::PAID ? now() : null,
            'due_date' => now()->addDay(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function campaignPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => '审查活动'.uniqid(),
            'weekdays' => [(int) now()->dayOfWeek],
            'trigger_time' => '00:00',
            'issue_quantity' => 2,
            'discount_type' => 'fixed',
            'discount_scope' => 'first_month',
            'discount_value' => 5,
            'status' => 1,
        ], $overrides);
    }

    private function campaignService(): CouponCampaignService
    {
        return app(CouponCampaignService::class);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function makeAdmin(array $permissions): AdminUser
    {
        $role = Role::query()->create([
            'name' => 'role_'.uniqid(),
            'label' => '审查批次测试角色',
            'permissions' => $permissions,
        ]);

        return AdminUser::query()->create([
            'username' => 'admin_'.uniqid(),
            'password' => 'secret123',
            'nickname' => '审查批次测试管理员',
            'status' => 1,
            'role_id' => (int) $role->id,
        ]);
    }
}
