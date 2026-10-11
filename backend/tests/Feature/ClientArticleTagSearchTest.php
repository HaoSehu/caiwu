<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Models\ContentArticle;
use App\Models\ContentTag;
use App\Services\Content\ContentArticleService;
use App\Services\Content\ContentTagService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * M8 前台文章标签检索与标签云：any/all 组合、keyword 命中、软删文章不计入标签云。
 * 走 Service 层（HTTP 层 filters 白名单透传由集成阶段补齐，见 m8/INTEGRATION.md）。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class ClientArticleTagSearchTest extends TestCase
{
    use DatabaseTransactions;

    private ContentArticleService $articleService;

    private ContentTagService $tagService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->articleService = app(ContentArticleService::class);
        $this->tagService = app(ContentTagService::class);
    }

    public function test_tag_mode_any_matches_articles_with_any_selected_tag(): void
    {
        [$tagA, $tagB, $tagC] = [$this->makeTag(), $this->makeTag(), $this->makeTag()];
        [$articleA, $articleB, $articleC] = [$this->makeArticle(), $this->makeArticle(), $this->makeArticle()];

        $this->tagService->syncArticleTags((int) $articleA->id, [(int) $tagA->id]);
        $this->tagService->syncArticleTags((int) $articleB->id, [(int) $tagB->id]);
        $this->tagService->syncArticleTags((int) $articleC->id, [(int) $tagA->id, (int) $tagC->id]);

        $ids = [$tagA->id, $tagB->id];
        $matched = $this->publishedIds(['tag_ids' => implode(',', $ids), 'tag_mode' => 'any']);

        $this->assertContains((int) $articleA->id, $matched);
        $this->assertContains((int) $articleB->id, $matched);
        $this->assertContains((int) $articleC->id, $matched);
        $this->assertCount(3, $matched);
    }

    public function test_tag_mode_all_requires_every_selected_tag(): void
    {
        [$tagA, $tagB, $tagC] = [$this->makeTag(), $this->makeTag(), $this->makeTag()];
        [$articleA, $articleB] = [$this->makeArticle(), $this->makeArticle()];

        $this->tagService->syncArticleTags((int) $articleA->id, [(int) $tagA->id]);
        $this->tagService->syncArticleTags((int) $articleB->id, [(int) $tagA->id, (int) $tagC->id]);

        $ids = [$tagA->id, $tagC->id];
        $matched = $this->publishedIds(['tag_ids' => implode(',', $ids), 'tag_mode' => 'all']);

        $this->assertSame([(int) $articleB->id], $matched);
    }

    public function test_default_tag_mode_is_any(): void
    {
        [$tagA, $tagB] = [$this->makeTag(), $this->makeTag()];
        $articleA = $this->makeArticle();
        $articleB = $this->makeArticle();

        $this->tagService->syncArticleTags((int) $articleA->id, [(int) $tagA->id]);
        $this->tagService->syncArticleTags((int) $articleB->id, [(int) $tagB->id]);

        // 未传 tag_mode 时默认 any：两个标签各自命中的文章都应返回
        $matched = $this->publishedIds(['tag_ids' => $tagA->id.','.$tagB->id]);

        $this->assertEqualsCanonicalizing(
            [(int) $articleA->id, (int) $articleB->id],
            $matched,
        );
    }

    public function test_keyword_filters_by_title(): void
    {
        $marker = 'KEYWORD'.uniqid();
        $hit = $this->makeArticle($marker.' installation guide');
        $this->makeArticle('无关文章');

        $matched = $this->publishedIds(['keyword' => $marker]);

        $this->assertSame([(int) $hit->id], $matched);
    }

    public function test_soft_deleted_articles_are_excluded_from_tag_cloud(): void
    {
        $tagAlive = $this->makeTag();
        $tagOnlyDeleted = $this->makeTag();

        $aliveArticle = $this->makeArticle();
        $deletedArticle = $this->makeArticle();

        $this->tagService->syncArticleTags((int) $aliveArticle->id, [(int) $tagAlive->id]);
        $this->tagService->syncArticleTags((int) $deletedArticle->id, [(int) $tagOnlyDeleted->id]);

        // 软删后 pivot 行保留（无物理级联），但标签云必须排除
        $this->articleService->delete($deletedArticle);

        $cloud = $this->tagService->clientTagCloud();
        $cloudTagIds = $cloud->pluck('id')->map(intval(...))->all();

        $this->assertContains((int) $tagAlive->id, $cloudTagIds);
        $this->assertNotContains((int) $tagOnlyDeleted->id, $cloudTagIds);
    }

    public function test_tag_cloud_orders_by_usage_count_desc(): void
    {
        $hotTag = $this->makeTag();
        $coldTag = $this->makeTag();

        $articles = [$this->makeArticle(), $this->makeArticle(), $this->makeArticle()];
        $this->tagService->syncArticleTags((int) $articles[0]->id, [(int) $hotTag->id]);
        $this->tagService->syncArticleTags((int) $articles[1]->id, [(int) $hotTag->id]);
        $this->tagService->syncArticleTags((int) $articles[2]->id, [(int) $coldTag->id]);

        $cloud = $this->tagService->clientTagCloud();
        $usageById = $cloud->pluck('usage_count', 'id')->map(fn ($count): int => (int) $count);

        $this->assertGreaterThan((int) $usageById[$coldTag->id], (int) $usageById[$hotTag->id]);
        $this->assertSame(2, (int) $usageById[$hotTag->id]);
    }

    public function test_sync_maintains_usage_count_cache_and_diff(): void
    {
        $tag = $this->makeTag();
        $article = $this->makeArticle();

        $this->tagService->syncArticleTags((int) $article->id, [(int) $tag->id, (int) $this->makeTag()->id]);
        // 绑 2 个标签：每个新标签各自 +1（usage_count 原子维护），pivot 落 2 行。
        $this->assertSame(1, (int) $tag->refresh()->usage_count);
        $this->assertSame(2, DB::table('content_article_tags')->where('article_id', $article->id)->count());

        // diff：换绑后只保留新集合，usage_count 同步回落
        $replacement = $this->makeTag();
        $this->tagService->syncArticleTags((int) $article->id, [(int) $replacement->id]);

        $this->assertSame(0, (int) $tag->refresh()->usage_count);
        $this->assertSame(1, (int) $replacement->refresh()->usage_count);
        $this->assertSame(
            [(int) $replacement->id],
            DB::table('content_article_tags')->where('article_id', $article->id)->pluck('tag_id')->map(intval(...))->all(),
        );
    }

    public function test_sync_rejects_unknown_tag_ids(): void
    {
        $article = $this->makeArticle();

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('存在无效的标签');

        $this->tagService->syncArticleTags((int) $article->id, [999999999]);
    }

    public function test_publish_cache_bump_keeps_tag_filters_fresh(): void
    {
        // 冒烟：带标签过滤的发布列表能正常返回结构化分页（列表 + 标签组合路径）
        $tag = $this->makeTag();
        $article = $this->makeArticle();
        $this->tagService->syncArticleTags((int) $article->id, [(int) $tag->id]);

        $paginator = $this->articleService->publishedList('help', [
            'tag_ids' => (string) $tag->id,
        ], 12);

        $this->assertSame(1, (int) $paginator->total());
        $this->assertSame((int) $article->id, (int) $paginator->items()[0]->id);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, int>
     */
    private function publishedIds(array $filters): array
    {
        return collect(
            $this->articleService->publishedList('help', $filters, 50)->items()
        )->map(fn (ContentArticle $article): int => (int) $article->id)->all();
    }

    private function makeTag(): ContentTag
    {
        return $this->tagService->create([
            'name' => '标签'.uniqid(),
        ]);
    }

    private function makeArticle(?string $title = null): ContentArticle
    {
        $categoryId = (int) DB::table('content_categories')->insertGetId([
            'content_type' => 'help',
            'name' => '检索分类'.uniqid(),
            'slug' => 'search-cat-'.uniqid(),
            'status' => 1,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->articleService->create([
            'content_type' => 'help',
            'category_id' => $categoryId,
            'title' => $title ?? '检索文章'.uniqid(),
            'content' => '正文内容 '.uniqid(),
            'status' => 1,
        ], adminId: 1);
    }
}
