<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\Referral;

use App\Http\Requests\Admin\Common\AdminFormRequest;

class ShowReferralOverviewRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'per_page' => ['prohibited'],
            'pageSize' => ['prohibited'],
        ];
    }
}
