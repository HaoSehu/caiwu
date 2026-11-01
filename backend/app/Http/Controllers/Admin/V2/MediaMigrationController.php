<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V2;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\V2\MediaMigration\CreateMediaMigrationRequest;
use App\Services\Content\Storage\MediaMigrationService;
use Illuminate\Http\JsonResponse;

class MediaMigrationController extends Controller
{
    public function __construct(
        private readonly MediaMigrationService $migrations,
    ) {}

    public function store(CreateMediaMigrationRequest $request): JsonResponse
    {
        $result = $this->migrations->start($request->validated(), (int) $request->user()->id);

        return $this->success($result, '迁移任务已创建');
    }

    public function show(string $batchId): JsonResponse
    {
        return $this->success($this->migrations->progress(trim($batchId)));
    }
}
