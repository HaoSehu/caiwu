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
            'queue' => ['required', 'string', Rule::in(['pending', 'failed'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'page_size' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'pageSize' => ['prohibited'],
            'per_page' => ['prohibited'],
        ];
    }

    public function queue(): string
    {
        return (string) $this->validated()['queue'];
    }
}
