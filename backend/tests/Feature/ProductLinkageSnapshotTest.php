<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\ProductType;
use App\Exceptions\BusinessException;
use App\Models\FirstProductGroup;
use App\Models\Product;
use App\Models\SecondProductGroup;
use App\Models\ThirdProductGroup;
use App\Services\Finance\CheckoutService;
use App\Services\ProductCatalog\ProductAdminService;
use App\Support\Money;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * M7 联动保存与计价快照：
 * - 保存校验：ProductAdminService 拦截非法规则；合法规则落库；未提交键不触碰原值。
 * - 下单重算：quoteWithLinkage 按规则重算最终价（预览与下单同一真源）。
 * - 快照：config_pricing_snapshot 并入 linkage + rules_hash。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class ProductLinkageSnapshotTest extends TestCase
{
    use DatabaseTransactions;

    /** @var array<int, mixed> */
    private array $configOptions = [
        ['field' => 'os', 'label' => '操作系统', 'option_type' => 1, 'sub' => [
            ['id' => 'windows', 'label' => 'Windows'],
            ['id' => 'linux', 'label' => 'Linux'],
        ]],
        ['field' => 'rdp_edition', 'label' => 'RDP 版本', 'option_type' => 1, 'sub' => [
            ['id' => 'standard', 'label' => '标准版'],
            ['id' => 'pro', 'label' => '专业版'],
        ]],
        ['field' => 'memory', 'label' => '内存', 'option_type' => 1, 'sub' => [
            ['id' => '4g', 'label' => '4G', 'pricing' => ['monthly' => '10.00']],
            ['id' => '8g', 'label' => '8G', 'pricing' => ['monthly' => '20.00']],
        ]],
    ];

    /** @var array<int, array<string, mixed>> */
    private array $rules = [
        [
            'id' => 'rule_os_win',
            'when' => ['all' => [['field' => 'os', 'op' => 'eq', 'value' => 'windows']]],
            'effects' => [
                ['type' => 'show', 'target' => 'rdp_edition'],
                ['type' => 'price_adjust', 'target' => 'memory', 'scope' => ['sub_option_id' => '8g'], 'mode' => 'fixed', 'value' => 5],
            ],
        ],
    ];

    public function test_update_product_rejects_invalid_linkage_rules(): void
    {
        $product = $this->makeProduct();

        $this->expectException(BusinessException::class);

        app(ProductAdminService::class)->updateProduct($product, [
            'third_product_group_id' => (int) $product->product_group_id,
            'pricing' => ['monthly' => '35.00'],
            'config_options' => $this->configOptions,
            // 非法：目标字段不存在
            'config_linkage_rules' => [
                [
                    'id' => 'bad_rule',
                    'when' => ['all' => [['field' => 'os', 'op' => 'eq', 'value' => 'windows']]],
                    'effects' => [['type' => 'show', 'target' => 'ghost_field']],
                ],
            ],
        ]);
    }

    public function test_update_product_persists_valid_linkage_rules(): void
    {
        $product = $this->makeProduct();

        $updated = app(ProductAdminService::class)->updateProduct($product, [
            'third_product_group_id' => (int) $product->product_group_id,
            'pricing' => ['monthly' => '35.00'],
            'config_options' => $this->configOptions,
            'config_linkage_rules' => $this->rules,
        ]);

        // config_linkage_rules 落在 MySQL json 列：对象键被规范化重排，回读值与提交
        // 字面量键序不同但语义等价，故按规范形态比较（数组列表顺序保持不变）。
        $this->assertSame(
            self::canonicalize($this->rules),
            self::canonicalize($updated->fresh()->config_linkage_rules)
        );
    }

    public function test_quote_with_linkage_recalculates_final_amount(): void
    {
        $service = app(CheckoutService::class);
        $product = $this->makeProductWithRules();

        $normalized = $service->normalizeConfig($product, [
            'os' => 'windows',
            'rdp_edition' => 'standard',
            'memory' => '8g',
        ]);

        $result = $service->quoteWithLinkage($product, 'monthly', $normalized, 1);

        // 基础 35.00 + 内存 8g 20.00 + 联动 fixed 5.00 = 60.00
        $this->assertSame('60.00', $result['quote']['total_amount']);
        $this->assertSame('25.00', $result['quote']['unit_config_amount']);
        $this->assertSame(['rule_os_win'], $result['linkage']['applied_rule_ids']);
        $this->assertSame(5.0, $result['linkage']['price_delta']);
        // 指纹真源是落库后的（经 json 规范化的）规则，与 quoteWithLinkage 同源。
        $this->assertSame(md5(json_encode((array) $product->config_linkage_rules)), $result['rules_hash']);
    }

    public function test_quote_without_rules_keeps_legacy_behavior(): void
    {
        // 回归红线：无规则（null/空数组）时金额与既有 quote() 完全一致
        $service = app(CheckoutService::class);
        $product = $this->makeProduct();

        foreach ([null, []] as $rules) {
            $product->config_linkage_rules = $rules;
            $normalized = $service->normalizeConfig($product, ['os' => 'windows', 'memory' => '8g']);

            $result = $service->quoteWithLinkage($product, 'monthly', $normalized, 1);
            $legacy = $service->quote($product, 'monthly', $normalized, 1);

            $this->assertNull($result['linkage']);
            $this->assertNull($result['rules_hash']);
            $this->assertSame($legacy['total_amount'], $result['quote']['total_amount']);
            $this->assertSame($legacy['unit_config_amount'], $result['quote']['unit_config_amount']);
        }
    }

    public function test_pricing_snapshot_contains_linkage_and_rules_hash(): void
    {
        $service = app(CheckoutService::class);
        $product = $this->makeProductWithRules();

        $normalized = $service->normalizeConfig($product, [
            'os' => 'windows',
            'rdp_edition' => 'standard',
            'memory' => '8g',
        ]);
        $result = $service->quoteWithLinkage($product, 'monthly', $normalized, 1);

        $snapshot = $service->buildConfigPricingSnapshot($product, 'monthly', $result['config'], 1);
        $snapshot = $service->withLinkageSnapshot($snapshot, $result['linkage'], $result['rules_hash']);

        $this->assertSame(['rule_os_win'], $snapshot['linkage']['applied_rule_ids']);
        $this->assertSame(md5(json_encode((array) $product->config_linkage_rules)), $snapshot['rules_hash']);
        $this->assertSame('25.00', $snapshot['unit_config_amount']);
    }

    public function test_snapshot_without_linkage_has_no_extra_keys(): void
    {
        // 回归红线：无规则时快照不新增任何键
        $service = app(CheckoutService::class);
        $product = $this->makeProduct();

        $snapshot = $service->buildConfigPricingSnapshot($product, 'monthly', ['os' => 'windows', 'memory' => '8g'], 1);
        $merged = $service->withLinkageSnapshot($snapshot, null, null);

        $this->assertSame($snapshot, $merged);
        $this->assertArrayNotHasKey('linkage', $merged);
        $this->assertArrayNotHasKey('rules_hash', $merged);
    }

    public function test_money_helpers_keep_snapshot_precision(): void
    {
        // 金额口径守卫：快照金额经 Money 分位舍入
        $this->assertSame('60.00', Money::format(Money::add(35.0, 20.0, 5.0)));
    }

    private function makeProductWithRules(): Product
    {
        $product = $this->makeProduct();
        $product->forceFill(['config_linkage_rules' => $this->rules])->save();

        return $product->refresh();
    }

    /**
     * MySQL json 列会按「键长度+字节序」规范化对象键序，回读数组与提交字面量
     * 键序不同但语义等价；递归按键排序得到规范形态（列表顺序保持不变）。
     */
    private static function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $canonical = array_map(self::canonicalize(...), $value);
        if (! array_is_list($canonical)) {
            ksort($canonical);
        }

        return $canonical;
    }

    private function makeProduct(): Product
    {
        // 结账/保存链路的分组可见性复核要求商品挂可见三级分组
        $first = FirstProductGroup::query()->firstOrCreate(
            ['code' => ProductType::VPS],
            [
                'product_type' => 'cloud_server',
                'name' => '联动测试一级分组',
                'slug' => 'linkage-first-'.uniqid(),
                'sort_order' => 999,
                'is_visible' => 1,
                'is_system' => 0,
            ]
        );
        $second = SecondProductGroup::query()->create([
            'first_product_group_id' => (int) $first->id,
            'name' => '联动测试二级分组',
            'slug' => 'linkage-second-'.uniqid(),
            'sort_order' => 999,
            'is_visible' => 1,
        ]);
        $third = ThirdProductGroup::query()->create([
            'second_product_group_id' => (int) $second->id,
            'name' => '联动测试三级分组',
            'slug' => 'linkage-third-'.uniqid(),
            'sort_order' => 999,
            'is_visible' => 1,
        ]);

        return Product::query()->create([
            'product_type' => 'cloud_host',
            'name' => '联动测试商品-'.uniqid(),
            'product_group_id' => (int) $third->id,
            'status' => 1,
            'stock' => -1,
            'pricing' => ['monthly' => '35.00'],
            'setup_fee' => 0,
            'config_options' => $this->configOptions,
        ]);
    }
}
