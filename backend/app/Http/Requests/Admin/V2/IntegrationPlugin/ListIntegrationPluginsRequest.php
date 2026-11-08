<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\IntegrationPlugin;

use App\Http\Requests\Admin\V2\Common\AdminFormRequest;
use App\Services\Integrations\Plugins\PluginDomain;
use App\Support\Pagination;
use Illuminate\Validation\Rule;

class ListIntegrationPluginsRequest extends AdminFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            ...$this->paginationRules(50, 'sometimes'),
            'domain' => ['sometimes', 'nullable', 'string', Rule::in(PluginDomain::values())],
            'pageSize' => ['prohibited'],
            'per_page' => ['prohibited'],
        ];
    }

    public function domain(): ?string
    {
        $domain = trim((string) $this->input('domain', ''));

        return $domain !== '' ? $domain : null;
    }

    public function pageNumber(): int
    {
        return max(1, (int) $this->integer('page', 1));
    }

    public function pageSize(): int
    {
        return Pagination::clamp((int) $this->integer('page_size', 50), 50);
    }
}
