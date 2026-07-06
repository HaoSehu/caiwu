<?php

declare(strict_types=1);

namespace App\Http\Requests\Client\V2\Action;

use Illuminate\Foundation\Http\FormRequest;

class ClientActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'per_page' => ['prohibited'],
        ];
    }
}
