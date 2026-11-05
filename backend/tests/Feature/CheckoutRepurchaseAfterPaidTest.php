<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Constants\ProductType;
use App\Models\FirstProductGroup;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\SecondProductGroup;
use App\Models\ThirdProductGroup;
use App\Models\User;
use App\Services\Finance\CheckoutSecurityService;
use App\Services\Finance\CheckoutService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 重复购买回归：180 秒指纹去重只用于拦截「同一配置的重复提交」，
 * 不能把用户已支付的上一张账单当成重复提交复用。
 *
 * 同一用户对同一商品、同一周期、同一配置连续下单两次（第二次在前一次已支付后发起），
 * 第二次必须生成一张新账单；否则前端拿到已支付账单会提示「该账单已支付」，
 * 用户无法完成第二次购买。
 */
class CheckoutRepurchaseAfterPaidTest extends TestCase
{
    use DatabaseTransactions;

    public function test_second_checkout_creates_new_invoice_after_previous_one_paid(): void
    {
        $user = User::factory()->create(['is_verified' => 1]);
        $product = $this->makeVisibleProduct();

        $first = $this->createInvoice($user, $product);
        $this->assertSame(InvoiceStatus::UNPAID, (int) $first->status);

        // 用户完成支付：状态置为已支付，模拟真实已支付账单
        $first->forceFill([
            'status' => InvoiceStatus::PAID,
            'paid_amount' => $first->amount,
            'paid_at' => now(),
        ])->save();

        // 第二次购买使用不同的幂等键（官网每次点击购买都会生成新键），
        // 但商品、周期、配置完全相同，指纹一致
        $second = $this->createInvoice($user, $product);

        $this->assertNotSame(
            (int) $first->id,
            (int) $second->id,
            '第二次购买复用了已支付账单，导致用户看到「该账单已支付」'
        );
        $this->assertSame(InvoiceStatus::UNPAID, (int) $second->status);
        $this->assertSame(2, Invoice::query()->where('user_id', $user->id)->count());
    }

    public function test_second_checkout_still_reuses_recent_unpaid_invoice(): void
    {
        $user = User::factory()->create(['is_verified' => 1]);
        $product = $this->makeVisibleProduct();

        $first = $this->createInvoice($user, $product);
        // 未支付时的重复提交仍应按指纹复用，避免重复建单
        $second = $this->createInvoice($user, $product);

        $this->assertSame((int) $first->id, (int) $second->id);
        $this->assertSame(1, Invoice::query()->where('user_id', $user->id)->count());
    }

    /**
     * 造「可见一级 → 可见二级 → 可见三级」分组内的上架商品。
     */
    private function makeVisibleProduct(): Product
    {
        $first = FirstProductGroup::query()->firstOrCreate(
            ['code' => ProductType::VPS],
            [
                'product_type' => ProductType::CLOUD_SERVER,
                'name' => '云服务器',
                'slug' => 'repurchase-test-first-'.uniqid(),
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
            'name' => '可见二级分组',
            'slug' => 'repurchase-test-second-'.uniqid(),
            'sort_order' => 999,
            'is_visible' => 1,
        ]);
        $third = ThirdProductGroup::query()->create([
            'second_product_group_id' => $second->id,
            'name' => '可见三级分组',
            'slug' => 'repurchase-test-third-'.uniqid(),
            'sort_order' => 999,
            'is_visible' => 1,
        ]);

        return Product::query()->create([
            'product_type' => ProductType::CLOUD_SERVER,
            'service_type_code' => ProductType::CLOUD_SERVER,
            'custom_display_name' => '重复购买测试商品',
            'product_group_id' => (int) $third->id,
            'status' => 1,
            'stock' => 100,
            'pricing' => ['monthly' => '0.01'],
        ]);
    }

    private function createInvoice(User $user, Product $product): Invoice
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
            'idempotency_key' => 'repurchase-'.uniqid('', true),
        ]);
    }
}
