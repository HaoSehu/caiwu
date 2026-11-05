<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\ProductType;
use App\Exceptions\BusinessException;
use App\Models\AdminUser;
use App\Models\FirstProductGroup;
use App\Models\Product;
use App\Models\Role;
use App\Models\SecondProductGroup;
use App\Models\Setting;
use App\Models\ThirdProductGroup;
use App\Models\User;
use App\Services\Finance\CheckoutSecurityService;
use App\Services\Finance\CheckoutService;
use App\Services\User\AccountPolicyService;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * 账户策略（强制实名 / 强制绑定手机号 / 强制绑定邮箱）回归：
 * - 三个开关默认关闭，存量行为不变：未完成项不拦截任何关键操作；
 * - 开启对应开关后，下单/支付/充值/提现按缺失项抛出 40301/40302/40303，
 *   并在 data 中带完整缺失清单供前端渲染引导；
 * - 商品自身 purchase_requires.require_phone 仍独立生效，不受全局开关影响。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class AccountPolicyEnforcementTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        // 恢复默认，避免污染同库并行的其他用例
        $this->setPolicy(false, false, false);

        parent::tearDown();
    }

    public function test_switches_off_do_not_block_unverified_user_checkout(): void
    {
        $this->setPolicy(false, false, false);

        $user = $this->makeUser(verified: false, phone: null, email: null);
        $product = $this->makeVisibleProduct(['monthly' => '35.00']);

        // 策略关闭时未实名用户可正常创建账单（后续履约与策略无关）
        $invoice = $this->createCheckoutInvoice($user, $product);

        $this->assertSame((int) $user->id, (int) $invoice->user_id);
    }

    public function test_require_verification_blocks_checkout(): void
    {
        $this->setPolicy(true, false, false);

        $user = $this->makeUser(verified: false, phone: '13800000000', email: 'policy@example.test');
        $product = $this->makeVisibleProduct(['monthly' => '35.00']);

        try {
            $this->createCheckoutInvoice($user, $product);
            $this->fail('未实名用户在强制实名开启时应被拦截');
        } catch (BusinessException $exception) {
            $this->assertSame(40301, $exception->getErrorCode());
            $this->assertSame(['verification'], $exception->getData()['missing'] ?? null);
            $this->assertSame('checkout', $exception->getData()['action'] ?? null);
        }
    }

    public function test_require_phone_blocks_recharge(): void
    {
        $this->setPolicy(false, true, false);

        $user = $this->makeUser(verified: true, phone: null, email: 'policy@example.test');

        try {
            app(AccountPolicyService::class)->assertSatisfied($user, 'recharge');
            $this->fail('未绑定手机号时应被拦截');
        } catch (BusinessException $exception) {
            $this->assertSame(40302, $exception->getErrorCode());
            $this->assertSame(['phone'], $exception->getData()['missing'] ?? null);
        }
    }

    public function test_require_email_blocks_payment(): void
    {
        $this->setPolicy(false, false, true);

        $user = $this->makeUser(verified: true, phone: '13800000000', email: null);

        try {
            app(AccountPolicyService::class)->assertSatisfied($user, 'payment');
            $this->fail('未绑定邮箱时应被拦截');
        } catch (BusinessException $exception) {
            $this->assertSame(40303, $exception->getErrorCode());
            $this->assertSame(['email'], $exception->getData()['missing'] ?? null);
        }
    }

    public function test_require_phone_blocks_withdrawal(): void
    {
        $this->setPolicy(false, true, false);

        $user = $this->makeUser(verified: true, phone: null, email: 'policy@example.test');

        try {
            app(AccountPolicyService::class)->assertSatisfied($user, 'withdrawal');
            $this->fail('未绑定手机号时提现应被拦截');
        } catch (BusinessException $exception) {
            $this->assertSame(40302, $exception->getErrorCode());
            $this->assertSame('withdrawal', $exception->getData()['action'] ?? null);
        }
    }

    public function test_missing_list_contains_all_items_in_order(): void
    {
        $this->setPolicy(true, true, true);

        $user = $this->makeUser(verified: false, phone: null, email: null);

        try {
            app(AccountPolicyService::class)->assertSatisfied($user, 'checkout');
            $this->fail('三项全缺失时应被拦截');
        } catch (BusinessException $exception) {
            $this->assertSame(40301, $exception->getErrorCode(), '文案与业务码取第一个缺失项');
            $this->assertSame(['verification', 'phone', 'email'], $exception->getData()['missing'] ?? null);
        }
    }

    public function test_fully_compliant_user_passes_all_actions(): void
    {
        $this->setPolicy(true, true, true);

        $user = $this->makeUser(verified: true, phone: '13800000000', email: 'policy@example.test');
        $service = app(AccountPolicyService::class);

        foreach (['checkout', 'recharge', 'payment', 'withdrawal'] as $action) {
            $service->assertSatisfied($user, $action);
        }

        $this->assertSame([], $service->missingItems($user));
    }

    public function test_product_level_phone_requirement_survives_global_switch_off(): void
    {
        $this->setPolicy(false, false, false);

        $user = $this->makeUser(verified: true, phone: null, email: 'policy@example.test');
        $product = $this->makeVisibleProduct(['monthly' => '35.00'], ['require_phone' => true]);

        try {
            $this->createCheckoutInvoice($user, $product);
            $this->fail('商品级绑定手机要求应独立于全局开关生效');
        } catch (BusinessException $exception) {
            $this->assertSame(40302, $exception->getErrorCode());
            $this->assertStringContainsString('该商品需要绑定手机号', $exception->getMessage());
            // 商品级拦截不带策略清单，避免前端误以为可由策略开关关闭
            $this->assertNull($exception->getData());
        }
    }

    public function test_auth_info_exposes_account_policy_and_missing_items(): void
    {
        $this->setPolicy(true, true, true);

        $user = $this->makeUser(verified: false, phone: null, email: null);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v2/client/auth/info')->assertOk();
        $policy = $response->json('data.account_policy');

        $this->assertTrue($policy['require_verification']);
        $this->assertTrue($policy['require_phone']);
        $this->assertTrue($policy['require_email']);
        $this->assertSame(['verification', 'phone', 'email'], $policy['missing_items']);
    }

    public function test_admin_can_save_account_policy_switches(): void
    {
        $this->actingAsSettingsAdmin();

        $this->postJson('/api/v2/admin/settings', [
            'group' => 'account_policy',
            'settings' => [
                'require_verification' => 1,
                'require_phone' => 1,
                'require_email' => 0,
            ],
        ])->assertOk();

        // 保存必须真正落库，而不只是通过校验
        $this->assertSame('1', Setting::getValue('account_policy', 'require_verification'));
        $this->assertSame('1', Setting::getValue('account_policy', 'require_phone'));
        $this->assertSame('0', Setting::getValue('account_policy', 'require_email'));
        $this->assertTrue(app(AccountPolicyService::class)->policy()['require_verification']);
    }

    public function test_admin_save_rejects_unknown_account_policy_key(): void
    {
        $this->actingAsSettingsAdmin();

        $response = $this->postJson('/api/v2/admin/settings', [
            'group' => 'account_policy',
            'settings' => [
                'require_verification' => 1,
                'dirty_key' => 1,
            ],
        ])->assertStatus(422);

        $this->assertStringContainsString('不受支持的字段', (string) $response->json('message').json_encode($response->json('data.errors'), JSON_UNESCAPED_UNICODE));
        $this->assertNull(Setting::getValue('account_policy', 'dirty_key'), '脏键必须被拒绝，不能落库');
    }

    public function test_other_setting_groups_are_not_restricted_by_policy_whitelist(): void
    {
        $this->actingAsSettingsAdmin();

        // 白名单只作用于 account_policy，其它分组的既有键不能被连带拒绝
        $this->postJson('/api/v2/admin/settings', [
            'group' => 'referral',
            'settings' => ['enabled' => 1],
        ])->assertOk();
    }

    private function actingAsSettingsAdmin(): void
    {
        $role = Role::query()->create([
            'name' => 'role_'.uniqid(),
            'label' => '账户策略测试角色',
            'permissions' => [AdminPermissions::SETTINGS_VIEW, AdminPermissions::SETTINGS_MANAGE],
        ]);

        $admin = AdminUser::query()->create([
            'username' => 'admin_'.uniqid(),
            'password' => 'secret123',
            'nickname' => '账户策略测试管理员',
            'status' => 1,
            'role_id' => (int) $role->id,
        ]);

        Sanctum::actingAs($admin);
    }

    private function setPolicy(bool $verification, bool $phone, bool $email): void
    {
        Setting::setValue('account_policy', 'require_verification', $verification ? '1' : '0');
        Setting::setValue('account_policy', 'require_phone', $phone ? '1' : '0');
        Setting::setValue('account_policy', 'require_email', $email ? '1' : '0');
    }

    private function makeUser(bool $verified, ?string $phone, ?string $email): User
    {
        return User::query()->create([
            'email' => $email,
            'phone' => $phone,
            'password' => 'x',
            'nickname' => 'account-policy-tester',
            'total_sales_amount' => 0,
            'is_verified' => $verified ? 1 : 0,
            'verification_status' => $verified ? 2 : 0,
            'status' => 1,
        ]);
    }

    private function createCheckoutInvoice(User $user, Product $product)
    {
        $checkout = app(CheckoutService::class);
        $quote = $checkout->quote($product, 'monthly', [], 1);
        $issued = app(CheckoutSecurityService::class)->issueQuoteToken(
            (int) $product->id,
            'monthly',
            [],
            $quote,
            ['user_id' => (int) $user->id],
        );

        return $checkout->create((int) $user->id, [
            'product_id' => (int) $product->id,
            'billing_cycle' => 'monthly',
            'quantity' => 1,
            'config' => [],
            'quote_token' => (string) $issued['quote_token'],
        ], [
            'idempotency_key' => 'account-policy-'.uniqid(),
        ]);
    }

    /**
     * @param  array<string, string>  $pricing
     * @param  array<string, mixed>  $purchaseRequires
     */
    private function makeVisibleProduct(array $pricing, array $purchaseRequires = []): Product
    {
        $first = FirstProductGroup::query()->firstOrCreate(
            ['code' => ProductType::VPS],
            [
                'product_type' => 'cloud_server',
                'name' => '云服务器',
                'slug' => 'account-policy-first-'.uniqid(),
                'sort_order' => 999,
                'is_visible' => 1,
                'is_system' => 0,
            ]
        );
        if ((int) $first->is_visible !== 1) {
            $first->forceFill(['is_visible' => 1])->save();
        }

        $second = SecondProductGroup::query()->create([
            'first_product_group_id' => $first->id,
            'name' => '策略测试二级分组',
            'slug' => 'account-policy-second-'.uniqid(),
            'sort_order' => 999,
            'is_visible' => 1,
        ]);
        $third = ThirdProductGroup::query()->create([
            'second_product_group_id' => $second->id,
            'name' => '策略测试三级分组',
            'slug' => 'account-policy-third-'.uniqid(),
            'sort_order' => 999,
            'is_visible' => 1,
        ]);

        return Product::query()->create([
            'product_type' => ProductType::VPS,
            'name' => '账户策略测试商品',
            'product_group_id' => (int) $third->id,
            'status' => 1,
            'stock' => 10,
            'pricing' => $pricing,
            'purchase_requires' => $purchaseRequires,
        ]);
    }
}
