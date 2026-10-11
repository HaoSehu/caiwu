<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\TicketDepartment;

use App\Http\Requests\Admin\V2\Common\AdminFormRequest;
use App\Models\TicketDepartment;
use Illuminate\Validation\Rule;

class SaveDepartmentRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:255'],
            'auto_assign_mode' => ['nullable', 'integer', Rule::in([
                TicketDepartment::AUTO_ASSIGN_MANUAL,
                TicketDepartment::AUTO_ASSIGN_ROUND_ROBIN,
                TicketDepartment::AUTO_ASSIGN_LEAST_LOAD,
            ])],
            'is_enabled' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $validated = $this->validated();

        return [
            'name' => (string) ($validated['name'] ?? ''),
            'description' => $validated['description'] ?? null,
            'auto_assign_mode' => (int) ($validated['auto_assign_mode'] ?? TicketDepartment::AUTO_ASSIGN_MANUAL),
            'is_enabled' => isset($validated['is_enabled']) ? (int) (bool) $validated['is_enabled'] : 1,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ];
    }
}
