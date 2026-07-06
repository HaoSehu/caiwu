<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\User;

use App\Http\Requests\Admin\User\UserInvoicesRequest as BaseUserInvoicesRequest;
use App\Http\Requests\Admin\V2\Concerns\RejectsLegacyPagination;

class ListUserInvoicesRequest extends BaseUserInvoicesRequest
{
    use RejectsLegacyPagination;

    public function rules(): array
    {
        return array_merge(parent::rules(), $this->legacyPaginationRules());
    }
}
