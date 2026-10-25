<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\UserService;

use App\Http\Requests\Admin\V2\Common\AdminFormRequest;
use App\Models\User;
use Illuminate\Validation\Validator;

class ShowUserServiceRequest extends AdminFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user' => ['required', 'integer', 'min:1'],
            'service' => ['required', 'integer', 'min:1'],
            'refresh' => ['sometimes', 'boolean'],
            'sync_remote' => ['sometimes', 'boolean'],
            'page' => ['prohibited'],
            'page_size' => ['prohibited'],
            'pageSize' => ['prohibited'],
            'per_page' => ['prohibited'],
        ];
    }

    /**
     * sync_remote=0 走本地快照短路，refresh 会被忽略，因此两者不能同时出现，避免静默丢弃调用方意图。
     *
     * @return array<int, \Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->boolean('refresh') && ! $this->boolean('sync_remote', true)) {
                    $validator->errors()->add('refresh', 'refresh 与 sync_remote=0 不能同时使用');
                }
            },
        ];
    }

    public function validationData(): array
    {
        $user = $this->route('user');

        return array_merge(parent::validationData(), [
            'user' => $user instanceof User ? (int) $user->id : $user,
            'service' => $this->route('service'),
        ]);
    }
}
