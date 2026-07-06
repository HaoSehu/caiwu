<?php

declare(strict_types=1);

namespace App\Http\Requests\Client\V2\Service;

use App\Http\Requests\Client\Service\ReinstallRequest;

class ReinstallationRequest extends ReinstallRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'per_page' => ['prohibited'],
        ]);
    }
}
