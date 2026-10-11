<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\Invoice;

use App\Http\Requests\Admin\V2\Common\AdminFormRequest;

/**
 * 账单合并请求：同用户多张待支付账单。
 * 归属/状态/币种/未合并守卫在 InvoiceMergeService（锁内复核，防请求与
 * 支付回调竞态），此处只做结构校验。
 */
class MergeInvoicesRequest extends AdminFormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'min:1'],
            'invoice_ids' => ['required', 'array', 'min:2', 'distinct'],
            'invoice_ids.*' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_id.required' => '请指定账单归属用户',
            'invoice_ids.required' => '请选择要合并的账单',
            'invoice_ids.min' => '至少选择两张账单进行合并',
            'invoice_ids.distinct' => '账单不能重复选择',
        ];
    }

    /**
     * @return array{user_id: int, invoice_ids: list<int>}
     */
    public function payload(): array
    {
        $validated = $this->safe()->only(['user_id', 'invoice_ids']);

        return [
            'user_id' => (int) $validated['user_id'],
            'invoice_ids' => array_map(static fn (mixed $id): int => (int) $id, (array) $validated['invoice_ids']),
        ];
    }
}
