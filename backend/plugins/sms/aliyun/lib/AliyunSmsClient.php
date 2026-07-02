<?php

declare(strict_types=1);

namespace Caiwu\Plugins\Sms\Aliyun\Lib;

use Illuminate\Support\Facades\Log;

/**
 * 阿里云短信 HTTP 客户端 — 完全自包含，不依赖内核驱动。
 */
class AliyunSmsClient
{
    /**
     * @param  array<string, mixed>  $config  插件配置（来自 execute() 的 $request['config']）
     */
    public function __construct(
        private readonly array $config,
    ) {}

    /**
     * @return array{success: bool, request_id?: string, raw?: array}
     */
    public function sendVerifyCode(string $phone, string $code, array $options = []): array
    {
        $signName = (string) ($options['sign_name'] ?? $this->config['sign_name'] ?? '速通互联验证码');
        $templateCode = (string) ($options['template_code'] ?? $this->config['template_code'] ?? '100001');
        $templateParams = ['code' => $code, 'min' => '5'];

        $accessKeyId = (string) ($this->config['access_key'] ?? '');
        $accessKeySecret = (string) ($this->config['secret_key'] ?? '');

        if ($accessKeyId === '' || $accessKeySecret === '') {
            return ['success' => false, 'message' => '短信接口配置不完整'];
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

        $apiEndpoint = (string) (config('idc.sms.api_endpoint') ?? 'https://dypnsapi.aliyuncs.com/');
        $result = $this->request($apiEndpoint, $params, $accessKeyId, $accessKeySecret, $phone);

        if (! is_array($result)) {
            return ['success' => false, 'message' => '短信接口请求失败，请稍后重试'];
        }

        if (($result['Code'] ?? '') !== 'OK' || ($result['Success'] ?? false) !== true) {
            Log::warning('[短信] 阿里云短信发送失败', [
                'code' => (string) ($result['Code'] ?? ''),
                'message' => $this->resolveFailureMessage($result['Message'] ?? ''),
            ]);

            return ['success' => false, 'message' => $this->resolveFailureMessage($result['Message'] ?? '')];
        }

        return [
            'success' => true,
            'request_id' => isset($result['RequestId']) ? (string) $result['RequestId'] : null,
            'template_code' => (string) $templateCode,
            'template_params' => $templateParams,
            'raw' => $result,
        ];
    }

    private function request(string $url, array $params, string $accessKeyId, string $accessKeySecret, string $phone): array|false
    {
        $fixedParams = [
            'Format' => 'json',
            'RegionId' => 'cn-hangzhou',
            'SignatureMethod' => 'HMAC-SHA1',
            'SignatureNonce' => uniqid((string) mt_rand(0, 0xFFFF), true),
            'SignatureVersion' => '1.0',
            'Timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'Version' => '2017-05-25',
            'AccessKeyId' => $accessKeyId,
        ];

        $apiParams = array_merge($fixedParams, $params);
        ksort($apiParams);

        $sortedQuery = '';
        foreach ($apiParams as $key => $value) {
            $sortedQuery .= '&'.$this->encode($key).'='.$this->encode((string) $value);
        }

        $method = 'POST';
        $stringToSign = $method.'&%2F&'.$this->encode(substr($sortedQuery, 1));
        $sign = base64_encode(hash_hmac('sha1', $stringToSign, $accessKeySecret.'&', true));
        $body = 'Signature='.$this->encode($sign).$sortedQuery;

        Log::info('[短信] 请求阿里云短信接口', [
            'url' => $url,
            'action' => $params['Action'] ?? null,
            'phone' => $this->maskPhone($phone),
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['x-sdk-client: php/2.0.0']);

        if (str_starts_with($url, 'https')) {
            $sslVerify = app()->environment('production') ? true : (bool) config('idc.sms.ssl_verify', true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $sslVerify);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $sslVerify ? 2 : 0);

            $caBundle = (string) config('idc.sms.ca_bundle', '');
            if ($caBundle !== '' && is_file($caBundle)) {
                curl_setopt($ch, CURLOPT_CAINFO, $caBundle);
            }
        }

        $result = curl_exec($ch);
        if ($result === false) {
            Log::error('[短信] CURL 请求失败', [
                'errno' => curl_errno($ch),
                'error' => curl_error($ch),
            ]);
            curl_close($ch);

            return false;
        }

        curl_close($ch);

        return json_decode($result, true) ?: false;
    }

    private function encode(string $value): string
    {
        $encoded = urlencode($value);
        $encoded = str_replace('+', '%20', $encoded);
        $encoded = str_replace('*', '%2A', $encoded);

        return str_replace('%7E', '~', $encoded);
    }

    private function maskPhone(string $phone): string
    {
        if (mb_strlen($phone) <= 7) {
            return $phone;
        }

        return mb_substr($phone, 0, 3).'****'.mb_substr($phone, -4);
    }

    private function resolveFailureMessage(mixed $message): string
    {
        $text = trim((string) $message);
        if ($text === '') {
            return '短信发送失败，请稍后重试';
        }

        if (preg_match('/[a-z]{3,}|error|failed|exception|timeout|denied|invalid/i', $text) === 1) {
            return '短信发送失败，请稍后重试';
        }

        return $text;
    }
}
