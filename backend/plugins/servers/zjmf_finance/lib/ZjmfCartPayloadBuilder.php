<?php

declare(strict_types=1);

namespace Caiwu\Plugins\Servers\ZjmfFinance\Lib;

use App\Exceptions\BusinessException;
use App\Models\Order;
use App\Models\Product;

/**
 * 上游购物车载荷构建（从 ZjmfProvisionService 抽出的无状态协作器）。
 *
 * 负责把本地商品的 config_options 与订单 config_snapshot 翻译成魔方财务
 * /cart/add_to_shop 需要的 configoption（选项 ID => 子项 ID）与币种。
 * 只做数据翻译，不做任何网络调用，便于单独回归规格映射规则。
 */
final class ZjmfCartPayloadBuilder
{
    /** 值直接填进 configoption 的选项类型（数量/带宽/磁盘等可输入项）。 */
    private const RANGE_TYPES = [4, 7, 9, 11, 14, 15, 16, 17, 18, 19];

    /** 选项类型 => 本地配置字段名（选项未显式给 field 时的兜底）。 */
    private const TYPE_FIELD_MAP = [
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
        16 => 'cpu',
        17 => 'memory',
        18 => 'bw',
        19 => 'system_disk_size',
    ];

    /**
     * 构建加购载荷。
     *
     * @param  int  $upstreamProductId  上游商品 ID（由调用方经绑定解析，失败即抛）
     */
    public static function buildPayload(Order $order, Product $product, int $currencyId, int $upstreamProductId): array
    {
        $configSnapshot = array_merge(
            (array) (($product->purchase_requires ?? [])['upstream_default_config'] ?? []),
            (array) ($order->config_snapshot ?? [])
        );
        $hostname = trim((string) ($configSnapshot['hostname'] ?? $order->domain ?? ''));
        $password = trim((string) ($configSnapshot['password'] ?? ''));

        if ($password === '') {
            $password = self::generatePassword();
        }

        $configOption = self::buildConfigOptionMap($product, $configSnapshot);

        $payload = [
            'pid' => $upstreamProductId,
            'billingcycle' => (string) $order->billing_cycle,
            'qty' => 1,
            'configoption' => $configOption,
            'customfield' => (array) ($configSnapshot['customfield'] ?? []),
            'currencyid' => $currencyId,
            'host' => $hostname,
            'password' => $password,
            'checkout' => 0,
        ];

        $osSelectionId = self::resolveOsSelectionId($product, $configOption);
        if ($osSelectionId > 0) {
            $payload['os'] = ['id' => $osSelectionId];
        }

        return $payload;
    }

    /**
     * 选项 ID => 已选子项 ID 的映射；空值视为未选择，不下发。
     *
     * @return array<int, int>
     */
    public static function buildConfigOptionMap(Product $product, array $configSnapshot): array
    {
        $result = [];

        foreach ((array) ($product->config_options ?? []) as $item) {
            $item = (array) $item;
            $optionId = self::resolveOptionId($item);
            $field = self::parseField($item);
            $type = (int) ($item['option_type'] ?? -1);

            if ($optionId <= 0 || $field === '') {
                continue;
            }

            if (in_array($type, self::RANGE_TYPES, true)) {
                $value = $configSnapshot[$field] ?? null;
                if ($value === null || $value === '') {
                    continue;
                }
                $result[$optionId] = (int) $value;

                continue;
            }

            if ($field === 'os' && ! empty($configSnapshot['os_sub_id'])) {
                $result[$optionId] = (int) $configSnapshot['os_sub_id'];

                continue;
            }

            $selected = $configSnapshot[$field] ?? null;
            if ($selected === null || $selected === '') {
                continue;
            }

            $subId = self::resolveSubId($item, $selected);
            if ($subId !== null) {
                $result[$optionId] = $subId;
            }
        }

        return $result;
    }

    /**
     * 选项 ID：依次尝试 id/config_id 与 extra 内的同名键，最后回落到子项 config_id。
     */
    public static function resolveOptionId(array $item): int
    {
        foreach ([
            $item['id'] ?? null,
            $item['config_id'] ?? null,
            $item['extra']['id'] ?? null,
            $item['extra']['config_id'] ?? null,
        ] as $candidate) {
            if (is_numeric($candidate) && (int) $candidate > 0) {
                return (int) $candidate;
            }
        }

        foreach ([(array) ($item['sub'] ?? []), (array) ($item['extra']['sub'] ?? [])] as $subList) {
            foreach ($subList as $sub) {
                $configId = $sub['config_id'] ?? $sub['configid'] ?? null;
                if (is_numeric($configId) && (int) $configId > 0) {
                    return (int) $configId;
                }
            }
        }

        return 0;
    }

