<?php

declare(strict_types=1);

namespace App\Services\Upstream\Support;

use Illuminate\Support\Str;

/**
 * 上游配置项归一化的单一实现（D3B-04）。
 *
 * 平台驱动（HandlesApiConfigOptions）与 zjmf_finance 插件（ZjmfCatalogService）
 * 原有 6 个逐字节相同的归一化方法双份维护，任一侧修正无法传导到另一侧；
 * 现收敛为本类，两侧共同调用。无状态、纯函数，不依赖容器与网络。
 */
final class UpstreamConfigOptionNormalizer
{
    /**
     * 上游 option_type → 平台规格字段映射（null 表示按名称推断）。
     *
     * @var array<int, string|null>
     */
    public const CONFIG_OPTION_FIELD_MAP = [
        1 => null,
        2 => null,
        3 => null,
        4 => 'ip_num',
        5 => 'os',
        6 => 'cpu',
        7 => 'cpu',
        8 => 'memory',
        9 => 'memory',
        10 => 'bw',
        11 => 'bw',
        12 => 'area',
        13 => 'system_disk_size',
        14 => 'system_disk_size',
        15 => null,
        16 => 'cpu',
        17 => 'memory',
        18 => 'bw',
        19 => 'system_disk_size',
        20 => null,
    ];

    /**
     * 范围/滑块型 option_type：不从 sub_options 推断规格值，使用 qty_min/max 区间。
     *
     * @var array<int, int>
     */
    public const RANGE_OPTION_TYPES = [4, 7, 9, 11, 14, 15, 16, 17, 18, 19];

