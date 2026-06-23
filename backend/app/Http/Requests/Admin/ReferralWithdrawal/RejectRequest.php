<?php

namespace App\Http\Requests\Admin\ReferralWithdrawal;

use App\Http\Requests\Admin\Common\AdminFormRequest;

class RejectRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'remark' => ['required', 'string', 'max:255'],
        ];
    }
}
