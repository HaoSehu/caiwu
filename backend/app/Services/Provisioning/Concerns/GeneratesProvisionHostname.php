<?php

namespace App\Services\Provisioning\Concerns;

use App\Models\Order;
use App\Models\Product;
use App\Models\Service;
use App\Models\Supplier;
use App\Support\ProductProvisionHostname;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * 主机名生成与上游主机名规则解析。
 *
 * 规则优先级：系统强制规则 > 商品固定 > 商品前缀 > 上游模板 > 下单快照。
 * 上游模板字段口径在 `normalizeUpstreamProvisionHostnameRule()` 统一收敛，
 * 生成结果是确定性的（sha256 派生），同一订单重复调用得到同一主机名。
 */
trait GeneratesProvisionHostname
{
    /**
     * 生成（或复用）订单主机名，并把结果回写进订单的 config_snapshot。
     */
    private function resolveProvisionHostname(Order $order, ?Service $service = null): string
    {
        $configSnapshot = (array) ($order->config_snapshot ?? []);
        $hostnameConfig = $this->settingService->getProvisionHostnameConfig();
        $productHostnameRule = ProductProvisionHostname::fromPurchaseRequires(
            is_array($order->product?->purchase_requires ?? null) ? $order->product->purchase_requires : []
        );
        $upstreamHostnameRule = $this->resolveUpstreamProvisionHostnameRule($order, $service);
        $snapshotHostname = $this->settingService->normalizeHostname((string) ($configSnapshot['hostname'] ?? ''));
        $hostnameMode = (string) ($productHostnameRule['mode'] ?? ProductProvisionHostname::MODE_SYSTEM);
        $enforceSystemRule = (bool) ($hostnameConfig['enforce'] ?? false);
        $hostname = $enforceSystemRule
            ? $this->generateProvisionHostname(
                $order,
                ProductProvisionHostname::buildGenerationRule($hostnameConfig, [], $upstreamHostnameRule)
            )
            : match ($hostnameMode) {
                ProductProvisionHostname::MODE_FIXED => $this->settingService->normalizeHostname(
                    (string) ($productHostnameRule['value'] ?? ''),
                    true
                ),
                ProductProvisionHostname::MODE_PREFIX => $this->generateProvisionHostname(
                    $order,
                    ProductProvisionHostname::buildGenerationRule($hostnameConfig, $productHostnameRule, $upstreamHostnameRule)
                ),
                default => $upstreamHostnameRule !== []
                    ? $this->generateProvisionHostname(
                        $order,
                        ProductProvisionHostname::buildGenerationRule($hostnameConfig, [], $upstreamHostnameRule)
                    )
                    : $snapshotHostname,
            };

        if ($hostname === '' && $upstreamHostnameRule !== []) {
            $hostname = $this->generateProvisionHostname(
                $order,
                ProductProvisionHostname::buildGenerationRule($hostnameConfig, [], $upstreamHostnameRule)
            );
        }

        if ($hostname === '') {
            $hostname = $this->generateProvisionHostname(
                $order,
                ProductProvisionHostname::buildGenerationRule($hostnameConfig)
            );
        }

        if (($configSnapshot['hostname'] ?? '') !== $hostname) {
            $configSnapshot['hostname'] = $hostname;
            $order->forceFill([
                'config_snapshot' => $configSnapshot,
            ])->save();
        }

        return $hostname;
    }

    /**
     * 读取上游商品的主机名模板规则，30 分钟缓存；上游不可用时不抛错，回退本地规则。
     */
    private function resolveUpstreamProvisionHostnameRule(Order $order, ?Service $service = null): array
    {
        $product = $order->product;
        // 与提交同源：在途订单读冻结快照，避免改绑后用新上游的规则生成旧上游的主机名。
        $supplier = $product instanceof Product ? $this->resolveProductSupplier($product, $service) : null;

        if (! $product instanceof Product || ! $supplier instanceof Supplier) {
            return [];
        }

        if (! $this->shouldAutoProvision($product, $service)) {
            return [];
        }

        $supplierProductId = $this->resolveUpstreamProductId($product, $service);
        if ($supplierProductId <= 0) {
            return [];
        }

        $cacheKey = sprintf('provision:upstream_hostname_rule:%d:%d', (int) $supplier->id, $supplierProductId);
        $cachedRule = Cache::get($cacheKey);
        if (is_array($cachedRule) && $cachedRule !== []) {
            return $cachedRule;
        }

        $rule = (function () use ($product, $supplier, $supplierProductId, $service) {
            try {
                $provisioning = $this->resolveProvisioningCapability($product, $service);
                if (method_exists($provisioning, 'getProductProvisionConfig')) {
                    $response = $provisioning->getProductProvisionConfig($supplier, $supplierProductId);

                    return $this->extractUpstreamProvisionHostnameRule($response, $supplierProductId);
                }

                $jwt = $provisioning->login($supplier);
                $response = $provisioning->get($supplier, '/v1/productsconfig', $jwt, [
                    'product_id' => $supplierProductId,
                ]);

                return $this->extractUpstreamProvisionHostnameRule($response, $supplierProductId);
            } catch (\Throwable $exception) {
                Log::warning('[自动开通] 读取上游商品主机名规则失败，将回退本地主机名规则', [
                    'supplier_id' => (int) $supplier->id,
                    'upstream_product_id' => $supplierProductId,
                    'message' => $exception->getMessage(),
                    'exception' => $exception::class,
                ]);

                return [];
            }
        })();

        if ($rule === []) {
            Cache::forget($cacheKey);

            return [];
        }

        Cache::put($cacheKey, $rule, now()->addMinutes(30));

        return $rule;
    }

