<?php

declare(strict_types=1);

namespace App\Http\Requests\Client\V2\Notification;

use App\Support\Pagination;
use Illuminate\Foundation\Http\FormRequest;

class ListNotificationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'unread_only' => ['sometimes', 'boolean'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'page_size' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'pageSize' => ['prohibited'],
            'per_page' => ['prohibited'],
        ];
    }

    public function unreadOnly(): bool
    {
        return (bool) $this->boolean('unread_only');
    }

    public function pageNumber(): int
    {
        return max(1, (int) $this->integer('page', 1));
    }

    public function perPage(): int
    {
        // 通知列表有意收窄到 50：一遍读完未读通知才有意义，超过 50 会退化成翻页找未读。
        return Pagination::clamp((int) $this->integer('page_size', 15), 50);
    }
}
