<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\MediaFile;
use App\Services\Content\Storage\MediaMigrationService;
use App\Services\Content\Storage\MediaStorageResolver;
use ReflectionMethod;
use Tests\TestCase;

/**
 * 迁移「同目标跳过」行为回归：
 * to_remote 时磁盘值已等于目标驱动、to_local 时磁盘值已为 local 的行，
 * 应被 isAlreadyAtTarget 判定为已在目标形态，run() 循环按 skipped 归集而非重传或误报失败。
 */
class MediaMigrationTargetCheckTest extends TestCase
{
    public function test_to_remote_same_driver_is_already_at_target(): void
    {
        $row = new MediaFile(['disk' => 'ftp']);
        $method = new ReflectionMethod(MediaMigrationService::class, 'isAlreadyAtTarget');

        $service = app(MediaMigrationService::class);
        $this->assertTrue($method->invoke($service, $row, 'to_remote', 'ftp'));
        $this->assertFalse($method->invoke($service, $row, 'to_remote', 'qiniu'));
    }

    public function test_to_local_local_disk_is_already_at_target(): void
    {
        $row = new MediaFile(['disk' => 'local']);
        $method = new ReflectionMethod(MediaMigrationService::class, 'isAlreadyAtTarget');

        $this->assertTrue($method->invoke(app(MediaMigrationService::class), $row, 'to_local', 'ftp'));
    }

    public function test_empty_disk_falls_back_to_local_semantics(): void
    {
        // 历史行 disk 为空串/null 时按 local 语义处理：to_local 视为已在目标，to_remote 不视为同目标。
        $row = new MediaFile(['disk' => '']);
        $method = new ReflectionMethod(MediaMigrationService::class, 'isAlreadyAtTarget');
        $service = app(MediaMigrationService::class);

        $this->assertTrue($method->invoke($service, $row, 'to_local', 'ftp'));
        $this->assertFalse($method->invoke($service, $row, 'to_remote', 'ftp'));
    }

    public function test_local_driver_constant_matches(): void
    {
        $this->assertSame('local', MediaStorageResolver::LOCAL_DRIVER);
    }
}
