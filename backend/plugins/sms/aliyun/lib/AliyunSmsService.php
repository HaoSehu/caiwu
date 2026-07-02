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
        return '阿里云短信';
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
                code: '888888',
                options: [],
            );

            return [
                'success' => $result['success'] ?? false,
                'action' => $action,
                'message' => ($result['success'] ?? false) ? '测试短信发送成功' : ($result['message'] ?? '发送失败'),
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
}
