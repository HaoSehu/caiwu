<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\UserService;

use App\Http\Requests\Admin\User\StoreUserServiceRequest as BaseStoreUserServiceRequest;
use App\Http\Requests\Admin\V2\Concerns\RejectsLegacyPagination;

class StoreUserServiceRequest extends BaseStoreUserServiceRequest
{
    use RejectsLegacyPagination;

    public function rules(): array
    {
        return array_merge(parent::rules(), $this->allPaginationRules());
    }
}
