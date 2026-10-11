<?php

declare(strict_types=1);

namespace App\Services\Pricing\ConfigLinkage;

use App\Support\Money;

/**
 * M7 配置联动规则评估器：服务端单一真源的纯函数。
 *
 * 前端联动仅做 UX 预览，提交后由本评估器按同一规则重算显隐与最终价
 * （预览与下单共用，见 CheckoutService::quoteWithLinkage）。
 * 无副作用：不读写模型/缓存/DB，所有输入显式传入。
 *
 * 语义约定：
 * - show 目标默认不可见，条件命中才显示；hide 目标默认可见，条件命中才隐藏。
 * - visibility=false 的字段由调用方从计价配置中剔除（计价剔除在调用方完成）。
 * - price_adjust 只作用于可见项（第二遍评估时隐项 target 的调整被跳过）。
 * - 多规则按声明顺序叠加；fixed/percent/set 均基于目录基础金额换算，叠加即求和。
 * - 金额统一经 App\Support\Money 按分位（2 位小数）舍入。
 */
final class ConfigLinkageEvaluator
{
    public function evaluate(?array $rules, array $selections, array $baseAmounts = []): array
    {
        $result = [
            'visibility' => [],
            'disabled' => [],
            'adjustments' => [],
            'applied_rule_ids' => [],
            'price_delta' => 0.0,
        ];

        if ($rules === null || $rules === []) {
            return $result;
        }

        // 第一遍：显隐投影。show 目标默认 false，hide 目标默认 true；声明顺序后者覆盖前者。
        foreach ($rules as $rule) {
            if (! is_array($rule)) {
                continue;
            }

            foreach ((array) ($rule['effects'] ?? []) as $effect) {
                if (! is_array($effect)) {
                    continue;
                }

                $target = trim((string) ($effect['target'] ?? ''));
                if ($target === '') {
                    continue;
                }

                $type = trim((string) ($effect['type'] ?? ''));
                if ($type === 'show' && ! array_key_exists($target, $result['visibility'])) {
                    $result['visibility'][$target] = false;
                }
                if ($type === 'hide' && ! array_key_exists($target, $result['visibility'])) {
                    $result['visibility'][$target] = true;
                }
            }
        }

        // 第二遍：按声明顺序评估条件并应用效果
        foreach ($rules as $rule) {
            if (! is_array($rule)) {
                continue;
            }

            $ruleId = trim((string) ($rule['id'] ?? ''));
            if (! $this->conditionsMatch($rule['when'] ?? null, $selections)) {
                continue;
            }

            foreach ((array) ($rule['effects'] ?? []) as $effect) {
                if (! is_array($effect)) {
                    continue;
                }

                $target = trim((string) ($effect['target'] ?? ''));
                if ($target === '') {
                    continue;
                }

                $type = trim((string) ($effect['type'] ?? ''));
                if ($type === 'show') {
                    $result['visibility'][$target] = true;
                } elseif ($type === 'hide') {
                    $result['visibility'][$target] = false;
                } elseif ($type === 'disable') {
                    $result['disabled'][$target] = $target;
                } elseif ($type === 'price_adjust') {
                    // 只作用于可见项：hide 目标（或默认隐藏且未被 show 的目标）不参与加价
                    if (($result['visibility'][$target] ?? true) === false) {
                        continue;
                    }
                    $this->applyPriceAdjust($effect, $target, $baseAmounts, $result);
                }
            }

            if ($ruleId !== '') {
                $result['applied_rule_ids'][] = $ruleId;
            }
        }

        $result['disabled'] = array_values($result['disabled']);

        return $result;
    }

    /**
     * when.all 条件全满足才命中；空条件视为不命中（避免误触发全量效果）。
     */
    private function conditionsMatch(mixed $when, array $selections): bool
    {
        $conditions = is_array($when) ? ($when['all'] ?? null) : null;
        if (! is_array($conditions) || $conditions === []) {
            return false;
        }

        foreach ($conditions as $condition) {
            if (! is_array($condition) || ! $this->conditionMatches($condition, $selections)) {
                return false;
            }
        }

        return true;
    }

