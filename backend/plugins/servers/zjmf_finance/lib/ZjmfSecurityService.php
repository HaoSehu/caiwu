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
        return $this->transport->post(
            $supplier,
            $this->normalizeCustomModuleEndpoint($endpoint),
            $payload,
            $this->resolveJwt($supplier, $jwt),
            ['content-type: application/x-www-form-urlencoded']
        );
    }

    /**
     * Reads the same-system ZJMF security-group list used by the cloud and DCIM consoles.
     */
    public function getSecurityGroups(Supplier $supplier, int $page = 1, int $limit = 9999, ?string $jwt = null): array
    {
        return $this->transport->get(
            $supplier,
            '/security_group',
            $this->resolveJwt($supplier, $jwt),
            [
                'page' => max(1, $page),
                'limit' => max(1, $limit),
            ]
        );
    }

    /**
     * Associates a same-system ZJMF security group with one upstream host.
     */
    public function applySecurityGroup(Supplier $supplier, int $groupId, int $hostId, ?string $jwt = null): array
    {
        throw_if($groupId <= 0 || $hostId <= 0, new BusinessException('安全组或实例参数无效', 42200));

        return $this->transport->post(
            $supplier,
            "/security_group/{$groupId}/host/{$hostId}",
            [
                'id' => $groupId,
                'host_id' => $hostId,
            ],
            $this->resolveJwt($supplier, $jwt),
            ['content-type: application/x-www-form-urlencoded']
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

        if (! str_starts_with($path, '/provision/custom/')) {
            throw new BusinessException('安全组模块未返回有效的同系统请求地址', 42200);
        }

        return $endpoint;
    }
}
