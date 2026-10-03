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
            'keyword' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', 'integer'],
            'type' => ['nullable', 'string', Rule::in(OrderType::values())],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
            'date_range' => ['prohibited'],
            'page' => ['nullable', 'integer', 'min:1'],
            'page_size' => ['nullable', 'integer', 'min:1', 'max:100'],
            'pageSize' => ['prohibited'],
            'per_page' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator): mixed => $this->validateDateRange($validator));
    }
}
