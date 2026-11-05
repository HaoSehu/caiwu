<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\Setting;

use App\Http\Requests\Admin\V2\Common\AdminFormRequest;
use Closure;

class UpdateSettingsRequest extends AdminFormRequest
{
    /**
     * 账户策略组只接受这三个开关，避免任意脏键写进 settings。
     */
    private const ACCOUNT_POLICY_KEYS = [
        'require_verification',
        'require_phone',
        'require_email',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'group' => ['nullable', 'string', 'max:50'],
            'settings' => [
                'required',
                'array',
                // settings.* 校验的是「值」不是「键」：键白名单必须用自定义闭包读 $value 的键。
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($this->rawGroup() !== 'account_policy' || ! is_array($value)) {
                        return;
                    }

                    $unsupported = array_diff(array_map('strval', array_keys($value)), self::ACCOUNT_POLICY_KEYS);
                    if ($unsupported !== []) {
                        $fail('账户策略配置包含不受支持的字段：'.implode('、', $unsupported));
                    }
                },
            ],
            'per_page' => ['prohibited'],
            'pageSize' => ['prohibited'],
        ];
    }

    public function group(): string
    {
        return $this->rawGroup();
    }

    /**
     * 读原始 group 输入：rules() 阶段 validated() 尚不可用。
     */
    private function rawGroup(): string
    {
        $group = trim((string) $this->input('group', 'system'));

        return $group !== '' ? $group : 'system';
    }

    /**
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        return (array) $this->validated('settings', []);
    }
}
