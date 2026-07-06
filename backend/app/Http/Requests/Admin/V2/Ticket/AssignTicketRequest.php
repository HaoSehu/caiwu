<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\Ticket;

use App\Http\Requests\Admin\Common\AdminFormRequest;

class AssignTicketRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'assignee_id' => ['required', 'integer', 'exists:admin_users,id'],
            'per_page' => ['prohibited'],
            'pageSize' => ['prohibited'],
        ];
    }

    public function assigneeId(): int
    {
        return (int) $this->validated()['assignee_id'];
    }
}