    /**
     * 已选子项 ID：先按 id/名称匹配子项，匹配不到时接受纯数字串。
     */
    public static function resolveSubId(array $item, mixed $selected): ?int
    {
        $selected = trim((string) $selected);
        if ($selected === '') {
            return null;
        }

        foreach ((array) ($item['sub'] ?? []) as $sub) {
            $subId = (int) ($sub['id'] ?? 0);
            $optionNameFirst = trim((string) ($sub['option_name_first'] ?? ''));
            $optionName = trim((string) ($sub['option_name'] ?? ''));

            if ($selected === (string) $subId || $selected === $optionNameFirst || $selected === $optionName) {
                return $subId > 0 ? $subId : null;
            }
        }

        return is_numeric($selected) ? (int) $selected : null;
    }

    /**
     * 本地配置字段名：显式 field 优先，其次按选项类型查表，最后取 option_name 的 | 前缀。
     */
    public static function parseField(array $item): string
    {
        $field = trim((string) ($item['field'] ?? ''));
        if ($field !== '') {
            return $field;
        }

        $type = (int) ($item['option_type'] ?? -1);
        if (isset(self::TYPE_FIELD_MAP[$type])) {
            return self::TYPE_FIELD_MAP[$type];
        }

        $source = (string) ($item['option_name'] ?? $item['spec_key'] ?? '');
        $parts = explode('|', $source);

        return trim($parts[0]);
    }

    /**
     * 操作系统子项 ID：上游结算需要单独带 os.id 时才生效，取不到返回 0。
     */
    public static function resolveOsSelectionId(Product $product, array $configOption): int
    {
        foreach ((array) ($product->config_options ?? []) as $item) {
            $item = (array) $item;
            if (self::parseField($item) !== 'os') {
                continue;
            }

            $optionId = self::resolveOptionId($item);
            $selectionId = $configOption[$optionId] ?? null;
            if (is_numeric($selectionId) && (int) $selectionId > 0) {
                return (int) $selectionId;
            }
        }

        return 0;
    }

    /**
     * 上游币种：订单快照 > 商品属性 > 上游定价表 > 选项定价，全缺失即失败。
     *
     * 币种错了会直接开成另一个价格的订单，因此不猜默认值。
     */
    public static function resolveCurrencyId(Order $order, Product $product, array $upstreamProductConfig, int $upstreamProductId): int
    {
        $configSnapshot = (array) ($order->config_snapshot ?? []);
        $candidates = [
            $configSnapshot['currencyid'] ?? null,
            $configSnapshot['currency_id'] ?? null,
            $product->getAttribute('currency_id'),
        ];

        foreach ((array) ($upstreamProductConfig['product_pricings'] ?? []) as $pricing) {
            if (! is_array($pricing)) {
                continue;
            }

            $pricedProductId = (int) ($pricing['relid'] ?? 0);
            if ($pricedProductId > 0 && $pricedProductId !== $upstreamProductId) {
                continue;
            }

            $candidates[] = $pricing['currency'] ?? $pricing['currencyid'] ?? $pricing['currency_id'] ?? null;
        }

        foreach ((array) ($product->config_options ?? []) as $option) {
            foreach ((array) ((array) $option)['sub'] as $subOption) {
                foreach ((array) (((array) $subOption)['pricings'] ?? []) as $pricing) {
                    if (is_array($pricing)) {
                        $candidates[] = $pricing['currency'] ?? $pricing['currencyid'] ?? $pricing['currency_id'] ?? null;
                    }
                }
            }
        }

        foreach ($candidates as $candidate) {
            if (is_numeric($candidate) && (int) $candidate > 0) {
                return (int) $candidate;
            }
        }

        throw new BusinessException('无法确定上游商品币种，已停止创建上游订单');
    }

    /**
     * 随机初始密码：去掉易混字符（l/I/0/O），末尾补足大小写与数字各一。
     */
    public static function generatePassword(): string
    {
        $chars = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $password = '';

        for ($i = 0; $i < 13; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return substr($password.'Aa1', 0, 16);
    }
}
