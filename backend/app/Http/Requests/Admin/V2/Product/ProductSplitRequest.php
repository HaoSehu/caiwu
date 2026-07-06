<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\Product;

use App\Http\Requests\Admin\Product\SplitRequest;

class ProductSplitRequest extends SplitRequest
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
