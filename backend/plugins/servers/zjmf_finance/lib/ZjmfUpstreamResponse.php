<?php

declare(strict_types=1);

namespace Caiwu\Plugins\Servers\ZjmfFinance\Lib;

use App\Exceptions\BusinessException;
use App\Exceptions\UpstreamStockShortageException;
use App\Services\Integrations\Support\ProviderErrorMapper;
use App\Services\Upstream\ProviderKey;
use Illuminate\Support\Facades\Log;

/**
 * 上游响应契约与失败断言（从 ZjmfProvisionService 抽出的无状态协作器）。
 *
 * 魔方财务的响应体有两种形态：业务状态码在 status/code/status_code，
 * 而 HTTP 状态只在传输层 meta 里。开通链路任何一步都不能把
 * 「HTTP 5xx + 无业务状态码」的响应当成成功，因此这里统一在响应体里
 * 打上 http_code 标记（UPSTREAM_HTTP_CODE_META_KEY），断言时先看 HTTP
 * 再看业务码，失败统一走 ProviderErrorMapper 翻译成用户可读文案。
 */
final class ZjmfUpstreamResponse
{
    /** 传输层 HTTP 状态在响应体中的携带键。 */
    public const HTTP_CODE_META_KEY = '_zjmf_upstream_http_code';

    /**
     * 去掉信封：data 是数组时取 data，否则响应体本身就是载荷。
     */
    public static function payload(array $response): array
    {
        return is_array($response['data'] ?? null) ? $response['data'] : $response;
    }

    /**
     * 账单 ID：载荷优先，其次信封，最后 invoice 子对象。
     */
    public static function invoiceId(array $response, array $payload): int
    {
        $invoice = is_array($payload['invoice'] ?? null) ? $payload['invoice'] : [];

        return (int) (
            $payload['invoiceid']
            ?? $payload['invoice_id']
            ?? $invoice['id']
            ?? $response['invoiceid']
            ?? $response['invoice_id']
            ?? 0
        );
    }

    /**
     * 购物车位置：部分魔方财务版本的标准结算接口不返回位置 i，
     * 加购前已在供应商级锁内清空购物车，回退用位置 0 是安全的。
     */
    public static function cartPosition(array $response, array $payload): int
    {
        foreach ([
            $payload['i'] ?? null,
            $payload['position'] ?? null,
            $response['i'] ?? null,
            $response['position'] ?? null,
            $response['data'] ?? null,
        ] as $candidate) {
            if (is_numeric($candidate) && (int) $candidate >= 0) {
                return (int) $candidate;
            }
        }

        return 0;
    }

    /**
     * 实例 ID 列表：兼容 hostid/host_id/hostids/host_ids 四种键，过滤非正整数。
     *
     * @return array<int, int>
     */
    public static function hostIds(array $response, array $payload): array
    {
        $hostIds = $payload['hostid']
            ?? $payload['host_id']
            ?? $payload['hostids']
            ?? $payload['host_ids']
            ?? $response['hostid']
            ?? $response['host_id']
            ?? $response['hostids']
            ?? $response['host_ids']
            ?? [];

        if (! is_array($hostIds)) {
            $hostIds = [$hostIds];
        }

        return array_values(array_filter(array_map(
            fn ($value) => is_numeric($value) && (int) $value > 0 ? (int) $value : null,
            $hostIds
        )));
    }

    /**
     * 业务状态码（status/code/status_code 三选一，缺省 0）。
     */
    public static function status(array $response): int
    {
        return (int) ($response['status'] ?? $response['code'] ?? $response['status_code'] ?? 0);
    }

