<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\Setting;

use App\Http\Requests\Admin\V2\Common\AdminFormRequest;
use App\Support\Pagination;

class ListSettingsRequest extends AdminFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            ...$this->paginationRules(presence: 'sometimes'),
            'group' => ['sometimes', 'nullable', 'string', 'max:50'],
            'per_page' => ['prohibited'],
            'pageSize' => ['prohibited'],
        ];
    }

    public function group(): string
    {
        $group = trim((string) $this->input('group', 'system'));

        return $group !== '' ? $group : 'system';
    }

    public function pageNumber(): int
    {
        return max(1, (int) $this->integer('page', 1));
    }

    public function pageSize(): int
    {
        return Pagination::clamp((int) $this->integer('page_size', 100));
    }
}
