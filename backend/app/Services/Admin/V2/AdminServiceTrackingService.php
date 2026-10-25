<?php

declare(strict_types=1);

namespace App\Services\Admin\V2;

use App\Exceptions\BusinessException;
use App\Models\ServiceTrackingRecord;
use App\Models\User;
use App\Services\ClientServiceConsole\ServiceDetailService;
use App\Services\Content\MediaFileService;
use App\Services\System\OperationLogService;
use App\Support\TextSanitizer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * 管理端服务实例「历史跟踪」备注。
 *
 * 仅面向管理端：按实例维度记录跟进留言与截图，操作人由当前登录管理员写入，
 * 落库后同时写一条服务操作日志，供「操作日志」面板统一回溯。
 */
class AdminServiceTrackingService
{
    private const MAX_IMAGE_COUNT = 9;

    public function __construct(
        private readonly ServiceDetailService $detailService,
        private readonly OperationLogService $operationLogService,
        private readonly MediaFileService $mediaFileService,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(User $user, int $serviceId, array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $this->detailService->findUserService($user, $serviceId);

        $query = ServiceTrackingRecord::query()->where('service_id', $serviceId);

        $keyword = trim((string) ($filters['keyword'] ?? ''));
        if ($keyword !== '') {
            $query->where(function ($builder) use ($keyword): void {
                $builder->where('content', 'like', '%'.$keyword.'%')
                    ->orWhere('operator_name', 'like', '%'.$keyword.'%');
            });
        }

        $startDate = trim((string) ($filters['start_date'] ?? ''));
        if ($startDate !== '') {
            $query->where('tracked_at', '>=', $startDate.' 00:00:00');
        }

        $endDate = trim((string) ($filters['end_date'] ?? ''));
        if ($endDate !== '') {
            $query->where('tracked_at', '<=', $endDate.' 23:59:59');
        }

        return $query->orderByDesc('tracked_at')->orderByDesc('id')->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $context
     */
    public function store(User $user, int $serviceId, array $payload, array $context = []): ServiceTrackingRecord
    {
        $service = $this->detailService->findUserService($user, $serviceId);

        $images = $this->normalizeImages((array) ($payload['images'] ?? []));

        $record = DB::transaction(function () use ($service, $payload, $images, $context): ServiceTrackingRecord {
            $record = ServiceTrackingRecord::query()->create([
                'service_id' => (int) $service->id,
                'user_id' => (int) $service->user_id,
                'tracked_at' => (string) $payload['tracked_at'],
                'content' => TextSanitizer::clean((string) $payload['content'], true),
                'images' => $images,
                'operator_id' => (int) ($context['operator_id'] ?? 0),
                'operator_name' => (string) ($context['operator_name'] ?? ''),
            ]);

            $this->operationLogService->writeServiceConsoleLog($service, 'service.console.tracking.create', [
                'category' => 'service',
                'summary' => '新增历史跟踪记录',
                'tracked_at' => $this->trackedAtForLog($record),
                'content' => $this->truncateForLog((string) $record->content),
                'image_count' => count($images),
            ], $context);

            return $record;
        });

        return $record->refresh();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function delete(User $user, int $serviceId, int $recordId, array $context = []): void
    {
        $service = $this->detailService->findUserService($user, $serviceId);

        $record = ServiceTrackingRecord::query()
            ->where('service_id', $service->id)
            ->find($recordId);

        throw_if(! $record, new BusinessException('跟踪记录不存在', 40400, 404));

        DB::transaction(function () use ($service, $record, $context): void {
            $this->operationLogService->writeServiceConsoleLog($service, 'service.console.tracking.delete', [
                'category' => 'service',
                'summary' => '删除历史跟踪记录',
                'tracked_at' => $this->trackedAtForLog($record),
                'content' => $this->truncateForLog((string) $record->content),
            ], $context);

            $record->delete();
        });
    }

    /**
     * 上传历史跟踪截图，复用媒体库落盘与图片校验。
     *
     * @return array{id: int, url: ?string, filename: string, width: ?int, height: ?int}
     */
    public function uploadImage(int $adminId, User $user, int $serviceId, UploadedFile $file): array
    {
        $this->detailService->findUserService($user, $serviceId);

        $media = $this->mediaFileService->upload($file, $adminId, 'service-tracking');

        return [
            'id' => (int) $media->id,
            'url' => $media->url,
            'filename' => (string) $media->filename,
            'width' => $media->width !== null ? (int) $media->width : null,
            'height' => $media->height !== null ? (int) $media->height : null,
        ];
    }

    /**
     * @param  array<int, mixed>  $images
     * @return array<int, array{url: string, name: string}>
     */
    private function normalizeImages(array $images): array
    {
        $normalized = [];
        foreach ($images as $image) {
            if (! is_array($image)) {
                continue;
            }
            $url = trim((string) ($image['url'] ?? ''));
            if ($url === '') {
                continue;
            }
            $normalized[] = [
                'url' => $url,
                'name' => TextSanitizer::clean((string) ($image['name'] ?? '')),
            ];
        }

        if (count($normalized) > self::MAX_IMAGE_COUNT) {
            throw new BusinessException('最多上传 '.self::MAX_IMAGE_COUNT.' 张图片');
        }

        return $normalized;
    }

    private function trackedAtForLog(ServiceTrackingRecord $record): ?string
    {
        $value = $record->getAttribute('tracked_at');

        return $value instanceof \DateTimeInterface ? $value->format('Y-m-d H:i:s') : null;
    }

    private function truncateForLog(string $content): string
    {
        return TextSanitizer::truncateWithEllipsis(TextSanitizer::clean($content, true), 200);
    }
}
