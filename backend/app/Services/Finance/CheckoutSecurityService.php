<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Constants\InvoiceStatus;
use App\Constants\InvoiceType;
use App\Exceptions\BusinessException;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Support\Cache\VolatileStore;
use App\Support\CacheTtl;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class CheckoutSecurityService
{
    private const QUOTE_TTL_SECONDS = CacheTtl::CHECKOUT_QUOTE_SECONDS;

    private const ORDER_IDEMPOTENCY_TTL_SECONDS = CacheTtl::CHECKOUT_ORDER_IDEMPOTENCY_SECONDS;

    private const ORDER_FINGERPRINT_TTL_SECONDS = CacheTtl::CHECKOUT_ORDER_FINGERPRINT_SECONDS;

    private const PAYMENT_SESSION_TTL_SECONDS = CacheTtl::CHECKOUT_PAYMENT_SESSION_SECONDS;

    /**
     * 所有 checkout 临时数据使用 volatile store（Redis DB 2），与业务缓存隔离。
     */
    private function volatileStore()
    {
        return VolatileStore::store();
    }

    public function issueQuoteToken(
        int $productId,
        string $billingCycle,
        array $config,
        array $quotePayload,
        array $context = [],
    ): array {
        $now = CarbonImmutable::now();
        $expiresAt = $now->addSeconds(self::QUOTE_TTL_SECONDS);
        $token = $this->generateToken('quote');

        $this->volatileStore()->put($this->quoteCacheKey($token), [
            'product_id' => $productId,
            'billing_cycle' => $billingCycle,
            'quantity' => max((int) ($quotePayload['quantity'] ?? 1), 1),
            'config_hash' => $this->hashPayload($config),
            'original_amount' => Money::format($quotePayload['subtotal_amount'] ?? $quotePayload['total_amount'] ?? 0),
            'amount' => Money::format($quotePayload['total_amount'] ?? 0),
            'base_amount' => Money::format($quotePayload['base_amount'] ?? 0),
            'config_amount' => Money::format($quotePayload['config_amount'] ?? 0),
            'setup_fee' => Money::format($quotePayload['setup_fee'] ?? 0),
            'discount_amount' => Money::format($quotePayload['discount_amount'] ?? 0),
            'member_discount_amount' => Money::format($quotePayload['member_discount_amount'] ?? 0),
            'user_coupon_id' => (int) ($quotePayload['coupon']['user_coupon_id'] ?? $quotePayload['user_coupon_id'] ?? 0),
            'user_id' => max((int) ($context['user_id'] ?? 0), 0),
            'request_id' => trim((string) ($context['request_id'] ?? '')),
            'ip_address' => trim((string) ($context['ip_address'] ?? '')),
            'issued_at' => $now->toIso8601String(),
            'expires_at' => $expiresAt->toIso8601String(),
        ], $expiresAt);

        return [
            'quote_token' => $token,
            'quote_expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    public function assertQuoteToken(
        string $token,
        int $productId,
        string $billingCycle,
        int $quantity,
        array $config,
        string $originalAmount,
        string $amount,
        ?int $couponId = null,
        ?string $memberDiscountAmount = null,
        int $userId = 0,
    ): array {
        $payload = $this->volatileStore()->get($this->quoteCacheKey($token));

        throw_if(! is_array($payload), new BusinessException('报价凭证已失效，请刷新配置后重试'));

        $tokenProductId = (int) ($payload['product_id'] ?? 0);
        $tokenBillingCycle = (string) ($payload['billing_cycle'] ?? '');
        $tokenQuantity = max((int) ($payload['quantity'] ?? 1), 1);
        $tokenConfigHash = (string) ($payload['config_hash'] ?? '');
        $tokenOriginalAmount = Money::format($payload['original_amount'] ?? 0);
        $tokenAmount = Money::format($payload['amount'] ?? 0);
        $tokenCouponId = (int) ($payload['user_coupon_id'] ?? 0);
        // 旧令牌无此键时按 0 处理，与未生效会员折扣的请求天然一致
        $tokenMemberDiscountAmount = Money::format($payload['member_discount_amount'] ?? 0);

        throw_if(
            $tokenProductId !== $productId
            || $tokenBillingCycle !== $billingCycle
            || $tokenQuantity !== max($quantity, 1)
            || $tokenConfigHash !== $this->hashPayload($config),
            new BusinessException('订单配置与报价不一致，请重新获取报价')
        );

        throw_if(
            $tokenOriginalAmount !== Money::format($originalAmount)
            || $tokenCouponId !== (int) ($couponId ?? 0)
            || $tokenAmount !== Money::format($amount),
            new BusinessException('报价已变更，请刷新页面后重试')
        );

        throw_if(
            $tokenMemberDiscountAmount !== Money::format((float) ($memberDiscountAmount ?? 0)),
            new BusinessException('会员折扣已变更，请刷新页面后重试', 40305)
        );

        // 报价令牌绑定签发用户：登录态签发的令牌不可跨用户复用；游客报价（user_id=0）仍受金额与配置绑定约束
        $tokenUserId = (int) ($payload['user_id'] ?? 0);
        throw_if(
            $tokenUserId > 0 && $tokenUserId !== $userId,
            new BusinessException('报价凭证与当前用户不一致，请重新获取报价')
        );

        return $payload;
    }

    public function buildCheckoutFingerprint(int $productId, string $billingCycle, int $quantity, array $config, int $couponId = 0): string
    {
        return hash('sha256', implode('|', [
            $productId,
            $billingCycle,
            max($quantity, 1),
            $this->hashPayload($config),
            (int) $couponId,
        ]));
    }

    public function rememberCreatedInvoice(
        int $userId,
        string $idempotencyKey,
        string $fingerprint,
        int $invoiceId,
    ): void {
        $idempotencyExpiresAt = CarbonImmutable::now()->addSeconds(self::ORDER_IDEMPOTENCY_TTL_SECONDS);
        $fingerprintExpiresAt = CarbonImmutable::now()->addSeconds(self::ORDER_FINGERPRINT_TTL_SECONDS);

        $this->volatileStore()->put($this->invoiceIdempotencyCacheKey($userId, $idempotencyKey), $invoiceId, $idempotencyExpiresAt);
        $this->volatileStore()->put($this->invoiceFingerprintCacheKey($userId, $fingerprint), $invoiceId, $fingerprintExpiresAt);
    }

    public function resolveIdempotentInvoiceId(int $userId, string $idempotencyKey): ?int
    {
        $invoiceId = (int) $this->volatileStore()->get($this->invoiceIdempotencyCacheKey($userId, $idempotencyKey), 0);

        return $invoiceId > 0 ? $invoiceId : null;
    }

    public function resolveFingerprintInvoiceId(int $userId, string $fingerprint): ?int
    {
        $invoiceId = (int) $this->volatileStore()->get($this->invoiceFingerprintCacheKey($userId, $fingerprint), 0);

        return $invoiceId > 0 ? $invoiceId : null;
    }

    public static function paymentSessionTtlSeconds(): int
    {
        return self::PAYMENT_SESSION_TTL_SECONDS;
    }

    /**
     * 支付窗口截止时间唯一计算入口：按 due_date 计费的账单（续费账单、管理员挂账单）及其影子订单
     * 跟随账期，其余记录按 5 分钟支付会话窗口。
     *
     * 续费账单建单后要承载到期前 1/3/5 天提醒与逾期催收，若沿用 5 分钟窗口，
     * 账单会被请求路径懒清理与定时清理当成过期单取消，催收提醒永不可达、
     * 用户点开提醒落地页即见"已取消"，续费链路被阻断。
     * 管理员挂账单同样是"按账期收款"的账单（创建时不扣余额、保持待支付等用户付款），
     * 5 分钟窗口会让用户在账期内根本无法完成支付。
     */
    public function paymentSessionExpiresAt(Invoice|Order $record): CarbonImmutable
    {
        $dueDateWindowEnd = $this->dueDateWindowEnd($record);
        if ($dueDateWindowEnd instanceof CarbonImmutable) {
            return $dueDateWindowEnd;
        }

        $createdAt = $record->created_at;
        $base = $createdAt instanceof \DateTimeInterface
            ? CarbonImmutable::instance($createdAt)
            : ($createdAt ? CarbonImmutable::parse((string) $createdAt) : CarbonImmutable::now());

        return $base->addSeconds(self::PAYMENT_SESSION_TTL_SECONDS);
    }

    /**
     * 账单过期清理的候选集 SQL 约束：请求路径懒清理与定时任务共用同一处口径。
     *
     * 按 due_date 计窗口的续费账单在账期内不会过期，先用 SQL 把它们剔出候选集，
     * 避免每次扫描都把账期内续费账单（定时任务还带 product 预载）逐条装载进内存再判定。
     * due_date 已过（早于今天）的续费账单仍留在候选集内，由正常清理路径按同一判据关闭。
     * 管理员挂账单的标记在 config_snapshot JSON 内：MySQL 可以用 JSON_EXTRACT 判定，
     * 但该表达式无法命中索引（每次都要回表逐行求值），因此这里不做 SQL 收窄，仍留在候选集，
     * 由 CheckoutService::cancelExpiredUnpaidInvoice() 的 admin_manual 豁免拒绝。
     */
    public function applyExpiredInvoiceSweepScope(Builder $query): void
    {
        $today = CarbonImmutable::now()->toDateString();

        $query->where(function (Builder $builder) use ($today): void {
            $builder->where('type', '!=', InvoiceType::RENEW)
                ->orWhereNull('due_date')
                ->orWhereDate('due_date', '<', $today);
        });
    }

    /**
     * 订单过期清理的候选集 SQL 约束：与账单侧同源。
     *
     * 影子续费订单跟随关联账单按 due_date 计窗口，剔除「关联账单是续费且账期未过」的订单；
     * 挂账订单的标记同样在 config_snapshot JSON 内，可用 JSON_EXTRACT 判定但不可索引，
     * 因此不做 SQL 收窄，仍留在候选集，由 OrderService::cancelExpiredPendingOrder()
     * 的 admin_manual 豁免拒绝。
     */
    public function applyExpiredOrderSweepScope(Builder $query): void
    {
        $today = CarbonImmutable::now()->toDateString();

        $query->whereDoesntHave('invoice', function (Builder $invoiceQuery) use ($today): void {
            $invoiceQuery->where('type', InvoiceType::RENEW)
                ->whereDate('due_date', '>=', $today);
        });
    }

    /**
     * 按 due_date（账期截止日）计费的窗口结束时间：续费账单、管理员挂账单及其影子订单。
     *
     * 以 due_date 当日 23:59:59 为界，与逾期提醒口径（due_date 早于今天才算逾期）保持一致；
     * 订单表没有 due_date 列，影子订单跟随关联账单。
     */
    private function dueDateWindowEnd(Invoice|Order $record): ?CarbonImmutable
    {
        $invoice = $record instanceof Invoice
            ? $record
            : ($record->relationLoaded('invoice') ? $record->invoice : $record->invoice()->first());

        if (! $invoice instanceof Invoice || ! $this->isDueDateBilledInvoice($invoice)) {
            return null;
        }

        $dueDate = $invoice->due_date;
        if ($dueDate === null) {
            return null;
        }

        // due_date 为 date cast，统一按字符串解析（与催收提醒链路口径一致：due_date 当日 23:59:59 截止）
        return CarbonImmutable::parse((string) $dueDate)->endOfDay();
    }

    /**
     * 按 due_date 计费的账单口径唯一判断：系统续费账单，或管理端手动开通/补录的挂账单。
     */
    private function isDueDateBilledInvoice(Invoice $invoice): bool
    {
        return (string) $invoice->type === InvoiceType::RENEW || $this->isAdminManualInvoice($invoice);
    }

    /**
     * 管理员挂账单判断：创建时 config_snapshot 打了 admin_manual 标记
     * （createDirect 直接写入，createFromOrder 从订单快照继承）。
     * 挂账单不参与支付会话窗口自动清理，与订单侧 OrderService::isAdminManualOrder 同口径。
     */
    public function isAdminManualInvoice(Invoice $invoice): bool
    {
        $snapshot = $invoice->config_snapshot;

        return is_array($snapshot) && filter_var($snapshot['admin_manual'] ?? false, FILTER_VALIDATE_BOOL);
    }

    public function paymentRecordExpiresAt(Payment $payment): CarbonImmutable
    {
        $createdAt = $payment->created_at;
        $base = $createdAt instanceof \DateTimeInterface
            ? CarbonImmutable::instance($createdAt)
            : ($createdAt ? CarbonImmutable::parse((string) $createdAt) : CarbonImmutable::now());

        return $base->addSeconds(self::PAYMENT_SESSION_TTL_SECONDS);
    }

    public function isPaymentSessionExpired(Invoice|Order $record): bool
    {
        return $this->paymentSessionExpiresAt($record)->lessThanOrEqualTo(CarbonImmutable::now());
    }

    public function issueInvoicePaymentSession(Invoice $invoice, int $userId): array
    {
        $payableAmount = $invoice->payableAmount();
        if ((int) $invoice->status !== InvoiceStatus::UNPAID) {
            return [
                'session_token' => '',
                'expires_at' => null,
            ];
        }

        $now = CarbonImmutable::now();
        $expiresAt = $this->paymentSessionExpiresAt($invoice);
        if ($expiresAt->lessThanOrEqualTo($now)) {
            return [
                'session_token' => '',
                'expires_at' => $expiresAt->toIso8601String(),
            ];
        }
        $token = $this->generateToken('inv_session');

        $this->volatileStore()->put($this->invoicePaymentSessionCacheKey($token), [
            'invoice_id' => (int) $invoice->id,
            'user_id' => $userId,
            'invoice_status' => (int) $invoice->status,
            'payable_amount' => Money::format($payableAmount),
            'issued_at' => $now->toIso8601String(),
            'expires_at' => $expiresAt->toIso8601String(),
        ], $expiresAt);

        return [
            'session_token' => $token,
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    public function assertInvoicePaymentSessionToken(string $token, Invoice $invoice, int $userId): array
    {
        $this->assertPaymentSessionWindowOpen($invoice);

        $payload = $this->volatileStore()->get($this->invoicePaymentSessionCacheKey($token));

        throw_if(! is_array($payload), new BusinessException('支付会话已失效，请刷新页面后重试'));

        $currentPayableAmount = Money::format($invoice->payableAmount());

        throw_if(
            (int) ($payload['invoice_id'] ?? 0) !== (int) $invoice->id
            || (int) ($payload['user_id'] ?? 0) !== $userId
            || (string) ($payload['payable_amount'] ?? '') !== $currentPayableAmount,
            new BusinessException('支付会话校验失败，请刷新页面后重试')
        );

        return $payload;
    }

    public function issueInvoicePaymentPollToken(Payment $payment, Invoice $invoice, int $userId, string $clientIp): array
    {
        throw_if(trim($clientIp) === '', new BusinessException('无法获取客户端网络信息，请刷新页面重试'));

        $now = CarbonImmutable::now();
        $expiresAt = $this->paymentSessionExpiresAt($invoice);
        $token = $this->generateToken('inv_poll');

        $this->volatileStore()->put($this->invoicePaymentPollCacheKey($token), [
            'payment_id' => (int) $payment->id,
            'payment_no' => (string) $payment->payment_no,
            'invoice_id' => (int) $invoice->id,
            'user_id' => $userId,
            'gateway' => $payment->gatewayKey(),
            'client_ip_hash' => $this->hashClientIp($clientIp),
            'issued_at' => $now->toIso8601String(),
            'expires_at' => $expiresAt->toIso8601String(),
        ], $expiresAt);

        return [
            'poll_token' => $token,
            'poll_expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    public function assertInvoicePaymentPollToken(string $token, Payment $payment, Invoice $invoice, int $userId, string $clientIp = ''): array
    {
        $payload = $this->volatileStore()->get($this->invoicePaymentPollCacheKey($token));

        throw_if(! is_array($payload), new BusinessException('支付轮询凭证已失效，请重新获取二维码'));

        throw_if(
            (int) ($payload['payment_id'] ?? 0) !== (int) $payment->id
            || (string) ($payload['payment_no'] ?? '') !== (string) $payment->payment_no
            || (int) ($payload['invoice_id'] ?? 0) !== (int) $invoice->id
            || (int) ($payload['user_id'] ?? 0) !== $userId
            || ! $this->verifyClientIp($payload, $clientIp),
            new BusinessException('支付轮询凭证校验失败，请重新获取二维码')
        );

        return $payload;
    }

    public function issueRechargePollToken(Payment $payment, int $userId, string $clientIp): array
    {
        throw_if(trim($clientIp) === '', new BusinessException('无法获取客户端网络信息，请刷新页面重试'));

        $now = CarbonImmutable::now();
        $expiresAt = $this->paymentRecordExpiresAt($payment);
        $token = $this->generateToken('recharge_poll');

        $this->volatileStore()->put($this->rechargePollCacheKey($token), [
            'payment_id' => (int) $payment->id,
            'payment_no' => (string) $payment->payment_no,
            'user_id' => $userId,
            'gateway' => $payment->gatewayKey(),
            'client_ip_hash' => $this->hashClientIp($clientIp),
            'issued_at' => $now->toIso8601String(),
            'expires_at' => $expiresAt->toIso8601String(),
        ], $expiresAt);

        return [
            'poll_token' => $token,
            'poll_expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    public function assertRechargePollToken(string $token, Payment $payment, int $userId, string $clientIp = ''): array
    {
        $payload = $this->volatileStore()->get($this->rechargePollCacheKey($token));

        throw_if(! is_array($payload), new BusinessException('充值轮询凭证已失效，请重新获取二维码'));

        throw_if(
            (int) ($payload['payment_id'] ?? 0) !== (int) $payment->id
            || (string) ($payload['payment_no'] ?? '') !== (string) $payment->payment_no
            || (int) ($payload['user_id'] ?? 0) !== $userId
            || ! $this->verifyClientIp($payload, $clientIp),
            new BusinessException('充值轮询凭证校验失败，请重新获取二维码')
        );

        return $payload;
    }

    private function quoteCacheKey(string $token): string
    {
        return 'checkout:quote:'.$token;
    }

    private function invoiceIdempotencyCacheKey(int $userId, string $idempotencyKey): string
    {
        return 'checkout:invoice:idempotency:'.$userId.':'.sha1($idempotencyKey);
    }

    private function invoiceFingerprintCacheKey(int $userId, string $fingerprint): string
    {
        return 'checkout:invoice:fingerprint:'.$userId.':'.$fingerprint;
    }

    private function rechargePollCacheKey(string $token): string
    {
        return 'checkout:recharge:poll:'.$token;
    }

    private function invoicePaymentSessionCacheKey(string $token): string
    {
        return 'checkout:invoice:session:'.$token;
    }

    private function invoicePaymentPollCacheKey(string $token): string
    {
        return 'checkout:invoice:poll:'.$token;
    }

    private function generateToken(string $prefix): string
    {
        return $prefix.'_'.Str::lower(Str::random(48));
    }

    private function assertPaymentSessionWindowOpen(Invoice|Order $record): void
    {
        throw_if(
            $this->isPaymentSessionExpired($record),
            new BusinessException('支付会话已失效，请重新创建账单后支付')
        );
    }

    private function hashPayload(array $payload): string
    {
        return hash('sha256', json_encode($this->sortPayload($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function sortPayload(array $payload): array
    {
        ksort($payload);

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->sortPayload($value);

                continue;
            }

            if (is_bool($value)) {
                $payload[$key] = $value ? 1 : 0;

                continue;
            }

            if ($value === null) {
                $payload[$key] = '';

                continue;
            }

            if (is_numeric($value)) {
                $payload[$key] = (string) $value;

                continue;
            }

            $payload[$key] = trim((string) $value);
        }

        return $payload;
    }

    private function hashClientIp(string $clientIp): string
    {
        $ip = trim($clientIp);
        if ($ip === '') {
            return '';
        }

        return hash('sha256', $ip);
    }

    private function verifyClientIp(array $payload, string $clientIp): bool
    {
        $storedHash = (string) ($payload['client_ip_hash'] ?? '');

        // 签发时未绑定 IP 的令牌直接判为不通过，避免空 IP 签发后任意来源可轮询
        if ($storedHash === '') {
            return false;
        }

        // 如果当前请求无 IP，则校验失败
        if (trim($clientIp) === '') {
            return false;
        }

        return hash_equals($storedHash, $this->hashClientIp($clientIp));
    }
}
