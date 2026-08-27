<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\PromotionAmbassador;

use App\Models\PromotionAmbassador;

class UpdatePromotionAmbassadorRequest extends CreatePromotionAmbassadorRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'promotionAmbassador' => ['required', 'integer', 'min:1'],
        ]);
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
