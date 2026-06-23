<?php

namespace App\Http\Requests\Admin\Verification;

use App\Http\Requests\Admin\Common\AdminFormRequest;

class UnbindRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'reject_reason' => ['required', 'string', 'max:255'],
        ];
    }
}
