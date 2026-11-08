<?php

declare(strict_types=1);

namespace App\Http\Requests\Client\V2\Ticket;

use App\Support\Pagination;
use Illuminate\Foundation\Http\FormRequest;

class ListTicketRepliesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ticket' => ['required', 'integer', 'min:1'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'page_size' => ['sometimes', 'integer', 'min:1', 'max:'.Pagination::MAX_PAGE_SIZE],
            'pageSize' => ['prohibited'],
            'per_page' => ['prohibited'],
        ];
    }

    public function validationData(): array
    {
        return array_merge(parent::validationData(), [
            'ticket' => $this->route('id'),
        ]);
    }
}
