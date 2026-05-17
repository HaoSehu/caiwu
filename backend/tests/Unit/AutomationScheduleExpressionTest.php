<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\AutomationScheduleExpression;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AutomationScheduleExpressionTest extends TestCase
{
    #[Test]
    public function it_resolves_hourly_expression_from_configured_minute(): void
    {
        $expression = AutomationScheduleExpression::resolve(
            AutomationScheduleExpression::MODE_HOURLY,
            '00:35:00'
        );

        $this->assertSame('35 * * * *', $expression);
        $this->assertSame('每小时第 35 分钟', AutomationScheduleExpression::describe(
            AutomationScheduleExpression::MODE_HOURLY,
            '00:35:00'
        ));
    }

    #[Test]
    public function it_resolves_daily_expression_from_configured_time(): void
    {
        $expression = AutomationScheduleExpression::resolve(
            AutomationScheduleExpression::MODE_DAILY,
            '02:15:00'
        );

        $this->assertSame('15 2 * * *', $expression);
        $this->assertSame('每天 02:15', AutomationScheduleExpression::describe(
            AutomationScheduleExpression::MODE_DAILY,
            '02:15:00'
        ));
    }

    #[Test]
    public function it_falls_back_to_default_mode_and_time_when_value_is_invalid(): void
    {
        $expression = AutomationScheduleExpression::resolve(
            'invalid-mode',
            'bad-time',
            AutomationScheduleExpression::MODE_HOURLY,
            '00:20:00'
        );

        $this->assertSame('20 * * * *', $expression);
    }
}
