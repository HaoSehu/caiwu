<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\VncRelayCommand;
use App\Services\VerificationService;
use ReflectionMethod;
use Tests\TestCase;

class ConfigRuntimeAccessTest extends TestCase
{
    public function test_verification_service_builds_urls_from_config_frontend_url(): void
    {
        config([
            'app.frontend_url' => 'https://frontend.example.com',
            'app.url' => 'https://backend.example.com',
        ]);

        $service = new VerificationService();

        $callbackUrl = $this->invokePrivateMethod($service, 'resolveCallbackUrl');
        $proxyUrl = $this->invokePrivateMethod($service, 'buildQrCodeProxyUrl', ['cert-123']);

        $this->assertSame(
            'https://frontend.example.com/api/client/verification/callback',
            $callbackUrl
        );
        $this->assertSame(
            'https://frontend.example.com/api/client/verification/scan?certify_id=cert-123',
            $proxyUrl
        );
    }

    public function test_verification_service_falls_back_to_app_url_when_frontend_url_is_empty(): void
    {
        config([
            'app.frontend_url' => '',
            'app.url' => 'https://backend.example.com',
        ]);

        $service = new VerificationService();

        $callbackUrl = $this->invokePrivateMethod($service, 'resolveCallbackUrl');
        $proxyUrl = $this->invokePrivateMethod($service, 'buildQrCodeProxyUrl', ['cert-456']);

        $this->assertSame(
            'https://backend.example.com/api/client/verification/callback',
            $callbackUrl
        );
        $this->assertSame(
            'https://backend.example.com/api/client/verification/scan?certify_id=cert-456',
            $proxyUrl
        );
    }

    public function test_vnc_relay_command_origin_header_uses_config_values(): void
    {
        config([
            'app.frontend_url' => 'https://frontend.example.com',
            'app.url' => 'https://backend.example.com',
        ]);

        $command = new VncRelayCommand();

        $this->assertSame(
            'https://frontend.example.com',
            $this->invokePrivateMethod($command, 'resolveOriginHeader')
        );

        config(['app.frontend_url' => '']);

        $this->assertSame(
            'https://backend.example.com',
            $this->invokePrivateMethod($command, 'resolveOriginHeader')
        );
    }

    public function test_runtime_target_files_no_longer_use_env_directly(): void
    {
        $files = [
            base_path('app/Services/ClientServiceConsole/ServiceVncService.php'),
            base_path('app/Services/VerificationService.php'),
            base_path('app/Console/Commands/VncRelayCommand.php'),
        ];

        foreach ($files as $file) {
            $content = file_get_contents($file);

            $this->assertIsString($content);
            $this->assertStringNotContainsString('env(', $content, $file);
        }
    }

    private function invokePrivateMethod(object $target, string $method, array $arguments = []): mixed
    {
        $reflection = new ReflectionMethod($target, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($target, $arguments);
    }
}
