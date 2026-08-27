<?php

declare(strict_types=1);

namespace App\Http\Requests\Client\V2\Ticket;

use App\Http\Requests\Client\V2\Common\ClientFormRequest;

class ListTicketsRequest extends ClientFormRequest
{
    public function rules(): array
    {
        return [
            // keyword/status 为可选过滤词，维持历史透传行为，不额外收紧校验；
            // 这里仅注册标准分页参数，供基类 perPage() 单点提取页大小。
            ...$this->paginationRules(),
        ];
    }
}
