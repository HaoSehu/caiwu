<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\User;

use App\Http\Requests\Admin\V2\Common\AdminFormRequest;

class AdjustPromotionAmbassadorRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            // null 表示置为未指派（新购/续费返利分别按全局 referral.reward_rate / renewal_reward_rate 兜底）
            'promotion_ambassador_id' => ['present', 'nullable', 'integer', 'exists:promotion_ambassadors,id'],
        ];
    }

    public function promotionAmbassadorId(): ?int
    {
        $value = $this->validated()['promotion_ambassador_id'] ?? null;

        return $value === null ? null : (int) $value;
    }
}
