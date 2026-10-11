<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\V2;

use App\Models\TicketDepartment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TicketDepartment */
class AdminTicketDepartmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var TicketDepartment $department */
        $department = $this->resource;

        return [
            'id' => (int) $department->id,
            'name' => (string) $department->name,
            'description' => (string) ($department->description ?? ''),
            'auto_assign_mode' => (int) $department->auto_assign_mode,
            'auto_assign_mode_label' => $department->autoAssignModeLabel(),
            'is_enabled' => (int) $department->is_enabled,
            'sort_order' => (int) $department->sort_order,
            'staff_count' => isset($department->staff_count) ? (int) $department->staff_count : null,
            'staff' => $department->relationLoaded('staff')
                ? $department->staff
                    ->map(fn ($staff) => [
                        'admin_user_id' => (int) $staff->admin_user_id,
                        'is_leader' => (int) $staff->is_leader,
                    ])
                    ->values()
                    ->all()
                : null,
            'created_at' => $department->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $department->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
