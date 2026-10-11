<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\V2;

use App\Http\Resources\Concerns\StripsSensitiveResourceData;
use App\Support\SensitiveDataSanitizer;
use App\Support\TextSanitizer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminQueueJobResource extends JsonResource
{
    use StripsSensitiveResourceData;

    private const TEXT_LIMIT = 300;

    private const EXCEPTION_PREVIEW_LIMIT = 500;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $job = is_array($this->resource) ? $this->resource : [];

        return [
            'id' => (int) ($job['id'] ?? 0),
            'kind' => $this->compactScalar($job['kind'] ?? 'pending'),
            'queue' => $this->compactScalar($job['queue'] ?? ''),
            'display_name' => $this->compactScalar($job['display_name'] ?? ''),
            'state' => $this->compactScalar($job['state'] ?? null),
            'attempts' => (int) ($job['attempts'] ?? 0),
            'max_tries' => $job['max_tries'] ?? null,
            'uuid' => $this->compactScalar($job['uuid'] ?? ''),
            'available_at' => $this->compactScalar($job['available_at'] ?? null),
            'reserved_at' => $this->compactScalar($job['reserved_at'] ?? null),
            'created_at' => $this->compactScalar($job['created_at'] ?? null),
            'failed_at' => $this->compactScalar($job['failed_at'] ?? null),
            'exception' => $this->exceptionPreview($job['exception'] ?? null),
        ];
    }

    private function compactScalar(mixed $value): mixed
    {
        if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        $text = SensitiveDataSanitizer::sanitizeText((string) $value);
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);

        return TextSanitizer::truncateWithEllipsis($text, self::TEXT_LIMIT);
    }

    /**
     * 失败任务异常堆栈只下发单行预览，完整堆栈留在 failed_jobs 表内排查。
     */
    private function exceptionPreview(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $text = SensitiveDataSanitizer::sanitizeText((string) $value);
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        if ($text === '') {
            return null;
        }

        return TextSanitizer::truncateWithEllipsis($text, self::EXCEPTION_PREVIEW_LIMIT);
    }
}
