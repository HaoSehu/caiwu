<?php

namespace App\Http\Requests\Admin\ReferralWithdrawal;

use App\Http\Requests\Admin\Common\AdminFormRequest;

class ApproveRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'remark' => ['nullable', 'string', 'max:255'],
        ];
    }
}
