<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\Product;

use App\Http\Requests\Admin\Product\BatchUpdateCategoryRequest;

class ProductBatchUpdateCategoryRequest extends BatchUpdateCategoryRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'page' => ['prohibited'],
            'page_size' => ['prohibited'],
            'pageSize' => ['prohibited'],
            'per_page' => ['prohibited'],
        ]);
    }
}
