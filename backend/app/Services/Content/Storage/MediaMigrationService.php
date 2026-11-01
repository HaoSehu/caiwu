<?php

declare(strict_types=1);

namespace App\Services\Content\Storage;

use App\Exceptions\BusinessException;
use App\Jobs\MigrateMediaFilesJob;
use App\Models\MediaFile;
use App\Services\Content\MediaFileService;
use App\Support\LikeEscape;
use App\Support\UploadUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * 媒体库本地/远程双向迁移编排。
 *
 * 迁移是平台侧能力：插件只提供 storage.put/get/delete 原子动作，
 * 本服务负责选行、逐文件执行、引用替换、进度与失败归集。
 * 进度写缓存（media:migration:{id}，TTL 24h），不新建业务表。
 */
class MediaMigrationService
{
    private const CACHE_PREFIX = 'media:migration:';

    private const CACHE_TTL_SECONDS = 86400;

    private const MAX_FAILURES_RECORDED = 100;

    private const STAGE_PATTERN = '/^\[([a-z_]+)\]\s*(.+)$/s';

    public function __construct(
        private readonly MediaStorageResolver $storageResolver,
        private readonly RemoteMediaStorage $remoteStorage,
        private readonly MediaFileService $mediaFiles,
        private readonly MediaReferenceRewriter $references,
    ) {}

    /**
     * 创建迁移批次并派发队列 Job。
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function start(array $input, int $adminId): array
    {
        $direction = (string) ($input['direction'] ?? '');
        $driver = trim((string) ($input['driver'] ?? ''));
        $scope = (string) ($input['scope'] ?? '');
        $deleteSource = (bool) ($input['delete_source'] ?? false);

        if ($direction === 'to_remote') {
            if ($driver === '') {
                throw new BusinessException('迁移到远程必须指定目标存储驱动', 42200);
            }

            $this->storageResolver->assertDriverAvailable($driver);
        } elseif ($direction !== 'to_local') {
            throw new BusinessException('迁移方向不合法', 42200);
        }

        $ids = $this->selectRowIds($direction, $scope, $input);
        if ($ids === []) {
            throw new BusinessException('没有满足迁移条件的文件', 42200);
        }

        $batchId = 'mig_'.now()->format('YmdHis').'_'.strtolower(Str::random(6));

        $this->saveProgress($batchId, $this->initialProgress($batchId, $direction, $driver, count($ids), $deleteSource, $adminId));

        MigrateMediaFilesJob::dispatch($batchId, $direction, $driver !== '' ? $driver : null, $ids, $deleteSource, $adminId);

        return $this->progress($batchId);
    }

    /**
     * @return array<string, mixed>
     */
    public function progress(string $batchId): array
    {
        $state = Cache::get(self::CACHE_PREFIX.trim($batchId));

        if (! is_array($state)) {
            throw new BusinessException('迁移批次不存在或已过期', 42200);
        }

        return $state;
    }

    /**
     * Job 执行体：逐文件迁移，单文件失败不回滚已完成项。
     *
     * @param  array<int, int>  $ids
     */
    public function run(string $batchId, string $direction, ?string $driver, array $ids, bool $deleteSource): void
    {
        $state = Cache::get(self::CACHE_PREFIX.$batchId);
        if (! is_array($state)) {
            // 缓存被淘汰：按传入清单重建最小进度，保证批次可执行、可轮询。
            $state = $this->initialProgress($batchId, $direction, $driver ?? '', count($ids), $deleteSource, 0);
            $state['status'] = 'running';
            $this->saveProgress($batchId, $state);
        }

        foreach ($ids as $mediaFileId) {
            try {
                $row = MediaFile::query()->find((int) $mediaFileId);

                if ($row === null) {
                    $this->recordOutcome($batchId, 'skipped', (int) $mediaFileId, '文件记录不存在');

                    continue;
                }

                // 目标存储区与源相同：跳过而非重传/误报失败（与 selectRowIds 的 ids 不过滤口径配套，
                // 保持前端勾选清单与结果反馈的一致性）。
                if ($this->isAlreadyAtTarget($row, $direction, (string) ($driver ?? ''))) {
                    $this->recordOutcome($batchId, 'skipped', (int) $mediaFileId, '文件已在目标存储区');

                    continue;
                }

                if ($direction === 'to_remote') {
                    $this->migrateToRemote($row, (string) $driver, $deleteSource);
                } else {
                    $this->migrateToLocal($row, $deleteSource);
                }

                $this->recordOutcome($batchId, 'done', (int) $mediaFileId);
            } catch (\Throwable $exception) {
                $this->recordOutcome($batchId, 'failed', (int) $mediaFileId, $exception->getMessage());
            }
        }

        $state = Cache::get(self::CACHE_PREFIX.$batchId);
        if (is_array($state)) {
            $state['status'] = 'completed';
            $state['finished_at'] = now()->toDateTimeString();
            $this->saveProgress($batchId, $state);
        }
    }

