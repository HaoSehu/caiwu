<?php

namespace App\Http\Requests\Client\Ticket;

use App\Http\Requests\Client\Common\ClientFormRequest;

class UploadImageRequest extends ClientFormRequest
{
    public function rules(): array
    {
        return [
            'file' => ['required', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120'],
        ];
    }
}
