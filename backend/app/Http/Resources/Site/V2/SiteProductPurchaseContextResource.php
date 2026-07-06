<?php

declare(strict_types=1);

namespace App\Http\Resources\Site\V2;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SiteProductPurchaseContextResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payload = is_array($this->resource) ? $this->resource : [];
        $rootGroups = $payload['root_groups'] ?? null;

        return [
            'types' => array_values((array) ($payload['types'] ?? [])),
            'root_groups' => $rootGroups instanceof LengthAwarePaginator
                ? $this->rootGroupItems($rootGroups, $request)
                : [],
            'root_groups_total' => $rootGroups instanceof LengthAwarePaginator ? $rootGroups->total() : 0,
            'root_groups_page' => $rootGroups instanceof LengthAwarePaginator ? $rootGroups->currentPage() : 1,
            'root_groups_page_size' => $rootGroups instanceof LengthAwarePaginator ? $rootGroups->perPage() : 0,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rootGroupItems(LengthAwarePaginator $paginator, Request $request): array
    {
        return collect($paginator->items())
            ->map(fn (mixed $group): array => (new SiteProductGroupResource($group))->resolve($request))
            ->values()
            ->all();
    }
}
