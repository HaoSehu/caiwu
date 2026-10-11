<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\V2;

use App\Models\MassSend;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminMassSendDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var MassSend $massSend */
        $massSend = $this->resource;

        return AdminMassSendListResource::make($massSend)->toArray($request) + [
            'subject' => $massSend->subject,
            'content' => $massSend->content,
            'filter_json' => $massSend->filter_json,
            'updated_at' => $massSend->updated_at?->toDateTimeString(),
        ];
    }
}