    private function conditionMatches(array $condition, array $selections): bool
    {
        $field = trim((string) ($condition['field'] ?? ''));
        if ($field === '' || ! array_key_exists($field, $selections)) {
            return false;
        }

        $selection = $selections[$field];
        $expected = $condition['value'] ?? null;
        $op = trim((string) ($condition['op'] ?? ''));

        return match ($op) {
            'eq' => $this->looseEquals($selection, $expected),
            'neq' => ! $this->looseEquals($selection, $expected),
            'in' => $this->inSet($selection, $expected),
            'notin' => ! $this->inSet($selection, $expected),
            'gt', 'gte', 'lt', 'lte' => $this->numericCompare($selection, $expected, $op),
            'between' => $this->betweenMatches($selection, $expected),
            default => false,
        };
    }

    /**
     * 精确比较：两侧均为数值时按数值比较，否则按字符串严格比较。
     */
    private function looseEquals(mixed $selection, mixed $expected): bool
    {
        if (is_numeric($selection) && is_numeric($expected)) {
            return (float) $selection === (float) $expected;
        }

        return (string) $selection === (string) $expected;
    }

    private function inSet(mixed $selection, mixed $expected): bool
    {
        if (! is_array($expected)) {
            return false;
        }

        foreach ($expected as $candidate) {
            if ($this->looseEquals($selection, $candidate)) {
                return true;
            }
        }

        return false;
    }

    private function numericCompare(mixed $selection, mixed $expected, string $op): bool
    {
        if (! is_numeric($selection) || ! is_numeric($expected)) {
            return false;
        }

        return match ($op) {
            'gt' => (float) $selection > (float) $expected,
            'gte' => (float) $selection >= (float) $expected,
            'lt' => (float) $selection < (float) $expected,
            'lte' => (float) $selection <= (float) $expected,
            default => false,
        };
    }

    private function betweenMatches(mixed $selection, mixed $expected): bool
    {
        if (! is_numeric($selection) || ! is_array($expected) || count($expected) !== 2) {
            return false;
        }

        [$min, $max] = array_values($expected);
        if (! is_numeric($min) || ! is_numeric($max)) {
            return false;
        }

        $value = (float) $selection;

        return $value >= (float) $min && $value <= (float) $max;
    }

    /**
     * 计算并累加一笔价格调整（scope.sub_option_id 或整字段）。
     *
     * @param  array<string, float>  $baseAmounts  field => 目录基础金额；支持 "field::sub_option_id" 细粒度键
     * @param  array{visibility: array<string,bool>, disabled: list<string>, adjustments: array<string,array<string,float>>, applied_rule_ids: list<string>, price_delta: float}  $result
     */
    private function applyPriceAdjust(array $effect, string $target, array $baseAmounts, array &$result): void
    {
        $mode = trim((string) ($effect['mode'] ?? ''));
        $value = $effect['value'] ?? null;
        if (! in_array($mode, ['fixed', 'percent', 'set'], true) || ! is_numeric($value)) {
            return;
        }

        $scopeKey = '*';
        if (is_array($effect['scope'] ?? null)) {
            $subOptionId = trim((string) ($effect['scope']['sub_option_id'] ?? ''));
            if ($subOptionId !== '') {
                $scopeKey = $subOptionId;
            }
        }

        // 子选项粒度优先取 "field::sub" 基础金额，缺省回落到整字段金额
        $base = $baseAmounts[$target.'::'.$scopeKey] ?? $baseAmounts[$target] ?? 0.0;

        $delta = match ($mode) {
            'fixed' => Money::round($value),
            'percent' => Money::round(Money::multiply($base, (float) $value / 100)),
            'set' => Money::round((float) $value - $base),
        };

        $result['adjustments'][$target][$scopeKey] = Money::add($result['adjustments'][$target][$scopeKey] ?? 0, $delta);
        $result['price_delta'] = Money::add($result['price_delta'], $delta);
    }
}
