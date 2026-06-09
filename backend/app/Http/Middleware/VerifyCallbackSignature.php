<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ApiResponseBuilder;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyCallbackSignature
{
    private const MAX_TIMESTAMP_SKEW_SECONDS = 300;

    public function handle(Request $request, Closure $next): Response
    {
        $signature = $request->header('X-Signature') ?? $request->input('sign', '');
        $certifyId = $request->input('certify_id', $request->input('order_no', ''));
        $timestamp = (string) ($request->header('X-Timestamp') ?? $request->input('timestamp', ''));
        $nonce = (string) ($request->header('X-Nonce') ?? $request->input('nonce', ''));

        if ($signature === '' || $certifyId === '' || $timestamp === '' || $nonce === '') {
            $this->auditFailure('missing_required_field', $certifyId, $timestamp, $nonce);

            return ApiResponseBuilder::error(40001, '签名验证失败', null, 401);
        }

        $timestampValue = (int) $timestamp;
        if ($timestampValue <= 0 || abs(now()->timestamp - $timestampValue) > self::MAX_TIMESTAMP_SKEW_SECONDS) {
            $this->auditFailure('timestamp_expired', $certifyId, $timestamp, $nonce);

            return ApiResponseBuilder::error(40001, '签名验证失败', null, 401);
        }

        $key = (string) config('idc.verification.key', '');
        if ($key === '') {
            $key = (string) config('app.key', '');
        }

        $expectedSign = hash_hmac('sha256', $this->canonicalPayload($request, $timestamp, $nonce), $key);

        if (! hash_equals($expectedSign, $signature)) {
            $this->auditFailure('signature_mismatch', $certifyId, $timestamp, $nonce);

            return ApiResponseBuilder::error(40001, '签名验证失败', null, 401);
        }

        $replayKey = 'callback:verification:nonce:'.hash('sha256', $certifyId.'|'.$nonce);
        if (! Cache::add($replayKey, true, now()->addSeconds(self::MAX_TIMESTAMP_SKEW_SECONDS * 2))) {
            $this->auditFailure('nonce_replayed', $certifyId, $timestamp, $nonce);

            return ApiResponseBuilder::error(40001, '回调请求已处理，请勿重复提交', null, 409);
        }

        return $next($request);
    }

    private function canonicalPayload(Request $request, string $timestamp, string $nonce): string
    {
        $payload = $request->all();
        unset($payload['sign'], $payload['signature']);

        $payload['timestamp'] = $timestamp;
        $payload['nonce'] = $nonce;

        $this->ksortRecursive($payload);

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    /**
     * 对回调参数做稳定排序，确保签名原文跨 PHP 版本一致。
     *
     * @param  array<string, mixed>  $payload
     */
    private function ksortRecursive(array &$payload): void
    {
        ksort($payload);

        foreach ($payload as &$value) {
            if (is_array($value)) {
                $this->ksortRecursive($value);
            }
        }
    }

    private function auditFailure(string $reason, mixed $certifyId, string $timestamp, string $nonce): void
    {
        Log::warning('[实名认证回调] 签名验证失败', [
            'reason' => $reason,
            'certify_id_hash' => hash('sha256', (string) $certifyId),
            'timestamp' => $timestamp,
            'nonce_hash' => hash('sha256', $nonce),
        ]);
    }
}
