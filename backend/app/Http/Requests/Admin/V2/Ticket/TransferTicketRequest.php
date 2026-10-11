<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\Ticket;

use App\Http\Requests\Admin\V2\Common\AdminFormRequest;

class TransferTicketRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'to_department_id' => ['required', 'integer', 'min:1'],
            'to_assignee_id' => ['nullable', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function toDepartmentId(): int
    {
        return (int) $this->validated('to_department_id');
    }

    public function toAssigneeId(): ?int
    {
        $value = $this->validated('to_assignee_id');

        return $value !== null ? (int) $value : null;
    }

    public function reason(): string
    {
        return trim((string) ($this->validated('reason') ?? ''));
    }
}
