<?php

declare(strict_types=1);

namespace Caiwu\Plugins\Servers\ZjmfFinance\Lib;

use App\Exceptions\BusinessException;
use App\Models\Order;
use App\Models\Product;
use App\Models\Service;
use App\Models\Supplier;
use App\Services\Integrations\Plugins\PluginBindingResolver;
use App\Services\Upstream\ProviderKey;
use Illuminate\Support\Facades\Log;

final class ZjmfProvisionService
{
    public function __construct(
        private readonly ZjmfFinanceTransport $transport,
        private readonly ?PluginBindingResolver $bindingResolver = null,
    ) {}

    /**
     * 绑定关系解析器：构造注入优先，未注入时回落到容器解析。
     */
    private function bindingResolver(): PluginBindingResolver
    {
        return $this->bindingResolver ?? app(PluginBindingResolver::class);
    }

    public function provisionOrder(Order $order, Supplier $supplier, ?Service $existingService = null): array
    {
        $product = $order->product;
        if (! $product instanceof Product) {
            throw new BusinessException('商品信息不存在，无法自动开通');
        }

        if ($existingService && $this->resolveExistingHostId($existingService) > 0) {
            $existingHostId = $this->resolveExistingHostId($existingService);
            $existingProvisionData = (array) ($existingService->provision_data ?? []);
            try {
                $jwt = $this->transport->login($supplier);
                $detailResponse = ZjmfUpstreamResponse::withHttpCode(
                    $this->transport->getHostDetailWithMeta($supplier, $existingHostId, $jwt)
                );
                ZjmfUpstreamResponse::assertSuccess($detailResponse, [200], '确认已创建的上游实例状态');
                $detailPayload = ZjmfUpstreamResponse::payload($detailResponse);
                $hostDetail = is_array($detailPayload['host'] ?? null) ? $detailPayload['host'] : [];
                ZjmfUpstreamResponse::assertHostDetailPresent($hostDetail, $existingHostId);
                // 防御纵深：检查点 ID 均来自已过一致性校验的路径，此处复核兜底
                // （换绑后沿用旧检查点重试等边缘）。校验失败由外层 catch 统一包装
                // 为「无法确认上游实例状态」，具体不一致原因已落日志。
                $this->assertUpstreamHostMatchesBinding($hostDetail, $existingHostId, $product);

                // 幂等回查除确认 host 存在外，还需校验其上游账单已支付：
                // 若 host 在欠费/待支付模式下仍返回详情，本地会把服务确认成 ACTIVE、订单 COMPLETED，
                // 而上游账单实际未扣款，造成收入缺口；账单未付时重试供应商余额支付。
                $existingInvoiceId = (int) ($existingProvisionData['upstream_invoice_id'] ?? 0);
                if ($existingInvoiceId > 0) {
                    // withHttpCode 读的是 requestWithMeta 的元信息结构；用 get() 的裸响应体
                    // 传进来会得到空数组 + http_code=0，恒判失败——幂等回查会被误阻断。
                    $invoiceResponse = ZjmfUpstreamResponse::withHttpCode(
                        $this->transport->requestWithMeta($supplier, 'GET', "/v1/invoices/{$existingInvoiceId}", [], $jwt)
                    );
                    ZjmfUpstreamResponse::assertSuccess($invoiceResponse, [200, 1000, 1001], '校验上游开通账单支付状态');

                    // 上游 /v1/invoices/{id} 把它放在 data.invoices.status（业务码在顶层
                    // data.status=200）——按顶层读会恒得到 "200"，每张已付账单都会被当成
                    // 未付再去 fund，而上游对已付账单回 400「Bill paid」，幂等回查直接失败。
                    $upstreamStatus = ZjmfUpstreamResponse::invoiceStatus(
                        $invoiceResponse,
                        ZjmfUpstreamResponse::payload($invoiceResponse)
                    );

                    if ($upstreamStatus !== 'paid') {
                        // 与账单查询同口径保留 HTTP 状态：裸 post 只带业务码，
                        // 上游 5xx 且业务码看似正常时会被误判为支付成功。
                        $fundResponse = ZjmfUpstreamResponse::withHttpCode(
                            $this->transport->requestWithMeta($supplier, 'POST', "/v1/invoices/{$existingInvoiceId}/fund", [], $jwt)
                        );
                        ZjmfUpstreamResponse::assertSuccess($fundResponse, [200, 1000, 1001], '重试供应商余额支付开通账单');
                    }
                }

                Log::info('[ZJMF 财务开通] 上游 host 已存在，跳过重复开通', [
                    'order_id' => $order->id,
                    'upstream_host_id' => $existingHostId,
                ]);

                return [
                    'requested_host' => (string) ($existingProvisionData['requested_host'] ?? ''),
                    'upstream_invoice_id' => (int) ($existingProvisionData['upstream_invoice_id'] ?? 0),
                    'upstream_host_ids' => $existingProvisionData['upstream_host_ids'] ?? [$existingHostId],
                    'upstream_host_id' => $existingHostId,
                    'host_detail' => $hostDetail,
                ];
            } catch (\Throwable $exception) {
                Log::warning('[ZJMF 财务开通] 上游 host 幂等回查失败，已停止重复开通', [
                    'order_id' => $order->id,
                    'upstream_host_id' => $existingHostId,
                    'message' => $exception->getMessage(),
                ]);

                throw new BusinessException('无法确认已创建的上游实例状态，已停止重复开通，请稍后重试或先核实上游实例');
            }
        }

        $startedAt = microtime(true);
        $latency = [
            'login_ms' => 0,
            'clear_cart_before_ms' => 0,
            'product_config_ms' => 0,
            'add_cart_ms' => 0,
            'checkout_ms' => 0,
            'apply_credit_ms' => 0,
            'find_host_ids_ms' => 0,
            'host_detail_ms' => 0,
            'clear_cart_after_ms' => 0,
        ];
        $result = 'failed';
        $requestedHost = '';
        $invoiceId = 0;
        $hostId = 0;
        $errorMessage = '';
        $errorClass = '';
        $jwt = null;
        $cartCookieJar = [];

        try {
            $stepStartedAt = microtime(true);
            $jwt = $this->transport->login($supplier);
            $latency['login_ms'] = $this->elapsedMilliseconds($stepStartedAt);

            // 对齐魔方财务同系统协议：购物车链路携带 downstream_* 注册推送目标并激活
            // 上游两级幂等门（/cart/clear 收编、/cart/settle 同 token 防重）。
            // token 必须跨重试稳定（上游按 token 匹配既有主机），因此先于任何上游调用落库。
            $downstream = $this->resolveDownstreamContext($existingService);

            $stepStartedAt = microtime(true);
            $gate = $this->clearCart($supplier, $jwt, $cartCookieJar, $downstream);
            $latency['clear_cart_before_ms'] = $this->elapsedMilliseconds($stepStartedAt);

            if ($gate['gate'] === 'paid') {
                $gateOutcome = $this->adoptExistingUpstreamHost($existingService, $supplier, $product, (int) $gate['host_id'], $jwt);
                $result = 'success';

                return $gateOutcome;
            }

            if ($gate['gate'] === 'unpaid') {
                $gateOutcome = $this->payExistingUpstreamInvoice($existingService, $supplier, $product, $jwt, $cartCookieJar, $downstream, $gate);
                $result = 'success';

                return $gateOutcome;
            }

            $upstreamProductId = $this->resolveProductUpstreamProductId($product);

            $stepStartedAt = microtime(true);
            $productConfigResponse = $this->requestCartWithSession(
                $supplier,
                'GET',
                '/cart/get_product_config',
                [],
                $jwt,
                $cartCookieJar,
                [],
                ['pid' => $upstreamProductId]
            );
            $latency['product_config_ms'] = $this->elapsedMilliseconds($stepStartedAt);
            ZjmfUpstreamResponse::assertSuccess($productConfigResponse, [200], '读取上游商品配置');

            $payload = ZjmfCartPayloadBuilder::buildPayload(
                $order,
                $product,
                ZjmfCartPayloadBuilder::resolveCurrencyId($order, $product, ZjmfUpstreamResponse::payload($productConfigResponse), $upstreamProductId),
                $upstreamProductId
            );
            $requestedHost = (string) ($payload['host'] ?? '');

            $stepStartedAt = microtime(true);
            $addCartResponse = $this->addProductToCart($supplier, $jwt, $payload, $cartCookieJar);
            $latency['add_cart_ms'] = $this->elapsedMilliseconds($stepStartedAt);
            ZjmfUpstreamResponse::assertSuccess($addCartResponse, [200], '加入上游购物车');
            $cartPosition = ZjmfUpstreamResponse::cartPosition($addCartResponse, ZjmfUpstreamResponse::payload($addCartResponse));

            $stepStartedAt = microtime(true);
            $checkoutResponse = $this->requestCartWithSession(
                $supplier,
                'POST',
                '/cart/settle',
                [
                    'pos' => [$cartPosition],
                    'checkout' => 1,
                    // downstream_* 让上游 is_downstream 生效：结算响应直接携带 data.hostid，
                    // 且同 token 重入时上游防重门返回既有实例(1001)并回滚重复插入。
                    ...$downstream,
                ],
                $jwt,
                $cartCookieJar,
                [
                    'Content-Type: application/x-www-form-urlencoded',
                    'Accept: application/json',
                ]
            );
            $latency['checkout_ms'] = $this->elapsedMilliseconds($stepStartedAt);
            ZjmfUpstreamResponse::assertSuccess($checkoutResponse, [200, 1001], '上游购物车结算');

            $checkoutPayload = ZjmfUpstreamResponse::payload($checkoutResponse);
            $invoiceId = ZjmfUpstreamResponse::invoiceId($checkoutResponse, $checkoutPayload);
            $hostIds = ZjmfUpstreamResponse::hostIds($checkoutResponse, $checkoutPayload);

            if ($invoiceId <= 0 && $hostIds === []) {
                throw new BusinessException('上游已接受结算，但未返回账单 ID');
            }
            // invoiceId=0 且已拿到实例 ID：上游同 token 防重门(1001)或 0 元单——
            // 实例已存在、无账单可付，跳过付款段直接进入详情确认。
            // 防重门命中的可能是换绑前旧商品实例，故此处不落检查点，待下方
            // assertUpstreamHostMatchesBinding 校验通过后再写。

            // 结算成功即先落检查点：上游此刻已生成订单、账单与 host 行（余额足够时
            // 由后续 apply_credit 支付开通）。若付款失败（如供应商余额不足）而这里不写，
            // 下次重试就只剩 token 可依——账单/实例标识双双丢失，重试只能重开一单。
            // 同 token 防重门(1001)命中的实例尚未校验商品归属，故排除在该路径之外。
            $hasPayableInvoice = $invoiceId > 0 && ZjmfUpstreamResponse::status($checkoutResponse) !== 1001;

            if ($hasPayableInvoice) {
                $this->checkpointUpstreamProvision($existingService, $supplier, $product, $invoiceId, $hostIds, $requestedHost);

                $stepStartedAt = microtime(true);
                $creditResponse = $this->requestCartWithSession(
                    $supplier,
                    'POST',
                    '/apply_credit',
                    [
                        'invoiceid' => $invoiceId,
                        'use_credit' => 1,
                        'enough' => 1,
                        // 支付完成(1001)时上游回写 host.stream_info，开通后可反向推送
                        ...$downstream,
                    ],
                    $jwt,
                    $cartCookieJar,
                    [
                        'Content-Type: application/x-www-form-urlencoded',
                        'Accept: application/json',
                    ]
                );
                $latency['apply_credit_ms'] = $this->elapsedMilliseconds($stepStartedAt);
                ZjmfUpstreamResponse::assertSuccess($creditResponse, [200, 1001], '使用供应商余额支付上游账单');

                $creditPayload = ZjmfUpstreamResponse::payload($creditResponse);
                $hostIds = array_values(array_unique([
                    ...$hostIds,
                    ...ZjmfUpstreamResponse::hostIds($creditResponse, $creditPayload),
                ]));
                $this->checkpointUpstreamProvision($existingService, $supplier, $product, $invoiceId, $hostIds, $requestedHost);
            }

            if ($hostIds === []) {
                $stepStartedAt = microtime(true);
                $hostIds = $this->findHostIdsForProvision($supplier, $jwt, $invoiceId, (string) $payload['host']);
                $latency['find_host_ids_ms'] = $this->elapsedMilliseconds($stepStartedAt);
                $this->checkpointUpstreamProvision($existingService, $supplier, $product, $invoiceId, $hostIds, $requestedHost);
            }

            if ($hostIds === []) {
                throw new BusinessException('上游已接受订单，但未返回已开通的产品 ID');
            }

            $hostId = (int) $hostIds[0];

            $stepStartedAt = microtime(true);
            $detailResponse = ZjmfUpstreamResponse::withHttpCode(
                $this->transport->getHostDetailWithMeta($supplier, $hostId, $jwt)
            );
            $latency['host_detail_ms'] = $this->elapsedMilliseconds($stepStartedAt);
            ZjmfUpstreamResponse::assertSuccess($detailResponse, [200], '读取上游产品详情');
            $detailPayload = ZjmfUpstreamResponse::payload($detailResponse);
            $hostDetail = is_array($detailPayload['host'] ?? null) ? $detailPayload['host'] : [];
            ZjmfUpstreamResponse::assertHostDetailPresent($hostDetail, $hostId);
            // 覆盖 settle 同 token 防重门(1001)收编路径：命中旧商品实例时在此拦截，
            // 检查点在其后落库，错配实例不进 provision_data。正常新开实例的商品
            // 即加购所用绑定商品，恒通过，无误杀面。
            $this->assertUpstreamHostMatchesBinding($hostDetail, $hostId, $product);
            $this->checkpointUpstreamProvision($existingService, $supplier, $product, $invoiceId, $hostIds, $requestedHost);

            $result = 'success';

            return [
                'requested_host' => (string) $payload['host'],
                'upstream_invoice_id' => $invoiceId,
                'upstream_host_ids' => $hostIds,
                'upstream_host_id' => $hostId,
                'host_detail' => $hostDetail,
            ];
        } catch (\Throwable $exception) {
            $errorMessage = $exception->getMessage();
            $errorClass = $exception::class;

            throw $exception;
        } finally {
            $clearStartedAt = microtime(true);

            try {
                if (is_string($jwt) && trim($jwt) !== '') {
                    $this->clearCart($supplier, $jwt, $cartCookieJar);
                }
            } catch (\Throwable $exception) {
                Log::warning('[ZJMF 财务开通] 清理供应商购物车失败', [
                    'supplier_id' => $supplier->id,
                    'order_id' => $order->id,
                    'message' => $exception->getMessage(),
                ]);
            } finally {
                $latency['clear_cart_after_ms'] = $this->elapsedMilliseconds($clearStartedAt);
            }

            $logContext = array_merge($latency, [
                'result' => $result,
                'order_id' => (int) $order->id,
                'order_no' => (string) $order->order_no,
                'supplier_id' => (int) $supplier->id,
                'provider_key' => ProviderKey::ZJMF_FINANCE_API,
                'upstream_product_id' => $this->resolveProductUpstreamProductId($product),
                'requested_host' => $requestedHost,
                'upstream_invoice_id' => $invoiceId,
                'upstream_host_id' => $hostId,
                'duration_ms' => $this->elapsedMilliseconds($startedAt),
            ]);

            if ($result === 'success') {
                Log::info('[ZJMF 财务开通] 上游开通请求耗时', $logContext);
            } else {
                $logContext['message'] = $errorMessage;
                $logContext['exception'] = $errorClass;
                Log::warning('[ZJMF 财务开通] 上游开通请求耗时', $logContext);
            }
        }
    }

