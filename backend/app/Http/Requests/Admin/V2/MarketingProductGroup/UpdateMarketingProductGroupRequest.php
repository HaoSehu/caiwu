<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\MarketingProductGroup;

use App\Models\MarketingProductGroup;

class UpdateMarketingProductGroupRequest extends StoreMarketingProductGroupRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'marketingProductGroup' => ['required', 'integer', 'min:1'],
        ]);
    }

    protected function prepareForValidation(): void
    {
        $group = $this->route('marketingProductGroup');

        $this->merge([
            'marketingProductGroup' => $group instanceof MarketingProductGroup ? $group->getKey() : $group,
        ]);
    }
}
