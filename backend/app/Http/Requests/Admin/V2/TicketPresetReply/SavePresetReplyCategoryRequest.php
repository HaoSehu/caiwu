<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\TicketPresetReply;

use App\Http\Requests\Admin\V2\Common\AdminFormRequest;

class SavePresetReplyCategoryRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'is_enabled' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $validated = $this->validated();

        return [
            'name' => (string) $validated['name'],
            'is_enabled' => isset($validated['is_enabled']) ? (int) (bool) $validated['is_enabled'] : 1,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ];
    }
}
