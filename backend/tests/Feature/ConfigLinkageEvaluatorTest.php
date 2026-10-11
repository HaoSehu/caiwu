<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Pricing\ConfigLinkage\ConfigLinkageEvaluator;
use Tests\TestCase;

/**
 * M7 联动评估器（纯函数）：显隐投影 / disable / fixed / percent / set / between /
 * 多规则叠加 / 隐藏项计价剔除 / 空规则零输出。
 * 不落库、不依赖 DB。
 */
class ConfigLinkageEvaluatorTest extends TestCase
{
    private ConfigLinkageEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->evaluator = new ConfigLinkageEvaluator;
    }

    public function test_empty_rules_return_empty_projection(): void
    {
        foreach ([null, []] as $rules) {
            $result = $this->evaluator->evaluate($rules, ['os' => 'windows']);

            $this->assertSame([], $result['visibility']);
            $this->assertSame([], $result['disabled']);
            $this->assertSame([], $result['adjustments']);
            $this->assertSame([], $result['applied_rule_ids']);
            $this->assertSame(0.0, $result['price_delta']);
        }
    }

    public function test_show_target_hidden_by_default_and_visible_when_matched(): void
    {
        $rules = [
            [
                'id' => 'r1',
                'when' => ['all' => [['field' => 'os', 'op' => 'eq', 'value' => 'windows']]],
                'effects' => [['type' => 'show', 'target' => 'rdp_edition']],
            ],
        ];

        // 命中：显示
        $matched = $this->evaluator->evaluate($rules, ['os' => 'windows']);
        $this->assertTrue($matched['visibility']['rdp_edition']);
        $this->assertSame(['r1'], $matched['applied_rule_ids']);

        // 未命中：默认隐藏，且规则不计入 applied
        $unmatched = $this->evaluator->evaluate($rules, ['os' => 'linux']);
        $this->assertFalse($unmatched['visibility']['rdp_edition']);
        $this->assertSame([], $unmatched['applied_rule_ids']);
    }

    public function test_hide_effect_removes_visibility(): void
    {
        $rules = [
            [
                'id' => 'r1',
                'when' => ['all' => [['field' => 'os', 'op' => 'eq', 'value' => 'linux']]],
                'effects' => [['type' => 'hide', 'target' => 'rdp_edition']],
            ],
        ];

        $result = $this->evaluator->evaluate($rules, ['os' => 'linux', 'rdp_edition' => 'standard']);
        $this->assertFalse($result['visibility']['rdp_edition']);
    }

    public function test_disable_effect_collects_targets(): void
    {
        $rules = [
            [
                'id' => 'r1',
                'when' => ['all' => [['field' => 'memory', 'op' => 'gt', 'value' => 4]]],
                'effects' => [['type' => 'disable', 'target' => 'rdp_edition']],
            ],
        ];

        $result = $this->evaluator->evaluate($rules, ['memory' => 8]);
        $this->assertSame(['rdp_edition'], $result['disabled']);
    }

    public function test_fixed_adjust_adds_amount(): void
    {
        $rules = [
            [
                'id' => 'r1',
                'when' => ['all' => [['field' => 'os', 'op' => 'eq', 'value' => 'windows']]],
                'effects' => [['type' => 'price_adjust', 'target' => 'memory', 'mode' => 'fixed', 'value' => 5]],
            ],
        ];

        $result = $this->evaluator->evaluate($rules, ['os' => 'windows'], ['memory' => 20.0]);

        $this->assertSame(5.0, $result['price_delta']);
        $this->assertSame(['memory' => ['*' => 5.0]], $result['adjustments']);
    }

    public function test_percent_adjust_uses_base_amount(): void
    {
        $rules = [
            [
                'id' => 'r1',
                'when' => ['all' => [['field' => 'os', 'op' => 'eq', 'value' => 'windows']]],
                'effects' => [['type' => 'price_adjust', 'target' => 'memory', 'mode' => 'percent', 'value' => 10]],
            ],
        ];

        $result = $this->evaluator->evaluate($rules, ['os' => 'windows'], ['memory' => 20.0]);

        $this->assertSame(2.0, $result['price_delta']);
    }

    public function test_set_adjust_diffs_against_base_amount(): void
    {
        $rules = [
            [
                'id' => 'r1',
                'when' => ['all' => [['field' => 'os', 'op' => 'eq', 'value' => 'windows']]],
                'effects' => [['type' => 'price_adjust', 'target' => 'memory', 'mode' => 'set', 'value' => 15]],
            ],
        ];

        $result = $this->evaluator->evaluate($rules, ['os' => 'windows'], ['memory' => 20.0]);

        $this->assertSame(-5.0, $result['price_delta']);
        $this->assertSame(['memory' => ['*' => -5.0]], $result['adjustments']);
    }

    public function test_sub_option_scoped_adjust_uses_fine_grained_base(): void
    {
        $rules = [
            [
                'id' => 'r1',
                'when' => ['all' => [['field' => 'os', 'op' => 'eq', 'value' => 'windows']]],
                'effects' => [['type' => 'price_adjust', 'target' => 'memory', 'scope' => ['sub_option_id' => '8g'], 'mode' => 'percent', 'value' => 50]],
            ],
        ];

        // 细粒度键 "field::sub" 优先于整字段基数
        $result = $this->evaluator->evaluate($rules, ['os' => 'windows'], ['memory' => 20.0, 'memory::8g' => 10.0]);

        $this->assertSame(5.0, $result['price_delta']);
        $this->assertSame(['memory' => ['8g' => 5.0]], $result['adjustments']);
    }

    public function test_adjust_skipped_for_hidden_target(): void
    {
        // price_adjust 与 hide 同规则：目标被隐藏后加价必须剔除
        $rules = [
            [
                'id' => 'r1',
                'when' => ['all' => [['field' => 'os', 'op' => 'eq', 'value' => 'windows']]],
                'effects' => [
                    ['type' => 'hide', 'target' => 'rdp_edition'],
                    ['type' => 'price_adjust', 'target' => 'rdp_edition', 'mode' => 'fixed', 'value' => 99],
                ],
            ],
        ];

        $result = $this->evaluator->evaluate($rules, ['os' => 'windows'], ['rdp_edition' => 30.0]);

        $this->assertFalse($result['visibility']['rdp_edition']);
        $this->assertSame([], $result['adjustments']);
        $this->assertSame(0.0, $result['price_delta']);
    }

    public function test_between_condition_matches_inclusive_bounds(): void
    {
        $rules = [
            [
                'id' => 'r1',
                'when' => ['all' => [['field' => 'bw', 'op' => 'between', 'value' => [10, 50]]]],
                'effects' => [['type' => 'disable', 'target' => 'rdp_edition']],
            ],
        ];

        $this->assertSame(['rdp_edition'], $this->evaluator->evaluate($rules, ['bw' => 10])['disabled']);
        $this->assertSame(['rdp_edition'], $this->evaluator->evaluate($rules, ['bw' => 50])['disabled']);
        $this->assertSame([], $this->evaluator->evaluate($rules, ['bw' => 51])['disabled']);
    }

    public function test_multiple_rules_stack_in_declaration_order(): void
    {
        $rules = [
            [
                'id' => 'r_os_win',
                'when' => ['all' => [['field' => 'os', 'op' => 'eq', 'value' => 'windows']]],
                'effects' => [
                    ['type' => 'show', 'target' => 'rdp_edition'],
                    ['type' => 'price_adjust', 'target' => 'memory', 'scope' => ['sub_option_id' => '8g'], 'mode' => 'fixed', 'value' => 3],
                ],
            ],
            [
                'id' => 'r_memory_big',
                'when' => ['all' => [['field' => 'memory', 'op' => 'in', 'value' => ['8g', '16g']]]],
                'effects' => [['type' => 'price_adjust', 'target' => 'memory', 'mode' => 'fixed', 'value' => 2.55]],
            ],
            [
                'id' => 'r_not_hit',
                'when' => ['all' => [['field' => 'os', 'op' => 'notin', 'value' => ['windows']]]],
                'effects' => [['type' => 'price_adjust', 'target' => 'memory', 'mode' => 'fixed', 'value' => 100]],
            ],
        ];

        $result = $this->evaluator->evaluate(
            $rules,
            ['os' => 'windows', 'memory' => '8g'],
            ['memory' => 20.0, 'memory::8g' => 20.0]
        );

        $this->assertTrue($result['visibility']['rdp_edition']);
        $this->assertSame(['r_os_win', 'r_memory_big'], $result['applied_rule_ids']);
        // 叠加即求和：3 + 2.55（未命中的 100 不计入）
        $this->assertSame(5.55, $result['price_delta']);
        $this->assertSame(['memory' => ['8g' => 3.0, '*' => 2.55]], $result['adjustments']);
    }

    public function test_missing_selection_fails_condition(): void
    {
        $rules = [
            [
                'id' => 'r1',
                'when' => ['all' => [['field' => 'os', 'op' => 'neq', 'value' => 'windows']]],
                'effects' => [['type' => 'disable', 'target' => 'rdp_edition']],
            ],
        ];

        // 未选择 os 时不触发任何条件
        $this->assertSame([], $this->evaluator->evaluate($rules, [])['disabled']);
    }
}
