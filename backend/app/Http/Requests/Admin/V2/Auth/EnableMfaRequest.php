<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\Auth;

use App\Http\Requests\Admin\V2\Common\AdminFormRequest;

class EnableMfaRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return array_merge([
            'code' => ['required', 'string', 'max:32'],
        ], [
            'per_page' => ['prohibited'],
        ]);
    }
}
