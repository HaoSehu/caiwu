<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use App\Services\ProductCatalog\ProductDisplayNameResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 展示名 remark HTML 渗入修复回归：
 * - Resolver 输出的展示名必须剥离 HTML 标签/实体，回退链不得引入 remark（魔方财务商品卡片 HTML）。
 * - 账单明细投影（invoice_items.item_name）写入前二次清洗限长 190，作为第二道防线。
 * Resolver 部分使用未落库的 Product 实例（id=0 时不触发规格目录与兜底单查），Invoice 部分使用
 * DatabaseTransactions：所有写入在测试结束后回滚，不污染 idc_test 现有数据。
 */
class ProductDisplayNameSanitizeTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * remark 存的是魔方财务商品卡片 HTML：展示名回退链必须排除 remark，
     * 只回退到首个非空候选（supplier_product_name），而不是把干草堆整体拼进展示名。
     */
    public function test_display_name_fallback_excludes_remark_html(): void
    {
        // supplier_product_name 不在 $fillable 中，DB 读取场景用 forceFill 直接写入属性
        $product = (new Product)->forceFill([
            'supplier_product_name' => 'Cloud Basic',
            'remark' => '<div class="product-card"><span>高端企业级方案</span></div>',
        ]);

        $resolved = (new ProductDisplayNameResolver)->resolveForProduct($product);

        $this->assertSame('Cloud Basic', $resolved['product_display_name']);
        $this->assertSame('Cloud Basic', $resolved['product_spec_display']);
        $this->assertSame('Cloud Basic', $resolved['combined_display_name']);
        $this->assertStringNotContainsString('<', $resolved['product_display_name'], '展示名不得残留 HTML 标签');
        $this->assertStringNotContainsString('高端企业级方案', $resolved['product_display_name'], 'remark 内容不得渗入展示名');
    }

    /**
     * 展示名清洗：解码 HTML 实体、去标签、压缩空白、限长 200。
     */
    public function test_display_name_decodes_entities_and_caps_length(): void
    {
        $product = new Product([
            'custom_display_name' => '<b>企业'.str_repeat('云', 260).'&nbsp;服务器</b>',
        ]);

        $resolved = (new ProductDisplayNameResolver)->resolveForProduct($product);

        $this->assertLessThanOrEqual(200, mb_strlen($resolved['product_display_name']), '展示名限长 200');
        $this->assertStringStartsWith('企业', $resolved['product_display_name']);
        $this->assertStringNotContainsString('<b>', $resolved['product_display_name'], '展示名不得残留 HTML 标签');
        $this->assertStringNotContainsString('&nbsp;', $resolved['product_display_name'], 'HTML 实体必须被解码');
        $this->assertLessThanOrEqual(200, mb_strlen($resolved['combined_display_name']), '合并展示名同样限长 200');
    }

    /**
     * 第二道防线：item_name 还可能从 Order::display_product_name 等快照来源取值，
     * 不全都经过 Resolver，账单明细写入前必须再清洗并限长 190。
     */
    public function test_invoice_item_name_strips_html_and_caps_length(): void
    {
        $user = User::query()->create([
            'email' => 'display-sanitize-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'display-sanitize-tester',
            'total_sales_amount' => 0,
        ]);

        $invoice = Invoice::query()->create([
            'invoice_no' => 'IV'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => $user->id,
            'type' => 'renew',
            'amount' => 40.00,
            'paid_amount' => 0,
            'status' => InvoiceStatus::UNPAID,
            'config_snapshot' => [
                'product_name' => '<div class="card">企业与'.str_repeat('高', 180).'&nbsp;套餐</div>',
            ],
        ]);

        $invoice->syncInvoiceItemProjection();

        $item = DB::table('invoice_items')->where('invoice_id', (int) $invoice->id)->first();
        $this->assertNotNull($item);
        $this->assertStringNotContainsString('<', (string) $item->item_name, '账单明细名不得残留 HTML 标签');
        $this->assertStringNotContainsString('&nbsp;', (string) $item->item_name, 'HTML 实体必须被解码');
        $this->assertStringNotContainsString('  ', (string) $item->item_name, '连续空白必须被压缩');
        $this->assertLessThanOrEqual(190, mb_strlen((string) $item->item_name), '账单明细名限长 190（对齐 item_name 列宽）');
    }
}
