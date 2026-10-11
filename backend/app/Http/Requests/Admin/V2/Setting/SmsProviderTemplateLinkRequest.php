<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\Setting;

use App\Http\Requests\Admin\V2\Common\AdminFormRequest;
use Illuminate\Validation\Rule;

class SmsProviderTemplateLinkRequest extends AdminFormRequest
{
    public const ACTION_LINK = 'link';

    public const ACTION_UNLINK = 'unlink';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mapping_id' => ['required', 'integer', 'min:1'],
            'action' => ['required', 'string', Rule::in([self::ACTION_LINK, self::ACTION_UNLINK])],
            'business_code' => ['required_if:action,'.self::ACTION_LINK, 'nullable', 'string', 'max:32'],
        ];
    }

    public function mappingId(): int
    {
        return (int) $this->validated('mapping_id');
    }

    public function linkAction(): string
    {
        return (string) $this->validated('action');
    }

    public function businessCode(): string
    {
        return trim((string) $this->validated('business_code', ''));
    }
}
