<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\CouponStatus;
use App\Constants\OrderStatus;
use App\Exceptions\BusinessException;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\UserCoupon;
use App\Services\Finance\CouponService;
use Tests\TestCase;

class CouponLifecycleRegressionTest extends TestCase
{
    public function test_sync_order_coupon_usage_counts_only_paid_like_orders(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $user = User::query()->create([
            'email' => 'coupon-usage-'.$suffix.'@example.com',
            'password' => 'secret123',
            'phone' => '136'.str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT),
            'status' => 1,
        ]);

        $coupon = Coupon::query()->create([
            'name' => 'Usage Coupon '.$suffix,
            'code' => 'USAGE'.strtoupper($suffix),
            'distribution_type' => 'public',
            'discount_scope' => 'first_month',
            'discount_type' => 'fixed',
            'discount_value' => '5.00',
            'min_amount' => '0.00',
            'used_count' => 0,
            'status' => CouponStatus::ACTIVE,
            'starts_at' => now()->subHour(),
            'expires_at' => now()->addDay(),
        ]);

        $userCoupon = UserCoupon::query()->create([
            'coupon_id' => (int) $coupon->id,
            'user_id' => (int) $user->id,
            'receive_type' => 'claim',
            'status' => 1,
            'claimed_at' => now(),
        ]);

        $order = Order::query()->create([
            'order_no' => 'CUPSYNC'.strtoupper($suffix),
            'user_id' => (int) $user->id,
            'coupon_id' => (int) $coupon->id,
            'user_coupon_id' => (int) $userCoupon->id,
            'type' => 'new',
            'amount' => '20.00',
            'discount' => '5.00',
            'billing_cycle' => 'monthly',
            'status' => OrderStatus::PENDING,
        ]);

        $service = app(CouponService::class);

        $service->syncOrderCouponUsage($order);
        $this->assertSame(0, (int) $coupon->fresh()->used_count);
        $this->assertNull($userCoupon->fresh()->last_used_at);

        $order->forceFill([
            'status' => OrderStatus::PAID,
            'paid_at' => now(),
        ])->save();
        $service->syncOrderCouponUsage($order->fresh());
        $this->assertSame(1, (int) $coupon->fresh()->used_count);
        $this->assertNotNull($userCoupon->fresh()->last_used_at);

