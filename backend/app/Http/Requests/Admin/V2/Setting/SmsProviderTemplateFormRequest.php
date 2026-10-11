<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\Setting;

use App\Http\Requests\Admin\V2\Common\AdminFormRequest;

/**
 * 短信运营商模板创建/修改请求（照抄 ZJMF createCnTemplate 参数语义：
 * title=TemplateName、content=TemplateContent、remark=Remark）。
 */
class SmsProviderTemplateFormRequest extends AdminFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'content' => ['required', 'string', 'max:2000'],
            'remark' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function templateTitle(): string
    {
        return trim((string) $this->validated('title'));
    }

    public function templateContent(): string
    {
        return trim((string) $this->validated('content'));
    }

    public function templateRemark(): string
    {
        return trim((string) $this->validated('remark', ''));
    }
}
