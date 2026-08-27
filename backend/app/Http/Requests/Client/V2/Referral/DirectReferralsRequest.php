<?php

declare(strict_types=1);

namespace App\Http\Requests\Client\V2\Referral;

use App\Http\Requests\Client\V2\Common\ClientFormRequest;

class DirectReferralsRequest extends ClientFormRequest
{
    public function rules(): array
    {
        return [
            // 仅注册标准分页参数，供基类 perPage() 单点提取页大小。
            ...$this->paginationRules(),
        ];
    }
}
