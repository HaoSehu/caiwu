<?php

declare(strict_types=1);

namespace App\Services\Site;

use App\Models\ContentArticle;
use App\Services\Content\ContentArticleService;
use App\Services\Content\HomeHeroService;
use App\Support\ContentPublishedCacheVersion;
use App\Support\SiteSeoConfig;
use App\Support\UploadUrl;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class SiteHomeService
{
    private const HOME_CACHE_TTL_SECONDS = 120;

    public function __construct(
        private readonly ContentArticleService $contentArticleService,
        private readonly SiteProductReadService $siteProductReadService,
        private readonly HomeHeroService $homeHeroService,
    ) {}

    public function overview(int $groupLimit = 4, int $noticeLimit = 50, int $helpLimit = 4): array
    {
        $contentVersion = ContentPublishedCacheVersion::current();
        $cacheKey = sprintf('site:home:%d:%d:%d:v%d', $groupLimit, $noticeLimit, $helpLimit, $contentVersion);

        return Cache::remember(
            $cacheKey,
            now()->addSeconds(self::HOME_CACHE_TTL_SECONDS),
            function () use ($groupLimit, $noticeLimit, $helpLimit): array {
                $contentOverview = $this->contentArticleService->publishedOverview($noticeLimit, $helpLimit);
                $rootGroups = collect($this->siteProductReadService->productGroups()['list'] ?? [])
                    ->slice(0, $groupLimit)
                    ->values()
                    ->all();

                $groupCatalogMap = $this->siteProductReadService->groupCatalogMap(
                    array_map(
                        static fn (array $group): int => (int) ($group['id'] ?? 0),
                        $rootGroups
                    )
                );

                return [
                    'site_config' => $this->resolveSiteConfig(),
                    'hero' => $this->homeHeroService->getHero(),
                    'notices' => $this->transformArticles($contentOverview['notices'] ?? collect()),
                    'help_articles' => $this->transformArticles($contentOverview['help_articles'] ?? collect()),
                    'root_groups' => $this->transformRootGroups($rootGroups),
                    'group_catalog_map' => $groupCatalogMap,
                ];
            }
        );
    }

    /**
     * @return array{slides: array<int, array<string, mixed>>, features: array<int, array<string, mixed>>}
     */
    public function hero(): array
    {
        return $this->homeHeroService->getHero();
    }

    /**
     * @param  array<int, array<string, mixed>>  $groups
     * @return array<int, array<string, mixed>>
     */
    private function transformRootGroups(array $groups): array
    {
        return collect($groups)
            ->map(fn (array $group): array => [
                'id' => (int) ($group['id'] ?? 0),
                'name' => (string) ($group['name'] ?? ''),
                'slogan' => (string) ($group['slogan'] ?? ''),
                'product_count' => (int) ($group['product_count'] ?? 0),
                'product_type' => (string) ($group['product_type'] ?? ''),
                'product_type_id' => (int) ($group['product_type_id'] ?? 0),
                'product_type_label' => (string) ($group['product_type_label'] ?? ''),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, ContentArticle>|iterable<int, ContentArticle>  $articles
     * @return array<int, array<string, mixed>>
     */
    private function transformArticles(iterable $articles): array
    {
        return collect($articles)
            ->filter(fn ($article) => $article instanceof ContentArticle)
            ->map(function (ContentArticle $article): array {
                $excerpt = $article->summary ?: Str::limit(
                    preg_replace(
                        '/\s+/u',
                        ' ',
                        strip_tags(Str::markdown((string) $article->content))
                    ),
                    120,
                    '...'
                );

                return [
                    'id' => (int) $article->id,
                    'title' => (string) $article->title,
                    'summary' => $article->summary,
                    'excerpt' => $excerpt,
                    'cover_image' => $this->resolveCoverImageUrl($article->cover_image),
                    'category' => (string) ($article->contentCategory?->name ?: $article->category_name ?: ''),
                    'is_pinned' => (int) $article->is_pinned,
                    'is_recommended' => (int) $article->is_recommended,
                    'publish_at' => optional($article->publish_at)?->toDateTimeString(),
                    'updated_at' => optional($article->updated_at)?->toDateTimeString(),
                ];
            })
            ->values()
            ->all();
    }

    private function resolveCoverImageUrl(?string $value): ?string
    {
        return UploadUrl::resolve($value);
    }

    /**
     * @return array<string, string>
     */
    private function resolveSiteConfig(): array
    {
        return SiteSeoConfig::payload();
    }
}
