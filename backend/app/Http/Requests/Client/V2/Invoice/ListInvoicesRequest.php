<?php

declare(strict_types=1);

namespace App\Http\Requests\Client\V2\Invoice;

use App\Constants\InvoiceType;
use App\Http\Requests\Concerns\HasDateRangeFilter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ListInvoicesRequest extends FormRequest
{
    use HasDateRangeFilter;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * HTTP 查询串带来的 status 是字符串（如 ?status=5），统一转型为 int，
     * 保证下游与 InvoiceStatus::REFUNDED 的严格比较成立，否则「已退款」筛选恒假。
     * 非数字入参不转型，交由 rules 的 integer 校验拒绝（422），避免静默归 0 变成「待支付」筛选。
     */
    protected function prepareForValidation(): void
    {
        $status = $this->input('status');

        if ($status === null || $status === '') {
            return;
        }

        if (is_numeric($status)) {
            $this->merge(['status' => (int) $status]);
        }
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'integer'],
            'type' => ['sometimes', 'string', function (string $attribute, mixed $value, \Closure $fail): void {
                $types = array_values(array_filter(
                    array_map('trim', explode(',', (string) $value)),
                    static fn (string $type): bool => $type !== ''
                ));

                if ($types === []) {
                    $fail('账单类型不能为空。');

                    return;
                }

                foreach ($types as $type) {
                    if (! in_array($type, $this->allowedTypes(), true)) {
                        $fail('账单类型不正确。');

                        return;
                    }
                }
            }],
            'keyword' => ['sometimes', 'string', 'max:80'],
            'start_date' => ['sometimes', 'date_format:Y-m-d'],
            'end_date' => ['sometimes', 'date_format:Y-m-d'],
            'date_range' => ['prohibited'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'page_size' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'pageSize' => ['prohibited'],
            'per_page' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator): mixed => $this->validateDateRange($validator));
    }

    /**
     * @return list<string>
     */
    private function allowedTypes(): array
    {
        return [
            InvoiceType::NEW_PURCHASE,
            'normal',
            InvoiceType::RENEW,
            InvoiceType::RECHARGE,
            InvoiceType::UPGRADE,
            InvoiceType::DEDUCTION,
            InvoiceType::REFERRAL_CREDIT,
            InvoiceType::MANUAL,
        ];
    }
}
