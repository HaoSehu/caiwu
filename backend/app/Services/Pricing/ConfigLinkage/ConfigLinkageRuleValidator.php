<?php

declare(strict_types=1);

namespace App\Services\Pricing\ConfigLinkage;

use App\Exceptions\BusinessException;

/**
 * M7 配置联动规则保存前校验器（结构 + 目标存在性 + 冲突 + 自引用 + 范围型限制）。
 *
 * 规则 Schema（与设计方案一致，集中式存储于 products.config_linkage_rules）：
 * [
 *   {
 *     "id": "rule_os_win",
 *     "when": { "all": [ { "field": "os", "op": "eq", "value": "windows" } ] },
 *     "effects": [
 *       { "type": "show", "target": "rdp_edition" },
 *       { "type": "price_adjust", "target": "memory", "scope": { "sub_option_id": "8g" },
 *         "mode": "fixed", "value": 10.00 }
 *     ]
 *   }
 * ]
 *
 * 无状态纯校验：validate() 失败抛 BusinessException，成功静默返回。
 */
final class ConfigLinkageRuleValidator
{
    private const OPS = ['eq', 'neq', 'in', 'notin', 'gt', 'gte', 'lt', 'lte', 'between'];

    private const EFFECT_TYPES = ['show', 'hide', 'disable', 'price_adjust'];

    private const ADJUST_MODES = ['fixed', 'percent', 'set'];

    /**
     * 范围/滑块型 option_type（与 Order\Concerns\HandlesOrderCalculation::RANGE_TYPES 同口径，
     * 该常量为 private 无法复用，此处镜像并注释来源；另支持 option_mode='range' 与
     * 「无子选项但有 qty 区间」两种自定义配置项形态）。
     */
    private const RANGE_TYPES = [4, 7, 9, 11, 14, 15, 16, 17, 18, 19];

    /**
     * 校验规则集合与商品配置项的匹配性。
     *
     * @param  array<int, mixed>|null  $rules  联动规则数组（null/空数组直接放行）
     * @param  array<int, mixed>  $configOptions  商品 config_options（提交时的归一化结果）
     *
     * @throws BusinessException 规则非法时
     */
    public static function validate(?array $rules, array $configOptions): void
    {
        if ($rules === null || $rules === []) {
            return;
        }

        if (! array_is_list($rules)) {
            throw new BusinessException('配置联动规则必须是数组');
        }

        $fields = self::resolveFields($configOptions);
        $showTargets = [];
        $hideTargets = [];

        $seenIds = [];
        foreach ($rules as $index => $rule) {
            if (! is_array($rule)) {
                throw new BusinessException(sprintf('配置联动规则第 %d 项格式错误', $index + 1));
            }

            $ruleId = trim((string) ($rule['id'] ?? ''));
            if ($ruleId === '') {
                throw new BusinessException(sprintf('配置联动规则第 %d 项缺少 id', $index + 1));
            }
            if (isset($seenIds[$ruleId])) {
                throw new BusinessException(sprintf('配置联动规则 id「%s」重复', $ruleId));
            }
            $seenIds[$ruleId] = true;

            $conditionFields = self::validateWhen($rule, $ruleId, $fields);
            $effects = $rule['effects'] ?? null;
            if (! is_array($effects) || $effects === [] || ! array_is_list($effects)) {
                throw new BusinessException(sprintf('联动规则「%s」的 effects 必须是非空数组', $ruleId));
            }

            foreach ($effects as $effectIndex => $effect) {
                if (! is_array($effect)) {
                    throw new BusinessException(sprintf('联动规则「%s」第 %d 个效果格式错误', $ruleId, $effectIndex + 1));
                }

                $type = trim((string) ($effect['type'] ?? ''));
                if (! in_array($type, self::EFFECT_TYPES, true)) {
                    throw new BusinessException(sprintf('联动规则「%s」的效果类型「%s」不支持', $ruleId, $type));
                }

                $target = trim((string) ($effect['target'] ?? ''));
                if ($target === '' || ! isset($fields[$target])) {
                    throw new BusinessException(sprintf('联动规则「%s」的目标字段「%s」不存在于商品配置项', $ruleId, $target));
                }

                // 自引用：条件字段与 show/hide 目标相同会造成语义死循环
                if (in_array($type, ['show', 'hide'], true) && in_array($target, $conditionFields, true)) {
                    throw new BusinessException(sprintf('联动规则「%s」的 show/hide 目标「%s」不能同时作为自身条件字段', $ruleId, $target));
                }

                // 范围/滑块型（无子选项、按 qty 数值取值）不支持 show/hide，仅允许 disable/price_adjust
                if (in_array($type, ['show', 'hide'], true) && $fields[$target]['is_range']) {
                    throw new BusinessException(sprintf('联动规则「%s」的范围型字段「%s」不支持 show/hide，仅允许 disable/price_adjust', $ruleId, $target));
                }

                if ($type === 'show') {
                    $showTargets[$target] = true;
                }
                if ($type === 'hide') {
                    $hideTargets[$target] = true;
                }

                if ($type === 'price_adjust') {
                    self::validatePriceAdjust($effect, $ruleId, $fields[$target]);
                }
            }
        }

        // 同一 target 同时被 show 与 hide 命中 → 显隐语义冲突，直接拒绝保存
        $conflicts = array_keys(array_intersect_key($showTargets, $hideTargets));
        if ($conflicts !== []) {
            throw new BusinessException(sprintf('字段「%s」同时被 show 与 hide 规则命中，请调整规则', implode('、', $conflicts)));
        }
    }

