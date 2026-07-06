<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\HomeHero;

use App\Http\Requests\Admin\HomeHero\UpdateHomeHeroRequest as BaseUpdateHomeHeroRequest;

class UpdateHomeHeroRequest extends BaseUpdateHomeHeroRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'per_page' => ['prohibited'],
        ]);
    }
}
