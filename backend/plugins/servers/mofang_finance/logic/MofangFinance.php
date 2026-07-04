<?php

declare(strict_types=1);

namespace Caiwu\Plugins\Servers\MofangFinance\Logic;

use Caiwu\Plugins\Servers\MofangFinance\Lib\MofangFinanceDriver;

class MofangFinance
{
    public function __construct(
        private readonly MofangFinanceDriver $driver,
    ) {}

    public function key(): string
    {
        return $this->driver->key();
    }

    public function label(): string
    {
        return $this->driver->label();
    }

    public function capabilities(): array
    {
        return $this->driver->capabilities();
    }

    public function supports(string $capability): bool
    {
        return $this->driver->supports($capability);
    }

    public function resolve(string $capability): ?object
    {
        return $this->driver->resolve($capability);
    }

    public function execute(array $request): array
    {
        $action = trim((string) ($request['action'] ?? ''));
        $payload = is_array($request['payload'] ?? null) ? $request['payload'] : [];

        return match ($action) {
            'server.metadata' => [
                'success' => true,
                'action' => $action,
                'data' => [
                    'key' => $this->key(),
                    'label' => $this->label(),
                    'capabilities' => $this->capabilities(),
                ],
            ],
            'server.supports' => [
                'success' => true,
                'action' => $action,
                'data' => [
                    'supported' => $this->supports((string) ($payload['capability'] ?? '')),
                ],
            ],
            'server.resolve_capability' => [
                'success' => true,
                'action' => $action,
                'data' => [
                    'resolved' => $this->resolve((string) ($payload['capability'] ?? '')),
                ],
            ],
            'server.supplier_form_schema' => [
                'success' => true,
                'action' => $action,
                'data' => $this->driver->supplierFormSchema(),
            ],
            default => [
                'success' => false,
                'action' => $action,
                'message' => 'Unsupported plugin action',
                'data' => [],
            ],
        };
    }
}
