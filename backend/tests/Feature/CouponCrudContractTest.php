<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Exceptions\BusinessException;
use App\Models\Coupon;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use App\Models\UserCoupon;
use App\Services\Finance\CouponService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 优惠券管理端 CRUD 契约测试（补齐执行计划 P2-14「CRUD 契约测试待补」）。
 *
 * 只锁定对外契约：建券校验与默认值、私有发放与配额、锁定字段、删除级联与约束、
 * 列表筛选与汇总口径、公开领取前置条件。占用/核销/释放与续费链路已由
 * CouponReserveGuardTest / CouponOccupancyConsistencyTest / CouponLifecycleRuleTest /
 * ServiceRenewCouponRegressionTest 覆盖，此处不重复。
 *
 * 使用 DatabaseTransactions：所有写入在测试结束后回滚，不污染 idc_test 现有数据。
 */
class CouponCrudContractTest extends TestCase
{
    use DatabaseTransactions;

    // ---------------------------------------------------------------- 建券契约

    public function test_create_applies_defaults_and_returns_admin_contract_fields(): void
    {
        $coupon = $this->service()->createCoupon([
            'name' => '默认值券'.uniqid(),
            'discount_type' => 'fixed',
            'discount_value' => 5,
        ], ['operator' => 'tester']);

        // 未传编码时自动生成（管理端契约不外露券码：券码为内部标识，caiwu 无兑换流程）
        $model = Coupon::query()->findOrFail((int) $coupon['id']);
        $this->assertNotSame('', (string) $model->code);
        $this->assertArrayNotHasKey('code', $coupon);
        $this->assertSame('public', $coupon['distribution_type']);
        $this->assertSame('first_month', $coupon['discount_scope']);
        $this->assertSame('fixed', $coupon['discount_type']);
        $this->assertSame(1, (int) $coupon['status']);

        // 管理端契约字段齐全（前端依赖这些键渲染列表与操作按钮）
        foreach ([
            'id', 'name', 'distribution_type', 'discount_scope', 'discount_type',
            'discount_type_label', 'discount_value', 'discount_label', 'min_amount',
            'max_discount_amount', 'billing_cycles', 'product_ids', 'first_order_only',
            'total_usage_limit', 'per_user_limit', 'used_count', 'recipient_count',
            'remaining_stock', 'status', 'status_label', 'display_status',
            'display_status_label', 'display_status_reason', 'validity_text',
            'can_update', 'can_delete', 'lock_reason', 'locked_fields', 'delete_reason',
        ] as $key) {
            $this->assertArrayHasKey($key, $coupon, "管理端契约缺少字段 {$key}");
        }

        $this->assertSame('立减 ¥5.00', $coupon['discount_label']);
        $this->assertTrue($coupon['can_delete']);
        $this->assertSame([], $coupon['locked_fields']);
    }

    public function test_create_rejects_unknown_discount_type(): void
    {
        // 请求层已限制类型；服务层必须同口径，否则落库即永久不可用的死券
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('优惠类型不正确');

        $this->service()->createCoupon([
            'name' => '未知类型券'.uniqid(),
            'discount_type' => 'override',
            'discount_value' => 5,
        ], ['operator' => 'tester']);
    }

    public function test_create_rejects_non_positive_discount_value(): void
    {
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('优惠值必须大于 0');

        $this->service()->createCoupon([
            'name' => '零值券'.uniqid(),
            'discount_type' => 'fixed',
            'discount_value' => 0,
        ], ['operator' => 'tester']);
    }

    public function test_create_rejects_percentage_above_100(): void
    {
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('折扣百分比不能大于 100');

        $this->service()->createCoupon([
            'name' => '超百折扣券'.uniqid(),
            'discount_type' => 'percentage',
            'discount_value' => 120,
        ], ['operator' => 'tester']);
    }

    public function test_create_rejects_duplicate_code(): void
    {
        $code = 'DUP'.uniqid();
        $this->service()->createCoupon([
            'name' => '首发券'.uniqid(),
            'code' => $code,
            'discount_type' => 'fixed',
            'discount_value' => 5,
        ], ['operator' => 'tester']);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('优惠券编码已存在');

        $this->service()->createCoupon([
            'name' => '重复编码券'.uniqid(),
            'code' => $code,
            'discount_type' => 'fixed',
            'discount_value' => 5,
        ], ['operator' => 'tester']);
    }

