<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client\V2;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\V2\Service\CreateHostUpgradeOrderRequest;
use App\Http\Requests\Client\V2\Service\CreateRenewOrderRequest;
use App\Http\Requests\Client\V2\Service\CreateTrafficPackageOrderRequest;
use App\Http\Requests\Client\V2\Service\QuoteHostUpgradeRequest;
use App\Http\Requests\Client\V2\Service\QuoteTrafficPackageRequest;
use App\Http\Requests\Client\V2\Service\RenewPreviewRequest;
use App\Http\Requests\Client\V2\Service\UpdateAutoRenewRequest;
use App\Models\Invoice;
use App\Services\ClientServiceConsole\ClientServiceConsoleService;
use App\Services\ClientServiceConsole\ServiceActionLock;
use App\Services\Provisioning\ServiceRenewService;
use App\Support\RequestContext;
use Illuminate\Http\Request;

class ServiceBillingController extends Controller
{
    public function __construct(
        private ClientServiceConsoleService $clientServiceConsoleService,
        private ServiceRenewService $serviceRenewService,
        private ServiceActionLock $serviceActionLock,
    ) {}

    public function renewPreview(RenewPreviewRequest $request, int $id)
    {
        $data = $request->validated();

        return $this->success(
            $this->serviceRenewService->previewForUser(
                $request->user(),
                $id,
                $data['billing_cycle'] ?? null,
                (int) ($data['user_coupon_id'] ?? 0)
            )
        );
    }

    public function trafficPackages(Request $request, int $id)
    {
        return $this->success(
            $this->clientServiceConsoleService->getTrafficPackagePreviewForUser(
                $request->user(),
                $id
            )
        );
    }

    public function quoteTrafficPackage(QuoteTrafficPackageRequest $request, int $id)
    {
        $data = $request->validated();

        return $this->success(
            $this->clientServiceConsoleService->quoteTrafficPackageForUser(
                $request->user(),
                $id,
                $data
            )
        );
    }

    public function hostUpgradePreview(Request $request, int $id)
    {
        return $this->success(
            $this->clientServiceConsoleService->getHostUpgradePreviewForUser(
                $request->user(),
                $id
            )
        );
    }

    public function quoteHostUpgrade(QuoteHostUpgradeRequest $request, int $id)
    {
        $data = $request->validated();

        return $this->success(
            $this->clientServiceConsoleService->quoteHostUpgradeForUser(
                $request->user(),
                $id,
                $data
            )
        );
    }

    public function createRenewOrder(CreateRenewOrderRequest $request, int $id)
    {
        $data = $request->validated();

        $invoice = $this->lockedAction($request, $id, 'renew_order', fn () => $this->serviceRenewService->createRenewInvoiceForUser(
            $request->user(),
            $id,
            (string) $data['billing_cycle'],
            (int) ($data['user_coupon_id'] ?? 0),
            RequestContext::forClient($request)
        ));

        return $this->success($this->invoicePayload($invoice), '续费账单创建成功');
    }

    public function createTrafficPackageOrder(CreateTrafficPackageOrderRequest $request, int $id)
    {
        $data = $request->validated();

        $invoice = $this->lockedAction($request, $id, 'traffic_package_order', fn () => $this->clientServiceConsoleService->createTrafficPackageInvoiceForUser(
            $request->user(),
            $id,
            $data,
            RequestContext::forClient($request)
        ));

        return $this->success($this->invoicePayload($invoice), '流量包账单创建成功');
    }

    public function createHostUpgradeOrder(CreateHostUpgradeOrderRequest $request, int $id)
    {
        $data = $request->validated();

        $invoice = $this->lockedAction($request, $id, 'host_upgrade_order', fn () => $this->clientServiceConsoleService->createHostUpgradeInvoiceForUser(
            $request->user(),
            $id,
            $data,
            RequestContext::forClient($request)
        ));

        return $this->success($this->invoicePayload($invoice), '产品升降级账单创建成功');
    }

    public function updateAutoRenew(UpdateAutoRenewRequest $request, int $id)
    {
        $data = $request->validated();

        return $this->success(
            $this->lockedAction($request, $id, 'renew_auto', fn () => $this->serviceRenewService->updateAutoRenewForUser(
                $request->user(),
                $id,
                (int) $data['auto_renew'],
                RequestContext::forClient($request)
            )),
            '自动续费状态已更新'
        );
    }

    private function lockedAction(Request $request, int $id, string $action, callable $callback): mixed
    {
        return $this->serviceActionLock->execute(
            (int) ($request->user()?->id ?? 0),
            $id,
            $action,
            $callback
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function invoicePayload(Invoice $invoice): array
    {
        $invoice->loadMissing(['product:id,product_type,product_group_id,service_type_code,config_options,purchase_requires', 'service']);

        return [
            'id' => (int) $invoice->id,
            'invoice_no' => (string) $invoice->invoice_no,
            'service_id' => (int) ($invoice->service_id ?? 0),
        ];
    }
}