    public function getProductProvisionConfig(Supplier $supplier, int $productId): array
    {
        return $this->transport->get($supplier, '/cart/get_product_config', $this->transport->login($supplier), [
            'pid' => $productId,
        ]);
    }

    private function resolveExistingHostId(Service $service): int
    {
        $bindingHostId = $this->bindingResolver()->upstreamServiceIdForService($service);
        $provisionData = (array) ($service->provision_data ?? []);

        return (int) (($bindingHostId ?? '') ?: ($provisionData['upstream_host_id'] ?? 0) ?: 0);
    }

    /**
     * 在任何后续网络调用可能失败之前，先把上游引用持久化到服务记录。
     *
     * 同时更新传入的服务实例，让核心失败路径能保留检查点，
     * 重试时校验已存在的资源而不是再结算一个购物车。
     */
    private function checkpointUpstreamProvision(
        ?Service $service,
        Supplier $supplier,
        Product $product,
        int $upstreamInvoiceId,
        array $upstreamHostIds,
        string $requestedHost,
    ): void {
        if (! $service instanceof Service || ! $service->exists) {
            return;
        }

        $freshService = $service->fresh() ?? $service;
        $provisionData = (array) ($freshService->provision_data ?? []);
        $hostIds = array_values(array_filter(array_map('intval', $upstreamHostIds), fn (int $hostId) => $hostId > 0));
        $hostId = (int) ($hostIds[0] ?? $provisionData['upstream_host_id'] ?? 0);

        $freshService->forceFill([
            'provision_data' => array_merge($provisionData, array_filter([
                'provider_key' => ProviderKey::ZJMF_FINANCE_API,
                'supplier_id' => (int) $supplier->id,
                'requested_host' => trim($requestedHost) !== '' ? trim($requestedHost) : null,
                'upstream_invoice_id' => $upstreamInvoiceId > 0 ? $upstreamInvoiceId : null,
                'upstream_host_id' => $hostId > 0 ? $hostId : null,
                'upstream_host_ids' => $hostIds !== [] ? $hostIds : null,
                'upstream_product_id' => $this->resolveProductUpstreamProductId($product),
                'last_provision_attempt_at' => now()->format('Y-m-d H:i:s'),
            ], static fn (mixed $value): bool => $value !== null)),
        ])->save();

        $service->setAttribute('provision_data', $freshService->provision_data);
    }