    public function test_create_rejects_invalid_distribution_type_and_scope(): void
    {
        try {
            $this->service()->createCoupon([
                'name' => '非法发放方式'.uniqid(),
                'distribution_type' => 'gift',
                'discount_type' => 'fixed',
                'discount_value' => 5,
            ], ['operator' => 'tester']);
            $this->fail('非法发放方式必须被拒绝');
        } catch (BusinessException $exception) {
            $this->assertSame('发放方式不正确', $exception->getMessage());
        }

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('优惠阶段不正确');

        $this->service()->createCoupon([
            'name' => '非法优惠阶段'.uniqid(),
            'discount_scope' => 'second_month',
            'discount_type' => 'fixed',
            'discount_value' => 5,
        ], ['operator' => 'tester']);
    }

    // ------------------------------------------------------- 私有发放与配额契约

    public function test_create_private_coupon_requires_recipients(): void
    {
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('私有优惠券必须选择至少一个用户');

        $this->service()->createCoupon([
            'name' => '无对象私发券'.uniqid(),
            'distribution_type' => 'private',
            'discount_type' => 'fixed',
            'discount_value' => 5,
        ], ['operator' => 'tester']);
    }

    public function test_create_private_coupon_grants_owned_records_to_recipients(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $coupon = $this->service()->createCoupon([
            'name' => '定向券'.uniqid(),
            'distribution_type' => 'private',
            'user_ids' => [(int) $userA->id, (int) $userB->id],
            'discount_type' => 'fixed',
            'discount_value' => 5,
        ], ['operator' => 'tester']);

        $records = UserCoupon::query()->where('coupon_id', (int) $coupon['id'])->get();
        $this->assertCount(2, $records);
        $this->assertSame(['grant'], $records->pluck('receive_type')->unique()->values()->all());
        $this->assertSame([1], $records->pluck('status')->unique()->values()->all());
        $this->assertSame(2, (int) $coupon['recipient_count']);
    }

    public function test_create_private_coupon_rejects_grant_beyond_total_usage_limit(): void
    {
        $users = collect(range(1, 3))->map(fn () => User::factory()->create());

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('超过优惠券总发放上限');

        $this->service()->createCoupon([
            'name' => '超额定向券'.uniqid(),
            'distribution_type' => 'private',
            'user_ids' => $users->map(fn (User $user) => (int) $user->id)->all(),
            'discount_type' => 'fixed',
            'discount_value' => 5,
            'total_usage_limit' => 2,
        ], ['operator' => 'tester']);
    }

    public function test_private_coupon_remaining_stock_tracks_issued_count(): void
    {
        $user = User::factory()->create();

        $coupon = $this->service()->createCoupon([
            'name' => '库存券'.uniqid(),
            'distribution_type' => 'private',
            'user_ids' => [(int) $user->id],
            'discount_type' => 'fixed',
            'discount_value' => 5,
            'total_usage_limit' => 5,
        ], ['operator' => 'tester']);

        // 发放量口径：剩余库存 = 总发放上限 - 已发放张数
        $this->assertSame(5, (int) $coupon['total_usage_limit']);
        $this->assertSame(1, (int) $coupon['recipient_count']);
        $this->assertSame(4, (int) $coupon['remaining_stock']);
    }

    // ------------------------------------------------------------ 更新契约

    public function test_update_rejects_changing_locked_fields_after_issuance(): void
    {
        $user = User::factory()->create();
        $coupon = $this->service()->createCoupon([
            'name' => '已发放券'.uniqid(),
            'distribution_type' => 'private',
            'user_ids' => [(int) $user->id],
            'discount_type' => 'fixed',
            'discount_value' => 5,
        ], ['operator' => 'tester']);

        $model = Coupon::query()->findOrFail((int) $coupon['id']);

        // 已发放 → 锁定集为 distribution_type/discount_type/discount_scope，且列表返回锁定原因
        $item = $this->service()->adminList(['keyword' => (string) $coupon['name']])->items()[0];
        $this->assertSame('已发放的优惠券', $item['lock_reason']);
        $this->assertSame(['distribution_type', 'discount_type', 'discount_scope'], $item['locked_fields']);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('已发放的优惠券，无法修改「discount_type」');

        $this->service()->updateCoupon($model, [
            'name' => (string) $coupon['name'],
            'discount_type' => 'percentage',
            'discount_value' => 5,
        ], ['operator' => 'tester']);
    }

    public function test_update_allows_value_change_after_issuance(): void
    {
        $user = User::factory()->create();
        $coupon = $this->service()->createCoupon([
            'name' => '可调额券'.uniqid(),
            'distribution_type' => 'private',
            'user_ids' => [(int) $user->id],
            'discount_type' => 'fixed',
            'discount_value' => 5,
        ], ['operator' => 'tester']);

        // 面额类字段不在锁定集内（仅 distribution_type/discount_type/discount_scope 锁定）
        $updated = $this->service()->updateCoupon(Coupon::query()->findOrFail((int) $coupon['id']), [
            'name' => (string) $coupon['name'],
            'discount_type' => 'fixed',
            'discount_value' => 8,
        ], ['operator' => 'tester']);

        $this->assertSame('8.00', (string) $updated['discount_value']);
    }

