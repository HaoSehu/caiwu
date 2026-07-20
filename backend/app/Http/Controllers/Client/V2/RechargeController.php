<?php

namespace App\Http\Controllers\Client\V2;

use App\Constants\PaymentGatewayCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\V2\Recharge\StatusRequest;
use App\Http\Requests\Client\V2\Recharge\StoreRequest;
use App\Models\Payment;
use App\Services\Finance\CheckoutSecurityService;
use App\Services\Finance\PaymentService;
use App\Services\Integrations\Payments\PaymentGatewayManager;

class RechargeController extends Controller
{
    public function __construct(
        private PaymentService $paymentService,
        private CheckoutSecurityService $checkoutSecurityService,
        private PaymentGatewayManager $paymentGatewayManager,
    ) {}

    public function gateways()
    {
        return $this->success([
            'list' => $this->availableGatewayOptions(),
        ]);
    }

    /**
     * 创建充值订单（第三方支付预下单）
     */
    public function store(StoreRequest $request)
    {
        $data = $request->validated();
        $availableGatewayOptions = $this->availableGatewayOptions();
        $gateway = trim((string) ($data['gateway'] ?? ''));
        $paymentType = trim((string) ($data['payment_type'] ?? ''));
        if ($gateway === '') {
            $firstOption = $availableGatewayOptions[0] ?? [];
            $gateway = (string) ($firstOption['key'] ?? '');
            $paymentType = (string) ($firstOption['payment_type'] ?? '');
        }

        $selectedOption = $this->findGatewayOption($availableGatewayOptions, $gateway, $paymentType);
        if (! $selectedOption) {
            return $this->error(42200, '当前没有可用支付方式，请联系管理员开启支付渠道');
        }

        $paymentType = (string) ($selectedOption['payment_type'] ?? '');
        $gatewayContext = $paymentType !== '' ? ['payment_type' => $paymentType] : [];
        $result = $this->paymentService->rechargeByGateway(
            $request->user(),
            (float) $data['amount'],
            $gateway,
            $gatewayContext
        );

        $payment = Payment::query()
            ->where('payment_no', (string) ($result['payment_no'] ?? ''))
            ->where('user_id', $request->user()->id)
            ->whereGatewayKey($gateway)
            ->whereNull('invoice_id')
            ->first();

        if (! $payment) {
            return $this->error(40400, '充值支付记录不存在');
        }

        $gatewayPayload = [
            'gateway' => $gateway,
            'gateway_key' => $gateway,
            'gateway_label' => (string) ($selectedOption['name'] ?? PaymentGatewayCode::label($gateway)),
        ];

        if ($paymentType !== '') {
            $gatewayPayload['payment_type'] = $paymentType;
            $gatewayPayload['payment_type_label'] = (string) ($selectedOption['label'] ?? '');
        }

        return $this->success(
            array_merge(
                $gatewayPayload,
                $result,
                $this->checkoutSecurityService->issueRechargePollToken($payment, (int) $request->user()->id, $request->ip())
            ),
            '充值二维码已生成'
        );
    }

    /**
     * 轮询充值状态
     */
    public function status(StatusRequest $request, string $paymentNo)
    {
        $data = $request->validated();

        $payment = Payment::where('payment_no', $paymentNo)
            ->where('user_id', $request->user()->id)
            ->whereGatewayKeyIn(PaymentGatewayCode::thirdPartyGateways())
            ->firstOrFail();

        $payment = $this->paymentService->cancelExpiredPendingRecharge($payment, [
            'actor_type' => 'system',
            'actor_name' => 'recharge-status-poll',
            'reason' => 'payment_window_expired',
        ]);

        $this->checkoutSecurityService->assertRechargePollToken(
            (string) $data['poll_token'],
            $payment,
            (int) $request->user()->id,
            $request->ip()
        );

        $result = $this->paymentService->queryRechargeStatus($payment);

        if ($result['paid']) {
            $request->user()->refresh();
            $result['balance'] = number_format((float) $request->user()->balance, 2, '.', '');
        }

        return $this->success($result);
    }

    private function availableGatewayOptions(): array
    {
        return $this->paymentGatewayManager->availableThirdPartyGatewayOptions();
    }

    /**
     * @param  array<int, array<string, mixed>>  $options
     * @return array<string, mixed>|null
     */
    private function findGatewayOption(array $options, string $gateway, string $paymentType): ?array
    {
        foreach ($options as $option) {
            if ((string) ($option['key'] ?? '') !== $gateway) {
                continue;
            }

            $optionPaymentType = trim((string) ($option['payment_type'] ?? ''));
            if ($paymentType !== '' && $paymentType !== $optionPaymentType) {
                continue;
            }

            return $option;
        }

        return null;
    }
}
