<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\ProductCatalog\Concerns\HandlesProductCatalogHelpers;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 商品目录缓存失效的事务语义回归。
 *
 * forgetSiteCatalogCache() 的调用方（ProductCategoryService 等）多数在 DB::transaction()
 * 内触发失效。若提交前就 bump 版本号，并发请求会用新版本键缓存住提交前的旧目录，
 * 提交后不再有第二次失效——旧数据要挂满整个 600-900 秒 TTL。
 *
 * 整体失效动作已注册为 DB::afterCommit 回调，这里锁住三种场景：
 * 无事务立即执行、事务提交后执行、事务回滚丢弃。
 *
 * 通过 trait 的匿名宿主类直接驱动私有 forgetSiteCatalogCache()，
 * 不依赖任何表结构（afterCommit 只涉及缓存与连接事务管理器）。
 */
class SiteCatalogCacheInvalidationTest extends TestCase
{
    private const ADMIN_SUMMARY_KEY = 'catalog:admin_summary:v1';

    private const SITE_CATALOG_KEY = 'catalog:site:v1';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    /**
     * @return object 匿名宿主类：合并 trait 后暴露 flush 与版本号读取
     */
    private function flusher(): object
    {
        return new class
        {
            use HandlesProductCatalogHelpers;

            public function flush(): void
            {
                $this->forgetSiteCatalogCache();
            }

            public function version(): int
            {
                return $this->siteCacheVersion();
            }
        };
    }

    /**
     * 往两个非版本化的目录键里塞值，返回失效前的版本号。
     */
    private function seedCatalogCaches(): int
    {
        Cache::put(self::ADMIN_SUMMARY_KEY, ['products_total' => 1], now()->addHour());
        Cache::put(self::SITE_CATALOG_KEY, ['stale'], now()->addHour());

        return $this->flusher()->version();
    }

    private function assertCatalogCachesInvalidated(int $versionBefore): void
    {
        // 版本号放第一条：它是唯一会让线上显示旧数据的那步，
        // 断言失败时希望直接看到它，而不是被前面的键断言挡住。
        $this->assertSame(
            $versionBefore + 1,
            $this->flusher()->version(),
            '目录拆分版本号未递增——官网目录页会继续读旧键直到 600-900 秒 TTL 过期'
        );
        $this->assertNull(Cache::get(self::ADMIN_SUMMARY_KEY), '管理端目录概览缓存未失效');
        $this->assertNull(Cache::get(self::SITE_CATALOG_KEY), '站点目录缓存未失效');
    }

    public function test_flush_without_transaction_takes_effect_immediately(): void
    {
        $versionBefore = $this->seedCatalogCaches();

        $this->flusher()->flush();

        $this->assertCatalogCachesInvalidated($versionBefore);
    }

    public function test_flush_inside_transaction_defers_until_commit(): void
    {
        $versionBefore = $this->seedCatalogCaches();

        DB::beginTransaction();
        $this->flusher()->flush();

        $this->assertSame(
            $versionBefore,
            $this->flusher()->version(),
            '事务提交前版本号不应递增，否则并发请求会把旧目录缓存进新版本键'
        );
        $this->assertNotNull(Cache::get(self::SITE_CATALOG_KEY), '事务提交前站点目录缓存不应被清除');

        DB::commit();

        $this->assertCatalogCachesInvalidated($versionBefore);
    }

    public function test_flush_inside_rolled_back_transaction_is_discarded(): void
    {
        // 回滚意味着数据没变，失效也应一并作废；否则每次失败的写操作都会白白打穿缓存。
        $versionBefore = $this->seedCatalogCaches();

        DB::beginTransaction();
        $this->flusher()->flush();
        DB::rollBack();

        $this->assertSame($versionBefore, $this->flusher()->version(), '回滚后版本号不应递增');
        $this->assertNotNull(Cache::get(self::ADMIN_SUMMARY_KEY), '回滚后缓存不应被清除');
        $this->assertNotNull(Cache::get(self::SITE_CATALOG_KEY));
    }
}
