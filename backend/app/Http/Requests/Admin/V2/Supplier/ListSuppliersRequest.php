<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\Supplier;

use App\Http\Requests\Admin\V2\Common\AdminFormRequest;
use App\Support\Pagination;
use Illuminate\Validation\Rule;

class ListSuppliersRequest extends AdminFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            ...$this->paginationRules(presence: 'sometimes'),
            'keyword' => ['sometimes', 'nullable', 'string', 'max:120'],
            'status' => ['sometimes', 'nullable', Rule::in(['0', '1', 0, 1])],
            'pageSize' => ['prohibited'],
            'per_page' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return $this->safe()->only(['keyword', 'status']);
    }

    public function pageSize(): int
    {
        return Pagination::clamp((int) $this->integer('page_size', 20));
    }
}
