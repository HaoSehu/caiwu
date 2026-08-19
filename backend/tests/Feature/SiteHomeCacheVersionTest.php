<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\Content\ContentArticleService;
use App\Services\Content\HomeHeroService;
use App\Services\Site\SiteHomeService;
use App\Services\Site\SiteProductReadService;
use App\Services\System\SettingService;
use App\Support\ContentPublishedCacheVersion;
use App\Support\SiteHomeCacheVersion;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SiteHomeCacheVersionTest extends TestCase
{
    public function test_saving_basic_settings_bumps_the_site_home_cache_version(): void
    {
        $basicKeys = ['site_name', 'site_logo'];
        $originalBasicRows = DB::table('settings')
            ->where('group_key', 'basic')
            ->whereIn('item_key', $basicKeys)
            ->get(['group_key', 'item_key', 'item_value'])
            ->map(fn (object $row): array => [
                'group_key' => (string) $row->group_key,
                'item_key' => (string) $row->item_key,
                'item_value' => $row->item_value,
            ])
            ->all();
        $originalVersion = DB::table('settings')
            ->where('group_key', 'site_home')
            ->where('item_key', 'cache_version')
            ->first();

        Cache::forget('site:home:version');

        try {
            $versionBeforeSave = SiteHomeCacheVersion::current();

            app(SettingService::class)->saveGroupSettings('basic', [
                'site_name' => '首页缓存刷新测试',
                'site_logo' => '/branding/home-cache-refresh-test.svg',
            ]);

            $this->assertSame($versionBeforeSave + 1, SiteHomeCacheVersion::current());
        } finally {
            DB::table('settings')
                ->where('group_key', 'basic')
                ->whereIn('item_key', $basicKeys)
                ->delete();

            if ($originalBasicRows !== []) {
                DB::table('settings')->insert($originalBasicRows);
            }

            if ($originalVersion) {
                DB::table('settings')->updateOrInsert(
                    [
                        'group_key' => 'site_home',
                        'item_key' => 'cache_version',
                    ],
                    [
                        'item_value' => (string) ($originalVersion->item_value ?? '1'),
                    ]
                );
            } else {
                DB::table('settings')
                    ->where('group_key', 'site_home')
                    ->where('item_key', 'cache_version')
                    ->delete();
            }

            Setting::forgetCachedGroup('basic');
            Cache::forget('site:home:version');
        }
    }

    public function test_saving_basic_settings_rebuilds_cached_home_branding(): void
    {
        $basicKeys = ['site_name', 'site_logo'];
        $originalBasicRows = DB::table('settings')
            ->where('group_key', 'basic')
            ->whereIn('item_key', $basicKeys)
            ->get(['group_key', 'item_key', 'item_value'])
            ->map(fn (object $row): array => [
                'group_key' => (string) $row->group_key,
                'item_key' => (string) $row->item_key,
                'item_value' => $row->item_value,
            ])
            ->all();
        $originalVersion = DB::table('settings')
            ->where('group_key', 'site_home')
            ->where('item_key', 'cache_version')
            ->first();
        $contentVersion = ContentPublishedCacheVersion::current();
        $homeVersions = [];

        Cache::forget('site:home:version');
        Setting::setValues('basic', [
            'site_name' => '缓存旧名称',
            'site_logo' => '/branding/cache-old-logo.svg',
        ]);

        try {
            $contentArticles = $this->createMock(ContentArticleService::class);
            $contentArticles->expects($this->exactly(2))
                ->method('publishedOverview')
                ->with(1, 1)
                ->willReturn(['notices' => collect(), 'help_articles' => collect()]);

            $siteProducts = $this->createMock(SiteProductReadService::class);
            $siteProducts->expects($this->exactly(2))
                ->method('productTypes')
                ->willReturn(['list' => []]);
            $siteProducts->expects($this->exactly(2))
                ->method('productGroups')
                ->willReturn(['list' => []]);
            $siteProducts->expects($this->exactly(2))
                ->method('groupCatalogMap')
                ->with([])
                ->willReturn([]);

            $homeHero = $this->createMock(HomeHeroService::class);
            $homeHero->expects($this->exactly(2))
                ->method('getHero')
                ->willReturn(['slides' => [], 'features' => []]);

            $service = new SiteHomeService($contentArticles, $siteProducts, $homeHero);
            $homeVersions[] = SiteHomeCacheVersion::current();
            $oldPayload = $service->overview(groupLimit: 1, noticeLimit: 1, helpLimit: 1);

            app(SettingService::class)->saveGroupSettings('basic', [
                'site_name' => '缓存新名称',
                'site_logo' => '/branding/cache-new-logo.svg',
            ]);

            $homeVersions[] = SiteHomeCacheVersion::current();
            $newPayload = $service->overview(groupLimit: 1, noticeLimit: 1, helpLimit: 1);

            $this->assertSame('缓存旧名称', $oldPayload['site_config']['site_name']);
            $this->assertSame('/branding/cache-old-logo.svg', $oldPayload['site_config']['site_logo']);
            $this->assertSame('缓存新名称', $newPayload['site_config']['site_name']);
            $this->assertSame('/branding/cache-new-logo.svg', $newPayload['site_config']['site_logo']);
        } finally {
            foreach ($homeVersions as $homeVersion) {
                Cache::forget(sprintf('site:home:1:1:1:v%d:b%d', $contentVersion, $homeVersion));
            }

            DB::table('settings')
                ->where('group_key', 'basic')
                ->whereIn('item_key', $basicKeys)
                ->delete();

            if ($originalBasicRows !== []) {
                DB::table('settings')->insert($originalBasicRows);
            }

            if ($originalVersion) {
                DB::table('settings')->updateOrInsert(
                    [
                        'group_key' => 'site_home',
                        'item_key' => 'cache_version',
                    ],
                    [
                        'item_value' => (string) ($originalVersion->item_value ?? '1'),
                    ]
                );
            } else {
                DB::table('settings')
                    ->where('group_key', 'site_home')
                    ->where('item_key', 'cache_version')
                    ->delete();
            }

            Setting::forgetCachedGroup('basic');
            Cache::forget('site:home:version');
        }
    }

    public function test_site_home_cache_version_persists_after_runtime_cache_is_cleared(): void
    {
        $original = DB::table('settings')
            ->where('group_key', 'site_home')
            ->where('item_key', 'cache_version')
            ->first();

        Cache::forget('site:home:version');

        try {
            DB::table('settings')
                ->where('group_key', 'site_home')
                ->where('item_key', 'cache_version')
                ->delete();

            $this->assertSame(1, SiteHomeCacheVersion::current());
            $this->assertSame(2, SiteHomeCacheVersion::bump());

            $persistedValue = DB::table('settings')
                ->where('group_key', 'site_home')
                ->where('item_key', 'cache_version')
                ->value('item_value');

            $this->assertSame('2', (string) $persistedValue);

            Cache::forget('site:home:version');

            $this->assertSame(2, SiteHomeCacheVersion::current());
        } finally {
            if ($original) {
                DB::table('settings')->updateOrInsert(
                    [
                        'group_key' => 'site_home',
                        'item_key' => 'cache_version',
                    ],
                    [
                        'item_value' => (string) ($original->item_value ?? '1'),
                    ]
                );
            } else {
                DB::table('settings')
                    ->where('group_key', 'site_home')
                    ->where('item_key', 'cache_version')
                    ->delete();
            }

            Cache::forget('site:home:version');
        }
    }
}
