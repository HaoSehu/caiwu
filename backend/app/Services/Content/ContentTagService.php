<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Exceptions\BusinessException;
use App\Models\ContentTag;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * M8 知识库标签：管理端 CRUD 与文章-标签 pivot 维护。
 *
 * usage_count 为写入路径原子维护的缓存列（仅作排序参考）；
 * 前台标签云以查询侧实时统计「未删文章」的绑定数为准，避免软删/恢复场景下缓存口径漂移。
 */
class ContentTagService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function adminList(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = ContentTag::query();

        if (! empty($filters['keyword'])) {
            $keyword = trim((string) $filters['keyword']);
            $query->where('name', 'like', "%{$keyword}%");
        }

        return $query
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function create(array $data): ContentTag
    {
        $name = $this->normalizeName((string) $data['name']);
        $this->assertNameAvailable($name);

        return ContentTag::query()->create([
            'name' => $name,
            'sort_order' => max((int) ($data['sort_order'] ?? 0), 0),
        ]);
    }

    public function update(ContentTag $tag, array $data): ContentTag
    {
        $name = $this->normalizeName((string) ($data['name'] ?? $tag->name));
        $this->assertNameAvailable($name, ignoreId: (int) $tag->id);

        $tag->update([
            'name' => $name,
            'sort_order' => array_key_exists('sort_order', $data)
                ? max((int) $data['sort_order'], 0)
                : $tag->sort_order,
        ]);

        return $tag->refresh();
    }

    /**
     * 删除前解绑校验：仍被文章绑定（含软删文章）时拒绝，提示先解绑。
     */
    public function delete(ContentTag $tag): void
    {
        $boundCount = DB::table('content_article_tags')->where('tag_id', (int) $tag->id)->count();
        throw_if($boundCount > 0, new BusinessException(
            sprintf('该标签仍被 %d 篇文章使用，请先解绑后再删除', $boundCount)
        ));

        $tag->delete();
    }

    /**
     * 文章保存路径的 pivot diff 维护：存在性校验 → diff 出增删集合 → 原子维护 usage_count。
     *
     * @param  array<int, int>|null  $tagIds  null=不改动（字段缺省语义）；[]=清空全部绑定
     */
    public function syncArticleTags(int $articleId, ?array $tagIds): void
    {
        if ($tagIds === null) {
            return;
        }

        $tagIds = array_values(array_unique(array_map(intval(...), $tagIds)));
        $existing = ContentTag::query()->whereIn('id', $tagIds)->pluck('id')->map(intval(...))->all();
        $missing = array_diff($tagIds, $existing);
        throw_if($missing !== [], new BusinessException(
            sprintf('存在无效的标签：ID %s', implode(', ', $missing))
        ));

        $current = DB::table('content_article_tags')
            ->where('article_id', $articleId)
            ->pluck('tag_id')
            ->map(intval(...))
            ->all();

        $toAdd = array_values(array_diff($tagIds, $current));
        $toRemove = array_values(array_diff($current, $tagIds));

        if ($toAdd === [] && $toRemove === []) {
            return;
        }

        DB::transaction(function () use ($articleId, $toAdd, $toRemove): void {
            if ($toAdd !== []) {
                $rows = array_map(fn (int $tagId): array => [
                    'article_id' => $articleId,
                    'tag_id' => $tagId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ], $toAdd);
                DB::table('content_article_tags')->insert($rows);
                // 每个新增标签各自 +1（whereIn+批量 increment 会把总数套到每一行）
                foreach ($toAdd as $tagId) {
                    ContentTag::query()->whereKey($tagId)->increment('usage_count');
                }
            }

            if ($toRemove !== []) {
                DB::table('content_article_tags')
                    ->where('article_id', $articleId)
                    ->whereIn('tag_id', $toRemove)
                    ->delete();
                // 每个移除标签各自 -1，与新增口径对称
                foreach ($toRemove as $tagId) {
                    ContentTag::query()->whereKey($tagId)->decrement('usage_count');
                }
            }
        });
    }

    /**
     * 前台标签云：实时统计未删文章的绑定数，按 count 倒序。
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function clientTagCloud(int $limit = 50): Collection
    {
        $rows = DB::table('content_tags')
            ->join('content_article_tags', 'content_article_tags.tag_id', '=', 'content_tags.id')
            ->join('content_articles', function ($join): void {
                $join->on('content_article_tags.article_id', '=', 'content_articles.id')
                    ->whereNull('content_articles.deleted_at');
            })
            ->groupBy('content_tags.id', 'content_tags.name', 'content_tags.sort_order')
            ->orderByRaw('COUNT(*) DESC')
            ->orderBy('content_tags.sort_order')
            ->orderBy('content_tags.id')
            ->limit($limit)
            ->get([
                'content_tags.id',
                'content_tags.name',
                'content_tags.sort_order',
                DB::raw('COUNT(*) AS usage_count'),
            ])
            ->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'sort_order' => (int) $row->sort_order,
                'usage_count' => (int) $row->usage_count,
            ]);

        /** @var Collection<int, array<string, mixed>> $rows */

        return $rows;
    }

    /**
     * 文章绑定的标签列表（详情/展示用）。
     *
     * @return Collection<int, ContentTag>
     */
    public function tagsOfArticle(int $articleId): Collection
    {
        return ContentTag::query()
            ->whereHas('articles', fn (Builder $builder) => $builder->where('content_articles.id', $articleId))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * 标签名归一：trim + 全半角统一（全角字母数字空格转半角），防同义重复。
     */
    private function normalizeName(string $name): string
    {
        $name = trim($name);
        throw_if($name === '', new BusinessException('标签名不能为空'));

        $normalized = mb_convert_kana($name, 'as', 'UTF-8');
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }

    private function assertNameAvailable(string $name, ?int $ignoreId = null): void
    {
        $taken = ContentTag::query()
            ->when($ignoreId, fn (Builder $query) => $query->where('id', '!=', $ignoreId))
            ->where('name', $name)
            ->exists();

        throw_if($taken, new BusinessException('标签名已存在'));
    }
}