    private function resolveProductUpstreamProductId(Product $product): int
    {
        $upstreamProductId = $this->bindingResolver()->upstreamProductIdForProduct($product);

        if ($upstreamProductId !== null) {
            return (int) $upstreamProductId;
        }

        throw new BusinessException('商品上游绑定不存在，无法自动开通');
    }

    private function addProductToCart(Supplier $supplier, string $jwt, array $payload, array &$cookieJar): array
    {
        return $this->requestCartWithSession(
            $supplier,
            'POST',
            '/cart/add_to_shop',
            $payload,
            $jwt,
            $cookieJar,
            [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
            ]
        );
    }

    /**
     * 清空上游购物车并识别下游幂等门结果。
     *
     * 携带 downstream_* 时，上游 /cart/clear 对 is_api 客户不是单纯清车：
     * - 命中既有主机且其账单已支付 → 400「该订单已开通,请勿重新开通」+ hostid：收编既有实例；
     * - 命中既有主机且其账单未支付 → 200 + invoiceid + hostid：跳过加购/结算，直接补付该账单；
     * - 未命中 → 普通 200 清车，继续正常开通链。
     * 不带 downstream_* 时保持历史行为：只清车，不识别门；此时上游「已开通」
     * 400 属 token 缺参退化（'%%' 全表匹配）误报，容忍继续，绝不能据此收编。
     *
     * @return array{gate: 'none'}|array{gate: 'paid', host_id: int}|array{gate: 'unpaid', invoice_id: int, host_id: int}
     */
    private function clearCart(Supplier $supplier, string $jwt, array &$cookieJar, array $downstream = []): array
    {
        $response = $this->requestCartWithSession(
            $supplier,
            'POST',
            '/cart/clear',
            $downstream,
            $jwt,
            $cookieJar,
            [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
            ]
        );

        $status = ZjmfUpstreamResponse::status($response);

        if ($status === 400) {
            $message = trim((string) ($response['msg'] ?? ''));
            if (str_contains($message, '该订单已开通') && str_contains($message, '请勿重新开通')) {
                if ($downstream === []) {
                    // 上游对 token 的 LIKE 匹配未判空（缺参时退化为 '%%' 全表匹配），
                    // 未携带注册参数时的「已开通」是误报——按历史口径容忍继续。
                    return ['gate' => 'none'];
                }

                $hostIds = ZjmfUpstreamResponse::hostIds($response, ZjmfUpstreamResponse::payload($response));

                return ['gate' => 'paid', 'host_id' => (int) ($hostIds[0] ?? 0)];
            }

            ZjmfUpstreamResponse::throwFailure($response, '清空上游购物车');
        }

        // 门结果只在携带注册参数时采信：上游 3.5.8 的 token 匹配无 uid 过滤且缺参
        // 退化为 '%%'，不带参时 200+invoiceid+hostid 也可能是命中他人主机的误报。
        if ($downstream !== [] && $status === 200) {
            $payload = ZjmfUpstreamResponse::payload($response);
            $invoiceId = ZjmfUpstreamResponse::invoiceId($response, $payload);
            $hostIds = ZjmfUpstreamResponse::hostIds($response, $payload);
            if ($invoiceId > 0 && $hostIds !== []) {
                return ['gate' => 'unpaid', 'invoice_id' => $invoiceId, 'host_id' => (int) $hostIds[0]];
            }
        }

        // 与历史行为对齐：非 200/400（如 405 登录失效、500）必须上抛，不得静默放行
        ZjmfUpstreamResponse::assertSuccess($response, [200], '清空上游购物车');

        return ['gate' => 'none'];
    }

