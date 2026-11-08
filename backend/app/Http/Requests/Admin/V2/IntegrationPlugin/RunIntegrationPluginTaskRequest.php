<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\IntegrationPlugin;

use App\Http\Requests\Admin\V2\Common\AdminFormRequest;
use Illuminate\Validation\Rule;

class RunIntegrationPluginTaskRequest extends AdminFormRequest
{
    public const TYPE_HEALTH_CHECK = 'health_check';

    public const TYPE_TEST_EMAIL = 'test_email';

    public const TYPE_TEST_SMS = 'test_sms';

    /** 短信插件：提交模板到运营商审核（POST /sms/templates） */
    public const TYPE_SMS_TEMPLATE_SUBMIT = 'sms_template_submit';

    /** 短信插件：查询运营商模板列表（GET /sms/templates），用于提交后确认 */
    public const TYPE_SMS_TEMPLATE_LIST = 'sms_template_list';

    /** 短信插件：读取预置模板种子（sms.templates.defaults），供勾选提交 */
    public const TYPE_SMS_TEMPLATE_PRESETS = 'sms_template_presets';

    protected function prepareForValidation(): void
    {
        $payload = $this->input('payload', []);
        $payload = is_array($payload) ? $payload : [];

        if (array_key_exists('to', $payload)) {
            $payload['to'] = trim((string) $payload['to']);
        }

        if (array_key_exists('subject', $payload)) {
            $payload['subject'] = trim((string) $payload['subject']);
        }

        if (array_key_exists('phone', $payload)) {
            $payload['phone'] = trim((string) $payload['phone']);
        }

        // 短信模板提交（POST /sms/templates）
        foreach (['name', 'scene', 'content', 'remark', 'sample_image'] as $key) {
            if (array_key_exists($key, $payload) && is_scalar($payload[$key])) {
                $payload[$key] = trim((string) $payload[$key]);
            }
        }

        if (array_key_exists('provider_template_id', $payload) && is_scalar($payload['provider_template_id'])) {
            $payload['provider_template_id'] = trim((string) $payload['provider_template_id']);
        }

        $this->merge([
            'type' => trim((string) $this->input('type', '')),
            'payload' => $payload,
        ]);
    }

    public function rules(): array
    {
        $type = (string) $this->input('type', '');
        $submitTemplate = $type === self::TYPE_SMS_TEMPLATE_SUBMIT;
        $batchSubmit = $submitTemplate && is_array($this->input('payload.templates')) && $this->input('payload.templates') !== [];

        return [
            'type' => ['required', 'string', Rule::in([
                self::TYPE_HEALTH_CHECK,
                self::TYPE_TEST_EMAIL,
                self::TYPE_TEST_SMS,
                self::TYPE_SMS_TEMPLATE_SUBMIT,
                self::TYPE_SMS_TEMPLATE_LIST,
                self::TYPE_SMS_TEMPLATE_PRESETS,
            ])],
            'payload' => ['nullable', 'array'],
            'payload.account_index' => [
                Rule::requiredIf($type === self::TYPE_TEST_EMAIL),
                'integer',
                'min:0',
            ],
            'payload.to' => [
                Rule::requiredIf($type === self::TYPE_TEST_EMAIL),
                'email',
                'max:100',
            ],
            'payload.subject' => [
                'nullable',
                'string',
                'max:255',
            ],
            'payload.body' => ['nullable', 'string', 'max:5000'],
            'payload.phone' => [
                Rule::requiredIf($type === self::TYPE_TEST_SMS),
                'string',
                'max:20',
            ],
            // 短信模板提交：主路径是勾选插件预置模板批量提交（payload.templates），
            // 顶层 name/content 保留为单条自定义提交。字段与必填项最终以运营商
            // 后台「模板表单」配置为准，本地只做基础类型与长度约束。
            'payload.templates' => ['nullable', 'array', 'max:50'],
            'payload.templates.*.name' => ['required_with:payload.templates', 'string', 'max:100'],
            'payload.templates.*.content' => ['required_with:payload.templates', 'string', 'max:1000'],
            'payload.templates.*.scene' => ['nullable', 'string', Rule::in(['verify', 'notify', 'marketing'])],
            'payload.templates.*.remark' => ['nullable', 'string', 'max:500'],
            'payload.templates.*.sample_image' => ['nullable', 'string', 'max:500'],
            'payload.name' => [
                Rule::requiredIf($submitTemplate && ! $batchSubmit),
                'nullable',
                'string',
                'max:100',
            ],
            'payload.scene' => [
                'nullable',
                'string',
                Rule::in(['verify', 'notify', 'marketing']),
            ],
            'payload.content' => [
                Rule::requiredIf($submitTemplate && ! $batchSubmit),
                'nullable',
                'string',
                'max:1000',
            ],
            'payload.remark' => ['nullable', 'string', 'max:500'],
            'payload.sample_image' => ['nullable', 'string', 'max:500'],
            'payload.provider_template_id' => ['nullable', 'string', 'max:64'],
            'page' => ['prohibited'],
            'page_size' => ['prohibited'],
            'pageSize' => ['prohibited'],
            'per_page' => ['prohibited'],
        ];
    }

    public function taskType(): string
    {
        return (string) $this->validated()['type'];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $payload = $this->validated()['payload'] ?? [];

        return is_array($payload) ? $payload : [];
    }
}
