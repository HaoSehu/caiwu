<?php

declare(strict_types=1);

namespace Caiwu\Plugins\Servers\ZjmfFinance\Lib;

use App\Exceptions\BusinessException;
use App\Models\Supplier;

final class ZjmfSecurityService
{
    public function __construct(
        private readonly ZjmfFinanceTransport $transport,
    ) {}

    public function submitCustomModuleAction(Supplier $supplier, string $endpoint, array $payload, ?string $jwt = null): array
    {
        $resolvedJwt = $this->resolveJwt($supplier, $jwt);

        return $this->transport->post(
            $supplier,
            $this->normalizeCustomModuleEndpoint($endpoint),
            $payload,
            $resolvedJwt,
            [
                'content-type: application/x-www-form-urlencoded',
                'Authorization: JWT '.$resolvedJwt,
            ]
        );
    }

    private function resolveJwt(Supplier $supplier, ?string $jwt): string
    {
        $jwt = trim((string) $jwt);

        return $jwt !== '' ? $jwt : $this->transport->login($supplier);
    }

    private function normalizeCustomModuleEndpoint(string $endpoint): string
    {
        $endpoint = trim($endpoint);
        $path = parse_url($endpoint, PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? $path : $endpoint;

        if (preg_match('#^/provision/custom/[1-9]\d*$#', $path) !== 1) {
            throw new BusinessException('安全组模块未返回有效的同系统请求地址', 42200);
        }

        return $endpoint;
    }
}
