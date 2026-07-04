<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\IntegrationPlugin;

use App\Http\Requests\Admin\Common\AdminFormRequest;

class TestEmailRequest extends AdminFormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'to' => trim((string) $this->input('to', '')),
            'subject' => trim((string) $this->input('subject', '')),
        ]);
    }

    public function rules(): array
    {
        return [
            'account_index' => ['required', 'integer', 'min:0'],
            'to' => ['required', 'email', 'max:100'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'account_index' => 'SMTP 账号',
            'to' => '收件人邮箱',
            'subject' => '邮件主题',
            'body' => '邮件正文',
        ];
    }
}
