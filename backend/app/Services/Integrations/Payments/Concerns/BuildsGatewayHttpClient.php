<?php

declare(strict_types=1);

namespace App\Services\Integrations\Payments\Concerns;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * 支付网关客户端的 HTTP 出站构建样板。
 *
 * 各网关客户端对上游网关的出站请求统一为「表单编码 + 15 秒超时」，查询类请求
 * 额外失败重试 1 次（间隔 200ms）。超时与重试数值不得随意调整：它们是对外承诺的
 * 出站请求预算，与各网关历史行为一一对应。
 *
 * 有副作用的写请求（precreate/refund 等 POST）默认不带重试：连接异常时无法确认
 * 请求是否已到达网关，重试存在重复提交风险；查询类请求无副作用，保留重试以
 * 吸收瞬时网络抖动。
 */
trait BuildsGatewayHttpClient
{
    /**
     * 构建访问支付网关的 HTTP 客户端。
     *
     * @param  bool  $retryOnFailure  是否对连接失败自动重试 1 次；写请求传 false
     */
    private function buildHttpClient(bool $retryOnFailure = true): PendingRequest
    {
        $client = Http::asForm()
            ->withOptions(['verify' => $this->httpClientVerifyOption()])
            ->timeout(15);

        return $retryOnFailure ? $client->retry(1, 200) : $client;
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
