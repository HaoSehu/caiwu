<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\V2;

use App\Models\CareTaskRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminCareTaskRunResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var CareTaskRun $run */
        $run = $this->resource;

        return [
            'id' => (int) $run->id,
            'care_plan_id' => (int) $run->care_plan_id,
            'planned_date' => $run->planned_date->toDateString(),
            'status' => (int) $run->status,
            'status_label' => [
                0 => '待执行', 1 => '成功', 2 => '部分失败', 3 => '失败', 4 => '跳过',
            ][(int) $run->status] ?? (string) $run->status,
            'total_count' => (int) $run->total_count,
            'sent_count' => (int) $run->sent_count,
            'failed_count' => (int) $run->failed_count,
            'skipped_count' => (int) $run->skipped_count,
            'started_at' => $run->started_at?->toDateTimeString(),
            'finished_at' => $run->finished_at?->toDateTimeString(),
        ];
    }
}
