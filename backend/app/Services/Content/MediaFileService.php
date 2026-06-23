<?php

namespace App\Services\Content;

use App\Models\MediaFile;
use App\Support\UploadedImage;
use App\Support\UploadUrl;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

class MediaFileService
{
    public const HERO_VIDEO_GROUP = 'hero-videos';

    private const HERO_VIDEO_DIRECTORY = 'uploads/hero-videos';

    private const VIDEO_EXTENSIONS = ['mp4', 'webm', 'ogg', 'mov', 'm4v'];

    public function list(array $filters, int $perPage = 24): LengthAwarePaginator
    {
        $query = MediaFile::query()->orderByDesc('id');

        if (! empty($filters['group'])) {
            $query->where('group', (string) $filters['group']);
        }

        if (! empty($filters['keyword'])) {
            $query->where('filename', 'like', '%'.trim($filters['keyword']).'%');
        }

        return $query->paginate($perPage);
    }

    public function upload(UploadedFile $file, int $adminId, string $group = 'content'): MediaFile
    {
        $group = UploadedImage::group($group);
        $dateSegment = now()->format('Ymd');
        $directory = public_path('uploads/'.$group.'/'.$dateSegment);
        File::ensureDirectoryExists($directory);

        $extension = UploadedImage::extension($file);
        $mimeType = UploadedImage::mimeType($file);
        $filename = sprintf('img_%s_%04d.%s', now()->format('His'), random_int(1000, 9999), $extension);
        $originalName = UploadedImage::originalName($file, $filename);

        $file->move($directory, $filename);

        $relativePath = '/uploads/'.$group.'/'.$dateSegment.'/'.$filename;
        $fullPath = $directory.DIRECTORY_SEPARATOR.$filename;

        $width = null;
        $height = null;
        if (function_exists('getimagesize') && @is_file($fullPath)) {
            $info = @getimagesize($fullPath);
            if ($info) {
                $width = $info[0];
                $height = $info[1];
            }
        }

        return MediaFile::query()->create([
            'filename' => $originalName,
            'path' => $relativePath,
            'url' => UploadUrl::resolve($relativePath),
            'mime_type' => $mimeType,
            'size' => @filesize($fullPath) ?: 0,
            'width' => $width,
            'height' => $height,
            'group' => $group,
            'uploaded_by' => $adminId,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listHeroVideos(): array
    {
        $directory = public_path(self::HERO_VIDEO_DIRECTORY);
        File::ensureDirectoryExists($directory);

        $files = collect(File::files($directory))
            ->filter(function ($file): bool {
                return in_array(strtolower($file->getExtension()), self::VIDEO_EXTENSIONS, true);
            })
            ->sortBy(fn ($file) => strtolower($file->getFilename()), SORT_NATURAL)
            ->values();

        return $files->map(function ($file, int $index): array {
            $relativePath = '/'.self::HERO_VIDEO_DIRECTORY.'/'.$file->getFilename();

            return [
                'id' => 'hero-video-'.($index + 1),
                'filename' => $file->getFilename(),
                'path' => $relativePath,
                'url' => UploadUrl::resolve($relativePath),
                'mime_type' => $this->guessVideoMimeType($file->getExtension()),
                'size' => $file->getSize(),
                'group' => self::HERO_VIDEO_GROUP,
                'created_at' => date('Y-m-d H:i:s', $file->getMTime()),
            ];
        })->all();
    }

    public function delete(MediaFile $mediaFile): void
    {
        $diskPath = $this->resolvePublicUploadPath((string) $mediaFile->path);

        if ($diskPath !== null && @is_file($diskPath)) {
            @unlink($diskPath);
        }

        $mediaFile->delete();
    }

    private function guessVideoMimeType(string $extension): string
    {
        return match (strtolower($extension)) {
            'webm' => 'video/webm',
            'ogg' => 'video/ogg',
            'mov' => 'video/quicktime',
            'm4v' => 'video/x-m4v',
            default => 'video/mp4',
        };
    }

    private function resolvePublicUploadPath(string $path): ?string
    {
        $normalized = ltrim(str_replace('\\', '/', trim($path)), '/');
        $segments = explode('/', $normalized);

        if (! str_starts_with($normalized, 'uploads/') || in_array('..', $segments, true) || in_array('.', $segments, true)) {
            return null;
        }

        $absolutePath = public_path(str_replace('/', DIRECTORY_SEPARATOR, $normalized));
        $uploadsRoot = realpath(public_path('uploads'));
        $realPath = realpath($absolutePath);

        if ($uploadsRoot === false || $realPath === false) {
            return null;
        }

        $uploadsRoot = str_replace('\\', '/', $uploadsRoot);
        $realPath = str_replace('\\', '/', $realPath);

        if ($realPath !== $uploadsRoot && ! str_starts_with($realPath, $uploadsRoot.'/')) {
            return null;
        }

        return $realPath;
    }
}
