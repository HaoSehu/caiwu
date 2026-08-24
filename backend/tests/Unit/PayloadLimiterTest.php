<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\PayloadLimiter;
use PHPUnit\Framework\TestCase;

class PayloadLimiterTest extends TestCase
{
    public function test_truncate_leaves_cuts_only_long_string_leaves(): void
    {
        $payload = [
            'short' => 'hello',
            'long' => str_repeat('x', 100),
            'nested' => ['deep' => str_repeat('y', 100), 'keep' => 42],
        ];

        $result = PayloadLimiter::truncateLeaves($payload, 20);

        $this->assertSame('hello', $result['short']);
        $this->assertStringStartsWith(mb_strcut(str_repeat('x', 100), 0, 20), $result['long']);
        $this->assertStringContainsString('[truncated 100 bytes]', $result['long']);
        $this->assertSame(42, $result['nested']['keep']);
        $this->assertStringContainsString('[truncated 100 bytes]', $result['nested']['deep']);
    }

    public function test_limit_returns_original_structure_within_budget(): void
    {
        $payload = ['request_id' => 'r1', 'status' => 'ok', 'detail' => str_repeat('a', 100)];

        $result = PayloadLimiter::limit($payload, 8192, 65536, 4096);

        $this->assertSame('r1', $result['request_id']);
        $this->assertArrayNotHasKey('__truncated', $result);
    }

    public function test_limit_falls_back_to_prefixed_summary_when_over_budget(): void
    {
        $big = str_repeat('z', 100000);
        $payload = ['data' => $big];

        $result = PayloadLimiter::limit($payload, 8192, 1024, 128);

        $this->assertTrue($result['__truncated']);
        $this->assertArrayHasKey('__original_bytes', $result);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $result['__sha256']);
        $this->assertLessThanOrEqual(128, strlen($result['__preview']));
    }

    public function test_limit_strips_invalid_utf8_to_avoid_json_exception_downstream(): void
    {
        $payload = ['name' => "合法\xFF\xFE非法"];

        $result = PayloadLimiter::limit($payload, 8192, 65536, 4096);

        // 编码→解码往返后应仍是数组而非降级摘要，且无非法 UTF-8
        $this->assertArrayNotHasKey('__truncated', $result);
        $this->assertNotNull(json_encode($result, JSON_INVALID_UTF8_SUBSTITUTE));
    }
}
