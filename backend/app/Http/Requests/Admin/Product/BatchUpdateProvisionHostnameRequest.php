<?php

namespace App\Http\Requests\Admin\Product;

use App\Http\Requests\Admin\Common\AdminFormRequest;
use Illuminate\Validation\Rule;

class BatchUpdateProvisionHostnameRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'product_ids' => ['required', 'array', 'min:1', 'max:200'],
            'product_ids.*' => ['integer', 'min:1', 'distinct'],
            'provision_hostname' => ['required', 'array'],
            'provision_hostname.mode' => ['required', 'string', Rule::in(ProductProvisionHostname::modes())],
            'provision_hostname.value' => ['nullable', 'string', 'max:200'],
            'provision_hostname.length' => ['nullable', 'integer', 'min:4', 'max:63'],
        ];
    }
}
