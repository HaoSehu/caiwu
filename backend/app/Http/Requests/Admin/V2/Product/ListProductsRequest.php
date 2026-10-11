<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\Product;

use App\Constants\ProductType;
use App\Http\Requests\Admin\V2\Common\AdminFormRequest;
use Illuminate\Validation\Rule;

class ListProductsRequest extends AdminFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $nullableFilters = [
            'keyword',
            'status',
            'lifecycle_status',
            'product_type',
            'type',
            'first_product_group_code',
            'first_product_group_id',
            'second_product_group_id',
            'third_product_group_id',
            'sort_by',
            'sort_order',
        ];

        $normalized = [];

        foreach ($nullableFilters as $field) {
            if (! $this->query->has($field)) {
                continue;
            }

            $value = $this->query($field);

            if (is_string($value)) {
                $value = trim($value);
            }

            $normalized[$field] = $value === '' ? null : $value;
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    public function rules(): array
    {
        return [
            'keyword' => ['sometimes', 'nullable', 'string', 'max:120'],
            'status' => ['sometimes', 'nullable', 'integer', Rule::in([0, 1])],
            'lifecycle_status' => ['sometimes', 'nullable', 'string', Rule::in(['active', 'deleted', 'all'])],
            'product_type' => ['sometimes', 'nullable', 'string', Rule::in(ProductType::businessAllowedValues())],
            'type' => ['sometimes', 'nullable', 'string', Rule::in(ProductType::businessAllowedValues())],
            'first_product_group_code' => ['sometimes', 'nullable', 'string', 'max:50'],
            'first_product_group_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'second_product_group_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'third_product_group_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'page_size' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort_by' => ['sometimes', 'nullable', 'string', Rule::in(['price', 'stock', 'services_count'])],
            'sort_order' => ['sometimes', 'nullable', 'string', Rule::in(['asc', 'desc'])],
            'pageSize' => ['prohibited'],
            'per_page' => ['prohibited'],
        ];
    }
}