    /**
     * Job 级失败兜底（超时、进程被杀等未进入正常收尾的路径）。
     */
    public function markFailed(string $batchId, string $reason): void
    {
        $state = Cache::get(self::CACHE_PREFIX.trim($batchId));
        if (! is_array($state)) {
            return;
        }

        $state['status'] = 'failed';
        $state['finished_at'] = now()->toDateTimeString();
        $state['error'] = $reason;
        $this->saveProgress($batchId, $state);
    }

    /**
     * 本地 -> 远程：上传 -> 改库 -> 引用替换 -> （可选）删本地源文件。
     */
    private function migrateToRemote(MediaFile $row, string $driver, bool $deleteSource): void
    {
        $localPath = $this->mediaFiles->resolvePublicUploadPath((string) $row->path);

        if ($localPath === null || ! is_file($localPath)) {
            $this->fail('locate', '本地文件不存在或路径不合法');
        }

        $objectKey = ltrim((string) $row->path, '/');

        try {
            $result = $this->remoteStorage->put($driver, $localPath, $objectKey, (string) $row->mime_type, (int) $row->size);
        } catch (BusinessException $exception) {
            $this->fail('upload', $exception->getMessage());
        }

        $oldPath = (string) $row->path;
        $oldUrl = (string) $row->url;
        $newUrl = $result['url'];

        $row->update([
            'disk' => $driver,
            'object_key' => $result['object_key'],
            'path' => $newUrl,
            'url' => $newUrl,
        ]);

        $this->replaceReferences($this->buildPathMap($oldPath, $oldUrl, $newUrl));

        if ($deleteSource) {
            // localPath 已通过 resolvePublicUploadPath 的前缀与 realpath 校验。
            if (! @unlink($localPath)) {
                $this->fail('cleanup', '本地源文件删除失败：'.$oldPath);
            }
        }
    }

    /**
     * 远程 -> 本地：下载 -> 落盘 -> 改库 -> 引用替换 -> （可选）删远程对象。
     */
    private function migrateToLocal(MediaFile $row, bool $deleteSource): void
    {
        $disk = trim((string) ($row->disk ?? ''));
        $objectKey = trim((string) ($row->object_key ?? ''));

        if ($disk === '' || $disk === MediaStorageResolver::LOCAL_DRIVER || $objectKey === '') {
            $this->fail('locate', '文件不在远程存储或缺少对象键');
        }

        try {
            $download = $this->remoteStorage->get($disk, $objectKey);
        } catch (BusinessException $exception) {
            $this->fail('download', $exception->getMessage());
        }

        $extension = strtolower((string) pathinfo($objectKey, PATHINFO_EXTENSION));
        if ($extension === '') {
            $extension = strtolower((string) pathinfo((string) $row->filename, PATHINFO_EXTENSION));
        }
        if ($extension === '') {
            $extension = 'bin';
        }

        // 文件名规则与 MediaFileService::upload() 的 sprintf 保持同形。
        $prefix = str_starts_with((string) $row->mime_type, 'video/') ? 'vid' : 'img';
        $filename = sprintf('%s_%s_%s.%s', $prefix, now()->format('His'), Str::random(8), $extension);

        $directory = public_path(MediaFileService::MEDIA_ROOT);
        File::ensureDirectoryExists($directory);

        $this->mediaFiles->guardDiskSpace($directory, $download['size']);

        if (! @rename($download['temp_path'], $directory.DIRECTORY_SEPARATOR.$filename)) {
            @unlink($download['temp_path']);
            $this->fail('store', '本地文件写入失败：'.$filename);
        }

        $relativePath = MediaFileService::relativePath($filename);
        $newUrl = (string) UploadUrl::resolve($relativePath);
        $oldPath = (string) $row->path;
        $oldUrl = (string) $row->url;

        $row->update([
            'disk' => MediaStorageResolver::LOCAL_DRIVER,
            'object_key' => null,
            'path' => $relativePath,
            'url' => $newUrl,
        ]);

        $this->replaceReferences($this->buildPathMap($oldPath, $oldUrl, $newUrl));

        if ($deleteSource) {
            try {
                $this->remoteStorage->delete($disk, $objectKey);
            } catch (BusinessException $exception) {
                $this->fail('cleanup', $exception->getMessage());
            }
        }
    }

