<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Staff;

use App\Http\Requests\Admin\Common\AdminFormRequest;

class DeleteAdminStaffRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [];
    }
}
