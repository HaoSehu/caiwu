<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\V2;

use App\Models\ContentArticle;
use App\Models\ContentTag;
use Illuminate\Http\Request;

class AdminContentArticleDetailResource extends AdminContentArticleListResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ContentArticle $article */
        $article = $this->resource;

        return array_merge(parent::toArray($request), [
            'content' => (string) $article->content,
            'keywords' => $article->keywords,
            'remark' => $article->remark,
            // M8：已选标签（show/store/update 均预载 tags:id,name；未预载时返回空数组不触发查询）
            'tags' => $article->relationLoaded('tags')
                ? $article->tags->map(fn (ContentTag $tag): array => [
                    'id' => (int) $tag->id,
                    'name' => (string) $tag->name,
                ])->values()->all()
                : [],
        ]);
    }
}
