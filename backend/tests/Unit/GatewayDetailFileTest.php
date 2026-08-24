<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\GatewayDetailFile;
use Tests\TestCase;

class GatewayDetailFileTest extends TestCase
{
    /** 专用未来日期文件名，避免与真实按日轮转的 gateway-json-*.log 冲突 */
    private const TEST_FILE = 'gateway-json-2099-12-31.log';

    protected function tearDown(): void
    {
        @unlink(storage_path('logs/'.self::TEST_FILE));
        parent::tearDown();
    }

    public function test_read_rejects_malicious_locator(): void
    {
        $this->assertNull(GatewayDetailFile::read(''));
        $this->assertNull(GatewayDetailFile::read('../../app/Casts/Foo.php:abc'));
        $this->assertNull(GatewayDetailFile::read('laravel-2026-08-25.log:abc'));
        $this->assertNull(GatewayDetailFile::read('gateway-json-not-a-date.log:abc'));
    }

    public function test_read_marks_unavailable_when_daily_file_missing(): void
    {
        $this->assertSame(
            ['detail_unavailable' => true],
            GatewayDetailFile::read(self::TEST_FILE.':'.str_repeat('k', 26)),
        );
    }

    public function test_read_marks_unavailable_when_entry_missing_from_existing_file(): void
    {
        $this->writeDetailLines([['detail_key' => 'another-key', 'gateway' => 'alipay']]);

        $this->assertSame(
            ['detail_unavailable' => true],
            GatewayDetailFile::read(self::TEST_FILE.':missing-key'),
        );
    }

    public function test_read_returns_entry_when_locator_matches(): void
    {
        $this->writeDetailLines([
            ['detail_key' => 'noise-1', 'gateway' => 'wechat'],
            ['detail_key' => 'target-1', 'gateway' => 'alipay', 'request_data' => ['a' => 1], 'response_data' => ['b' => 2]],
        ]);

        $entry = GatewayDetailFile::read(self::TEST_FILE.':target-1');

        $this->assertNotNull($entry);
        $this->assertSame('alipay', $entry['gateway']);
        $this->assertSame(['a' => 1], $entry['request_data']);
        $this->assertSame(['b' => 2], $entry['response_data']);
    }

    public function test_write_returns_null_in_testing_environment(): void
    {
        // gateway-json 测试通道为 NullHandler，write() 应显式返回 null，
        // 让调用方走库内截断摘要降级路径，避免测试拿到 locator 却断言空明细。
        $this->assertNull(GatewayDetailFile::write(['a' => 1], ['b' => 2], 'alipay'));
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function writeDetailLines(array $rows): void
    {
        $lines = array_map(
            static fn (array $context): string => json_encode(['context' => $context], JSON_UNESCAPED_UNICODE),
            $rows,
        );

        file_put_contents(
            storage_path('logs/'.self::TEST_FILE),
            implode("\n", $lines)."\n",
        );
    }
}
