<?php

namespace App\Http\Requests\Admin\User;

use App\Http\Requests\Admin\Common\AdminFormRequest;

class UserLogPaginationRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return $this->paginationRules();
    }
}
