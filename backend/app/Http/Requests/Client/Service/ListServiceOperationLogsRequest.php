<?php

namespace App\Http\Requests\Client\Service;

use App\Http\Requests\Client\Common\ClientFormRequest;

class ListServiceOperationLogsRequest extends ClientFormRequest
{
    public function rules(): array
    {
        return array_merge($this->paginationRules(), [
            'keyword' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'in:power,password,reinstall,renew,nat_forwarding,security_group,security_rule,service'],
        ]);
    }

    public function filters(): array
    {
        return $this->safe()->only([
            'keyword',
            'category',
        ]);
    }
}
