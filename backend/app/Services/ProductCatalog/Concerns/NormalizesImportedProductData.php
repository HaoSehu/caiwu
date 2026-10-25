<?php

declare(strict_types=1);

namespace App\Services\ProductCatalog\Concerns;

/**
 * 上游商品导入数据的归一化规则：
 * 配置项/子项键值、计费周期换算、价格区间外推、配置项参数串。
 *
 * 从 ProductSyncService 抽出，保持纯函数语义（不访问数据库、不发外部请求），
 * 便于单独回归与复用；依赖由使用方类提供 normalizePricing / normalizeConfigOptions。
 */
trait NormalizesImportedProductData
{
    /** @var array<string, int> */
    private const IMPORT_PRICING_MONTHS = [
        'monthly' => 1,
        'quarterly' => 3,
        'semiannually' => 6,
        'annually' => 12,
    ];

    /**
     * 配置项的稳定匹配键：优先 field，其次 name，最后回退下标。
     */
    private function resolveConfigOptionKey(array $option, int $index): string
    {
        $field = trim((string) ($option['field'] ?? ''));
        if ($field !== '') {
            return 'field:'.$field;
        }

        $name = trim((string) ($option['name'] ?? ''));
        if ($name !== '') {
            return 'name:'.$name;
        }

        return 'index:'.$index;
    }

    /**
     * 配置子项的稳定匹配键：优先 id，其次 option_name_first/option_name，最后回退下标。
     */
    private function resolveConfigSubOptionKey(array $subOption, int $index): string
    {
        $id = (int) ($subOption['id'] ?? 0);
        if ($id > 0) {
            return 'id:'.$id;
        }

        $value = trim((string) ($subOption['option_name_first'] ?? $subOption['option_name'] ?? ''));
        if ($value !== '') {
            return 'value:'.$value;
        }

        return 'index:'.$index;
    }

    /**
     * 上游金额统一成两位小数；非数字或非正数视为未提供。
     */
    private function normalizeImportedAmount(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $amount = (float) $value;

        return $amount > 0 ? number_format($amount, 2, '.', '') : null;
    }

    /**
     * 把上游「月付价」或「整期总价 + 周期」折算成月单价。
     */
    private function resolveMonthlyBaseAmount(mixed $monthlyCandidate, mixed $productPriceCandidate, mixed $billingCycle): ?float
    {
        $monthlyAmount = $this->normalizeImportedAmount($monthlyCandidate);
        if ($monthlyAmount !== null) {
            return (float) $monthlyAmount;
        }

        $productPrice = $this->normalizeImportedAmount($productPriceCandidate);
        if ($productPrice === null) {
            return null;
        }

        $months = $this->resolveBillingCycleMonths($billingCycle);
        if ($months <= 0) {
            return null;
        }

        return round(((float) $productPrice) / $months, 2);
    }

    /**
     * 上游计费周期别名映射到本项目周期标识，未知值按月付处理。
     */
    private function mapSupplierBillingCycle(mixed $billingCycle): string
    {
        return match (strtolower(trim((string) $billingCycle))) {
            'free', 'monthly' => 'monthly',
            'quarterly' => 'quarterly',
            'semiannually', 'semi-annually', 'semi' => 'semiannually',
            'annually', 'yearly' => 'annually',
            'biennially', 'biennial' => 'biennially',
            'triennially', 'triennial' => 'triennially',
            'onetime', 'one_time', 'once' => 'onetime',
            default => 'monthly',
        };
    }

    /**
     * 周期对应的月数；非标准周期按 1 个月计。
     */
    private function resolveBillingCycleMonths(mixed $billingCycle): int
    {
        $cycle = $this->mapSupplierBillingCycle($billingCycle);

        return self::IMPORT_PRICING_MONTHS[$cycle] ?? 1;
    }

    /**
     * 归一化上游配置项：补齐排序、必填、隐藏、升级、优惠码与参数串字段。
     */
    private function normalizeImportedConfigOptions(mixed $configOptions): array
    {
        $items = $this->normalizeConfigOptions($configOptions);

        return collect($items)
            ->map(function (array $item, int $index) {
                $normalized = $item;
                $normalized['sort_order'] = (int) ($item['sort_order'] ?? $item['order'] ?? ($index + 1));
                $normalized['required'] = (int) ($item['required'] ?? 0);
                $normalized['hidden'] = (int) ($item['hidden'] ?? 0);
                $normalized['allow_upgrade'] = (int) ($item['allow_upgrade'] ?? 0);
                $normalized['allow_promo_code'] = array_key_exists('allow_promo_code', $item)
                    ? (int) $item['allow_promo_code']
                    : 1;
                $normalized['parameter'] = trim((string) ($item['parameter'] ?? $this->buildImportedConfigParameter($item)));
                $normalized['sub'] = $this->normalizeImportedConfigSubOptions($item['sub'] ?? []);

                return $normalized;
            })
            ->values()
            ->all();
    }

