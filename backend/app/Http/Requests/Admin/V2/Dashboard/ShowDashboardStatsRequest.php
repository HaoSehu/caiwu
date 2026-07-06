<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class ShowDashboardStatsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'per_page' => ['prohibited'],
        ];
    }
}
