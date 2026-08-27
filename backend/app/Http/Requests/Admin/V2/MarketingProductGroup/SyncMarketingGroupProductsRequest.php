<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\MarketingProductGroup;

use App\Http\Requests\Admin\V2\Common\AdminFormRequest;
use App\Models\MarketingProductGroup;

class SyncMarketingGroupProductsRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'marketingProductGroup' => ['required', 'integer', 'min:1'],
            'product_ids' => ['present', 'array'],
            'product_ids.*' => ['integer', 'min:1'],
            'pageSize' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $group = $this->route('marketingProductGroup');

        $this->merge([
            'marketingProductGroup' => $group instanceof MarketingProductGroup ? $group->getKey() : $group,
        ]);
    }

    /**
     * @return array<int, int>
     */
    public function productIds(): array
    {
        /** @var array<int, mixed> $ids */
        $ids = (array) ($this->validated()['product_ids'] ?? []);

        return collect($ids)->map(fn ($id) => (int) $id)->values()->all();
    }
}
