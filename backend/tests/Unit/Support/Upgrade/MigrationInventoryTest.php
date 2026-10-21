<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Upgrade;

use App\Support\Upgrade\MigrationInventory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * 迁移盘点：区分可执行迁移、归档迁移、白名单孤儿与非白名单孤儿。
 */
class MigrationInventoryTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'migration-inventory-'.bin2hex(random_bytes(6));
        mkdir($this->root.DIRECTORY_SEPARATOR.'migrations', 0775, true);
        mkdir($this->root.DIRECTORY_SEPARATOR.'archive', 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);

        parent::tearDown();
    }

    #[Test]
    public function scans_only_php_files_sorted(): void
    {
        touch($this->root.'/migrations/2026_10_21_000002_b.php');
        touch($this->root.'/migrations/2026_10_21_000001_a.php');
        touch($this->root.'/migrations/README.md');

        $names = MigrationInventory::scanMigrations($this->root.'/migrations');

        $this->assertSame([
            '2026_10_21_000001_a',
            '2026_10_21_000002_b',
        ], $names);
    }

    #[Test]
    public function whitelist_requires_reason_and_date(): void
    {
        $manifest = $this->root.'/orphans.json';
        file_put_contents($manifest, json_encode([
            'orphans' => [
                ['migration' => 'a', 'reason' => '历史删除', 'recorded_at' => '2026-10-21'],
            ],
        ], JSON_UNESCAPED_UNICODE));

        $whitelist = MigrationInventory::loadOrphanWhitelist($manifest);

        $this->assertArrayHasKey('a', $whitelist);
        $this->assertSame('历史删除', $whitelist['a']['reason']);
    }

    #[Test]
    public function whitelist_entry_without_reason_is_rejected(): void
    {
        $manifest = $this->root.'/orphans.json';
        file_put_contents($manifest, json_encode([
            'orphans' => [['migration' => 'a']],
        ]));

        $this->expectException(RuntimeException::class);

        MigrationInventory::loadOrphanWhitelist($manifest);
    }

    #[Test]
    public function missing_manifest_yields_empty_whitelist(): void
    {
        $this->assertSame([], MigrationInventory::loadOrphanWhitelist($this->root.'/nope.json'));
    }

    #[Test]
    public function whitelisted_orphans_are_reported_as_allowed_not_unexpected(): void
    {
        // 用临时目录构造「账本有记录但文件全无」的场景，验证白名单分流。
        touch($this->root.'/migrations/2026_10_21_000001_kept.php');
        $manifest = $this->root.'/orphans.json';
        file_put_contents($manifest, json_encode([
            'orphans' => [
                ['migration' => '2026_01_01_000001_gone_but_allowed', 'reason' => '历史删除', 'recorded_at' => '2026-10-21'],
            ],
        ], JSON_UNESCAPED_UNICODE));

        $whitelist = MigrationInventory::loadOrphanWhitelist($manifest);
        $this->assertArrayHasKey('2026_01_01_000001_gone_but_allowed', $whitelist);

        // 未登记的名字不在白名单中，会被判为 unexpected。
        $this->assertArrayNotHasKey('2026_01_01_000002_not_registered', $whitelist);
    }

    #[Test]
    public function requires_attention_flags_pending_and_unexpected(): void
    {
        $clean = new MigrationInventory(['a'], ['old'], ['a'], [], [], []);
        $this->assertFalse($clean->requiresAttention());

        $withPending = new MigrationInventory(['a', 'b'], [], ['a'], ['b'], [], []);
        $this->assertTrue($withPending->requiresAttention());

        $withUnexpected = new MigrationInventory(['a'], [], ['a', 'gone'], [], [], ['gone']);
        $this->assertTrue($withUnexpected->requiresAttention());
    }

    private function removeDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $items = scandir($path) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $full = $path.DIRECTORY_SEPARATOR.$item;
            is_dir($full) ? $this->removeDirectory($full) : @unlink($full);
        }

        @rmdir($path);
    }
}