    /**
     * 上游计费周期键 → 平台周期键。
     *
     * @var array<string, string>
     */
    public const CONFIG_PRICING_CYCLE_MAP = [
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

    /**
     * 归一化上游配置项列表：字段推断、子项、排序、数量区间与计费周期统一口径。
     *
     * @param  array<int, mixed>  $configOptions
     * @return array<int, array<string, mixed>>
     */
    public static function normalizeRemoteConfigOptions(array $configOptions): array
    {
        return collect($configOptions)
            ->filter(fn ($item) => is_array($item))
            ->values()
            ->map(function (array $item, int $index) {
                $type = (int) ($item['option_type'] ?? 0);
                $name = trim((string) ($item['option_name'] ?? $item['name'] ?? ''));
                $nameParts = explode('|', $name, 2);
                $displayName = trim((string) (count($nameParts) > 1 ? $nameParts[1] : $name));
                $field = self::resolveConfigOptionField(
                    $item,
                    self::CONFIG_OPTION_FIELD_MAP[$type] ?? null,
                    count($nameParts) > 1 ? trim($nameParts[0]) : '',
                    $displayName
                );
                $subOptions = self::normalizeRemoteConfigSubOptions($item['sub'] ?? [], $type);
                $sortOrder = (int) ($item['sort_order'] ?? $item['order'] ?? ($index + 1));
                $optionId = (int) ($item['id'] ?? $item['config_id'] ?? 0);
                $isRange = in_array($type, self::RANGE_OPTION_TYPES, true);

                return array_merge($item, [
                    'id' => $optionId,
                    'config_id' => $optionId,
                    'field' => $field,
                    'name' => $displayName !== '' ? $displayName : $name,
                    'option_name' => $name,
                    'required' => (int) ($item['required'] ?? 0),
                    'hidden' => (int) ($item['hidden'] ?? 0),
                    'order' => $sortOrder,
                    'sort_order' => $sortOrder,
                    'allow_upgrade' => (int) ($item['allow_upgrade'] ?? $item['upgrade'] ?? 0),
                    'allow_promo_code' => array_key_exists('allow_promo_code', $item)
                        ? (int) $item['allow_promo_code']
                        : 1,
                    'qty_minimum' => $isRange ? (int) ($item['qty_minimum'] ?? 0) : 0,
                    'qty_maximum' => $isRange ? (int) ($item['qty_maximum'] ?? 0) : 0,
                    'qty_stage' => max(1, (int) ($item['qty_stage'] ?? 1)),
                    'unit' => (string) ($item['unit'] ?? ''),
                    'parameter' => trim((string) ($item['parameter'] ?? self::buildRemoteConfigOptionParameter($subOptions, $type))),
                    'sub' => $subOptions,
                ]);
            })
            ->all();
    }

    /**
     * 归一化配置项子项：名称拆分、展示标签、隐藏位与子项计费。
     *
     * @return array<int, array<string, mixed>>
     */
    public static function normalizeRemoteConfigSubOptions(mixed $subOptions, int $optionType): array
    {
        if (! is_array($subOptions)) {
            return [];
        }

        return collect($subOptions)
            ->filter(fn ($sub) => is_array($sub))
            ->values()
            ->map(function (array $sub, int $index) use ($optionType) {
                $rawOptionName = trim((string) ($sub['option_name'] ?? $sub['version'] ?? ''));
                [$optionValue, $optionLabel, $versionLabel] = self::parseRemoteSubOptionName(
                    $rawOptionName,
                    $optionType,
                    trim((string) ($sub['option_name_first'] ?? $sub['id'] ?? ''))
                );
                $pricing = self::normalizeRemoteSubPricing($sub['pricing'] ?? $sub['pricings'] ?? []);
                $subId = (int) ($sub['id'] ?? 0);
                $configId = (int) ($sub['config_id'] ?? $sub['configid'] ?? 0);

                return array_merge($sub, [
                    'id' => $subId,
                    'config_id' => $configId,
                    'configid' => $configId > 0 ? $configId : ($sub['configid'] ?? null),
                    'option_name' => $optionLabel !== '' ? $optionLabel : ($optionValue !== '' ? $optionValue : $rawOptionName),
                    'option_name_first' => $optionValue !== '' ? $optionValue : trim((string) ($sub['option_name_first'] ?? $subId)),
                    'version' => $versionLabel !== '' ? $versionLabel : ($optionLabel !== '' ? $optionLabel : $rawOptionName),
                    'hidden' => (int) ($sub['hidden'] ?? 0),
                    'sort_order' => (int) ($sub['sort_order'] ?? $sub['order'] ?? $index),
                    'qty_minimum' => (int) ($sub['qty_minimum'] ?? 0),
                    'qty_maximum' => (int) ($sub['qty_maximum'] ?? 0),
                    'pricing' => $pricing,
                ]);
            })
            ->all();
    }

    /**
     * 解析子项名称："value|label" 拆分；label 支持「^」分段取末段做展示标签；
     * option_type=5（操作系统）保留原始 label 不做分段。
     *
     * @return array{0: string, 1: string, 2: string} [值, 展示标签, 版本标签]
     */
    public static function parseRemoteSubOptionName(string $rawOptionName, int $optionType, string $fallbackValue = ''): array
    {
        $rawOptionName = trim($rawOptionName);
        [$firstPart, $secondPart] = array_pad(explode('|', $rawOptionName, 2), 2, '');

        $optionValue = trim($firstPart) !== '' ? trim($firstPart) : trim($fallbackValue);
        $rawLabel = trim($secondPart) !== '' ? trim($secondPart) : $rawOptionName;
        $rawLabel = $rawLabel !== '' ? $rawLabel : $optionValue;

        if ($optionType === 5) {
            return [$optionValue, $rawLabel, $rawLabel];
        }

        $displayLabel = $rawLabel;
        if (str_contains($rawLabel, '^')) {
            $segments = array_values(array_filter(array_map('trim', explode('^', $rawLabel))));
            $displayLabel = end($segments) ?: $rawLabel;
        }

        return [$optionValue, $displayLabel, $displayLabel];
    }

    /**
     * 归一化子项计费：仅保留映射表内、数值非空的周期，统一两位小数字符串；
     * one_time 键名双写兼容。
     *
     * @return array<string, string>
     */
    public static function normalizeRemoteSubPricing(mixed $pricing): array
    {
        if (! is_array($pricing)) {
            return [];
        }

        $pricingData = isset($pricing[0]) && is_array($pricing[0])
            ? (array) $pricing[0]
            : $pricing;

        $normalized = [];
        foreach (self::CONFIG_PRICING_CYCLE_MAP as $sourceKey => $targetKey) {
            if (! array_key_exists($sourceKey, $pricingData) || $pricingData[$sourceKey] === '' || $pricingData[$sourceKey] === null) {
                continue;
            }

            if (! is_numeric($pricingData[$sourceKey])) {
                continue;
            }

            $normalized[$targetKey] = number_format((float) $pricingData[$sourceKey], 2, '.', '');
        }

        if (isset($normalized['one_time']) && ! isset($normalized['onetime'])) {
            $normalized['onetime'] = $normalized['one_time'];
        }

        return $normalized;
    }

    /**
     * 由可见子项生成 "value|label" 逗号串作为购买参数；范围型配置项无参数。
     *
     * @param  array<int, mixed>  $subOptions
     */
    public static function buildRemoteConfigOptionParameter(array $subOptions, int $optionType): string
    {
        if (in_array($optionType, self::RANGE_OPTION_TYPES, true)) {
            return '';
        }

        return collect($subOptions)
            ->filter(fn ($sub) => is_array($sub) && (int) ($sub['hidden'] ?? 0) !== 1)
            ->map(function (array $sub) {
                $value = trim((string) ($sub['option_name_first'] ?? $sub['id'] ?? ''));
                $label = trim((string) ($sub['version'] ?? $sub['option_name'] ?? $value));

                return $value !== '' ? "{$value}|{$label}" : '';
            })
            ->filter()
            ->implode(',');
    }

    /**
     * 解析规格字段名：显式名称前缀优先，其次按展示名关键词（IPv6/IPv4/磁盘/带宽）
     * 与映射表推断，最后回退到展示名 slug。
     *
     * @param  array<string, mixed>  $item
     */
    public static function resolveConfigOptionField(array $item, ?string $mappedField, string $nameField, string $displayName): string
    {
        $explicitField = trim($nameField);
        if ($explicitField !== '') {
            return $explicitField;
        }

        $normalizedDisplayName = trim($displayName);
        $lowerDisplayName = Str::lower($normalizedDisplayName);

        if (str_contains($lowerDisplayName, 'ipv6')) {
            return 'ipv6_num';
        }

        if (str_contains($lowerDisplayName, 'ipv4')) {
            return 'ip_num';
        }

        if (str_contains($normalizedDisplayName, '数据盘')) {
            return 'data_disk_size';
        }

        if (str_contains($normalizedDisplayName, '系统盘')) {
            return 'system_disk_size';
        }

        if (str_contains($normalizedDisplayName, '下行带宽')) {
            return 'in_bw';
        }

        if (str_contains($normalizedDisplayName, '上行带宽')) {
            return 'out_bw';
        }

        if ($mappedField !== null && trim($mappedField) !== '') {
            return trim($mappedField);
        }

        $slug = Str::slug($normalizedDisplayName, '_');

        return $slug !== '' ? $slug : $normalizedDisplayName;
    }
}
