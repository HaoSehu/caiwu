<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Upstream\Drivers\HostingPanelApi\HostingPanelApiTransport;
use ReflectionClass;
use Tests\TestCase;

class HostingPanelApiTransportTest extends TestCase
{
    public function test_it_disables_automatic_redirects_in_stream_context(): void
    {
        $transport = new HostingPanelApiTransport();

        $options = $this->invokePrivateMethod($transport, 'buildContextOptions', [
            'GET',
            [],
            null,
        ]);

        $this->assertSame(0, $options['http']['follow_location']);
        $this->assertSame(0, $options['http']['max_redirects']);
    }

    public function test_it_disables_automatic_redirects_in_http_client_options(): void
    {
        $transport = new HostingPanelApiTransport();

        $options = $this->invokePrivateMethod($transport, 'buildHttpClientOptions');

        $this->assertFalse($options['allow_redirects']);
    }

    public function test_it_normalizes_base_url_without_duplicate_v1_prefix(): void
    {
        $transport = new HostingPanelApiTransport();

        $url = $this->invokePrivateMethod($transport, 'buildUrl', [
            'https://panel.example.test/v1/',
            '/v1/login_api',
            ['account' => 'demo'],
        ]);

        $this->assertSame('https://panel.example.test/v1/login_api?account=demo', $url);
    }

    private function invokePrivateMethod(object $object, string $method, array $arguments = []): mixed
    {
        $reflection = new ReflectionClass($object);
        $instanceMethod = $reflection->getMethod($method);
        $instanceMethod->setAccessible(true);

        return $instanceMethod->invokeArgs($object, $arguments);
    }
}
