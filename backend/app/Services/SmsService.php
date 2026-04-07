<?php

namespace App\Services;

use App\Models\NotificationLog;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;

class SmsService
{
    private array $serviceConfig;

    public function __construct()
    {
        $defaultConfig = config('idc.sms', []);
        $this->serviceConfig = [
            'api_endpoint' => (string) ($defaultConfig['api_endpoint'] ?? 'https://dypnsapi.aliyuncs.com/'),
            'ssl_verify' => $this->normalizeBoolean($defaultConfig['ssl_verify'] ?? true),
            'ca_bundle' => (string) ($defaultConfig['ca_bundle'] ?? ''),
        ];
    }

    public function sendVerifyCode(string $phone, string $code): void
    {
        $signName = (string) Setting::getValue('notification', 'sms_sign_name', '速通互联验证码');
        $templateCode = (string) Setting::getValue('notification', 'sms_template_code', '100001');
        $templateParams = ['code' => $code, 'min' => '5'];

        $log = NotificationLog::create([
            'channel'       => 'sms',
            'recipient'     => $phone,
            'template_code' => $templateCode,
            'content'       => $this->buildVerificationLogContent(),
            'params_json'   => $this->buildVerificationLogParams($templateParams),
            'provider'      => 'aliyun',
            'status'        => 'pending',
            'origin_type'   => 'sms_verify',
            'origin_id'     => 0,
        ]);

        try {
            if (! $this->isEnabled()) {
                throw new \RuntimeException('短信通知未启用');
            }

            $config = [
                'AccessKeyId' => (string) Setting::getValue('notification', 'sms_access_key', ''),
                'AccessKeySecret' => (string) Setting::getValue('notification', 'sms_secret_key', ''),
            ];

            if ($config['AccessKeyId'] === '' || $config['AccessKeySecret'] === '') {
                throw new \RuntimeException('短信接口配置不完整');
            }

            $params = [
                'Action' => 'SendSmsVerifyCode',
                'SchemeName' => '默认方案',
                'CountryCode' => '86',
                'PhoneNumber' => trim($phone),
                'SignName' => $signName !== '' ? $signName : '速通互联验证码',
                'TemplateCode' => $templateCode !== '' ? $templateCode : '100001',
                'TemplateParam' => json_encode($templateParams, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'CodeLength' => 6,
                'ValidTime' => 300,
                'CodeType' => 1,
            ];

            $result = $this->request($params, $config);

            if (! is_array($result)) {
                throw new \RuntimeException('短信接口请求失败');
            }

            if (($result['Code'] ?? '') !== 'OK' || ($result['Success'] ?? false) !== true) {
                throw new \RuntimeException((string) ($result['Message'] ?? '短信发送失败'));
            }

            $log->update([
                'status' => 'success',
                'request_id' => $result['RequestId'] ?? null,
                'sent_at' => now()
            ]);
        } catch (\Exception $e) {
            $log->update([
                'status' => 'failed',
                'error_msg' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    private function isEnabled(): bool
    {
        $value = Setting::getValue('notification', 'sms_enabled', '0');

        return in_array((string) $value, ['1', 'true', 'on'], true);
    }

    private function request(array $params, array $config, string $method = 'POST'): array|false
    {
        $url = $this->serviceConfig['api_endpoint'];
        $fixedParams = [
            'Format' => 'json',
            'RegionId' => 'cn-hangzhou',
            'SignatureMethod' => 'HMAC-SHA1',
            'SignatureNonce' => uniqid((string) mt_rand(0, 0xffff), true),
            'SignatureVersion' => '1.0',
            'Timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'Version' => '2017-05-25',
            'AccessKeyId' => $config['AccessKeyId'],
        ];

        $apiParams = array_merge($fixedParams, $params);
        ksort($apiParams);

        $sortedQuery = '';
        foreach ($apiParams as $key => $value) {
            $sortedQuery .= '&' . $this->encode($key) . '=' . $this->encode((string) $value);
        }

        $stringToSign = $method . '&%2F&' . $this->encode(substr($sortedQuery, 1));
        $sign = base64_encode(hash_hmac('sha1', $stringToSign, $config['AccessKeySecret'] . '&', true));
        $body = 'Signature=' . $this->encode($sign) . $sortedQuery;

        Log::info('[短信] 请求阿里云短信接口', [
            'url' => $url,
            'action' => $params['Action'] ?? null,
            'phone' => $this->maskPhoneForLog((string) ($params['PhoneNumber'] ?? '')),
            'sign_name' => $params['SignName'] ?? null,
            'template_code' => $params['TemplateCode'] ?? null,
        ]);

        $ch = curl_init();
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        } else {
            $url .= '?' . $body;
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'x-sdk-client: php/2.0.0',
        ]);

        if (str_starts_with($url, 'https')) {
            $sslVerify = app()->environment('production') ? true : $this->serviceConfig['ssl_verify'];
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $sslVerify);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $sslVerify ? 2 : 0);

            if ($this->serviceConfig['ca_bundle'] !== '' && is_file($this->serviceConfig['ca_bundle'])) {
                curl_setopt($ch, CURLOPT_CAINFO, $this->serviceConfig['ca_bundle']);
            }
        }

        $result = curl_exec($ch);
        if ($result === false) {
            $curlError = curl_error($ch);
            $curlErrno = curl_errno($ch);
            curl_close($ch);
            Log::error('[短信] CURL 请求失败', [
                'errno' => $curlErrno,
                'error' => $curlError,
            ]);
            return false;
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($result, true);

        Log::info('[短信] 阿里云短信接口响应', [
            'http_code' => $httpCode,
            'response' => $this->buildSmsResponseLogPayload($httpCode, $decoded),
        ]);

        return $decoded;
    }

    private function buildVerificationLogContent(): string
    {
        return '短信验证码已发送（内容已脱敏）';
    }

    private function buildVerificationLogParams(array $templateParams): array
    {
        return [
            'code' => '***',
            'min' => (string) ($templateParams['min'] ?? ''),
        ];
    }

    private function buildSmsResponseLogPayload(int $httpCode, array|false|null $decoded): array
    {
        $payload = is_array($decoded) ? $decoded : [];

        return [
            'http_code' => $httpCode,
            'code' => (string) ($payload['Code'] ?? ''),
            'message' => (string) ($payload['Message'] ?? ''),
            'request_id' => (string) ($payload['RequestId'] ?? ''),
            'success' => (bool) ($payload['Success'] ?? false),
        ];
    }

    private function maskPhoneForLog(string $phone): string
    {
        $normalized = trim($phone);
        if ($normalized === '') {
            return '';
        }

        if (mb_strlen($normalized) <= 7) {
            return $normalized;
        }

        return mb_substr($normalized, 0, 3) . '****' . mb_substr($normalized, -4);
    }

    private function normalizeBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return filter_var($value, FILTER_VALIDATE_BOOL);
        }

        return (bool) $value;
    }

    private function encode(string $value): string
    {
        $encoded = urlencode($value);
        $encoded = str_replace('+', '%20', $encoded);
        $encoded = str_replace('*', '%2A', $encoded);

        return str_replace('%7E', '~', $encoded);
    }
}