    /**
     * 解析/初始化下游注册参数（downstream_url/token/id）。
     *
     * 魔方财务同系统协议要求下游在购物车链路携带这三个参数：上游据此把 stream_info
     * 写到对应主机上，激活 /cart/clear 与 /cart/settle 的按 token 幂等门，并在支付
     * 完成后具备反向推送 /api/host/sync 的能力。token 必须跨重试稳定，首次生成后
     * 持久化进 provision_data；app.url 缺失或非 http(s) 时退化为不注册（各门不激活，
     * 行为与历史版本一致）。
     */
    private function resolveDownstreamContext(?Service $service): array
    {
        if (! $service instanceof Service || ! $service->exists) {
            return [];
        }

        $downstreamUrl = rtrim(trim((string) config('app.url')), '/');
        if (! preg_match('#^https?://#i', $downstreamUrl)) {
            return [];
        }

        $provisionData = (array) ($service->provision_data ?? []);
        $token = trim((string) ($provisionData['downstream_token'] ?? ''));
        if (strlen($token) !== 32) {
            $token = bin2hex(random_bytes(16));
            $service->forceFill([
                'provision_data' => [...$provisionData, 'downstream_token' => $token],
            ])->save();
            $service->setAttribute('provision_data', (array) $service->fresh()->provision_data);
        }

        return [
            'downstream_url' => $downstreamUrl,
            'downstream_token' => $token,
            'downstream_id' => (int) $service->id,
        ];
    }

