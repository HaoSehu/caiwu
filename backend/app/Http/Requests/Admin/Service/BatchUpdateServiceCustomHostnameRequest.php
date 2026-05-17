<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Service;

use App\Http\Requests\Admin\Common\AdminFormRequest;

class BatchUpdateServiceCustomHostnameRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.service_id' => ['required', 'integer', 'min:1', 'distinct'],
            'items.*.hostname' => ['nullable', 'string', 'max:200'],
        ];
    }

    public function payload(): array
    {
        $validated = $this->validated();

        return [
            'items' => collect((array) ($validated['items'] ?? []))
                ->map(fn (array $item) => [
                    'service_id' => (int) ($item['service_id'] ?? 0),
                    'hostname' => trim((string) ($item['hostname'] ?? '')),
                ])
                ->values()
                ->all(),
        ];
    }
}
