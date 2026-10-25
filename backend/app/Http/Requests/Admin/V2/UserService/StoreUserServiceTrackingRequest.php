<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\UserService;

use App\Http\Requests\Admin\V2\Common\AdminFormRequest;

class StoreUserServiceTrackingRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return array_merge([
            'tracked_at' => ['required', 'date_format:Y-m-d H:i:s'],
            'content' => ['required', 'string', 'max:2000'],
            'images' => ['nullable', 'array', 'max:9'],
            'images.*.url' => ['required', 'string', 'max:500'],
            'images.*.name' => ['nullable', 'string', 'max:200'],
        ], $this->allPaginationRules());
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $validated = $this->validated();

        return [
            'tracked_at' => (string) $validated['tracked_at'],
            'content' => (string) $validated['content'],
            'images' => array_values((array) ($validated['images'] ?? [])),
        ];
    }
}
