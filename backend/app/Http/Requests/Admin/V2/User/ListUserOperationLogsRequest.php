<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\User;

use App\Http\Requests\Admin\User\UserOperationLogsRequest as BaseUserOperationLogsRequest;
use App\Http\Requests\Admin\V2\Concerns\RejectsLegacyPagination;

class ListUserOperationLogsRequest extends BaseUserOperationLogsRequest
{
    use RejectsLegacyPagination;

    public function rules(): array
    {
        return array_merge(parent::rules(), $this->legacyPaginationRules());
    }
}
