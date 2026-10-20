<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\UserAgentSummary;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserAgentSummaryTest extends TestCase
{
    /**
     * @return array<string, array{string, string, string}>
     */
    public static function agents(): array
    {
        return [
            'chrome windows' => [
                'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36',
                'Chrome 130.0.0.0',
                'Windows 10/11',
            ],
            'edge windows' => [
                'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36 Edg/130.0.2849.68',
                'Edge 130.0.2849.68',
                'Windows 10/11',
            ],
            'firefox linux' => [
                'Mozilla/5.0 (X11; Linux x86_64; rv:131.0) Gecko/20100101 Firefox/131.0',
                'Firefox 131.0',
                'Linux',
            ],
            'safari iphone' => [
                'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1',
                'Safari 17.5',
                'iOS 17.5',
            ],
            'mac chrome' => [
                'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36',
                'Chrome 129.0.0.0',
                'macOS 10.15.7',
            ],
        ];
    }

    #[DataProvider('agents')]
    public function test_parses_browser_and_operating_system(string $agent, string $browser, string $os): void
    {
        $this->assertSame(
            ['browser' => $browser, 'operating_system' => $os],
            UserAgentSummary::parse($agent),
        );
    }

    public function test_blank_agent_returns_empty_summary(): void
    {
        $this->assertSame(['browser' => '', 'operating_system' => ''], UserAgentSummary::parse(null));
        $this->assertSame(['browser' => '', 'operating_system' => ''], UserAgentSummary::parse('  '));
    }

    public function test_unknown_agent_is_marked_unknown(): void
    {
        $this->assertSame(
            ['browser' => '未知', 'operating_system' => '未知'],
            UserAgentSummary::parse('curl/8.5.0'),
        );
    }
}
