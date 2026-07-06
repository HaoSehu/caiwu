<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\V2;

use App\Support\SensitiveDataSanitizer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminLogDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $row = is_array($this->resource) ? $this->resource : [];
        $fields = is_array($row['fields'] ?? null) ? $row['fields'] : [];

        return $this->dropSensitiveKeys([
            'id' => (string) ($row['id'] ?? $fields['id'] ?? ''),
            'channel' => (string) ($row['channel'] ?? ''),
            'source' => (string) ($row['source'] ?? $fields['source'] ?? ''),
            'fields' => SensitiveDataSanitizer::sanitize($fields),
            'message' => SensitiveDataSanitizer::sanitizeText((string) ($row['message'] ?? '')),
            'context' => SensitiveDataSanitizer::sanitize($row['context'] ?? []),
            'created_at' => $row['created_at'] ?? $fields['created_at'] ?? null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function dropSensitiveKeys(array $payload): array
    {
        $result = [];

        foreach ($payload as $key => $value) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                continue;
            }

            $result[$key] = is_array($value) ? $this->dropSensitiveKeys($value) : $value;
        }

        return $result;
    }

    private function isSensitiveKey(string $key): bool
    {
        $key = strtolower($key);

        foreach (['password', 'secret', 'api_key', 'raw_response', 'third_party_response'] as $needle) {
            if (str_contains($key, $needle)) {
                return true;
            }
        }

        return false;
    }
}
