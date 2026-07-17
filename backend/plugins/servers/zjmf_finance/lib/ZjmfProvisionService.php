<?php

declare(strict_types=1);

namespace Caiwu\Plugins\Servers\ZjmfFinance\Lib;

use App\Exceptions\BusinessException;
use App\Models\Order;
use App\Models\Product;
use App\Models\Service;
use App\Models\Supplier;
use App\Services\Integrations\Plugins\PluginBindingResolver;
use App\Services\Integrations\Support\ProviderErrorMapper;
use App\Services\Upstream\ProviderKey;
use App\Support\SensitiveDataSanitizer;
use Illuminate\Support\Facades\Log;

final class ZjmfProvisionService
{
    private const RANGE_TYPES = [4, 7, 9, 11, 14, 15, 16, 17, 18, 19];

    private const TYPE_FIELD_MAP = [
        4 => 'ip_num',
        5 => 'os',
        6 => 'cpu',
        7 => 'cpu',
        8 => 'memory',
        9 => 'memory',
        10 => 'bw',
        11 => 'bw',
        12 => 'area',
        13 => 'system_disk_size',
        14 => 'system_disk_size',
        16 => 'cpu',
        17 => 'memory',
        18 => 'bw',
        19 => 'system_disk_size',
    ];

    public function __construct(
        private readonly ZjmfFinanceTransport $transport,
        private ?PluginBindingResolver $bindingResolver = null,
    ) {}

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
                $detailResponse = $this->transport->getHostDetail($supplier, $existingHostId, $jwt);
                if (($detailResponse['status'] ?? 0) === 200) {
                    $detailPayload = $this->extractPayload($detailResponse);
                    $hostDetail = is_array($detailPayload['host'] ?? null) ? $detailPayload['host'] : [];

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
                }
            } catch (\Throwable $exception) {
                Log::warning('[ZJMF 财务开通] 上游 host 幂等回查失败，继续正常开通', [
                    'order_id' => $order->id,
                    'upstream_host_id' => $existingHostId,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        $startedAt = microtime(true);
        $latency = [
            'login_ms' => 0,
            'clear_cart_before_ms' => 0,
            'add_cart_ms' => 0,
            'get_cart_ms' => 0,
            'checkout_ms' => 0,
            'fund_invoice_ms' => 0,
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

            $stepStartedAt = microtime(true);
            $this->requestCartWithSession($supplier, 'DELETE', '/v1/cart/clear', [], $jwt, $cartCookieJar);
            $latency['clear_cart_before_ms'] = $this->elapsedMilliseconds($stepStartedAt);

            $payload = $this->buildCartPayload($order, $product);
            $requestedHost = (string) ($payload['host'] ?? '');

            $stepStartedAt = microtime(true);
            $addCartResponse = $this->addProductToCart($supplier, $jwt, $payload, $cartCookieJar);
            $latency['add_cart_ms'] = $this->elapsedMilliseconds($stepStartedAt);
            $this->assertUpstreamSuccess($addCartResponse, [200], '加入上游购物车');

            $stepStartedAt = microtime(true);
            $cartResponse = $this->requestCartWithSession($supplier, 'GET', '/v1/cart', [], $jwt, $cartCookieJar);
            $latency['get_cart_ms'] = $this->elapsedMilliseconds($stepStartedAt);
            $this->assertUpstreamSuccess($cartResponse, [200], '读取上游购物车');
            $cartPayload = $this->extractPayload($cartResponse);
            $gateway = $this->resolveGateway($cartPayload);

            $stepStartedAt = microtime(true);
            $checkoutResponse = $this->requestCartWithSession($supplier, 'POST', '/v1/cart/checkout', [
                'payment' => $gateway,
                'position' => [0],
            ], $jwt, $cartCookieJar);
            $latency['checkout_ms'] = $this->elapsedMilliseconds($stepStartedAt);
            $this->assertUpstreamSuccess($checkoutResponse, [200, 1001], '上游购物车结算');

            $checkoutPayload = $this->extractPayload($checkoutResponse);
            $invoiceId = $this->extractInvoiceId($checkoutResponse, $checkoutPayload);
            $hostIds = $this->extractHostIds($checkoutResponse, $checkoutPayload);

            if ($hostIds === [] && $invoiceId > 0 && ! $this->isCompletedCheckoutResponse($checkoutResponse)) {
                $stepStartedAt = microtime(true);
                $fundResponse = $this->requestCartWithSession(
                    $supplier,
                    'POST',
                    "/v1/invoices/{$invoiceId}/fund",
                    [],
                    $jwt,
                    $cartCookieJar
                );
                $latency['fund_invoice_ms'] = $this->elapsedMilliseconds($stepStartedAt);
                $this->assertUpstreamSuccess($fundResponse, [200, 1001], '使用供应商余额支付上游账单');
                $fundPayload = $this->extractPayload($fundResponse);
                $hostIds = $this->extractHostIds($fundResponse, $fundPayload);

                if ($hostIds === []) {
                    $message = trim((string) ($fundResponse['msg'] ?? ''));
                    throw new BusinessException($message !== ''
                        ? "上游账单 {$invoiceId} 支付未完成：{$message}"
                        : "上游账单 {$invoiceId} 未支付完成，请检查供应商余额是否充足");
                }
            }

            if ($hostIds === []) {
                $stepStartedAt = microtime(true);
                $hostIds = $this->findHostIdsByName($supplier, $jwt, (string) $payload['host']);
                $latency['find_host_ids_ms'] = $this->elapsedMilliseconds($stepStartedAt);
            }

            if ($hostIds === []) {
                throw new BusinessException('上游已接受订单，但未返回已开通的产品 ID');
            }

            $hostId = (int) $hostIds[0];

            $stepStartedAt = microtime(true);
            $detailResponse = $this->transport->getHostDetail($supplier, $hostId, $jwt);
            $latency['host_detail_ms'] = $this->elapsedMilliseconds($stepStartedAt);
            $this->assertUpstreamSuccess($detailResponse, [200], '读取上游产品详情');
            $detailPayload = $this->extractPayload($detailResponse);
            $hostDetail = is_array($detailPayload['host'] ?? null) ? $detailPayload['host'] : [];

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
                    $this->requestCartWithSession($supplier, 'DELETE', '/v1/cart/clear', [], $jwt, $cartCookieJar);
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
        return $this->transport->get($supplier, '/v1/productsconfig', $this->transport->login($supplier), [
            'product_id' => $productId,
        ]);
    }

    private function buildCartPayload(Order $order, Product $product): array
    {
        $configSnapshot = array_merge(
            (array) (($product->purchase_requires ?? [])['upstream_default_config'] ?? []),
            (array) ($order->config_snapshot ?? [])
        );
        $hostname = trim((string) ($configSnapshot['hostname'] ?? $order->domain ?? ''));
        $password = trim((string) ($configSnapshot['password'] ?? ''));

        if ($password === '') {
            $password = $this->generateProvisionPassword();
        }

        return [
            'product_id' => $this->resolveProductUpstreamProductId($product),
            'billingcycle' => (string) $order->billing_cycle,
            'qty' => 1,
            'host' => $hostname,
            'password' => $password,
            'configoption' => $this->buildConfigOptionMap($product, $configSnapshot),
        ];
    }

    private function resolveExistingHostId(Service $service): int
    {
        $bindingHostId = $this->bindingResolver()->upstreamServiceIdForService($service);
        $provisionData = (array) ($service->provision_data ?? []);

        return (int) (($bindingHostId ?? '') ?: ($provisionData['upstream_host_id'] ?? 0) ?: 0);
    }

    private function resolveProductUpstreamProductId(Product $product): int
    {
        $upstreamProductId = $this->bindingResolver()->upstreamProductIdForProduct($product);

        if ($upstreamProductId !== null) {
            return (int) $upstreamProductId;
        }

        throw new BusinessException('商品上游绑定不存在，无法自动开通');
    }

    private function bindingResolver(): PluginBindingResolver
    {
        return $this->bindingResolver ??= app(PluginBindingResolver::class);
    }

    private function buildConfigOptionMap(Product $product, array $configSnapshot): array
    {
        $result = [];

        foreach ((array) ($product->config_options ?? []) as $item) {
            $item = (array) $item;
            $optionId = $this->resolveOptionId($item);
            $field = $this->parseField($item);
            $type = (int) ($item['option_type'] ?? -1);

            if ($optionId <= 0 || $field === '') {
                continue;
            }

            if (in_array($type, self::RANGE_TYPES, true)) {
                $value = $configSnapshot[$field] ?? null;
                if ($value === null || $value === '') {
                    continue;
                }
                $result[$optionId] = (int) $value;

                continue;
            }

            if ($field === 'os' && ! empty($configSnapshot['os_sub_id'])) {
                $result[$optionId] = (int) $configSnapshot['os_sub_id'];

                continue;
            }

            $selected = $configSnapshot[$field] ?? null;
            if ($selected === null || $selected === '') {
                continue;
            }

            $subId = $this->resolveSubId($item, $selected);
            if ($subId !== null) {
                $result[$optionId] = $subId;
            }
        }

        return $result;
    }

    private function resolveOptionId(array $item): int
    {
        foreach ([
            $item['id'] ?? null,
            $item['config_id'] ?? null,
            $item['extra']['id'] ?? null,
            $item['extra']['config_id'] ?? null,
        ] as $candidate) {
            if (is_numeric($candidate) && (int) $candidate > 0) {
                return (int) $candidate;
            }
        }

        foreach ([(array) ($item['sub'] ?? []), (array) ($item['extra']['sub'] ?? [])] as $subList) {
            foreach ($subList as $sub) {
                $configId = $sub['config_id'] ?? $sub['configid'] ?? null;
                if (is_numeric($configId) && (int) $configId > 0) {
                    return (int) $configId;
                }
            }
        }

        return 0;
    }

    private function resolveSubId(array $item, mixed $selected): ?int
    {
        $selected = trim((string) $selected);
        if ($selected === '') {
            return null;
        }

        foreach ((array) ($item['sub'] ?? []) as $sub) {
            $subId = (int) ($sub['id'] ?? 0);
            $optionNameFirst = trim((string) ($sub['option_name_first'] ?? ''));
            $optionName = trim((string) ($sub['option_name'] ?? ''));

            if ($selected === (string) $subId || $selected === $optionNameFirst || $selected === $optionName) {
                return $subId > 0 ? $subId : null;
            }
        }

        return is_numeric($selected) ? (int) $selected : null;
    }

    private function parseField(array $item): string
    {
        $field = trim((string) ($item['field'] ?? ''));
        if ($field !== '') {
            return $field;
        }

        $type = (int) ($item['option_type'] ?? -1);
        if (isset(self::TYPE_FIELD_MAP[$type])) {
            return self::TYPE_FIELD_MAP[$type];
        }

        $source = (string) ($item['option_name'] ?? $item['spec_key'] ?? '');
        $parts = explode('|', $source);

        return trim((string) ($parts[0] ?? ''));
    }

    private function addProductToCart(Supplier $supplier, string $jwt, array $payload, array &$cookieJar): array
    {
        $requestPayload = $payload;
        if (($requestPayload['configoption'] ?? []) === []) {
            $requestPayload['configoption'] = (object) [];
        }

        return $this->requestCartWithSession(
            $supplier,
            'POST',
            '/v1/cart/products',
            (string) json_encode($requestPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $jwt,
            $cookieJar,
            [
                'Content-Type: application/json',
                'Accept: application/json',
            ]
        );
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

        $this->mergeResponseCookies($cookieJar, (array) ($meta['headers'] ?? []));

        return is_array($meta['response'] ?? null) ? $meta['response'] : [];
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

    private function resolveGateway(array $cartPayload): string
    {
        $gateway = trim((string) ($cartPayload['default_gateway'] ?? ''));
        if ($gateway !== '') {
            return $gateway;
        }

        $gatewayList = $cartPayload['gateway_list'] ?? [];
        if (is_array($gatewayList)) {
            foreach ($gatewayList as $item) {
                $name = trim((string) ($item['name'] ?? ''));
                if ($name !== '') {
                    return $name;
                }
            }
        }

        throw new BusinessException('上游未配置可用支付网关，无法完成自动开通');
    }

    private function extractPayload(array $response): array
    {
        return is_array($response['data'] ?? null) ? $response['data'] : $response;
    }

    private function extractInvoiceId(array $response, array $payload): int
    {
        return (int) ($payload['invoiceid'] ?? $response['invoiceid'] ?? 0);
    }

    private function extractHostIds(array $response, array $payload): array
    {
        $hostIds = $payload['hostid'] ?? $response['hostid'] ?? [];

        if (! is_array($hostIds)) {
            $hostIds = [$hostIds];
        }

        return array_values(array_filter(array_map(
            fn ($value) => is_numeric($value) && (int) $value > 0 ? (int) $value : null,
            $hostIds
        )));
    }

    private function assertUpstreamSuccess(array $response, array $allowedStatuses, string $action): void
    {
        $hasStatus = array_key_exists('status', $response)
            || array_key_exists('code', $response)
            || array_key_exists('status_code', $response);

        if (! $hasStatus) {
            return;
        }

        $status = $this->extractResponseStatus($response);
        if (in_array($status, $allowedStatuses, true)) {
            return;
        }

        $message = trim((string) ($response['msg'] ?? $response['message'] ?? ''));
        Log::warning('[ZJMF 财务开通] 返回失败', [
            'action' => $action,
            'status' => $status,
            'message' => SensitiveDataSanitizer::sanitizeText($message),
        ]);

        throw new BusinessException(app(ProviderErrorMapper::class)->toUserMessage(ProviderKey::ZJMF_FINANCE_API, $action, $message));
    }

    private function isCompletedCheckoutResponse(array $response): bool
    {
        return $this->extractResponseStatus($response) === 1001;
    }

    private function extractResponseStatus(array $response): int
    {
        return (int) ($response['status'] ?? $response['code'] ?? $response['status_code'] ?? 0);
    }

    private function findHostIdsByName(Supplier $supplier, string $jwt, string $hostname): array
    {
        $response = $this->transport->get($supplier, '/v1/hosts', $jwt, [
            'page' => 1,
            'limit' => 20,
            'keywords' => $hostname,
            'sort' => 'DESC',
        ]);

        $payload = $this->extractPayload($response);
        $hosts = is_array($payload['host'] ?? null) ? $payload['host'] : [];

        return array_values(array_filter(array_map(function ($host) use ($hostname) {
            if (! is_array($host)) {
                return null;
            }

            $domain = trim((string) ($host['domain'] ?? ''));
            if ($domain !== '' && $domain !== $hostname) {
                return null;
            }

            $id = (int) ($host['id'] ?? 0);

            return $id > 0 ? $id : null;
        }, $hosts)));
    }

    private function generateProvisionPassword(): string
    {
        $chars = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $password = '';

        for ($i = 0; $i < 13; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return substr($password.'Aa1', 0, 16);
    }

    private function elapsedMilliseconds(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
