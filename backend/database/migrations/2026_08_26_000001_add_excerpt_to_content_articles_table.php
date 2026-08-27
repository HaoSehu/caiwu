<?php

declare(strict_types=1);

use App\Support\ContentExcerpt;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_articles', function (Blueprint $table): void {
            $table->string('excerpt', 300)->nullable()->after('summary')->comment('摘要缓存：summary 为空时由正文预渲染固化');
        });

        // 回填历史行：仅 summary 为空的记录需要摘要，与写入侧规则保持一致
        DB::table('content_articles')
            ->where(function ($query): void {
                $query->whereNull('summary')->orWhere('summary', '');
            })
            ->orderBy('id')
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('content_articles')
                        ->where('id', $row->id)
                        ->update(['excerpt' => ContentExcerpt::fromMarkdown((string) $row->content)]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('content_articles', function (Blueprint $table): void {
            $table->dropColumn('excerpt');
        });
    }
};
