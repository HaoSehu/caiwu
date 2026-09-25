<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\ProductType;
use App\Exceptions\BusinessException;
use App\Models\FirstProductGroup;
use App\Models\Product;
use App\Models\SecondProductGroup;
use App\Models\ThirdProductGroup;
use App\Models\User;
use App\Services\Finance\CheckoutSecurityService;
use App\Services\Finance\CheckoutService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 结账校验的分组可见性复核回归：
 * 前台报价路径（SiteProductQuoteService::saleProductQuery）只对
 * withVisibleProductGroupPath 可见分组内的商品发报价，但结账事务内
 * 此前只校验商品 status 与库存。隐藏三级分组内的商品若持有效报价令牌
 * （隐藏前签发、令牌 TTL 未过期）仍可绕过前台过滤完成结账，
 * 结账校验必须以同一套可见性口径兜底拒绝。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class CheckoutGroupVisibilityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_checkout_rejects_product_inside_hidden_third_group(): void
    {
        $user = User::factory()->create(['is_verified' => 1]);
        $product = $this->makeProductInHiddenThirdGroup();

        // 用真实报价路径为该商品签发有效报价令牌（金额/配置/用户全部绑定一致），
        // 证明即使令牌本身完全有效，结账校验也会因分组不可见而拒绝。
        $checkout = app(CheckoutService::class);
        $quote = $checkout->quote($product, 'monthly', [], 1);
        $issued = app(CheckoutSecurityService::class)->issueQuoteToken(
            (int) $product->id,
            'monthly',
            [],
            $quote,
            ['user_id' => (int) $user->id],
        );

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('商品所在分组已隐藏，暂不可购买');

        $checkout->create((int) $user->id, [
            'product_id' => (int) $product->id,
            'billing_cycle' => 'monthly',
            'quantity' => 1,
            'config' => [],
            'quote_token' => (string) $issued['quote_token'],
        ], [
            'idempotency_key' => 'checkout-visibility-'.uniqid(),
        ]);
    }

    /**
     * 造「可见一级分组 → 可见二级分组 → 隐藏三级分组」内的上架商品。
     */
    private function makeProductInHiddenThirdGroup(): Product
    {
        // 一级分组 code 受唯一约束且类型同步可能已建行，复用既有可见分组
        $first = FirstProductGroup::query()->firstOrCreate(
            ['code' => ProductType::VPS],
            [
                'product_type' => 'cloud_server',
                'name' => '云服务器',
                'slug' => 'visibility-test-first-'.uniqid(),
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
            'slug' => 'visibility-test-second-'.uniqid(),
            'sort_order' => 999,
            'is_visible' => 1,
        ]);
        $third = ThirdProductGroup::query()->create([
            'second_product_group_id' => $second->id,
            'name' => '隐藏三级分组',
            'slug' => 'visibility-test-third-'.uniqid(),
            'sort_order' => 999,
            'is_visible' => 0,
        ]);

        return Product::query()->create([
            'product_type' => ProductType::VPS,
            'name' => '隐藏分组内商品',
            'product_group_id' => (int) $third->id,
            'status' => 1,
            'stock' => 10,
            'pricing' => ['monthly' => '35.00'],
        ]);
    }
}
