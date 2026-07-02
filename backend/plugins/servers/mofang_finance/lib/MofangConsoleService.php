<?php

declare(strict_types=1);

namespace Caiwu\Plugins\Servers\MofangFinance\Lib;

use App\Models\Supplier;

final class MofangConsoleService
{
    public function __construct(
        private readonly MofangFinanceTransport $transport,
    ) {}

    public function getHostDetail(Supplier $supplier, int $hostId, ?string $jwt = null): array
    {
        return $this->transport->get($supplier, "/v1/hosts/{$hostId}", $this->resolveJwt($supplier, $jwt));
    }

    public function getVncUrl(Supplier $supplier, int $hostId, ?string $jwt = null): array
    {
        return $this->transport->post(
            $supplier,
            '/provision/default',
            ['func' => 'vnc', 'id' => $hostId],
            $this->resolveJwt($supplier, $jwt),
            ['content-type: application/x-www-form-urlencoded']
        );
    }

    public function powerAction(Supplier $supplier, int $hostId, string $action, ?string $jwt = null): array
    {
        return $this->transport->put($supplier, "/v1/hosts/{$hostId}/module/{$action}", [], $this->resolveJwt($supplier, $jwt));
    }

    public function getModuleStatus(Supplier $supplier, int $hostId, string $type = 'host', ?string $jwt = null): array
    {
        return $this->transport->get($supplier, "/v1/hosts/{$hostId}/module/status", $this->resolveJwt($supplier, $jwt), [
            'type' => trim($type) !== '' ? trim($type) : 'host',
        ]);
    }

    public function getReinstallOptions(Supplier $supplier, int $hostId, ?string $jwt = null): array
    {
        return $this->transport->get($supplier, "/v1/hosts/{$hostId}/module/reinstall", $this->resolveJwt($supplier, $jwt));
    }

    public function resetPassword(Supplier $supplier, int $hostId, string $password, ?string $jwt = null): array
    {
        return $this->transport->put($supplier, "/v1/hosts/{$hostId}/module/repassword", [
            'password' => $password,
        ], $this->resolveJwt($supplier, $jwt));
    }

    public function reinstall(Supplier $supplier, int $hostId, string $osId, ?string $jwt = null): array
    {
        return $this->transport->put($supplier, "/v1/hosts/{$hostId}/module/reinstall", [
            'os_id' => $osId,
        ], $this->resolveJwt($supplier, $jwt));
    }

    public function getSupportedModules(Supplier $supplier, int $hostId, ?string $jwt = null): array
    {
        return $this->transport->get($supplier, "/v1/hosts/{$hostId}/module", $this->resolveJwt($supplier, $jwt));
    }

    public function fetchCustomModulePage(Supplier $supplier, int $hostId, string $moduleKey, ?string $jwt = null): string
    {
        $resolvedJwt = $this->resolveJwt($supplier, $jwt);
        $html = '';

        try {
            $rootUrl = $this->resolveSupplierRootUrl($supplier);
            $html = $this->normalizeModulePageBody($this->transport->getText(
                $supplier,
                $rootUrl.'/provision/custom/content',
                $resolvedJwt,
                ['id' => $hostId, 'key' => $moduleKey]
            ));
        } catch (\Throwable) {
            $html = '';
        }

        if (trim($html) !== '') {
            return $html;
        }

        return $this->normalizeModulePageBody($this->transport->getText(
            $supplier,
            "/v1/hosts/{$hostId}/module/custom",
            $resolvedJwt,
            ['key' => $moduleKey]
        ));
    }

    private function resolveJwt(Supplier $supplier, ?string $jwt): string
    {
        $jwt = trim((string) $jwt);

        return $jwt !== '' ? $jwt : $this->transport->login($supplier);
    }

    private function normalizeModulePageBody(string $body): string
    {
        $body = trim($body, "\xEF\xBB\xBF");
        $decoded = json_decode($body, true);

        if (! is_array($decoded)) {
            return $body;
        }

        $payload = $decoded['data'] ?? $decoded;

        if (is_string($payload) && trim($payload) !== '') {
            return $payload;
        }

        if (is_array($payload)) {
            foreach (['html', 'content', 'view', 'template'] as $key) {
                $value = $payload[$key] ?? null;
                if (is_string($value) && trim($value) !== '') {
                    return $value;
                }
            }
        }

        return $body;
    }

    private function resolveSupplierRootUrl(Supplier $supplier): string
    {
        $baseUrl = trim((string) $supplier->api_url);
        $parts = parse_url($baseUrl);

        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return rtrim($baseUrl, '/');
        }

        $rootUrl = $parts['scheme'].'://'.$parts['host'];
        if (isset($parts['port'])) {
            $rootUrl .= ':'.$parts['port'];
        }

        return $rootUrl;
    }
}
