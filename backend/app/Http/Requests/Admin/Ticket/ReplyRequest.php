<?php

namespace App\Http\Requests\Admin\Ticket;

use App\Http\Requests\Admin\Common\AdminFormRequest;

class ReplyRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'content' => ['nullable', 'string', 'max:10000'],
            'attachments' => ['nullable', 'array', 'max:9'],
            'attachments.*' => ['required', 'string', 'max:255'],
            'quote_reply_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