    private function extractUpstreamProvisionHostnameRule(array $response, int $productId): array
    {
        $product = $this->extractUpstreamProductConfigData($response, $productId);
        if (! is_array($product)) {
            return [];
        }

        return $this->normalizeUpstreamProvisionHostnameRule($product);
    }

    private function extractUpstreamProductConfigData(array $response, int $productId): ?array
    {
        $payload = is_array($response['data'] ?? null) ? $response['data'] : $response;

        foreach ([
            $payload['products'] ?? null,
            $payload['product'] ?? null,
            $payload['items'] ?? null,
        ] as $candidate) {
            $matchedProduct = $this->matchUpstreamProductCandidate($candidate, $productId);
            if ($matchedProduct !== null) {
                return $matchedProduct;
            }
        }

        if ((int) ($payload['id'] ?? 0) === $productId) {
            return $payload;
        }

        foreach ((array) ($payload['first_group'] ?? []) as $firstGroup) {
            foreach ((array) ($firstGroup['group'] ?? []) as $group) {
                $matchedProduct = $this->matchUpstreamProductCandidate(
                    $group['products'] ?? $group['product'] ?? null,
                    $productId
                );

                if ($matchedProduct !== null) {
                    return $matchedProduct;
                }
            }
        }

        return null;
    }

    private function matchUpstreamProductCandidate(mixed $candidate, int $productId): ?array
    {
        if (! is_array($candidate)) {
            return null;
        }

        if ((int) ($candidate['id'] ?? 0) === $productId) {
            return $candidate;
        }

        foreach ($candidate as $item) {
            if (is_array($item) && (int) ($item['id'] ?? 0) === $productId) {
                return $item;
            }
        }

        return null;
    }

    private function normalizeUpstreamProvisionHostnameRule(array $product): array
    {
        $hostRule = $this->normalizeUpstreamHostRule($product['host'] ?? null);
        $hostRuleRule = is_array($hostRule['rule'] ?? null) ? $hostRule['rule'] : [];
        $productRule = is_array($product['rule'] ?? null) ? $product['rule'] : [];
        $effectiveRule = $hostRuleRule !== [] ? $hostRuleRule : $productRule;
        $show = (int) ($hostRule['show'] ?? $product['show'] ?? 1);
        $sampleHostname = $this->settingService->normalizeHostname((string) (
            $hostRule['host']
            ?? $hostRule['hostname']
            ?? $hostRuleRule['host']
            ?? $hostRuleRule['hostname']
            ?? ($hostRule === [] && is_string($product['host'] ?? null) ? $product['host'] : '')
        ), true);
        $prefix = $this->settingService->sanitizeHostnamePrefix((string) (
            $hostRule['prefix']
            ?? $hostRuleRule['prefix']
            ?? $product['prefix']
            ?? ''
        ));
        $length = $this->resolveUpstreamProvisionHostnameLength($hostRule, $effectiveRule, $product, $sampleHostname, $prefix);

        if ($prefix === '' && preg_match('/^[a-zA-Z]+/', $sampleHostname, $matches) === 1) {
            $prefix = $this->settingService->sanitizeHostnamePrefix((string) ($matches[0] ?? ''));
        }

        $pool = '';
        if ($this->resolveUpstreamProvisionHostnameFlag(
            $hostRule['num'] ?? null,
            $hostRuleRule['num'] ?? null,
            $productRule['num'] ?? null,
            $product['num'] ?? null
        ) === 1) {
            $pool .= '0123456789';
        }
        if ($this->resolveUpstreamProvisionHostnameFlag(
            $hostRule['upper'] ?? null,
            $hostRuleRule['upper'] ?? null,
            $productRule['upper'] ?? null,
            $product['upper'] ?? null
        ) === 1) {
            $pool .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        }
        if ($this->resolveUpstreamProvisionHostnameFlag(
            $hostRule['lower'] ?? null,
            $hostRuleRule['lower'] ?? null,
            $productRule['lower'] ?? null,
            $product['lower'] ?? null
        ) === 1) {
            $pool .= 'abcdefghijklmnopqrstuvwxyz';
        }

        if ($pool === '' && $sampleHostname !== '') {
            if (preg_match('/[0-9]/', $sampleHostname) === 1) {
                $pool .= '0123456789';
            }
            if (preg_match('/[A-Z]/', $sampleHostname) === 1) {
                $pool .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            }
            if (preg_match('/[a-z]/', $sampleHostname) === 1) {
                $pool .= 'abcdefghijklmnopqrstuvwxyz';
            }
        }

        if ($show !== 1 && $prefix === '' && $sampleHostname === '') {
            return [];
        }

        $length = max($length, mb_strlen($prefix));
        if ($length <= 0) {
            $length = max(mb_strlen($sampleHostname), 12);
        }

        return [
            'prefix' => $prefix,
            'length' => max(4, min(63, $length)),
            'pool' => $pool !== '' ? $pool : '0123456789',
        ];
    }

