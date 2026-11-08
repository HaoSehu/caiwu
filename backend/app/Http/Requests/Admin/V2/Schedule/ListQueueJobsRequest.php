<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\Schedule;

use App\Http\Requests\Admin\V2\Common\AdminFormRequest;
use Illuminate\Validation\Rule;

class ListQueueJobsRequest extends AdminFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            ...$this->paginationRules(presence: 'sometimes'),
            'queue' => ['required', 'string', Rule::in(['pending', 'failed'])],
            'pageSize' => ['prohibited'],
            'per_page' => ['prohibited'],
        ];
    }

    public function queue(): string
    {
        return (string) $this->validated()['queue'];
    }
}
