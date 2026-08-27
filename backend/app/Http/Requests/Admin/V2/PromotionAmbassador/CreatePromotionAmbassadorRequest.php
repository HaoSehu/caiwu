<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\PromotionAmbassador;

use App\Http\Requests\Admin\V2\Common\AdminFormRequest;

class CreatePromotionAmbassadorRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'reward_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'status' => ['nullable', 'integer', 'in:0,1'],
            'remark' => ['nullable', 'string', 'max:255'],
            'per_page' => ['prohibited'],
            'pageSize' => ['prohibited'],
        ];
    }

    public function payload(): array
    {
        return $this->safe()->only([
            'name',
            'reward_rate',
            'status',
            'remark',
        ]);
    }
}