    /**
     * 幂等门「已开通已支付」收编：上游已存在同 token 主机且账单已支付，
     * 先核验实例与商品绑定一致，检查点落库后按成功返回，绝不重复走加购/结算。
     */
    private function adoptExistingUpstreamHost(
        ?Service $service,
        Supplier $supplier,
        Product $product,
        int $hostId,
        string $jwt,
    ): array {
        if ($hostId <= 0) {
            throw new BusinessException('上游提示订单已开通但未返回实例ID，请人工核实上游实例');
        }

        $provisionData = (array) ($service->provision_data ?? []);

        $detailResponse = ZjmfUpstreamResponse::withHttpCode($this->transport->getHostDetailWithMeta($supplier, $hostId, $jwt));
        ZjmfUpstreamResponse::assertSuccess($detailResponse, [200], '读取上游产品详情');
        $detailPayload = ZjmfUpstreamResponse::payload($detailResponse);
        $hostDetail = is_array($detailPayload['host'] ?? null) ? $detailPayload['host'] : [];
        ZjmfUpstreamResponse::assertHostDetailPresent($hostDetail, $hostId);
        $this->assertUpstreamHostMatchesBinding($hostDetail, $hostId, $product);

        $this->checkpointUpstreamProvision(
            $service,
            $supplier,
            $product,
            (int) ($provisionData['upstream_invoice_id'] ?? 0),
            [$hostId],
            (string) ($provisionData['requested_host'] ?? ''),
        );

        Log::info('[ZJMF 财务开通] 清车幂等门收编既有实例', [
            'supplier_id' => (int) $supplier->id,
            'upstream_host_id' => $hostId,
        ]);

        return [
            'requested_host' => (string) ($provisionData['requested_host'] ?? ''),
            'upstream_invoice_id' => (int) ($provisionData['upstream_invoice_id'] ?? 0),
            'upstream_host_ids' => [$hostId],
            'upstream_host_id' => $hostId,
            'host_detail' => $hostDetail,
        ];
    }

