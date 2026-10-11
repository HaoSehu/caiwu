<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Services\Pricing\ConfigLinkage\ConfigLinkageRuleValidator;
use Tests\TestCase;

/**
 * M7 配置联动规则校验器：结构 / op 白名单 / type 白名单 / 目标存在性 /
 * price_adjust 必带字段 / sub_option_id 存在性 / show+hide 冲突 / 自引用 / 范围型限制。
 * 纯校验逻辑，不落库。
 */
class ConfigLinkageValidatorTest extends TestCase
{
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
            ['id' => '4g', 'label' => '4G'],
            ['id' => '8g', 'label' => '8G'],
        ]],
        // 范围型：无 sub、有 qty 区间（设计方案口径），另有 option_mode='range' 的自定义形态
        ['field' => 'bw', 'label' => '带宽', 'option_type' => 99, 'qty_min' => 1, 'qty_max' => 100],
    ];

    public function test_null_and_empty_rules_pass(): void
    {
        ConfigLinkageRuleValidator::validate(null, $this->configOptions);
        ConfigLinkageRuleValidator::validate([], $this->configOptions);

        $this->assertTrue(true);
    }

    public function test_valid_rules_pass(): void
    {
        ConfigLinkageRuleValidator::validate([
            [
                'id' => 'rule_os_win',
                'when' => ['all' => [['field' => 'os', 'op' => 'eq', 'value' => 'windows']]],
                'effects' => [
                    ['type' => 'show', 'target' => 'rdp_edition'],
                    ['type' => 'disable', 'target' => 'bw'],
                    ['type' => 'price_adjust', 'target' => 'memory', 'scope' => ['sub_option_id' => '8g'], 'mode' => 'fixed', 'value' => 10],
                ],
            ],
        ], $this->configOptions);

        $this->assertTrue(true);
    }

    public function test_invalid_op_rejected(): void
    {
        $this->expectException(BusinessException::class);

        ConfigLinkageRuleValidator::validate([
            [
                'id' => 'r1',
                'when' => ['all' => [['field' => 'os', 'op' => 'regex', 'value' => 'win']]],
                'effects' => [['type' => 'show', 'target' => 'rdp_edition']],
            ],
        ], $this->configOptions);
    }

    public function test_invalid_effect_type_rejected(): void
    {
        $this->expectException(BusinessException::class);

        ConfigLinkageRuleValidator::validate([
            [
                'id' => 'r1',
                'when' => ['all' => [['field' => 'os', 'op' => 'eq', 'value' => 'windows']]],
                'effects' => [['type' => 'destroy', 'target' => 'rdp_edition']],
            ],
        ], $this->configOptions);
    }

    public function test_unknown_target_rejected(): void
    {
        $this->expectException(BusinessException::class);

        ConfigLinkageRuleValidator::validate([
            [
                'id' => 'r1',
                'when' => ['all' => [['field' => 'os', 'op' => 'eq', 'value' => 'windows']]],
                'effects' => [['type' => 'hide', 'target' => 'not_exist_field']],
            ],
        ], $this->configOptions);
    }

    public function test_unknown_condition_field_rejected(): void
    {
        $this->expectException(BusinessException::class);

        ConfigLinkageRuleValidator::validate([
            [
                'id' => 'r1',
                'when' => ['all' => [['field' => 'ghost', 'op' => 'eq', 'value' => 'x']]],
                'effects' => [['type' => 'hide', 'target' => 'rdp_edition']],
            ],
        ], $this->configOptions);
    }

    public function test_duplicate_rule_id_rejected(): void
    {
        $this->expectException(BusinessException::class);

        $rule = [
            'id' => 'dup',
            'when' => ['all' => [['field' => 'os', 'op' => 'eq', 'value' => 'windows']]],
            'effects' => [['type' => 'hide', 'target' => 'rdp_edition']],
        ];
        ConfigLinkageRuleValidator::validate([$rule, $rule], $this->configOptions);
    }

    public function test_empty_effects_rejected(): void
    {
        $this->expectException(BusinessException::class);

        ConfigLinkageRuleValidator::validate([
            [
                'id' => 'r1',
                'when' => ['all' => [['field' => 'os', 'op' => 'eq', 'value' => 'windows']]],
                'effects' => [],
            ],
        ], $this->configOptions);
    }

    public function test_price_adjust_requires_mode_and_numeric_value(): void
    {
        // 缺 mode
        try {
            ConfigLinkageRuleValidator::validate([
                [
                    'id' => 'r1',
                    'when' => ['all' => [['field' => 'os', 'op' => 'eq', 'value' => 'windows']]],
                    'effects' => [['type' => 'price_adjust', 'target' => 'memory', 'value' => 10]],
                ],
            ], $this->configOptions);
            $this->fail('缺 mode 应当抛异常');
        } catch (BusinessException) {
            $this->assertTrue(true);
        }

        // value 非数值
        $this->expectException(BusinessException::class);
        ConfigLinkageRuleValidator::validate([
            [
                'id' => 'r2',
                'when' => ['all' => [['field' => 'os', 'op' => 'eq', 'value' => 'windows']]],
                'effects' => [['type' => 'price_adjust', 'target' => 'memory', 'mode' => 'fixed', 'value' => 'abc']],
            ],
        ], $this->configOptions);
    }

    public function test_unknown_sub_option_id_rejected(): void
    {
        $this->expectException(BusinessException::class);

        ConfigLinkageRuleValidator::validate([
            [
                'id' => 'r1',
                'when' => ['all' => [['field' => 'os', 'op' => 'eq', 'value' => 'windows']]],
                'effects' => [['type' => 'price_adjust', 'target' => 'memory', 'scope' => ['sub_option_id' => '16g'], 'mode' => 'fixed', 'value' => 10]],
            ],
        ], $this->configOptions);
    }

    public function test_show_and_hide_conflict_across_rules_rejected(): void
    {
        $this->expectException(BusinessException::class);

        ConfigLinkageRuleValidator::validate([
            [
                'id' => 'r1',
                'when' => ['all' => [['field' => 'os', 'op' => 'eq', 'value' => 'windows']]],
                'effects' => [['type' => 'show', 'target' => 'rdp_edition']],
            ],
            [
                'id' => 'r2',
                'when' => ['all' => [['field' => 'memory', 'op' => 'eq', 'value' => '8g']]],
                'effects' => [['type' => 'hide', 'target' => 'rdp_edition']],
            ],
        ], $this->configOptions);
    }

    public function test_self_reference_show_rejected(): void
    {
        $this->expectException(BusinessException::class);

        ConfigLinkageRuleValidator::validate([
            [
                'id' => 'r1',
                'when' => ['all' => [['field' => 'rdp_edition', 'op' => 'eq', 'value' => 'pro']]],
                'effects' => [['type' => 'show', 'target' => 'rdp_edition']],
            ],
        ], $this->configOptions);
    }

    public function test_range_target_rejects_show_but_allows_disable_and_price_adjust(): void
    {
        // 范围型字段作为 show 目标 → 报错
        try {
            ConfigLinkageRuleValidator::validate([
                [
                    'id' => 'r1',
                    'when' => ['all' => [['field' => 'os', 'op' => 'eq', 'value' => 'windows']]],
                    'effects' => [['type' => 'show', 'target' => 'bw']],
                ],
            ], $this->configOptions);
            $this->fail('范围型字段 show 应当抛异常');
        } catch (BusinessException) {
            $this->assertTrue(true);
        }

        // 范围型字段作为 hide 目标 → 报错
        try {
            ConfigLinkageRuleValidator::validate([
                [
                    'id' => 'r2',
                    'when' => ['all' => [['field' => 'os', 'op' => 'eq', 'value' => 'windows']]],
                    'effects' => [['type' => 'hide', 'target' => 'bw']],
                ],
            ], $this->configOptions);
            $this->fail('范围型字段 hide 应当抛异常');
        } catch (BusinessException) {
            $this->assertTrue(true);
        }

        // 范围型字段 disable / price_adjust → 放行
        ConfigLinkageRuleValidator::validate([
            [
                'id' => 'r3',
                'when' => ['all' => [['field' => 'os', 'op' => 'eq', 'value' => 'windows']]],
                'effects' => [
                    ['type' => 'disable', 'target' => 'bw'],
                    ['type' => 'price_adjust', 'target' => 'bw', 'mode' => 'percent', 'value' => 10],
                ],
            ],
        ], $this->configOptions);

        $this->assertTrue(true);
    }

    public function test_between_condition_value_must_be_pair(): void
    {
        $this->expectException(BusinessException::class);

        ConfigLinkageRuleValidator::validate([
            [
                'id' => 'r1',
                'when' => ['all' => [['field' => 'bw', 'op' => 'between', 'value' => [10]]]],
                'effects' => [['type' => 'disable', 'target' => 'rdp_edition']],
            ],
        ], $this->configOptions);
    }
}
