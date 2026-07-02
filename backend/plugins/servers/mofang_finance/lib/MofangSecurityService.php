<?php

declare(strict_types=1);

namespace Caiwu\Plugins\Servers\MofangFinance\Lib;

use App\Models\Supplier;

final class MofangSecurityService
{
    public function __construct(
        private readonly MofangFinanceTransport $transport,
    ) {}

    public function submitCustomModuleAction(Supplier $supplier, string $endpoint, array $payload, ?string $jwt = null): array
    {
        return $this->transport->post(
            $supplier,
            $endpoint,
            $payload,
            $this->resolveJwt($supplier, $jwt),
            ['content-type: application/x-www-form-urlencoded']
        );
    }

    private function resolveJwt(Supplier $supplier, ?string $jwt): string
    {
        $jwt = trim((string) $jwt);

        return $jwt !== '' ? $jwt : $this->transport->login($supplier);
    }
}
