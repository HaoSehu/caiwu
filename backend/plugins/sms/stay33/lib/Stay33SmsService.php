<?php

declare(strict_types=1);

namespace Caiwu\Plugins\Sms\Stay33\Lib;

use App\Support\NumericCodeNormalizer;

class Stay33SmsService
{
    private ?Stay33SmsClient $client = null;

    public function key(): string
    {
        return 'stay33';
    }

    public function label(): string
    {
        return 'MC云短信';
    }

    /**
     * @param  array<string, mixed>  $request
     * @return array<string, mixed>
     */
    public function execute(array $request): array
    {
        $action = trim((string) ($request['action'] ?? ''));
        $payload = is_array($request['payload'] ?? null) ? $request['payload'] : [];
        $config = is_array($request['config'] ?? null) ? $request['config'] : [];

        if ($action === 'sms.test') {
            return $this->send(
                $action,
                $payload,
                $config,
                NumericCodeNormalizer::normalizeSixDigit((string) ($payload['code'] ?? ''))
            );
        }

        if ($action === 'sms.send_message') {
            return $this->sendMessage($action, $payload, $config);
        }

        if ($action === 'sms.send_verify_code') {
            return $this->send($action, $payload, $config, trim((string) ($payload['code'] ?? '')));
        }

        // 模板管理 action（照抄 ZJMF smsbao 范式）：MC 云通道为提交即用的
        // 文本通道，无运营商模板审核 API——create/get/put 一律「立即过审」，
        // pull 返回空列表（通道侧无独立模板库），delete 直接成功。
        if ($action === 'sms.templates.pull') {
            return $this->success($action, ['templates' => [], 'synced_at' => date('Y-m-d H:i:s')]);
        }

        if ($action === 'sms.templates.push' || $action === 'sms.templates.update') {
            $content = trim((string) ($payload['content'] ?? ''));
            if ($content === '') {
                return $this->failed($action, '缺少必要参数：content');
            }

            return $this->success($action, [
                'provider_template_id' => $action === 'sms.templates.update'
                    ? trim((string) ($payload['provider_template_id'] ?? ''))
                    : 'STAY33_'.strtoupper(dechex((int) (microtime(true) * 1000) % 0xFFFFFF)).'_'.random_int(100, 999),
                'status' => 'approved',
                'content' => $content,
                'variables' => $this->extractVariables($content),
            ]);
        }

        if ($action === 'sms.templates.status') {
            return $this->success($action, [
                'provider_template_id' => trim((string) ($payload['provider_template_id'] ?? '')),
                'status' => 'approved',
                'reject_reason' => '',
            ]);
        }

        if ($action === 'sms.templates.delete') {
            return $this->success($action, [
                'provider_template_id' => trim((string) ($payload['provider_template_id'] ?? '')),
                'deleted' => true,
            ]);
        }

        if ($action === 'sms.templates.defaults') {
            return $this->success($action, ['defaults' => $this->defaultTemplates()]);
        }

        return [
            'success' => false,
            'action' => $action,
            'message' => 'Unsupported plugin action',
            'data' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function success(string $action, array $data): array
    {
        return [
            'success' => true,
            'action' => $action,
            'message' => '',
            'data' => $data,
        ];
    }

    /**
     * 从运营商模板原文提取变量名（${var} / {var} / {{var}}）。
     *
     * @return array<int, string>
     */
    private function extractVariables(string $content): array
    {
        preg_match_all('/\$\{([a-zA-Z0-9_]+)\}|\{\{?\s*([a-zA-Z0-9_]+)\s*\}?\}/u', $content, $matches);

        /** @var array<int, string> $names */
        $names = array_merge($matches[1], $matches[2]);
        $names = array_map('strval', $names);

        return array_values(array_unique(array_filter($names, static fn (string $name): bool => $name !== '')));
    }

    /**
     * 插件默认模板种子（对齐 ZJMF install() 返回 smsTemplate.php 的机制）。
     *
     * @return array<int, array<string, mixed>>
     */
    private function defaultTemplates(): array
    {
        $seed = [
            ['账单支付提醒', '{username},您已成功支付账单号#{invoiceid},账单金额{total}.', '账单支付成功通知'],
            ['发送验证码', '验证码{code},5分钟内有效,请勿泄漏于他人', '登录/注册验证码'],
            ['服务到期提醒', '{username}，您的产品{product_name}(主机名{hostname})将于{product_end_time}到期.请及时续费', '续费提醒'],
            ['登录短信提醒', '{username}，您于{time}在IP{address}登录.如不是您的登录,请立即更改密码.', '登录安全提醒'],
        ];

        $defaults = [];
        foreach ($seed as [$title, $content, $remark]) {
            $defaults[] = [
                'title' => $title,
                'content' => $content,
                'remark' => $remark,
                'variables' => $this->extractVariables($content),
            ];
        }

        return $defaults;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function send(string $action, array $payload, array $config, string $code): array
    {
        $phone = trim((string) ($payload['phone'] ?? ''));
        if ($phone === '') {
            return $this->failed($action, '缺少必要参数：phone');
        }

        if ($code === '') {
            return $this->failed($action, '缺少必要参数：code');
        }

        $result = $this->client($config)->sendVerifyCode(
            phone: $phone,
            code: $code,
            options: is_array($payload['options'] ?? null) ? $payload['options'] : [],
        );

        return [
            'success' => (bool) ($result['success'] ?? false),
            'action' => $action,
            'message' => (string) ($result['message'] ?? (($result['success'] ?? false) ? '短信发送成功' : '短信发送失败，请稍后重试')),
            'data' => $result,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function sendMessage(string $action, array $payload, array $config): array
    {
        $phone = trim((string) ($payload['phone'] ?? ''));
        $templateCode = trim((string) ($payload['template_code'] ?? ''));
        $content = trim((string) ($payload['content'] ?? ''));

        if ($phone === '') {
            return $this->failed($action, '缺少必要参数：phone');
        }

        if ($content === '') {
            return $this->failed($action, '缺少必要参数：content');
        }

        $result = $this->client($config)->sendMessage(
            phone: $phone,
            templateCode: $templateCode,
            content: $content,
            options: is_array($payload['options'] ?? null) ? $payload['options'] : [],
        );

        return [
            'success' => (bool) ($result['success'] ?? false),
            'action' => $action,
            'message' => (string) ($result['message'] ?? (($result['success'] ?? false) ? '短信发送成功' : '短信发送失败，请稍后重试')),
            'data' => $result,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function failed(string $action, string $message): array
    {
        return [
            'success' => false,
            'action' => $action,
            'message' => $message,
            'data' => [
                'success' => false,
                'message' => $message,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function client(array $config): Stay33SmsClient
    {
        if ($this->client === null) {
            $this->client = new Stay33SmsClient($config);
        }

        return $this->client;
    }
}
