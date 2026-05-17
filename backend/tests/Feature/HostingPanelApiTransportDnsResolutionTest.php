<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Upstream\Drivers\HostingPanelApi\HostingPanelApiTransport;
use ReflectionMethod;
use Tests\TestCase;

class HostingPanelApiTransportDnsResolutionTest extends TestCase
{
    public function test_literal_ip_bypasses_dns_lookup(): void
    {
        $client = new class extends HostingPanelApiTransport
        {
            protected function lookupHostAddresses(string $host): array
            {
                throw new \RuntimeException('lookup should not be called for literal IP');
            }
        };

        $result = $this->invokePrivateMethod($client, 'resolveHostAddresses', ['203.0.113.10']);

        $this->assertSame(['203.0.113.10'], $result);
    }

    private function invokePrivateMethod(object $target, string $method, array $arguments = []): mixed
    {
        $reflection = new ReflectionMethod(HostingPanelApiTransport::class, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($target, $arguments);
    }
}
