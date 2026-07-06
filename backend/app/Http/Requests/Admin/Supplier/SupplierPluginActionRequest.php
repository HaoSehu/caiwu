<?php

namespace App\Http\Requests\Admin\Supplier;

use App\Http\Requests\Admin\Common\AdminFormRequest;

class SupplierPluginActionRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'payload' => ['nullable', 'array'],
        ];
    }

    public function pluginAction(): string
    {
        return trim((string) $this->route('action'));
    }

    /**
     * @return array<string, mixed>
     */
    public function pluginPayload(): array
    {
        $payload = $this->validated('payload');

        return is_array($payload) ? $payload : [];
    }
}
