<?php

namespace App\Http\Requests\Admin\ContentArticle;

use App\Http\Requests\Admin\Common\AdminFormRequest;

class UploadImageRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'file' => ['required', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120'],
        ];
    }
}
