<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Content\Storage\MediaStorageResolver;
use Tests\TestCase;

class MediaStorageResolverTest extends TestCase
{
    public function test_resolve_returns_local_for_explicit_local_driver(): void
    {
        $resolver = app(MediaStorageResolver::class);

        $this->assertSame(MediaStorageResolver::LOCAL_DRIVER, $resolver->resolve('content', 'local'));
    }

    public function test_resolve_falls_back_to_local_when_driver_unavailable(): void
    {
        $resolver = app(MediaStorageResolver::class);

        // 未安装/未启用的驱动必须回落 local，而不是抛异常或改变上传行为。
        $this->assertSame(MediaStorageResolver::LOCAL_DRIVER, $resolver->resolve('content', 'no_such_driver'));
    }

    public function test_resolve_defaults_to_local_without_any_policy(): void
    {
        $resolver = app(MediaStorageResolver::class);

        $this->assertSame(MediaStorageResolver::LOCAL_DRIVER, $resolver->resolve('content', null));
    }

    public function test_is_remote_driver_available_rejects_invalid_slugs(): void
    {
        $resolver = app(MediaStorageResolver::class);

        $this->assertFalse($resolver->isRemoteDriverAvailable(''));
        $this->assertFalse($resolver->isRemoteDriverAvailable('Bad Slug'));
        $this->assertFalse($resolver->isRemoteDriverAvailable('LOCAL'));
    }
}
