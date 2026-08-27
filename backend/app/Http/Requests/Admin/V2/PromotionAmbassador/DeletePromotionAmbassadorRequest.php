<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\PromotionAmbassador;

use App\Http\Requests\Admin\V2\Common\AdminFormRequest;
use App\Models\PromotionAmbassador;

class DeletePromotionAmbassadorRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'promotionAmbassador' => ['required', 'integer', 'min:1'],
            'per_page' => ['prohibited'],
            'pageSize' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $promotionAmbassador = $this->route('promotionAmbassador');

        $this->merge([
            'promotionAmbassador' => $promotionAmbassador instanceof PromotionAmbassador
                ? $promotionAmbassador->getKey()
                : $promotionAmbassador,
        ]);
    }
}