    /**
     * 幂等门「已开通未支付」补付：上游存在同 token 主机且其账单未支付
     * （上次开通在结算后、支付前中断），先核验实例与商品绑定一致再动用
     * 供应商余额补付既有账单，避免重开一单造成上游二次开通与重复扣款。
     */
    private function payExistingUpstreamInvoice(
        ?Service $service,
        Supplier $supplier,
        Product $product,
        string $jwt,
        array &$cookieJar,
        array $downstream,
        array $gate,
    ): array {
        $invoiceId = (int) $gate['invoice_id'];
        $gateHostId = (int) $gate['host_id'];
        $provisionData = (array) ($service->provision_data ?? []);
        $requestedHost = (string) ($provisionData['requested_host'] ?? '');

        $detailResponse = ZjmfUpstreamResponse::withHttpCode($this->transport->getHostDetailWithMeta($supplier, $gateHostId, $jwt));
        ZjmfUpstreamResponse::assertSuccess($detailResponse, [200], '读取上游产品详情');
        $detailPayload = ZjmfUpstreamResponse::payload($detailResponse);
        $hostDetail = is_array($detailPayload['host'] ?? null) ? $detailPayload['host'] : [];
        ZjmfUpstreamResponse::assertHostDetailPresent($hostDetail, $gateHostId);
        $this->assertUpstreamHostMatchesBinding($hostDetail, $gateHostId, $product);

        $creditResponse = $this->requestCartWithSession(
            $supplier,
            'POST',
            '/apply_credit',
            [
                'invoiceid' => $invoiceId,
                'use_credit' => 1,
                'enough' => 1,
                ...$downstream,
            ],
            $jwt,
            $cookieJar,
            [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
            ]
        );
        // enough=1 与主链付款语义一致：余额必须全额覆盖，不足直接失败浮出
        ZjmfUpstreamResponse::assertSuccess($creditResponse, [1001], '使用供应商余额支付既有开通账单');

        $creditPayload = ZjmfUpstreamResponse::payload($creditResponse);
        $hostIds = array_values(array_unique([
            $gateHostId,
            ...ZjmfUpstreamResponse::hostIds($creditResponse, $creditPayload),
        ]));
        $this->checkpointUpstreamProvision($service, $supplier, $product, $invoiceId, $hostIds, $requestedHost);

        Log::info('[ZJMF 财务开通] 清车幂等门补付既有账单', [
            'supplier_id' => (int) $supplier->id,
            'upstream_invoice_id' => $invoiceId,
            'upstream_host_id' => $gateHostId,
        ]);

        return [
            'requested_host' => $requestedHost,
            'upstream_invoice_id' => $invoiceId,
            'upstream_host_ids' => $hostIds,
            'upstream_host_id' => $gateHostId,
            'host_detail' => $hostDetail,
        ];
    }

