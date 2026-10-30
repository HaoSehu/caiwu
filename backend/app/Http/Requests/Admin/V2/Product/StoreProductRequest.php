<?php

namespace App\Http\Requests\Admin\V2\Product;

use App\Http\Requests\Admin\V2\Common\AdminFormRequest;
use App\Models\Product;
use App\Models\ThirdProductGroup;
use Illuminate\Validation\Rule;

class StoreProductRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'custom_display_name' => ['nullable', 'string', 'max:255'],
            'product_type' => ['nullable', 'string', 'max:50'],
            'service_type_code' => ['nullable', 'string', 'max:50'],
            'console_template' => ['nullable', 'string', Rule::in([
                Product::CONSOLE_TEMPLATE_COMPUTE,
                Product::CONSOLE_TEMPLATE_PORT_MAPPING,
            ])],
            'first_product_group_id' => ['nullable', 'integer', 'min:1'],
            'second_product_group_id' => ['nullable', 'integer', 'min:1'],
            'third_product_group_id' => ['required', 'integer', 'min:1', Rule::exists((new ThirdProductGroup)->getTable(), 'id')],
            'remark' => ['nullable', 'string', 'max:255'],
            'pricing' => ['required', 'array'],
            // 价格是 JSON 列，没有 decimal 精度兜底：必须在这里卡住指数记法（1e400 会归一成 inf 变成零价）
            'pricing.monthly' => ['nullable', 'numeric', 'min:0', 'max:99999999', 'regex:/^\d{1,8}(?:\.\d{1,2})?$/'],
            'pricing.quarterly' => ['nullable', 'numeric', 'min:0', 'max:99999999', 'regex:/^\d{1,8}(?:\.\d{1,2})?$/'],
            'pricing.semiannually' => ['nullable', 'numeric', 'min:0', 'max:99999999', 'regex:/^\d{1,8}(?:\.\d{1,2})?$/'],
            'pricing.annually' => ['nullable', 'numeric', 'min:0', 'max:99999999', 'regex:/^\d{1,8}(?:\.\d{1,2})?$/'],
            'setup_fee' => ['nullable', 'numeric', 'min:0', 'max:99999999', 'regex:/^\d{1,8}(?:\.\d{1,2})?$/'],
            'config_options' => ['nullable', 'array'],
            'purchase_requires' => ['nullable', 'array'],
            'purchase_requires.require_verification' => ['nullable', 'boolean'],
            'purchase_requires.require_phone' => ['nullable', 'boolean'],
            'purchase_requires.provision_hostname' => ['nullable', 'array'],
            'purchase_requires.provision_hostname.mode' => ['nullable', 'string', Rule::in(['system', 'fixed', 'prefix'])],
            'purchase_requires.provision_hostname.value' => ['nullable', 'string', 'max:200'],
            'purchase_requires.provision_hostname.length' => ['nullable', 'integer', 'min:4', 'max:63'],
            'stock' => ['nullable', 'integer', 'min:-1'],
            'status' => ['nullable', 'in:0,1'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'auto_setup' => ['nullable', 'in:0,1'],
            'upstream_binding' => ['nullable', 'array'],
            'upstream_binding.supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'upstream_binding.upstream_product_id' => ['nullable'],
            ...$this->allPaginationRules(),
        ];
    }
}
