<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\IntegrationPlugin;

use App\Http\Requests\Admin\Common\AdminFormRequest;

class TestSmsRequest extends AdminFormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => trim((string) $this->input('phone', '')),
        ]);
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:20'],
        ];
    }

    public function attributes(): array
    {
        return [
            'phone' => '手机号码',
        ];
    }
}
