<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V2;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\V2\Log\ListLogArchivesRequest;
use App\Http\Requests\Admin\V2\Log\SearchLogArchivesRequest;
use App\Http\Resources\Admin\V2\AdminLogArchiveResource;
use App\Services\System\LogArchiveV2Service;
use Illuminate\Http\JsonResponse;

/**
 * 管理端只读冷检索：V2 归档批次清单与归档 CSV 时间窗口检索。
 * 不提供归档文件下载或物理路径读取。
 */
class LogArchiveController extends Controller
{
    public function __construct(
        private readonly LogArchiveV2Service $archiveService,
    ) {}

    public function index(ListLogArchivesRequest $request): JsonResponse
    {
        $result = $this->archiveService->list([
            'table' => $request->input('table'),
            'status' => $request->input('status'),
            'batch_id' => $request->input('batch_id'),
            'page' => $request->pageNumber(),
            'page_size' => $request->perPage(),
        ]);

        return $this->success([
            'list' => AdminLogArchiveResource::collection((array) ($result['items'] ?? []))->resolve(),
            'total' => (int) ($result['total'] ?? 0),
            'page' => (int) ($result['page'] ?? $request->pageNumber()),
            'page_size' => (int) ($result['page_size'] ?? $request->perPage()),
        ]);
    }

    public function search(SearchLogArchivesRequest $request): JsonResponse
    {
        $result = $this->archiveService->search([
            'table' => (string) $request->input('table'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'limit' => 500,
        ], $request->pageNumber(), $request->perPage());

        return $this->success([
            'list' => AdminLogArchiveResource::collection((array) ($result['items'] ?? []))->resolve(),
            'total' => (int) ($result['total'] ?? 0),
            'page' => (int) ($result['page'] ?? 1),
            'page_size' => (int) ($result['page_size'] ?? 0),
            'incomplete' => (bool) ($result['incomplete'] ?? false),
            'unavailable_archives' => array_values(array_map(
                static fn (array $archive): array => [
                    'batch_id' => (string) ($archive['batch_id'] ?? ''),
                    'table' => (string) ($archive['table'] ?? ''),
                    'status' => (string) ($archive['status'] ?? ''),
                    'file' => isset($archive['file']) && $archive['file'] !== null ? basename((string) $archive['file']) : null,
                    'id_min' => $archive['id_min'] ?? null,
                    'id_max' => $archive['id_max'] ?? null,
                    'expected_rows' => (int) ($archive['expected_rows'] ?? 0),
                    'restorable' => false,
                    'reason' => (string) ($archive['reason'] ?? '归档来源不可用。'),
                ],
                array_filter((array) ($result['unavailable_archives'] ?? []), 'is_array'),
            )),
            'unavailable_archives_truncated' => (int) ($result['unavailable_archives_truncated'] ?? 0),
        ]);
    }
}
