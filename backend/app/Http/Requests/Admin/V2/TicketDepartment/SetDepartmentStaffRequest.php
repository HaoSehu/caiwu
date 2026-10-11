<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\TicketDepartment;

use App\Http\Requests\Admin\V2\Common\AdminFormRequest;

class SetDepartmentStaffRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'staff_ids' => ['required', 'array'],
            'staff_ids.*' => ['required', 'integer', 'min:1'],
            'leader_ids' => ['nullable', 'array'],
            'leader_ids.*' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return list<int>
     */
    public function staffIds(): array
    {
        return array_map(intval(...), (array) $this->validated('staff_ids', []));
    }

    /**
     * @return list<int>
     */
    public function leaderIds(): array
    {
        return array_map(intval(...), (array) $this->validated('leader_ids', []));
    }
}
