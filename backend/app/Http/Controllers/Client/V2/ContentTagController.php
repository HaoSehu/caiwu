<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client\V2;

use App\Http\Controllers\Controller;
use App\Services\Content\ContentV2QueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * M8 前台内容标签云：GET /v2/client/content/tags。
 *
 * 路由由集成阶段挂接（本智能体无 routes 写权限），见
 * docs/_local/parallel-impl-2026-10-11/m8/INTEGRATION.md。
 */
class ContentTagController extends Controller
{
    public function __construct(
        private readonly ContentV2QueryService $contentQueryService,
    ) {}

    public function tags(Request $request): JsonResponse
    {
        $limit = max(1, min((int) $request->integer('limit', 50), 100));

        return $this->success([
            'list' => $this->contentQueryService->clientTags($limit)
                ->map(fn (array $tag): array => [
                    'id' => (int) $tag['id'],
                    'name' => (string) $tag['name'],
                    'usage_count' => (int) $tag['usage_count'],
                ])->values()->all(),
        ]);
    }
}