    /**
     * 校验 when 条件块，返回条件引用的字段集合（供自引用检测）。
     *
     * @param  array<string, mixed>  $fields
     * @return list<string>
     */
    private static function validateWhen(array $rule, string $ruleId, array $fields): array
    {
        $when = $rule['when'] ?? null;
        if (! is_array($when)) {
            throw new BusinessException(sprintf('联动规则「%s」缺少 when 条件块', $ruleId));
        }

        $conditions = $when['all'] ?? null;
        if (! is_array($conditions) || ! array_is_list($conditions)) {
            throw new BusinessException(sprintf('联动规则「%s」的 when.all 必须是条件数组', $ruleId));
        }

        $conditionFields = [];
        foreach ($conditions as $conditionIndex => $condition) {
            if (! is_array($condition)) {
                throw new BusinessException(sprintf('联动规则「%s」第 %d 个条件格式错误', $ruleId, $conditionIndex + 1));
            }

            $field = trim((string) ($condition['field'] ?? ''));
            if ($field === '' || ! isset($fields[$field])) {
                throw new BusinessException(sprintf('联动规则「%s」的条件字段「%s」不存在于商品配置项', $ruleId, $field));
            }

            $op = trim((string) ($condition['op'] ?? ''));
            if (! in_array($op, self::OPS, true)) {
                throw new BusinessException(sprintf('联动规则「%s」的条件操作符「%s」不支持', $ruleId, $op));
            }

            if (! array_key_exists('value', $condition)) {
                throw new BusinessException(sprintf('联动规则「%s」的条件缺少比较值', $ruleId));
            }

            if ($op === 'between' && (! is_array($condition['value']) || count($condition['value']) !== 2)) {
                throw new BusinessException(sprintf('联动规则「%s」的 between 条件值必须是 [min, max]', $ruleId));
            }

            $conditionFields[] = $field;
        }

        return $conditionFields;
    }

    /**
     * @param  array{is_range: bool, sub_ids: list<string>}  $targetField
     */
    private static function validatePriceAdjust(array $effect, string $ruleId, array $targetField): void
    {
        $mode = trim((string) ($effect['mode'] ?? ''));
        if (! in_array($mode, self::ADJUST_MODES, true)) {
            throw new BusinessException(sprintf('联动规则「%s」的 price_adjust.mode 必须是 fixed/percent/set', $ruleId));
        }

        $value = $effect['value'] ?? null;
        if (! is_numeric($value)) {
            throw new BusinessException(sprintf('联动规则「%s」的 price_adjust.value 必须是数值', $ruleId));
        }

        $scope = $effect['scope'] ?? null;
        if ($scope === null) {
            return;
        }

        if (! is_array($scope)) {
            throw new BusinessException(sprintf('联动规则「%s」的 price_adjust.scope 格式错误', $ruleId));
        }

        $subOptionId = trim((string) ($scope['sub_option_id'] ?? ''));
        if ($subOptionId === '') {
            return;
        }

        if (! in_array($subOptionId, $targetField['sub_ids'], true)) {
            throw new BusinessException(sprintf('联动规则「%s」的 price_adjust.scope.sub_option_id「%s」不存在于字段子选项', $ruleId, $subOptionId));
        }
    }

    /**
     * 归集配置项字段：field 名 → {is_range, sub_ids}。
     * 字段解析与 HandlesOrderCalculation::parseField 同口径（field > TYPE_FIELD_MAP > option_name|spec_key 前缀）。
     *
     * @return array<string, array{is_range: bool, sub_ids: list<string>}>
     */
    private static function resolveFields(array $configOptions): array
    {
        $typeFieldMap = [
            4 => 'ip_num', 5 => 'os', 6 => 'cpu', 7 => 'cpu', 8 => 'memory', 9 => 'memory',
            10 => 'bw', 11 => 'bw', 12 => 'area', 13 => 'system_disk_size', 14 => 'system_disk_size',
            16 => 'cpu', 17 => 'memory', 18 => 'bw', 19 => 'system_disk_size',
        ];

        $fields = [];
        foreach ($configOptions as $option) {
            if (! is_array($option)) {
                continue;
            }

            $field = trim((string) ($option['field'] ?? ''));
            if ($field === '') {
                $type = (int) ($option['option_type'] ?? -1);
                if (isset($typeFieldMap[$type])) {
                    $field = $typeFieldMap[$type];
                } else {
                    $source = (string) ($option['option_name'] ?? $option['spec_key'] ?? '');
                    $field = trim((string) explode('|', $source)[0]);
                }
            }

            if ($field === '' || isset($fields[$field])) {
                continue;
            }

            $subOptions = self::subOptionsOf($option);
            $subIds = [];
            foreach ($subOptions as $sub) {
                $subId = trim((string) ($sub['id'] ?? ''));
                if ($subId !== '') {
                    $subIds[] = $subId;
                }
            }

            $hasQtyBounds = array_key_exists('qty_min', $option) || array_key_exists('qty_max', $option)
                || array_key_exists('qty_minimum', $option) || array_key_exists('qty_maximum', $option);
            $isRange = in_array((int) ($option['option_type'] ?? -1), self::RANGE_TYPES, true)
                || trim((string) ($option['option_mode'] ?? '')) === 'range'
                || ($subIds === [] && $hasQtyBounds);

            $fields[$field] = [
                'is_range' => $isRange,
                'sub_ids' => $subIds,
            ];
        }

        return $fields;
    }

    /**
     * 兼容两种子选项键名：既有计价链路使用 sub，设计方案描述使用 sub_options。
     *
     * @return array<int, mixed>
     */
    private static function subOptionsOf(array $option): array
    {
        foreach (['sub', 'sub_options'] as $key) {
            if (is_array($option[$key] ?? null)) {
                return $option[$key];
            }
        }

        return [];
    }
}