    /**
     * 收编/补付前的商品一致性校验：幂等门按 token 匹配主机，而 token 不随上游
     * 换绑失效，命中实例可能是换绑前旧商品的历史开通。实例的上游商品 id 必须与
     * 当前绑定一致，否则收编/补付会把错配实例（错误配置与金额）绑到本单——转人工核实。
     */
    private function assertUpstreamHostMatchesBinding(array $hostDetail, int $hostId, Product $product): void
    {
        $expectedUpstreamProductId = $this->resolveProductUpstreamProductId($product);
        $actualUpstreamProductId = (int) ($hostDetail['product_id'] ?? 0);

        if ($actualUpstreamProductId > 0 && $actualUpstreamProductId === $expectedUpstreamProductId) {
            return;
        }

        Log::warning('[ZJMF 财务开通] 幂等门命中实例与商品绑定不一致，已转人工核实', [
            'upstream_host_id' => $hostId,
            'expected_upstream_product_id' => $expectedUpstreamProductId,
            'actual_upstream_product_id' => $actualUpstreamProductId,
        ]);

        throw new BusinessException(sprintf(
            '上游实例 %d 与当前商品绑定不一致（实例商品 %d，绑定商品 %d），已暂停自动收编，请人工核实上游实例',
            $hostId,
            $actualUpstreamProductId,
            $expectedUpstreamProductId
        ));
    }

