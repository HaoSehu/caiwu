<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Integrations\Payments\Drivers\AlipayFaceToFaceGateway;
use App\Services\Integrations\Payments\PaymentGatewayManager;
use App\Services\Integrations\Payments\PaymentGatewayRegistry;
use App\Services\Sms\Drivers\AliyunSmsDriver;
use App\Services\Sms\SmsDriverManager;
use App\Services\Verification\Drivers\Stay33Driver;
use App\Services\Verification\VerificationDriverManager;
use Illuminate\Support\ServiceProvider;

class IntegrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AlipayFaceToFaceGateway::class);
        $this->app->tag([AlipayFaceToFaceGateway::class], 'payment.gateways');

        $this->app->singleton(PaymentGatewayRegistry::class, function ($app): PaymentGatewayRegistry {
            return new PaymentGatewayRegistry($app->tagged('payment.gateways'));
        });

        $this->app->singleton(PaymentGatewayManager::class);

        $this->app->singleton(Stay33Driver::class);
        $this->app->tag([Stay33Driver::class], 'identity.verification_drivers');
        $this->app->singleton(VerificationDriverManager::class, function ($app): VerificationDriverManager {
            return new VerificationDriverManager($app->tagged('identity.verification_drivers'));
        });

        $this->app->singleton(AliyunSmsDriver::class);
        $this->app->tag([AliyunSmsDriver::class], 'sms.drivers');
        $this->app->singleton(SmsDriverManager::class, function ($app): SmsDriverManager {
            return new SmsDriverManager($app->tagged('sms.drivers'));
        });
    }
}
