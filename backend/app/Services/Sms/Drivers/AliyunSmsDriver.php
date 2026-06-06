<?php

declare(strict_types=1);

namespace App\Services\Sms\Drivers;

use App\Models\Setting;
use App\Services\Sms\Contracts\SmsDriver;
use Illuminate\Support\Facades\Log;

class AliyunSmsDriver implements SmsDriver
{
    public function key(): string
    {
        return 'aliyun';
    }

    public function label(): string
    {
        return '阿里云短信';
    }

    public function sendVerifyCode(string $phone, string $code, array $options = []): array
    {
        $signName = $options['sign_name'] ?? (string) Setting::getValue('notification', 'sms_sign_name', '速通互联验证码');
        $templateCode = $options['template_code'] ?? (string) Setting::getValue('notification', 'sms_template_code', '100001');
        $templateParams = ['code' => $code, 'min' => '5'];

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

        $apiEndpoint = (string) (config('idc.sms.api_endpoint') ?? 'https://dypnsapi.aliyuncs.com/');
        $result = $this->request($apiEndpoint, $params, $config);

        if (! is_array($result)) {
            throw new \RuntimeException('短信接口请求失败');
        }

        if (($result['Code'] ?? '') !== 'OK' || ($result['Success'] ?? false) !== true) {
            throw new \RuntimeException((string) ($result['Message'] ?? '短信发送失败'));
        }

        return [
            'status' => 'success',
            'request_id' => $result['RequestId'] ?? null,
            'template_code' => $templateCode,
            'template_params' => $templateParams,
        ];
    }

    private function request(string $url, array $params, array $config, string $method = 'POST'): array|false
    {
        $fixedParams = [
            'Format' => 'json',
            'RegionId' => 'cn-hangzhou',
            'SignatureMethod' => 'HMAC-SHA1',
            'SignatureNonce' => uniqid((string) mt_rand(0, 0xFFFF), true),
            'SignatureVersion' => '1.0',
            'Timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'Version' => '2017-05-25',
            'AccessKeyId' => $config['AccessKeyId'],
        ];

        $apiParams = array_merge($fixedParams, $params);
        ksort($apiParams);

        $sortedQuery = '';
        foreach ($apiParams as $key => $value) {
            $sortedQuery .= '&'.$this->encode($key).'='.$this->encode((string) $value);
        }

        $stringToSign = $method.'&%2F&'.$this->encode(substr($sortedQuery, 1));
        $sign = base64_encode(hash_hmac('sha1', $stringToSign, $config['AccessKeySecret'].'&', true));
        $body = 'Signature='.$this->encode($sign).$sortedQuery;

        Log::info('[短信] 请求阿里云短信接口', [
            'url' => $url,
            'action' => $params['Action'] ?? null,
            'phone' => $this->maskPhone((string) ($params['PhoneNumber'] ?? '')),
        ]);

        $ch = curl_init();
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        } else {
            $url .= '?'.$body;
        }

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
}