    private function requestCartWithSession(
        Supplier $supplier,
        string $method,
        string $uri,
        array|string $payload,
        string $jwt,
        array &$cookieJar,
        array $headers = [],
        array $query = []
    ): array {
        $meta = $this->transport->requestWithMeta(
            $supplier,
            $method,
            $uri,
            $payload,
            $jwt,
            $this->buildCartSessionHeaders($cookieJar, $headers),
            $query
        );

        $this->mergeResponseCookies($cookieJar, $meta['headers']);

        return ZjmfUpstreamResponse::withHttpCode($meta);
    }

    private function buildCartSessionHeaders(array $cookieJar, array $headers = []): array
    {
        if ($cookieJar === []) {
            return $headers;
        }

        return array_merge($headers, ['Cookie: '.$this->formatCookieJar($cookieJar)]);
    }

    private function formatCookieJar(array $cookieJar): string
    {
        $pairs = [];

        foreach ($cookieJar as $name => $value) {
            $name = trim((string) $name);
            $value = trim((string) $value);

            if ($name !== '' && $value !== '') {
                $pairs[] = "{$name}={$value}";
            }
        }

        return implode('; ', $pairs);
    }

    private function mergeResponseCookies(array &$cookieJar, array $responseHeaders): void
    {
        foreach ($responseHeaders as $header) {
            $line = trim((string) $header);
            if (! str_starts_with(strtolower($line), 'set-cookie:')) {
                continue;
            }

            $cookie = trim(substr($line, strlen('set-cookie:')));
            [$pair, $attributes] = array_pad(explode(';', $cookie, 2), 2, '');
            [$name, $value] = array_pad(explode('=', trim($pair), 2), 2, '');
            $name = trim($name);
            $value = trim($value);

            if ($name === '') {
                continue;
            }

            if ($value === '' || str_contains(strtolower($attributes), 'max-age=0')) {
                unset($cookieJar[$name]);

                continue;
            }

            $cookieJar[$name] = $value;
        }
    }

    private function findHostIdsForProvision(Supplier $supplier, string $jwt, int $invoiceId, string $hostname): array
    {
        $response = ZjmfUpstreamResponse::withHttpCode($this->transport->requestWithMeta(
            $supplier,
            'GET',
            '/host/list',
            [],
            $jwt,
            [],
            [
                'show_type' => 'list',
                'orderby' => 'id',
                'sort' => 'DESC',
            ]
        ));
        ZjmfUpstreamResponse::assertSuccess($response, [200], '查询上游已开通产品');

        $payload = ZjmfUpstreamResponse::payload($response);
        $hosts = is_array($payload['list'] ?? null) ? $payload['list'] : [];

        return array_values(array_unique(array_filter(array_map(function ($host) use ($invoiceId, $hostname) {
            if (! is_array($host)) {
                return null;
            }

            $domain = trim((string) ($host['domain'] ?? ''));
            $hostInvoiceId = (int) ($host['invoice_id'] ?? $host['invoiceid'] ?? 0);
            $matchesInvoice = $invoiceId > 0 && $hostInvoiceId === $invoiceId;
            $matchesHostname = $hostname !== '' && $domain === $hostname;

            if (! $matchesInvoice && ! $matchesHostname) {
                return null;
            }

            $id = (int) ($host['id'] ?? 0);

            return $id > 0 ? $id : null;
        }, $hosts))));
    }

    private function elapsedMilliseconds(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
