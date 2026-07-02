<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\IntegrationPlugin;

use App\Http\Requests\Admin\Common\AdminFormRequest;
use App\Services\Integrations\Plugins\PluginDomain;
use Illuminate\Validation\Rule;

class InstallIntegrationPluginRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'domain' => ['required', 'string', Rule::in(PluginDomain::values())],
            'slug' => ['required', 'string', 'max:120'],
        ];
    }
}
