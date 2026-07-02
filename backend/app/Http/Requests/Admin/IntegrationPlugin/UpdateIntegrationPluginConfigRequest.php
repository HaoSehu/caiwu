<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\IntegrationPlugin;

use App\Http\Requests\Admin\Common\AdminFormRequest;

class UpdateIntegrationPluginConfigRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'config' => ['required', 'array'],
        ];
    }
}
