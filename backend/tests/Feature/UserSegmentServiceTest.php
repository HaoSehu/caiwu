<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Models\MemberLevel;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Services\User\AccountService;
use App\Services\User\UserSegmentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * UserSegmentService 客群筛选契约测试（M9/M12 公共组件）。
 *
 * 锁定：白名单校验、各内置条件口径（实名通过态与 User::hasCompletedVerification
 * 一致、last_login_before 含从未登录、product_ids whereExists、余额真源
 * user_accounts.cash_balance）。DatabaseTransactions 回滚不污染数据。
 */
class UserSegmentServiceTest extends TestCase
{
    use DatabaseTransactions;

    private UserSegmentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(UserSegmentService::class);
    }

    public function test_status_filter_accepts_single_and_array(): void
    {
        $active = $this->makeUser(['status' => 1]);
        $disabled = $this->makeUser(['status' => 0]);

        $single = $this->service->resolveBuilder(['status' => 1])->pluck('id')->all();
        $this->assertContains($active->id, $single);
        $this->assertNotContains($disabled->id, $single);

        $array = $this->service->resolveBuilder(['status' => [0, 1]])->pluck('id')->all();
        $this->assertContains($active->id, $array);
        $this->assertContains($disabled->id, $array);
    }

    public function test_verified_filter_matches_user_model_semantics(): void
    {
        // is_verified=1 与 verification_status=2 均视为已实名（对齐 hasCompletedVerification）。
        $byFlag = $this->makeUser(['is_verified' => 1, 'verification_status' => 0]);
        $byStatus = $this->makeUser(['is_verified' => 0, 'verification_status' => 2]);
        $unverified = $this->makeUser(['is_verified' => 0, 'verification_status' => 0]);

        $verifiedIds = $this->service->resolveBuilder(['verified' => true])->pluck('id')->all();
        $this->assertContains($byFlag->id, $verifiedIds);
        $this->assertContains($byStatus->id, $verifiedIds);
        $this->assertNotContains($unverified->id, $verifiedIds);

        $unverifiedIds = $this->service->resolveBuilder(['verified' => false])->pluck('id')->all();
        $this->assertContains($unverified->id, $unverifiedIds);
        $this->assertNotContains($byFlag->id, $unverifiedIds);
    }

    public function test_member_level_ids_filter(): void
    {
        // users.member_level_id FK→member_levels，先造等级父行再引用。
        $levelA = MemberLevel::query()->create(['name' => 'segment-level-a-'.uniqid(), 'status' => 1]);
        $levelB = MemberLevel::query()->create(['name' => 'segment-level-b-'.uniqid(), 'status' => 1]);

        $levelOne = $this->makeUser(['member_level_id' => (int) $levelA->id]);
        $levelTwo = $this->makeUser(['member_level_id' => (int) $levelB->id]);
        $none = $this->makeUser(['member_level_id' => null]);

        $ids = $this->service->resolveBuilder(['member_level_ids' => [(int) $levelA->id]])->pluck('id')->all();

        $this->assertContains($levelOne->id, $ids);
        $this->assertNotContains($levelTwo->id, $ids);
        $this->assertNotContains($none->id, $ids);
    }

    public function test_registered_between_filter(): void
    {
        $old = $this->makeUserWithCreatedDate('2024-01-01 00:00:00');
        $new = $this->makeUserWithCreatedDate(now()->subDays(1)->format('Y-m-d H:i:s'));

        $ids = $this->service->resolveBuilder([
            'registered_between' => [now()->subDays(7)->format('Y-m-d H:i:s'), now()->format('Y-m-d H:i:s')],
        ])->pluck('id')->all();

        $this->assertContains($new->id, $ids);
        $this->assertNotContains($old->id, $ids);
    }

    public function test_last_login_before_includes_never_logged_in(): void
    {
        $never = $this->makeUser(['last_login_at' => null]);
        $stale = $this->makeUser(['last_login_at' => now()->subDays(30)]);
        $fresh = $this->makeUser(['last_login_at' => now()->subHour()]);

        $ids = $this->service->resolveBuilder([
            'last_login_before' => now()->subDays(7)->format('Y-m-d H:i:s'),
        ])->pluck('id')->all();

        // 从未登录视为流失侧，纳入命中。
        $this->assertContains($never->id, $ids);
        $this->assertContains($stale->id, $ids);
        $this->assertNotContains($fresh->id, $ids);
    }

    public function test_product_ids_filter_uses_services_exists(): void
    {
        $owner = $this->makeUser([]);
        $other = $this->makeUser([]);

        // services.product_id FK→products，先造商品父行再引用。
        $productId = (int) Product::query()->create([
            'name' => 'segment-test-product-'.uniqid(),
            'product_type' => 'cloud_host',
            'status' => 1,
            'pricing' => ['monthly' => '10.00'],
            'setup_fee' => '0.00',
            'config_options' => [],
            'purchase_requires' => [],
            'stock' => -1,
            'auto_setup' => 0,
        ])->id;

        Service::query()->create([
            'user_id' => $owner->id,
            'product_id' => $productId,
            'name' => 'segment-test-service',
            'billing_cycle' => 'monthly',
            'amount' => '10.00',
            'status' => 1,
        ]);

        $ids = $this->service->resolveBuilder(['product_ids' => [$productId]])->pluck('id')->all();

        $this->assertContains($owner->id, $ids);
        $this->assertNotContains($other->id, $ids);
    }

    public function test_balance_between_filter_reads_user_accounts_source(): void
    {
        $rich = $this->makeUser([]);
        $poor = $this->makeUser([]);

        app(AccountService::class)->setCashBalance($rich, 100.00);
        app(AccountService::class)->setCashBalance($poor, 5.00);

        $ids = $this->service->resolveBuilder(['balance_between' => [50, 200]])->pluck('id')->all();

        $this->assertContains($rich->id, $ids);
        $this->assertNotContains($poor->id, $ids);
    }

    public function test_unknown_filter_key_is_rejected(): void
    {
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('不支持的键');

        $this->service->resolveBuilder(['evil_key' => '1; DROP TABLE users']);
    }

    public function test_invalid_verified_value_is_rejected(): void
    {
        $this->expectException(BusinessException::class);

        $this->service->resolveBuilder(['verified' => 'not-a-bool']);
    }

    public function test_count_hits_returns_integer(): void
    {
        $this->makeUser(['status' => 1]);

        $count = $this->service->countHits(['status' => 1]);

        $this->assertGreaterThanOrEqual(1, $count);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeUser(array $overrides): User
    {
        return User::query()->create($overrides + [
            'email' => 'segment-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'segment-tester',
            'status' => 1,
            'total_sales_amount' => 0,
        ]);
    }

    private function makeUserWithCreatedDate(string $createdAt): User
    {
        $user = $this->makeUser([]);

        // DatabaseTransactions 环境下直接改 created_at 模拟注册时间。
        User::query()->whereKey($user->id)->update(['created_at' => $createdAt]);
        $user->refresh();

        return $user;
    }
}
