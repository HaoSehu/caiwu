<?php

declare(strict_types=1);

namespace App\Http\Requests\System;

use Illuminate\Foundation\Http\FormRequest;

class InstallerDatabaseVerifyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['host' => ['required', 'string', 'max:255'], 'port' => ['required', 'integer', 'between:1,65535'], 'database' => ['required', 'regex:/^[A-Za-z0-9_]+$/', 'max:64'], 'username' => ['required', 'string', 'max:128'], 'password' => ['nullable', 'string']];
    }
}
