<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\User;

use App\Http\Requests\Admin\User\UserTicketsRequest as BaseUserTicketsRequest;
use App\Http\Requests\Admin\V2\Concerns\RejectsLegacyPagination;

class ListUserTicketsRequest extends BaseUserTicketsRequest
{
    use RejectsLegacyPagination;

    public function rules(): array
    {
        return array_merge(parent::rules(), $this->legacyPaginationRules());
    }
}
