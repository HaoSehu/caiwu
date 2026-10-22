<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\Auth;

use App\Http\Requests\Admin\V2\Common\AdminFormRequest;

class DisableMfaRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return array_merge([
            'password' => ['required', 'string', 'max:64'],
            // 动态码与恢复码共用同一字段：手机丢失时用恢复码自救，长度放宽到 32。
            'code' => ['required', 'string', 'max:32'],
        ], [
            'per_page' => ['prohibited'],
        ]);
    }
}
