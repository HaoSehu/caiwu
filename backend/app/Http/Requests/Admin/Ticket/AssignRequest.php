<?php

namespace App\Http\Requests\Admin\Ticket;

use App\Http\Requests\Admin\Common\AdminFormRequest;

class AssignRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return ['assignee_id' => ['required', 'integer', 'exists:admin_users,id']];
    }
}