    /**
     * 账单支付状态（小写，如 paid/unpaid/refunded）。
     *
     * 上游 /v1/invoices/{id} 把它嵌在 data.invoices.status，而顶层 status 是业务码
     * 200——只读顶层会恒得到 "200"，已付账单被当成未付。这里按
     * invoices.status → invoice.status → 顶层 status（非数字时）的顺序兼容取，
     * 顶层是数字业务码时一律视为缺失，避免把 200 当成支付状态。
     */
    public static function invoiceStatus(array $response, array $payload): string
    {
        foreach ([
            $payload['invoices']['status'] ?? null,
            $payload['invoice']['status'] ?? null,
            $payload['status'] ?? null,
            $response['invoices']['status'] ?? null,
        ] as $candidate) {
            if (! is_string($candidate)) {
                continue;
            }

            $normalized = strtolower(trim($candidate));

            // 纯数字是业务码（200/1001），不是支付状态。
            if ($normalized !== '' && ! is_numeric($normalized)) {
                return $normalized;
            }
        }

        return '';
    }

    /**
     * 保留底层 HTTP 状态，避免上游 4xx/5xx JSON 缺少业务状态码时被误判为成功。
     */
    public static function withHttpCode(array $meta): array
    {
        $response = is_array($meta['response'] ?? null) ? $meta['response'] : [];
        $response[self::HTTP_CODE_META_KEY] = (int) ($meta['http_code'] ?? 0);

        return $response;
    }

    /**
     * 断言上游成功：先看 HTTP 状态，再看业务状态码。
     *
     * 响应体完全没有业务状态码键时视为无状态可判（历史口径放行），
     * 一旦出现 status/code/status_code 就必须命中允许列表。
     *
     * @param  array<int, int>  $allowedStatuses
     */
    public static function assertSuccess(array $response, array $allowedStatuses, string $action): void
    {
        if (array_key_exists(self::HTTP_CODE_META_KEY, $response)
            && ! self::hasSuccessHttpStatus($response)) {
            self::throwFailure($response, $action);
        }

        $hasStatus = array_key_exists('status', $response)
            || array_key_exists('code', $response)
            || array_key_exists('status_code', $response);

        if (! $hasStatus) {
            return;
        }

        if (in_array(self::status($response), $allowedStatuses, true)) {
            return;
        }

        self::throwFailure($response, $action);
    }

    /**
     * 传输层是否 2xx。
     */
    public static function hasSuccessHttpStatus(array $response): bool
    {
        $httpCode = (int) ($response[self::HTTP_CODE_META_KEY] ?? 0);

        return $httpCode >= 200 && $httpCode < 300;
    }

    /**
     * 上游失败出口：落日志、翻译文案，库存类文案抛分类异常。
     */
    public static function throwFailure(array $response, string $action): never
    {
        $status = self::status($response);
        $message = trim((string) ($response['msg'] ?? $response['message'] ?? ''));
        Log::warning('[ZJMF 财务开通] 返回失败', [
            'action' => $action,
            'status' => $status,
            'http_code' => $response[self::HTTP_CODE_META_KEY] ?? null,
            'message' => $message,
        ]);

        $userMessage = app(ProviderErrorMapper::class)->toUserMessage(ProviderKey::ZJMF_FINANCE_API, $action, $message);

        // 上游加购/结算的库存校验是唯一真实库存口径；以「库存不足」拒绝时抛分类异常，
        // 供开通编排层把本地陈旧快照（prodetail.qty 展示口径）校准归零并写售罄覆盖。
        // 词表真源在 UpstreamStockShortageException::matches，勿在此处另立文案清单。
        if (self::isStockShortageMessage($message)) {
            throw new UpstreamStockShortageException($userMessage);
        }

        throw new BusinessException($userMessage);
    }

    /**
     * 是否属于「库存不足」类文案。
     */
    public static function isStockShortageMessage(string $message): bool
    {
        return UpstreamStockShortageException::matches($message);
    }

    /**
     * 实例详情为空时不得标记开通成功。
     */
    public static function assertHostDetailPresent(array $hostDetail, int $hostId): void
    {
        if ($hostDetail !== []) {
            return;
        }

        Log::warning('[ZJMF 财务开通] 上游实例详情为空，已停止标记开通成功', [
            'upstream_host_id' => $hostId,
        ]);

        throw new BusinessException('上游未返回实例详情，无法确认开通结果，请稍后重试或先核实上游实例');
    }
}
