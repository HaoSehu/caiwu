<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ApiResponseBuilder;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyCallbackSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $signature = $request->header('X-Signature') ?? $request->input('sign', '');
        $certifyId = $request->input('certify_id', $request->input('order_no', ''));

        if ($signature === '' || $certifyId === '') {
            return ApiResponseBuilder::error(40001, '签名验证失败', null, 401);
        }

        $key = config('idc.verification.key');
        $expectedSign = md5($certifyId.$key);

        if (! hash_equals($expectedSign, $signature)) {
            return ApiResponseBuilder::error(40001, '签名验证失败', null, 401);
        }

        return $next($request);
    }
}
