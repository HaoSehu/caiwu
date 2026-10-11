<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\TicketPresetReply;

use App\Http\Requests\Admin\V2\Common\AdminFormRequest;

class ListPresetRepliesRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'category_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'department_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'keyword' => ['sometimes', 'nullable', 'string', 'max:100'],
            'is_enabled' => ['sometimes', 'nullable', 'integer', 'in:0,1'],
            ...$this->paginationRules(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        $validated = $this->validated();
        $filters = [];

        foreach (['category_id', 'department_id', 'keyword', 'is_enabled'] as $key) {
            if (! array_key_exists($key, $validated)) {
                continue;
            }

            $value = $validated[$key];
            if ($value === null || $value === '') {
                continue;
            }

            $filters[$key] = $value;
        }

        return $filters;
    }

    public function pageSize(): int
    {
        return $this->perPage(50, 200);
    }
}
