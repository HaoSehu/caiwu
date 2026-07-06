<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\ProductType;

use Illuminate\Foundation\Http\FormRequest;

class ListProductTypesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'per_page' => ['prohibited'],
            'page' => ['prohibited'],
            'page_size' => ['prohibited'],
            'pageSize' => ['prohibited'],
        ];
    }
}
