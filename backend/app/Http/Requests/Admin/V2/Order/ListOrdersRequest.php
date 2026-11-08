<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\Order;

use App\Constants\OrderType;
use App\Http\Requests\Admin\V2\Common\AdminFormRequest;
use App\Http\Requests\Concerns\HasDateRangeFilter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ListOrdersRequest extends AdminFormRequest
{
    use HasDateRangeFilter;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            ...$this->paginationRules(),
            'keyword' => ['nullable', 'string', 'max:80'],
            'user_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'integer'],
            'type' => ['nullable', 'string', Rule::in(OrderType::values())],
            'upgrade_kind' => ['nullable', 'string', 'max:80'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
            'date_range' => ['prohibited'],
            'pageSize' => ['prohibited'],
            'per_page' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator): mixed => $this->validateDateRange($validator));
    }
}
