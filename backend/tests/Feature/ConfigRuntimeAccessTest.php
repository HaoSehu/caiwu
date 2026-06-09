<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\VncRelayCommand;
use App\Services\Auth\VerificationService;
use App\Services\ClientServiceConsole\ServiceDetailService;
use App\Services\ClientServiceConsole\ServiceTransformService;
use App\Services\ClientServiceConsole\ServiceVncService;
use App\Services\System\OperationLogService;
use ReflectionMethod;
use Tests\TestCase;

class ConfigRuntimeAccessTest extends TestCase
{
    public function test_verification_qrcode_payload_returns_provider_url_without_proxy_url(): void
    {
        $content = file_get_contents(base_path('app/Services/Auth/VerificationService.php'));

        $this->assertIsString($content);
        $this->assertStringContainsString("'url' => \$remoteUrl", $content);
        $this->assertStringNotContainsString("'proxy_url' =>", $content);
    }

    public function test_verification_service_builds_urls_from_config_frontend_url(): void
    {
        config([
            'app.frontend_url' => 'https://frontend.example.com',
            'app.url' => 'https://backend.example.com',
        ]);

        $service = app(VerificationService::class);

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

        $service = app(VerificationService::class);

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

        $command = new VncRelayCommand;

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

    public function test_vnc_service_prefers_frontend_url_as_public_api_base(): void
    {
        config([
            'app.frontend_url' => 'https://frontend.example.com',
            'app.url' => 'https://backend.example.com',
        ]);

        $service = $this->makeVncService();

        $this->assertSame(
            '',
            $this->invokePrivateMethod($service, 'resolveVncApiBase', ['https://frontend.example.com/vnc'])
        );
        $this->assertSame(
            'https://frontend.example.com',
            $this->invokePrivateMethod($service, 'resolveVncApiBase', ['https://viewer.example.com/vnc'])
        );
    }

    public function test_vnc_service_no_vnc_base_url_uses_frontend_url(): void
    {
        config([
            'app.frontend_url' => 'https://frontend.example.com',
            'app.url' => 'https://backend.example.com',
        ]);

        $service = $this->makeVncService();

        $this->assertSame(
            'https://frontend.example.com/vnc',
            $this->invokePrivateMethod($service, 'resolveNoVncBaseUrl')
        );
    }

    public function test_vnc_service_no_vnc_base_url_prefers_request_origin(): void
    {
        config([
            'app.frontend_url' => 'https://admin.example.com',
            'app.url' => 'https://backend.example.com',
        ]);

        $service = $this->makeVncService();

        $this->assertSame(
            'https://www.example.com/vnc',
            $this->invokePrivateMethod($service, 'resolveNoVncBaseUrl', [[
                'actor_type' => 'client',
                'request_origin' => 'https://www.example.com',
            ]])
        );
    }

    public function test_vnc_service_public_api_base_falls_back_to_app_url(): void
    {
        config([
            'app.frontend_url' => '',
            'app.url' => 'https://backend.example.com',
        ]);

        $service = $this->makeVncService();

        $this->assertSame(
            '',
            $this->invokePrivateMethod($service, 'resolveVncApiBase', ['https://backend.example.com/vnc'])
        );
        $this->assertSame(
            'https://backend.example.com',
            $this->invokePrivateMethod($service, 'resolveVncApiBase', ['https://viewer.example.com/vnc'])
        );
    }

    public function test_vnc_service_vnc_api_base_prefers_request_origin(): void
    {
        config([
            'app.frontend_url' => 'https://admin.example.com',
            'app.url' => 'https://backend.example.com',
        ]);

        $service = $this->makeVncService();

        $this->assertSame(
            '',
            $this->invokePrivateMethod($service, 'resolveVncApiBase', [
                'https://www.example.com/vnc',
                [
                    'actor_type' => 'client',
                    'request_origin' => 'https://www.example.com',
                ],
            ])
        );
    }

    public function test_vnc_service_no_vnc_base_url_falls_back_to_app_url(): void
    {
        config([
            'app.frontend_url' => '',
            'app.url' => 'https://backend.example.com',
        ]);

        $service = $this->makeVncService();

        $this->assertSame(
            'https://backend.example.com/vnc',
            $this->invokePrivateMethod($service, 'resolveNoVncBaseUrl')
        );
    }

    public function test_runtime_target_files_no_longer_use_env_directly(): void
    {
        $files = [
            base_path('app/Services/ClientServiceConsole/ServiceVncService.php'),
            base_path('app/Services/Auth/VerificationService.php'),
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

    private function makeVncService(): ServiceVncService
    {
        return new ServiceVncService(
            $this->createMock(OperationLogService::class),
            $this->createMock(ServiceDetailService::class),
            $this->createMock(ServiceTransformService::class),
        );
    }
}
