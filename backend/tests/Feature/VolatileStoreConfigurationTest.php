<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Cache\VolatileStore;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * 锁定 volatile 存储层的接线关系，避免「改了 store 名 / 改了连接名，测试照样全绿」。
 *
 * 基类 Tests\TestCase 只把 redis_volatile 的 driver 换成 array，不改 store 名与连接名；
 * 一旦这里失败，说明代码里的 VolatileStore 与 backend/config/cache.php 已经对不上，
 * 生产会在运行期抛「store 未定义」或悄悄落到默认缓存库。
 */
final class VolatileStoreConfigurationTest extends TestCase
{
    public function test_store_name_matches_config_key(): void
    {
        $stores = (array) config('cache.stores', []);

        $this->assertArrayHasKey(
            VolatileStore::name(),
            $stores,
            'VolatileStore::name() 必须与 config/cache.php 的 store 键一致，否则 VolatileStore::store() 会抛未定义 store'
        );
    }

    public function test_store_keeps_its_dedicated_connection(): void
    {
        // 基类覆盖只改 driver，连接名必须仍来自 config/cache.php（默认 volatile），
        // 否则「高频短 TTL 隔离到 DB 2」的设计会在测试环境下失去校验。
        $this->assertSame(
            'volatile',
            (string) config('cache.stores.'.VolatileStore::name().'.connection', ''),
            'volatile store 的连接名必须保持 config/cache.php 的默认值 volatile'
        );
    }

    public function test_base_test_case_swaps_driver_to_array(): void
    {
        $this->assertSame('array', (string) config('cache.stores.'.VolatileStore::name().'.driver'));

        // 真正拿到 Repository 也不应要求真实 Redis：写入后能立刻读回。
        $store = VolatileStore::store();
        $store->put('volatile-store-config-test', 'ok', 60);

        $this->assertSame('ok', $store->get('volatile-store-config-test'));
        $store->forget('volatile-store-config-test');
    }

    public function test_default_cache_store_is_independent_from_volatile(): void
    {
        // phpunit.xml.dist 把默认 store 固定为 array；volatile 是独立 store，
        // 两者不能是同一个配置键，否则测试里的隔离会掩盖生产的分层失效。
        $this->assertNotSame(
            (string) config('cache.default'),
            VolatileStore::name(),
            '默认 store 与 volatile store 必须是两个独立配置项'
        );
        $this->assertInstanceOf(Repository::class, Cache::store(VolatileStore::name()));
    }
}
