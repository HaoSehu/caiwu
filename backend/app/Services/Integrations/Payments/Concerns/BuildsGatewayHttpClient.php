<?php

declare(strict_types=1);

namespace App\Services\Integrations\Payments\Concerns;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * 支付网关客户端的 HTTP 出站构建样板。
 *
 * 各网关客户端对上游网关的出站请求统一为「表单编码 + 15 秒超时 + 失败重试 1 次
 * （间隔 200ms）」，只有 TLS 校验选项随各家配置不同（易支付固定校验、支付宝
 * 受 ssl_verify/ca_bundle 控制）。超时与重试数值不得随意调整：它们是对外承诺的
 * 出站请求预算，与各网关历史行为一一对应。
 */
trait BuildsGatewayHttpClient
{
    /**
     * 构建访问支付网关的 HTTP 客户端。
     */
    private function buildHttpClient(): PendingRequest
    {
        return Http::asForm()
            ->withOptions(['verify' => $this->httpClientVerifyOption()])
            ->timeout(15)
            ->retry(1, 200);
    }

    /**
     * HTTP 出站的 Guzzle verify 选项；默认始终校验证书。
     * 需要 CA 证书包或可关闭校验的网关客户端覆盖本方法。
     */
    protected function httpClientVerifyOption(): bool|string
    {
        return true;
    }

    /**
     * 由布尔开关与 CA 证书路径推导 verify 选项：
     * 关闭校验返回 false；配置了存在的 CA 证书文件则使用该文件；否则走系统默认校验。
     */
    protected function resolveGatewaySslVerifyOption(bool $sslVerify, string $caBundle): bool|string
    {
        if (! $sslVerify) {
            return false;
        }

        if ($caBundle !== '' && is_file($caBundle)) {
            return $caBundle;
        }

        return true;
    }
}