    /**
     * 按迁移方向与范围选取待迁移的媒体文件 id。
     *
     * @param  array<string, mixed>  $input
     * @return array<int, int>
     */
    private function selectRowIds(string $direction, string $scope, array $input): array
    {
        $query = MediaFile::query()->orderBy('id');

        if ($scope === 'ids') {
            $rawIds = is_array($input['ids'] ?? null) ? $input['ids'] : [];
            $ids = array_values(array_map('intval', array_filter($rawIds, 'is_numeric')));
            if ($ids === []) {
                throw new BusinessException('迁移范围 ids 不能为空', 42200);
            }

            // ids 范围不做 disk 过滤：已处于目标形态的行在执行期按 skipped 归集，
            // 保持与前端勾选清单的一致性。
            $query->whereIn('id', $ids);
        } elseif ($scope === 'all') {
            $this->applyDirectionFilter($query, $direction);
        } elseif ($scope === 'filter') {
            $this->applyDirectionFilter($query, $direction);

            $group = trim((string) ($input['filter']['group'] ?? ''));
            if ($group !== '') {
                $query->where('group', $group);
            }

            $keyword = trim((string) ($input['filter']['keyword'] ?? ''));
            if ($keyword !== '') {
                $query->where('filename', 'like', LikeEscape::contains($keyword));
            }

            $type = strtolower(trim((string) ($input['filter']['type'] ?? '')));
            if ($type === 'image' || $type === 'video') {
                $query->where('mime_type', 'like', $type.'/%');
            }
        } else {
            throw new BusinessException('迁移范围不合法', 42200);
        }

        return $query->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    private function applyDirectionFilter(Builder $query, string $direction): void
    {
        if ($direction === 'to_remote') {
            $query->where('disk', MediaStorageResolver::LOCAL_DRIVER);

            return;
        }

        $query->where('disk', '!=', MediaStorageResolver::LOCAL_DRIVER)
            ->whereNotNull('object_key')
            ->where('object_key', '!=', '');
    }

    /**
     * 引用替换：委托 MediaReferenceRewriter（长键优先、命中即停，未被替换的值原样保留）。
     */
    private function replaceReferences(array $pathMap): void
    {
        $this->references->replaceReferences($pathMap);
    }

    /**
     * 引用替换的键表：委托 MediaReferenceRewriter（完整 URL 长键排在相对路径短键之前）。
     *
     * @return array<string, string>
     */
    private function buildPathMap(string $oldPath, string $oldUrl, string $newUrl): array
    {
        return $this->references->buildPathMap($oldPath, $oldUrl, $newUrl);
    }

    private function recordOutcome(string $batchId, string $outcome, int $mediaFileId, ?string $message = null): void
    {
        $state = Cache::get(self::CACHE_PREFIX.$batchId);
        if (! is_array($state)) {
            return;
        }

        if ($outcome === 'done') {
            $state['done'] = (int) ($state['done'] ?? 0) + 1;
        } elseif ($outcome === 'skipped') {
            $state['skipped'] = (int) ($state['skipped'] ?? 0) + 1;
        } elseif ($outcome === 'failed') {
            $state['failed'] = (int) ($state['failed'] ?? 0) + 1;
        }

        if ($outcome === 'failed' && is_string($message) && $message !== '') {
            $failures = is_array($state['failures'] ?? null) ? $state['failures'] : [];
            if (count($failures) < self::MAX_FAILURES_RECORDED) {
                [$stage, $text] = $this->splitStage($message);
                $failures[] = [
                    'media_file_id' => $mediaFileId,
                    'stage' => $stage,
                    'message' => $text,
                ];
                $state['failures'] = $failures;
            }
        }

        $this->saveProgress($batchId, $state);
    }

    /**
     * @return array<string, mixed>
     */
    private function initialProgress(string $batchId, string $direction, string $driver, int $total, bool $deleteSource, int $adminId): array
    {
        return [
            'id' => $batchId,
            'direction' => $direction,
            'driver' => $driver,
            'delete_source' => $deleteSource,
            'status' => 'running',
            'total' => $total,
            'done' => 0,
            'failed' => 0,
            'skipped' => 0,
            'failures' => [],
            'started_by' => $adminId,
            'started_at' => now()->toDateTimeString(),
            'finished_at' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function saveProgress(string $batchId, array $state): void
    {
        Cache::put(self::CACHE_PREFIX.$batchId, $state, now()->addSeconds(self::CACHE_TTL_SECONDS));
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitStage(string $message): array
    {
        if (preg_match(self::STAGE_PATTERN, $message, $matches) === 1) {
            return [$matches[1], $matches[2]];
        }

        return ['execute', $message];
    }

    /**
     * 文件是否已在目标存储形态：to_remote 时 disk 已等于目标驱动、to_local 时 disk 为 local。
     * 此类行按 skipped 归集，不进入迁移执行体（避免同目标重传、本地行误报「文件不在远程存储」）。
     */
    private function isAlreadyAtTarget(MediaFile $row, string $direction, string $driver): bool
    {
        $disk = trim((string) ($row->disk ?? '')) ?: MediaStorageResolver::LOCAL_DRIVER;

        if ($direction === 'to_remote') {
            return $disk === $driver;
        }

        return $disk === MediaStorageResolver::LOCAL_DRIVER;
    }

    private function fail(string $stage, string $message): never
    {
        throw new RuntimeException("[{$stage}] {$message}");
    }
}
