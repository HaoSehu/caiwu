<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\UserService;

use App\Http\Requests\Admin\V2\Common\AdminFormRequest;

class UploadUserServiceTrackingImageRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return array_merge([
            'file' => ['required', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120'],
        ], $this->allPaginationRules());
    }
}
