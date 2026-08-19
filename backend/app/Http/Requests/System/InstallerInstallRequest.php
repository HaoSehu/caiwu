<?php

declare(strict_types=1);

namespace App\Http\Requests\System;

use Illuminate\Foundation\Http\FormRequest;

class InstallerInstallRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'app_name' => ['required', 'string', 'max:100'], 'host' => ['required', 'string', 'max:255'], 'port' => ['required', 'integer', 'between:1,65535'], 'database' => ['required', 'regex:/^[A-Za-z0-9_]+$/', 'max:64'], 'username' => ['required', 'string', 'max:128'], 'password' => ['nullable', 'string'],
            'app_url' => $this->urlRules(), 'frontend_url' => $this->urlRules(), 'client_console_url' => $this->urlRules(), 'admin_url' => $this->urlRules(),
            'admin_username' => ['required', 'regex:/^[A-Za-z0-9_]{3,32}$/'], 'admin_password' => ['required', 'string', 'min:12', 'not_in:password,123456789012'],
        ];
    }

    private function urlRules(): array
    {
        return ['required', 'url:http,https', 'regex:/^https?:\/\/[^\/]+(?:\:\d+)?$/'];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $urls = array_map(fn ($key) => (string) $this->input($key), ['app_url', 'frontend_url', 'client_console_url', 'admin_url']);
            if (count(array_unique($urls)) !== 4) {
                $validator->errors()->add('urls', '四个站点地址必须互不相同');
            }
            if (count(array_unique(array_map(fn ($url) => parse_url($url, PHP_URL_SCHEME), $urls))) > 1) {
                $validator->errors()->add('urls', '四个站点地址必须使用相同协议');
            }
        });
    }
}
