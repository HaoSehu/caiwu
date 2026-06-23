<?php

namespace App\Http\Requests\Site;

use App\Constants\ProductType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductGroupsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_type' => ['nullable', Rule::in(ProductType::allowedValues())],
        ];
    }
}