        $order->forceFill([
            'status' => OrderStatus::PENDING,
            'paid_at' => null,
        ])->save();
        $service->syncOrderCouponUsage($order->fresh());
        $this->assertSame(0, (int) $coupon->fresh()->used_count);
        $this->assertNull($userCoupon->fresh()->last_used_at);
    }

    public function test_update_coupon_preserves_order_bound_private_assignments(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $userA = $this->createUser('a', $suffix);
        $userB = $this->createUser('b', $suffix);
        $userC = $this->createUser('c', $suffix);

        $service = app(CouponService::class);
        $payload = $this->privateCouponPayload($suffix, [(int) $userA->id, (int) $userB->id]);
        $created = $service->createCoupon($payload, [
            'operator' => 'coupon-regression',
            'trace_id' => 'coupon-create-'.$suffix,
        ]);

        $coupon = Coupon::query()->findOrFail((int) $created['id']);
        $userCouponA = UserCoupon::query()
            ->where('coupon_id', (int) $coupon->id)
            ->where('user_id', (int) $userA->id)
            ->firstOrFail();
        $userCouponB = UserCoupon::query()
            ->where('coupon_id', (int) $coupon->id)
            ->where('user_id', (int) $userB->id)
            ->firstOrFail();

        Order::query()->create([
            'order_no' => 'CUPORD'.strtoupper($suffix),
            'user_id' => (int) $userA->id,
            'coupon_id' => (int) $coupon->id,
            'user_coupon_id' => (int) $userCouponA->id,
            'type' => 'new',
            'amount' => '20.00',
            'discount' => '5.00',
            'billing_cycle' => 'monthly',
            'status' => OrderStatus::PAID,
            'paid_at' => now(),
        ]);

        $service->updateCoupon(
            $coupon,
            $this->privateCouponPayload($suffix, [(int) $userC->id]),
            [
                'operator' => 'coupon-regression',
                'trace_id' => 'coupon-update-'.$suffix,
            ]
        );

        $this->assertDatabaseHas('user_coupons', [
            'id' => (int) $userCouponA->id,
            'status' => 0,
            'receive_type' => 'grant',
        ]);
        $this->assertDatabaseMissing('user_coupons', [
            'id' => (int) $userCouponB->id,
        ]);
        $this->assertDatabaseHas('user_coupons', [
            'coupon_id' => (int) $coupon->id,
            'user_id' => (int) $userC->id,
            'receive_type' => 'grant',
            'status' => 1,
        ]);
        $this->assertDatabaseHas('orders', [
            'user_coupon_id' => (int) $userCouponA->id,
        ]);
    }

    public function test_switching_public_coupon_to_private_expires_previous_claims_for_checkout(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $claimUser = $this->createUser('claim', $suffix);
        $grantedUser = $this->createUser('grant', $suffix);
        $product = $this->createProduct($suffix);
        $service = app(CouponService::class);

        $coupon = Coupon::query()->create([
            'name' => 'Switch Coupon '.$suffix,
            'code' => 'SWITCH'.strtoupper($suffix),
            'distribution_type' => 'public',
            'discount_scope' => 'first_month',
            'discount_type' => 'fixed',
            'discount_value' => '5.00',
            'min_amount' => '0.00',
            'status' => CouponStatus::ACTIVE,
            'starts_at' => now()->subHour(),
            'expires_at' => now()->addDay(),
        ]);

        $claimedCoupon = UserCoupon::query()->create([
            'coupon_id' => (int) $coupon->id,
            'user_id' => (int) $claimUser->id,
            'receive_type' => 'claim',
            'status' => 1,
            'claimed_at' => now(),
        ]);

        $service->updateCoupon(
            $coupon,
            $this->privateCouponPayload($suffix, [(int) $grantedUser->id], [
                'name' => 'Switch Coupon '.$suffix,
                'code' => 'SWITCH'.strtoupper($suffix),
            ]),
            [
                'operator' => 'coupon-regression',
                'trace_id' => 'coupon-switch-'.$suffix,
            ]
        );

        $summary = $service->summaryForUser($claimUser);
        $expiredPage = $service->paginateForUser($claimUser, ['status' => 'expired', 'keyword' => $suffix], 1, 10);

        $this->assertSame(1, $summary['total']);
        $this->assertSame(0, $summary['available']);
        $this->assertSame(1, $summary['expired']);
        $this->assertSame(1, $expiredPage['total']);
        $this->assertSame('expired', $expiredPage['list'][0]['status'] ?? null);
        $this->assertSame('当前优惠券已改为私有发放', $expiredPage['list'][0]['status_reason'] ?? null);
        $this->assertDatabaseHas('user_coupons', [
            'coupon_id' => (int) $coupon->id,
            'user_id' => (int) $grantedUser->id,
            'receive_type' => 'grant',
            'status' => 1,
        ]);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('当前优惠券仅对指定用户发放');

        $service->previewOwnedCoupon(
            (int) $claimedCoupon->id,
            (int) $claimUser->id,
            $product,
            'monthly',
            20.0,
            'new'
        );
    }

    public function test_update_private_coupon_allows_replacing_deleted_assignments_within_total_limit(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $userA = $this->createUser('limit-a', $suffix);
        $userB = $this->createUser('limit-b', $suffix);
        $userC = $this->createUser('limit-c', $suffix);
        $service = app(CouponService::class);

        $created = $service->createCoupon(
            $this->privateCouponPayload($suffix, [(int) $userA->id, (int) $userB->id], [
                'name' => 'Limited Private Coupon '.$suffix,
                'code' => 'LIMIT'.strtoupper($suffix),
                'total_usage_limit' => 2,
            ]),
            [
                'operator' => 'coupon-regression',
                'trace_id' => 'coupon-limit-create-'.$suffix,
            ]
        );

        $coupon = Coupon::query()->findOrFail((int) $created['id']);
        $updated = $service->updateCoupon(
            $coupon,
            $this->privateCouponPayload($suffix, [(int) $userA->id, (int) $userC->id], [
                'name' => 'Limited Private Coupon '.$suffix,
                'code' => 'LIMIT'.strtoupper($suffix),
                'total_usage_limit' => 2,
            ]),
            [
                'operator' => 'coupon-regression',
                'trace_id' => 'coupon-limit-update-'.$suffix,
            ]
        );

        $this->assertSame((int) $coupon->id, (int) ($updated['id'] ?? 0));
        $this->assertDatabaseHas('user_coupons', [
            'coupon_id' => (int) $coupon->id,
            'user_id' => (int) $userA->id,
            'receive_type' => 'grant',
            'status' => 1,
        ]);
        $this->assertDatabaseMissing('user_coupons', [
            'coupon_id' => (int) $coupon->id,
            'user_id' => (int) $userB->id,
            'receive_type' => 'grant',
        ]);
        $this->assertDatabaseHas('user_coupons', [
            'coupon_id' => (int) $coupon->id,
            'user_id' => (int) $userC->id,
            'receive_type' => 'grant',
            'status' => 1,
        ]);
    }

    public function test_create_and_update_coupon_persist_manual_code(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $user = $this->createUser('code', $suffix);
        $service = app(CouponService::class);
        $initialCode = 'MANUAL'.strtoupper($suffix);
        $updatedCode = 'MANUALUPD'.strtoupper($suffix);

        $created = $service->createCoupon(
            $this->privateCouponPayload($suffix, [(int) $user->id], [
                'name' => 'Manual Code Coupon '.$suffix,
                'code' => $initialCode,
            ]),
            [
                'operator' => 'coupon-regression',
                'trace_id' => 'coupon-code-create-'.$suffix,
            ]
        );

        $coupon = Coupon::query()->findOrFail((int) $created['id']);
        $this->assertSame($initialCode, (string) $coupon->code);

        $service->updateCoupon(
            $coupon,
            $this->privateCouponPayload($suffix, [(int) $user->id], [
                'name' => 'Manual Code Coupon Updated '.$suffix,
                'code' => $updatedCode,
            ]),
            [
                'operator' => 'coupon-regression',
                'trace_id' => 'coupon-code-update-'.$suffix,
            ]
        );

        $this->assertSame($updatedCode, (string) $coupon->fresh()->code);
    }

    private function createUser(string $tag, string $suffix): User
    {
        return User::query()->create([
            'email' => "coupon-{$tag}-{$suffix}@example.com",
            'password' => 'secret123',
            'phone' => '135'.str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT),
            'status' => 1,
        ]);
    }

    private function createProduct(string $suffix): Product
    {
        return Product::query()->create([
            'name' => 'Coupon Product '.$suffix,
            'product_type' => 'server',
            'pricing' => ['monthly' => '20.00'],
            'setup_fee' => '0.00',
            'config_options' => [],
            'purchase_requires' => [],
            'stock' => -1,
            'status' => 1,
            'auto_setup' => 0,
        ]);
    }

    private function privateCouponPayload(string $suffix, array $userIds, array $overrides = []): array
    {
        return array_merge([
            'name' => 'Private Coupon '.$suffix,
            'code' => 'PRIVATE'.strtoupper($suffix),
            'distribution_type' => 'private',
            'discount_scope' => 'first_month',
            'discount_type' => 'fixed',
            'discount_value' => 5,
            'min_amount' => 0,
            'max_discount_amount' => null,
            'billing_cycles' => [],
            'product_ids' => [],
            'first_order_only' => false,
            'user_ids' => $userIds,
            'total_usage_limit' => null,
            'per_user_limit' => null,
            'status' => CouponStatus::ACTIVE,
            'sort_order' => 0,
            'starts_at' => now()->subHour()->format('Y-m-d H:i:s'),
            'expires_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'description' => null,
            'remark' => null,
        ], $overrides);
    }
}
