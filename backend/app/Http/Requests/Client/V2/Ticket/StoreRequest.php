<?php

declare(strict_types=1);

namespace App\Http\Requests\Client\V2\Ticket;

use App\Http\Requests\Client\V2\Common\ClientFormRequest;
use App\Services\Ticket\TicketService;
use Illuminate\Validation\Rule;

class StoreRequest extends ClientFormRequest
{
    public function rules(): array
    {
        return [
            'department' => ['required', Rule::in(TicketService::DEPARTMENTS)],
            // M3：可选部门 ID，启用中校验由 TicketService::create 兜底（含默认部门回退）。
            'department_id' => ['nullable', 'integer', 'min:1'],
            'subject' => ['required', 'string', 'max:200'],
            'content' => ['nullable', 'string', 'max:10000'],
            'priority' => ['nullable', 'integer', 'between:1,4'],
            'service_id' => ['nullable', 'integer'],
            'attachments' => ['nullable', 'array', 'max:9'],
            'attachments.*' => ['required', 'string', 'max:255'],
        ];
    }
}
