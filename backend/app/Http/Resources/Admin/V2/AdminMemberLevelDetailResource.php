<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\V2;

use App\Models\MemberLevel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MemberLevel */
class AdminMemberLevelDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'name' => (string) $this->name,
            'status' => (int) $this->status,
            'remark' => $this->remark !== null ? (string) $this->remark : null,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