    private function normalizeUpstreamHostRule(mixed $host): array
    {
        if (is_array($host)) {
            return $host;
        }

        if (! is_string($host)) {
            return [];
        }

        $decoded = json_decode(trim($host), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function resolveUpstreamProvisionHostnameLength(
        array $hostRule,
        array $hostRuleRule,
        array $product,
        string $sampleHostname,
        string $prefix
    ): int {
        $length = $this->resolveUpstreamProvisionHostnameNumericValue([
            $hostRule['len_num'] ?? null,
            $hostRule['length'] ?? null,
            $product['len_num'] ?? null,
            $product['length'] ?? null,
        ]);

        $ruleLength = $this->resolveUpstreamProvisionHostnameNumericValue([
            $hostRuleRule['len_num'] ?? null,
            $hostRuleRule['length'] ?? null,
        ]);

        if ($ruleLength > 0) {
            $length = max($length, $ruleLength + mb_strlen($prefix));
        }

        if ($sampleHostname !== '') {
            $length = max($length, mb_strlen($sampleHostname));
        }

        if ($length <= 0) {
            $length = max(mb_strlen($prefix), 12);
        }

        return $length;
    }

    private function resolveUpstreamProvisionHostnameFlag(mixed ...$candidates): int
    {
        foreach ($candidates as $candidate) {
            if ($candidate === null || $candidate === '') {
                continue;
            }

            return (int) $candidate;
        }

        return 0;
    }

    private function resolveUpstreamProvisionHostnameNumericValue(array $candidates): int
    {
        foreach ($candidates as $candidate) {
            if ($candidate === null || $candidate === '' || ! is_numeric($candidate)) {
                continue;
            }

            return (int) $candidate;
        }

        return 0;
    }

    private function generateProvisionHostname(Order $order, array $config): string
    {
        $prefix = (string) ($config['prefix'] ?? 'srv');
        $length = max((int) ($config['length'] ?? 12), mb_strlen($prefix));
        $pool = (string) ($config['pool'] ?? '0123456789');
        $randomLength = max($length - mb_strlen($prefix), 0);

        if ($randomLength === 0) {
            return $prefix;
        }

        return $prefix.$this->buildProvisionHostnameSuffix(
            "{$order->order_no}|{$order->id}|{$order->user_id}",
            $pool,
            $randomLength
        );
    }

    private function buildProvisionHostnameSuffix(string $seed, string $pool, int $length): string
    {
        $poolLength = strlen($pool);
        if ($poolLength <= 0 || $length <= 0) {
            return '';
        }

        $result = '';
        $cursor = 0;

        while (strlen($result) < $length) {
            $hash = hash('sha256', $seed.'|'.$cursor);
            $hashLength = strlen($hash);

            for ($index = 0; $index < $hashLength && strlen($result) < $length; $index += 2) {
                $segment = substr($hash, $index, 2);
                $offset = hexdec($segment) % $poolLength;
                $result .= $pool[$offset];
            }

            $cursor++;
        }

        return substr($result, 0, $length);
    }
}