    public function test_update_rejects_locked_fields_for_campaign_coupon(): void
    {
        $campaignId = (int) DB::table('coupon_campaigns')->insertGetId([
            'name' => '契约活动'.uniqid(),
            'weekdays' => json_encode([1]),
            'trigger_time' => '00:00:00',
            'issue_quantity' => 1,
            'discount_type' => 'fixed',
            'discount_value' => 5,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $coupon = $this->service()->createCoupon([
            'name' => '活动券'.uniqid(),
            'coupon_campaign_id' => $campaignId,
            'discount_type' => 'fixed',
            'discount_value' => 5,
        ], ['operator' => 'tester']);

        $model = Coupon::query()->findOrFail((int) $coupon['id']);
        $this->assertSame('活动生成的优惠券', $this->service()->updateCoupon($model, [
            'name' => (string) $coupon['name'],
            'discount_type' => 'fixed',
            'discount_value' => 5,
        ], ['operator' => 'tester'])['lock_reason']);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('活动生成的优惠券，无法修改「discount_scope」');

        $this->service()->updateCoupon($model, [
            'name' => (string) $coupon['name'],
            'discount_type' => 'fixed',
            'discount_value' => 5,
            'discount_scope' => 'renew',
        ], ['operator' => 'tester']);
    }

    // ------------------------------------------------------------ 删除契约

    public function test_delete_removes_coupon_and_its_user_coupon_records(): void
    {
        $user = User::factory()->create();
        $coupon = $this->service()->createCoupon([
            'name' => '待删券'.uniqid(),
            'distribution_type' => 'private',
            'user_ids' => [(int) $user->id],
            'discount_type' => 'fixed',
            'discount_value' => 5,
        ], ['operator' => 'tester']);

        $couponId = (int) $coupon['id'];
        $this->assertSame(1, UserCoupon::query()->where('coupon_id', $couponId)->count());

        $this->service()->deleteCoupon(Coupon::query()->findOrFail($couponId), ['operator' => 'tester']);

        $this->assertNull(Coupon::query()->find($couponId));
        $this->assertSame(0, UserCoupon::query()->where('coupon_id', $couponId)->count());
    }

    public function test_delete_rejects_coupon_with_recorded_usage(): void
    {
        $coupon = $this->service()->createCoupon([
            'name' => '已用券'.uniqid(),
            'discount_type' => 'fixed',
            'discount_value' => 5,
        ], ['operator' => 'tester']);

        $model = Coupon::query()->findOrFail((int) $coupon['id']);
        $model->forceFill(['used_count' => 1])->save();

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('该优惠券已有人使用，不能删除');

        $this->service()->deleteCoupon($model->refresh(), ['operator' => 'tester']);
    }

    public function test_delete_rejects_coupon_referenced_by_invoice(): void
    {
        $user = User::factory()->create();
        $product = $this->product();
        $coupon = $this->service()->createCoupon([
            'name' => '被引用券'.uniqid(),
            'discount_type' => 'fixed',
            'discount_value' => 5,
        ], ['operator' => 'tester']);

        $model = Coupon::query()->findOrFail((int) $coupon['id']);

        Invoice::query()->create([
            'invoice_no' => Invoice::generateInvoiceNo(),
            'user_id' => (int) $user->id,
            'product_id' => (int) $product->id,
            'coupon_id' => (int) $model->id,
            'type' => 'new',
            'amount' => '15.00',
            'paid_amount' => '0.00',
            'billing_cycle' => 'monthly',
            'status' => InvoiceStatus::UNPAID,
            'due_date' => now()->addDay(),
        ]);

        $item = $this->service()->adminList(['keyword' => (string) $coupon['name']])->items()[0];
        $this->assertFalse((bool) $item['can_delete']);
        $this->assertSame('该优惠券仍有关联订单或账单，不能删除', $item['delete_reason']);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('该优惠券仍有关联订单或账单，不能删除');

        $this->service()->deleteCoupon($model, ['operator' => 'tester']);
    }

    // -------------------------------------------------------- 列表与汇总契约

    public function test_admin_list_filters_by_type_distribution_and_scope(): void
    {
        $user = User::factory()->create();
        $tag = 'FILTER'.uniqid();

        $this->service()->createCoupon([
            'name' => $tag.'公开满减', 'discount_type' => 'fixed', 'discount_value' => 5,
        ], ['operator' => 'tester']);
        $this->service()->createCoupon([
            'name' => $tag.'定向折扣', 'distribution_type' => 'private', 'user_ids' => [(int) $user->id],
            'discount_type' => 'percentage', 'discount_value' => 80, 'discount_scope' => 'renew',
        ], ['operator' => 'tester']);

        $filters = ['keyword' => $tag];
        $this->assertSame(2, $this->paginateTotal($filters));

        $this->assertSame(1, $this->paginateTotal($filters + ['discount_type' => 'percentage']));
        $this->assertSame(1, $this->paginateTotal($filters + ['distribution_type' => 'private']));
        $this->assertSame(1, $this->paginateTotal($filters + ['discount_scope' => 'renew']));
        $this->assertSame(0, $this->paginateTotal($filters + ['distribution_type' => 'gift']));
    }

    public function test_admin_summary_counts_ignore_status_filter(): void
    {
        $user = User::factory()->create();
        $tag = 'SUM'.uniqid();

        $this->service()->createCoupon([
            'name' => $tag.'公开', 'discount_type' => 'fixed', 'discount_value' => 5,
        ], ['operator' => 'tester']);
        $privateCoupon = $this->service()->createCoupon([
            'name' => $tag.'定向', 'distribution_type' => 'private', 'user_ids' => [(int) $user->id],
            'discount_type' => 'fixed', 'discount_value' => 5, 'status' => 0,
        ], ['operator' => 'tester']);
        $this->assertSame(0, (int) $privateCoupon['status']);

        $summary = $this->service()->adminSummary(['keyword' => $tag]);

        $this->assertSame(2, $summary['total']);
        $this->assertSame(1, $summary['public_total']);
        $this->assertSame(1, $summary['private_total']);
        $this->assertSame(1, $summary['active']);
        $this->assertSame(1, $summary['disabled']);

        // 汇总按筛选条件统计，但不叠加 status 筛选（口径：汇总始终反映全量分布）
        $this->assertSame(2, $this->service()->adminSummary(['keyword' => $tag, 'status' => '1'])['total']);
    }

    // ------------------------------------------------------------ 领取契约

    public function test_claim_public_coupon_rejects_second_claim(): void
    {
        $user = User::factory()->create();
        $coupon = $this->service()->createCoupon([
            'name' => '可领券'.uniqid(),
            'discount_type' => 'fixed',
            'discount_value' => 5,
        ], ['operator' => 'tester']);

        $model = Coupon::query()->findOrFail((int) $coupon['id']);
        $this->service()->claimPublicCoupon($user, $model);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('你已经领取过这张优惠券');

        $this->service()->claimPublicCoupon($user, $model);
    }

    public function test_claim_rejects_private_and_not_started_coupons(): void
    {
        $user = User::factory()->create();
        $privateCoupon = $this->service()->createCoupon([
            'name' => '私有券'.uniqid(),
            'distribution_type' => 'private',
            'user_ids' => [(int) $user->id],
            'discount_type' => 'fixed',
            'discount_value' => 5,
        ], ['operator' => 'tester']);
        $otherUser = User::factory()->create();

        try {
            $this->service()->claimPublicCoupon(
                $otherUser,
                Coupon::query()->findOrFail((int) $privateCoupon['id'])
            );
            $this->fail('私有券不得走公开领取');
        } catch (BusinessException $exception) {
            $this->assertSame('该优惠券不是公开领取类型', $exception->getMessage());
        }

        $futureCoupon = $this->service()->createCoupon([
            'name' => '未开始券'.uniqid(),
            'discount_type' => 'fixed',
            'discount_value' => 5,
            'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
        ], ['operator' => 'tester']);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('优惠券尚未开始领取');

        $this->service()->claimPublicCoupon(
            $user,
            Coupon::query()->findOrFail((int) $futureCoupon['id'])
        );
    }

    // ------------------------------------------------------------------ 辅助

    private function service(): CouponService
    {
        return app(CouponService::class);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function paginateTotal(array $filters): int
    {
        return (int) $this->service()->adminList($filters)->total();
    }

    private function product(): Product
    {
        return Product::query()->create([
            'name' => '契约商品'.uniqid(),
            'product_type' => 'cloud_host',
            'status' => 1,
            'pricing' => ['monthly' => '20.00'],
            'setup_fee' => '0.00',
            'config_options' => [],
            'purchase_requires' => [],
            'stock' => -1,
            'auto_setup' => 0,
        ]);
    }
}
