<?php

declare(strict_types=1);

namespace Caiwu\Plugins\Servers\ZjmfFinance\Lib;

use App\Exceptions\BusinessException;
use App\Models\Supplier;
use App\Services\Integrations\Support\ProviderErrorMapper;
use App\Services\Upstream\ProviderKey;
use App\Support\SensitiveDataSanitizer;
use Illuminate\Support\Facades\Log;

final class ZjmfRenewService
{
    public function __construct(
        private readonly ZjmfFinanceTransport $transport,
    ) {}

    public function getHostRenewInfo(Supplier $supplier, int $hostId, ?string $billingCycle = null): array
    {
        $query = [];
        if ($billingCycle !== null && trim($billingCycle) !== '') {
            $query['billingcycle'] = trim($billingCycle);
        }

        return $this->transport->get($supplier, "/v1/hosts/{$hostId}/renew", $this->transport->login($supplier), $query);
    }

    public function renewHost(Supplier $supplier, int $hostId, string $billingCycle): array
    {
        return $this->transport->post($supplier, "/v1/hosts/{$hostId}/renew", [
            'billingcycle' => trim($billingCycle),
        ], $this->transport->login($supplier));
    }

    public function setHostAutoRenew(Supplier $supplier, int $hostId, int $initiativeRenew): array
    {
        return $this->transport->put($supplier, "/v1/hosts/{$hostId}/renew", [
            'initiative_renew' => $initiativeRenew === 1 ? 1 : 0,
        ], $this->transport->login($supplier));
    }

    public function renewServiceInvoice(Supplier $supplier, int $hostId, string $billingCycle): array
    {
        $jwt = $this->transport->login($supplier);
        $renewResponse = $this->transport->post($supplier, "/v1/hosts/{$hostId}/renew", [
            'billingcycle' => trim($billingCycle),
        ], $jwt);
        $this->assertUpstreamSuccess($renewResponse, [200], '提交上游续费');

        $renewPayload = $this->extractPayload($renewResponse);
        $upstreamInvoiceId = $this->extractInvoiceId($renewResponse, $renewPayload);
        throw_if($upstreamInvoiceId <= 0, new BusinessException('上游未返回续费账单 ID'));

        $fundResponse = $this->fundInvoice($supplier, $upstreamInvoiceId, $jwt);
        $hostDetail = $this->readHostDetailSafely($supplier, $hostId, $jwt, $upstreamInvoiceId);

        return [
            'upstream_invoice_id' => $upstreamInvoiceId,
            'renew_response' => $renewResponse,
            'fund_response' => $fundResponse,
            'host_detail' => $hostDetail,
        ];
    }

    public function recoverRenewInvoice(Supplier $supplier, int $hostId, int $upstreamInvoiceId): ?array
    {
        if ($upstreamInvoiceId <= 0) {
            return null;
        }

        $jwt = $this->transport->login($supplier);
        $invoiceResponse = $this->transport->get($supplier, "/v1/invoices/{$upstreamInvoiceId}", $jwt);
        $invoicePayload = $this->extractPayload($invoiceResponse);
        $upstreamStatus = (string) ($invoicePayload['status'] ?? '');

        if ($upstreamStatus === 'Paid') {
            return [
                'upstream_invoice_id' => $upstreamInvoiceId,
                'upstream_status' => $upstreamStatus,
                'host_detail' => $this->readHostDetailSafely($supplier, $hostId, $jwt, $upstreamInvoiceId),
                'recovered' => true,
                'funded' => true,
            ];
        }

        if ($upstreamStatus === 'Unpaid') {
            $fundResponse = $this->fundInvoice($supplier, $upstreamInvoiceId, $jwt);

            return [
                'upstream_invoice_id' => $upstreamInvoiceId,
                'upstream_status' => $upstreamStatus,
                'fund_response' => $fundResponse,
                'host_detail' => $this->readHostDetailSafely($supplier, $hostId, $jwt, $upstreamInvoiceId),
                'recovered' => true,
                'funded' => true,
            ];
        }

        return null;
    }

    private function fundInvoice(Supplier $supplier, int $upstreamInvoiceId, string $jwt): array
    {
        $fundResponse = $this->transport->post($supplier, "/v1/invoices/{$upstreamInvoiceId}/fund", [], $jwt);
        $this->assertUpstreamSuccess($fundResponse, [200, 1001], '使用供应商余额支付续费账单');

        return $fundResponse;
    }

    private function readHostDetailSafely(Supplier $supplier, int $hostId, string $jwt, int $upstreamInvoiceId): array
    {
        try {
            $detailResponse = $this->transport->get($supplier, "/v1/hosts/{$hostId}", $jwt);
            $this->assertUpstreamSuccess($detailResponse, [200], '读取上游续费结果');
            $detailPayload = $this->extractPayload($detailResponse);

            return is_array($detailPayload['host'] ?? null) ? $detailPayload['host'] : [];
        } catch (\Throwable $exception) {
            Log::warning('[ZJMF 财务续费] 上游续费已提交，读取实例详情失败', [
                'supplier_id' => (int) $supplier->id,
                'host_id' => $hostId,
                'upstream_invoice_id' => $upstreamInvoiceId,
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            return [];
        }
    }

    private function extractPayload(array $response): array
    {
        return is_array($response['data'] ?? null) ? $response['data'] : $response;
    }

    private function extractInvoiceId(array $response, array $payload): int
    {
        return (int) ($payload['invoiceid'] ?? $response['invoiceid'] ?? 0);
    }

    private function assertUpstreamSuccess(array $response, array $allowedStatuses, string $action): void
    {
        $status = (int) ($response['status'] ?? $response['code'] ?? $response['status_code'] ?? 200);
        if (in_array($status, $allowedStatuses, true)) {
            return;
        }

        $message = trim((string) ($response['msg'] ?? $response['message'] ?? ''));
        Log::warning('[ZJMF 财务续费] 返回失败', [
            'action' => $action,
            'status' => $status,
            'message' => SensitiveDataSanitizer::sanitizeText($message),
        ]);

        throw new BusinessException(app(ProviderErrorMapper::class)->toUserMessage(ProviderKey::ZJMF_FINANCE_API, $action, $message));
    }
}
