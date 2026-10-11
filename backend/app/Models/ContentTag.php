<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ContentTag extends Model
{
    protected $fillable = [
        'name',
        'usage_count',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'usage_count' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(
            ContentArticle::class,
            'content_article_tags',
            'tag_id',
            'article_id',
        );
    }
}
