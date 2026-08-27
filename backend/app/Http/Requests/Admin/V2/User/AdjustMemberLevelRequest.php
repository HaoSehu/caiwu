<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\User;

use App\Http\Requests\Admin\V2\Common\AdminFormRequest;

class AdjustMemberLevelRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            // null 表示手工置为未分级
            'member_level_id' => ['present', 'nullable', 'integer', 'exists:member_levels,id'],
        ];
    }

    public function memberLevelId(): ?int
    {
        $value = $this->validated()['member_level_id'] ?? null;

        return $value === null ? null : (int) $value;
    }
}
