<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\Sms\Contracts\SmsDriver;
use App\Services\Sms\Data\SmsSendRequest;
use App\Services\Sms\Data\SmsSendResult;
use App\Services\Sms\SmsDriverManager;
use Tests\TestCase;

class SmsDriverManagerSettingsTest extends TestCase
{
    public function test_sms_manager_uses_legacy_sms_provider_when_sms_driver_is_empty(): void
    {
        $driver = new FeatureFakeSmsDriver('fake_sms_provider');
        $manager = new SmsDriverManager([$driver]);
        $originalDriver = Setting::getValue('notification', 'sms_driver', '');
        $originalProvider = Setting::getValue('notification', 'sms_provider', '');

        try {
            Setting::setValue('notification', 'sms_driver', '');
            Setting::setValue('notification', 'sms_provider', 'fake_sms_provider');

            $this->assertSame($driver, $manager->resolve());
        } finally {
            Setting::setValue('notification', 'sms_driver', $originalDriver);
            Setting::setValue('notification', 'sms_provider', $originalProvider);
        }
    }
}

final readonly class FeatureFakeSmsDriver implements SmsDriver
{
    public function __construct(
        private string $key,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function label(): string
    {
        return '测试短信';
    }

    public function sendVerifyCode(SmsSendRequest $request): SmsSendResult
    {
        return new SmsSendResult('success');
    }
}
