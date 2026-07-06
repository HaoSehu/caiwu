<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\User;

use App\Http\Requests\Admin\User\UserBalanceLogsRequest as BaseUserBalanceLogsRequest;
use App\Http\Requests\Admin\V2\Concerns\RejectsLegacyPagination;

class ListUserBalanceLogsRequest extends BaseUserBalanceLogsRequest
{
    use RejectsLegacyPagination;

    public function rules(): array
    {
        return array_merge(parent::rules(), $this->legacyPaginationRules());
    }
}