    /**
     * 归一化配置子项：补齐显示名、排序、数量区间与价格表。
     */
    private function normalizeImportedConfigSubOptions(mixed $subOptions): array
    {
        if (! is_array($subOptions)) {
            return [];
        }

        return collect($subOptions)
            ->filter(fn ($sub) => is_array($sub))
            ->map(function (array $sub, int $index) {
                $pricing = $this->normalizeImportedSubPricing($sub['pricing'] ?? $sub['pricings'] ?? []);
                $optionName = trim((string) ($sub['option_name'] ?? $sub['version'] ?? ''));
                $optionNameFirst = trim((string) ($sub['option_name_first'] ?? ''));

                if ($optionNameFirst === '') {
                    $optionNameFirst = $optionName !== '' ? $optionName : (string) ($sub['id'] ?? '');
                }

                return array_merge($sub, [
                    'option_name' => $optionName,
                    'option_name_first' => $optionNameFirst,
                    'hidden' => (int) ($sub['hidden'] ?? 0),
                    'sort_order' => (int) ($sub['sort_order'] ?? $sub['order'] ?? $index),
                    'qty_minimum' => (int) ($sub['qty_minimum'] ?? 0),
                    'qty_maximum' => (int) ($sub['qty_maximum'] ?? 0),
                    'pricing' => $pricing,
                ]);
            })
            ->values()
            ->all();
    }

    /**
     * 子项价格表：能直接识别的周期原样保留，否则按周期内首个可用价格反推月付价再外推。
     */
    private function normalizeImportedSubPricing(mixed $pricing): array
    {
        $pricingData = [];

        if (is_array($pricing)) {
            $pricingData = isset($pricing[0]) && is_array($pricing[0])
                ? (array) $pricing[0]
                : $pricing;
        }

        $directPricing = $this->normalizePricing($this->normalizeImportedPricingKeys($pricingData));
        if ($directPricing !== []) {
            return $directPricing;
        }

        $monthlyBasePrice = $this->resolveMonthlyBaseAmount(
            $pricingData['monthly'] ?? null,
            $this->resolveFirstAvailablePricingValue($pricingData),
            $this->resolveFirstAvailablePricingCycle($pricingData)
        );

        if ($monthlyBasePrice === null) {
            return [];
        }

        $normalized = [];
        foreach (self::IMPORT_PRICING_MONTHS as $cycle => $months) {
            $normalized[$cycle] = number_format($monthlyBasePrice * $months, 2, '.', '');
        }

        return $normalized;
    }

    /**
     * 上游周期键名到本项目周期键名的映射（保留上游额外周期）。
     */
    private function normalizeImportedPricingKeys(array $pricingData): array
    {
        $normalized = [];
        $cycleMap = [
            'hour' => 'hour',
            'day' => 'day',
            'ontrial' => 'ontrial',
            'monthly' => 'monthly',
            'quarterly' => 'quarterly',
            'semiannually' => 'semiannually',
            'annually' => 'annually',
            'biennially' => 'biennially',
            'triennially' => 'triennially',
            'fourly' => 'fourly',
            'fively' => 'fively',
            'sixly' => 'sixly',
            'sevenly' => 'sevenly',
            'eightly' => 'eightly',
            'ninely' => 'ninely',
            'tenly' => 'tenly',
            'onetime' => 'one_time',
            'one_time' => 'one_time',
        ];

        foreach ($cycleMap as $source => $target) {
            if (! array_key_exists($source, $pricingData)) {
                continue;
            }

            $normalized[$target] = $pricingData[$source];
        }

        return $normalized;
    }

    /**
     * 取首个可用价格值：先标准周期，再长周期/一次性。
     */
    private function resolveFirstAvailablePricingValue(array $pricingData): mixed
    {
        foreach (array_keys(self::IMPORT_PRICING_MONTHS) as $cycle) {
            if (($pricingData[$cycle] ?? null) !== null && $pricingData[$cycle] !== '') {
                return $pricingData[$cycle];
            }
        }

        foreach (['biennially', 'triennially', 'onetime', 'onetime_fee', 'yearly'] as $cycle) {
            if (($pricingData[$cycle] ?? null) !== null && $pricingData[$cycle] !== '') {
                return $pricingData[$cycle];
            }
        }

        return null;
    }

    /**
     * 与 resolveFirstAvailablePricingValue 对应的周期；无命中时按 monthly。
     */
    private function resolveFirstAvailablePricingCycle(array $pricingData): string
    {
        foreach (array_keys(self::IMPORT_PRICING_MONTHS) as $cycle) {
            if (($pricingData[$cycle] ?? null) !== null && $pricingData[$cycle] !== '') {
                return $cycle;
            }
        }

        foreach (['biennially', 'triennially', 'onetime', 'onetime_fee', 'yearly'] as $cycle) {
            if (($pricingData[$cycle] ?? null) !== null && $pricingData[$cycle] !== '') {
                return $cycle;
            }
        }

        return 'monthly';
    }

    /**
     * 由子项生成配置项参数串（value|label 逗号分隔，跳过隐藏项）。
     */
    private function buildImportedConfigParameter(array $item): string
    {
        if (! is_array($item['sub'] ?? null)) {
            return '';
        }

        return collect($item['sub'])
            ->filter(fn ($sub) => is_array($sub) && (int) ($sub['hidden'] ?? 0) !== 1)
            ->map(function (array $sub) {
                $value = trim((string) ($sub['option_name_first'] ?? $sub['option_name'] ?? $sub['id'] ?? ''));
                $label = trim((string) ($sub['version'] ?? $sub['option_name'] ?? $sub['option_name_first'] ?? $sub['id'] ?? ''));

                return $value !== '' ? "{$value}|{$label}" : '';
            })
            ->filter()
            ->implode(',');
    }
}
