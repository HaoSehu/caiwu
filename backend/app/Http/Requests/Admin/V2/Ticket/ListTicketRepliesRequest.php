<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\Ticket;

use App\Http\Requests\Admin\V2\Common\AdminFormRequest;
use App\Models\Ticket;

class ListTicketRepliesRequest extends AdminFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            ...$this->paginationRules(presence: 'sometimes'),
            'ticket' => ['required', 'integer', 'min:1'],
            'per_page' => ['prohibited'],
            'pageSize' => ['prohibited'],
        ];
    }

    public function validationData(): array
    {
        $ticket = $this->route('ticket');

        return array_merge(parent::validationData(), [
            'ticket' => $ticket instanceof Ticket ? (int) $ticket->id : $ticket,
        ]);
    }
}
