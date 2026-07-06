<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\Supplier;

use Illuminate\Foundation\Http\FormRequest;

class RevealSupplierSecretRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => ['prohibited'],
            'page_size' => ['prohibited'],
            'pageSize' => ['prohibited'],
            'per_page' => ['prohibited'],
        ];
    }

    public function secretKey(): string
    {
        return trim((string) $this->route('key', ''));
    }
}
