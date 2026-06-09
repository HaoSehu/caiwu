<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\VerifyCallbackSignature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CallbackSignatureMiddlewareTest extends TestCase
{
    public function test_verification_callback_accepts_hmac_signature_once(): void
    {
        config()->set('idc.verification.key', 'callback-test-key');
        Cache::flush();

        $payload = $this->signedPayload(['certify_id' => 'CERT-HMAC-001'], 'nonce-001');
        $request = Request::create('/api/client/verification/callback', 'POST', $payload);

        $response = (new VerifyCallbackSignature())->handle($request, fn () => response('ok'));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_verification_callback_rejects_replayed_nonce(): void
    {
        config()->set('idc.verification.key', 'callback-test-key');
        Cache::flush();

        $payload = $this->signedPayload(['certify_id' => 'CERT-HMAC-REPLAY'], 'nonce-replay');
        $middleware = new VerifyCallbackSignature();

        $middleware->handle(Request::create('/api/client/verification/callback', 'POST', $payload), fn () => response('ok'));
        $response = $middleware->handle(Request::create('/api/client/verification/callback', 'POST', $payload), fn () => response('ok'));

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame('回调请求已处理，请勿重复提交', $response->getData(true)['message'] ?? '');
    }

    public function test_verification_callback_rejects_expired_timestamp(): void
    {
        config()->set('idc.verification.key', 'callback-test-key');
        Cache::flush();

        $payload = $this->signedPayload(['certify_id' => 'CERT-HMAC-EXPIRED'], 'nonce-expired', now()->subMinutes(10)->timestamp);
        $response = (new VerifyCallbackSignature())->handle(
            Request::create('/api/client/verification/callback', 'POST', $payload),
            fn () => response('ok')
        );

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('签名验证失败', $response->getData(true)['message'] ?? '');
    }

    public function test_verification_callback_rejects_invalid_signature(): void
    {
        config()->set('idc.verification.key', 'callback-test-key');
        Cache::flush();

        $payload = $this->signedPayload(['certify_id' => 'CERT-HMAC-BAD'], 'nonce-bad');
        $payload['sign'] = str_repeat('0', 64);
        $response = (new VerifyCallbackSignature())->handle(
            Request::create('/api/client/verification/callback', 'POST', $payload),
            fn () => response('ok')
        );

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('签名验证失败', $response->getData(true)['message'] ?? '');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function signedPayload(array $payload, string $nonce, ?int $timestamp = null): array
    {
        $payload['timestamp'] = (string) ($timestamp ?? now()->timestamp);
        $payload['nonce'] = $nonce;
        $payload['sign'] = hash_hmac('sha256', $this->canonicalPayload($payload), 'callback-test-key');

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function canonicalPayload(array $payload): string
    {
        unset($payload['sign'], $payload['signature']);
        $this->ksortRecursive($payload);

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    /**
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
}
