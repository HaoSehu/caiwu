<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\V2;

use App\Models\ServiceTrackingRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ServiceTrackingRecord
 */
class AdminUserServiceTrackingRecordResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ServiceTrackingRecord $record */
        $record = $this->resource;

        return [
            'id' => (int) $record->id,
            'service_id' => (int) $record->service_id,
            'tracked_at' => $this->dateTime($record->getAttribute('tracked_at')),
            'content' => (string) $record->content,
            'images' => $this->images($record),
            'operator_id' => (int) $record->operator_id,
            'operator_name' => (string) $record->operator_name,
            'created_at' => $record->created_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @return array<int, array{url: string, name: string}>
     */
    private function images(ServiceTrackingRecord $record): array
    {
        $images = $record->getAttribute('images');
        if (! is_array($images)) {
            return [];
        }

        $result = [];
        foreach ($images as $image) {
            if (! is_array($image)) {
                continue;
            }
            $url = trim((string) ($image['url'] ?? ''));
            if ($url === '') {
                continue;
            }
            $result[] = [
                'url' => $url,
                'name' => (string) ($image['name'] ?? ''),
            ];
        }

        return $result;
    }

    private function dateTime(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return null;
    }
}
