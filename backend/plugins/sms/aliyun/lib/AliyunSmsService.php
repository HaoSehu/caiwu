<?php

declare(strict_types=1);

namespace Caiwu\Plugins\Sms\Aliyun\Lib;

class AliyunSmsService
{
    private ?AliyunSmsClient $client = null;

    public function key(): string
    {
        return 'aliyun';
    }

    public function label(): string
    {
        return '阿里云号码认证短信';
    }

    public function execute(array $request): array
    {
        $action = trim((string) ($request['action'] ?? ''));
        $payload = is_array($request['payload'] ?? null) ? $request['payload'] : [];
        $config = is_array($request['config'] ?? null) ? $request['config'] : [];

        if ($action === 'sms.test') {
            $phone = trim((string) ($payload['phone'] ?? ''));

            if ($phone === '') {
                return [
                    'success' => false,
                    'action' => $action,
                    'message' => '缺少必要参数：phone',
                    'data' => [],
                ];
            }

            $client = $this->client($config);
            $result = $client->sendVerifyCode(
                phone: $phone,
                code: $this->verificationCode($payload),
                options: is_array($payload['options'] ?? null) ? $payload['options'] : [],
            );

            return [
                'success' => $result['success'] ?? false,
                'action' => $action,
                'message' => ($result['success'] ?? false) ? '测试短信发送成功' : ($result['message'] ?? '发送失败'),
                'data' => $result,
            ];
        }

        if ($action === 'sms.send_message') {
            $client = $this->client($config);
            $result = $client->sendMessage(
                phone: (string) ($payload['phone'] ?? ''),
                templateCode: '',
                content: (string) ($payload['content'] ?? ''),
                options: is_array($payload['options'] ?? null) ? $payload['options'] : [],
            );

            return [
                'success' => $result['success'] ?? false,
                'action' => $action,
                'message' => $result['message'] ?? '',
                'data' => $result,
            ];
        }

        if ($action !== 'sms.send_verify_code') {
            return [
                'success' => false,
                'action' => $action,
                'message' => 'Unsupported plugin action',
                'data' => [],
            ];
        }

        $client = $this->client($config);
        $result = $client->sendVerifyCode(
            phone: (string) ($payload['phone'] ?? ''),
            code: (string) ($payload['code'] ?? ''),
            options: is_array($payload['options'] ?? null) ? $payload['options'] : [],
        );

        return [
            'success' => $result['success'] ?? false,
            'action' => $action,
            'message' => $result['message'] ?? '',
            'data' => $result,
        ];
    }

    private function client(array $config): AliyunSmsClient
    {
        if ($this->client === null) {
            $this->client = new AliyunSmsClient($config);
        }

        return $this->client;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function verificationCode(array $payload): string
    {
        $code = trim((string) ($payload['code'] ?? ''));

        return preg_match('/^\d{6}$/', $code) === 1 ? $code : (string) random_int(100000, 999999);
    }
}
