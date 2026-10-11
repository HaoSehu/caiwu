<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\TicketPresetReply;

use App\Http\Requests\Admin\V2\Common\AdminFormRequest;
use Illuminate\Validation\Rule;

class RenderPresetReplyRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'ticket_id' => ['required', 'integer', 'min:1', Rule::exists('tickets', 'id')],
        ];
    }

    public function ticketId(): int
    {
        return (int) $this->validated('ticket_id');
    }
}
