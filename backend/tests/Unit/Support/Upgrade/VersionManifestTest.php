<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Upgrade;

use App\Support\Upgrade\VersionManifest;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * 版本清单读取与升级兼容判断。
 */
class VersionManifestTest extends TestCase
{
    #[Test]
    public function loads_version_and_optional_minimum(): void
    {
        $manifest = VersionManifest::fromArray([
            'version' => '1.2.3',
            'minimum_upgrade_version' => '1.0.0',
            'released_at' => '2026-10-21',
        ]);

        $this->assertSame('1.2.3', $manifest->version);
        $this->assertSame('1.0.0', $manifest->minimumUpgradeVersion);
        $this->assertSame('2026-10-21', $manifest->releasedAt);
    }

    #[Test]
    public function minimum_is_null_when_absent(): void
    {
        $manifest = VersionManifest::fromArray(['version' => '1.0.0']);

        $this->assertNull($manifest->minimumUpgradeVersion);
        $this->assertTrue($manifest->allowsUpgradeFrom('0.9.0'));
    }

    #[Test]
    public function rejects_empty_version(): void
    {
        $this->expectException(RuntimeException::class);

        VersionManifest::fromArray(['version' => '   ']);
    }

    #[Test]
    public function rejects_malformed_version(): void
    {
        $this->expectException(RuntimeException::class);

        VersionManifest::fromArray(['version' => 'v1']);
    }

    #[Test]
    public function compares_minimum_upgrade_version(): void
    {
        $manifest = VersionManifest::fromArray([
            'version' => '2.0.0',
            'minimum_upgrade_version' => '1.5.0',
        ]);

        $this->assertTrue($manifest->allowsUpgradeFrom('1.5.0'));
        $this->assertTrue($manifest->allowsUpgradeFrom('1.9.9'));
        $this->assertFalse($manifest->allowsUpgradeFrom('1.4.9'));
    }

    #[Test]
    public function default_path_points_to_backend_root(): void
    {
        $this->assertSame(base_path('version.json'), VersionManifest::defaultPath());
        $this->assertFileExists(VersionManifest::defaultPath());
    }
}
