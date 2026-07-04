<?php

namespace App\Http\Requests\Client\Invoice;

use App\Constants\InvoiceType;
use App\Http\Requests\Client\Common\ClientFormRequest;
use App\Http\Requests\Concerns\HasDateRangeFilter;

class IndexRequest extends ClientFormRequest
{
    use HasDateRangeFilter;

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'integer'],
            'type' => ['nullable', 'string', function (string $attribute, mixed $value, \Closure $fail): void {
                $types = array_values(array_filter(
                    array_map('trim', explode(',', (string) $value)),
                    static fn (string $type): bool => $type !== ''
                ));

                if ($types === []) {
                    $fail('账单类型不能为空。');

                    return;
                }

                foreach ($types as $type) {
                    if (! in_array($type, self::allowedTypes(), true)) {
                        $fail('账单类型不正确。');

                        return;
                    }
                }
            }],
            'keyword' => ['nullable', 'string', 'max:80'],
            ...$this->dateRangeRules(),
            'page' => ['nullable', 'integer', 'min:1'],
            'page_size' => ['nullable', 'integer', 'min:1', 'max:100'],
            'per_page' => ['prohibited'],
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function allowedTypes(): array
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
