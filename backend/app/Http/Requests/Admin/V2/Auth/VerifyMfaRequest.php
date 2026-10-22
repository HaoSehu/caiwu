<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\Auth;

use App\Http\Requests\Admin\V2\Common\AdminFormRequest;

class VerifyMfaRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return array_merge([
            'challenge_token' => ['required', 'string', 'max:128'],
            'code' => ['required', 'string', 'max:32'],
        ], [
            'per_page' => ['prohibited'],
        ]);
    }
}
