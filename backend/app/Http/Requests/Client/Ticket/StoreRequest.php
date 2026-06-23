<?php

namespace App\Http\Requests\Client\Ticket;

use App\Http\Requests\Client\Common\ClientFormRequest;
use Illuminate\Validation\Rule;

class StoreRequest extends ClientFormRequest
{
    public function rules(): array
    {
        return [
            'department' => ['required', Rule::in(TicketService::DEPARTMENTS)],
            'subject' => ['required', 'string', 'max:200'],
            'content' => ['nullable', 'string', 'max:10000'],
            'priority' => ['nullable', 'integer', 'between:1,4'],
            'service_id' => ['nullable', 'integer'],
            'attachments' => ['nullable', 'array', 'max:9'],
            'attachments.*' => ['required', 'string', 'max:255'],
        ];
    }
}
