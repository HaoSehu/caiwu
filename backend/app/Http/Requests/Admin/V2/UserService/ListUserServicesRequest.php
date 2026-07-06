<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\UserService;

use App\Http\Requests\Admin\User\UserServicesRequest as BaseUserServicesRequest;
use App\Http\Requests\Admin\V2\Concerns\RejectsLegacyPagination;

class ListUserServicesRequest extends BaseUserServicesRequest
{
    use RejectsLegacyPagination;

    public function rules(): array
    {
        return array_merge(parent::rules(), $this->legacyPaginationRules());
    }
}
