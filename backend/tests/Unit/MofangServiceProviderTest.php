<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Integrations\Mofang\Adapters\MofangFinanceAdapter;
use App\Integrations\Mofang\Billing\MofangBillingRestoreProfile;
use App\Integrations\Mofang\Billing\MofangBillingRestoreService;
use App\Integrations\Mofang\Drivers\MofangFinanceDriver;
use App\Services\Auth\LegacyPasswordVerifier;
use App\Services\Upstream\Contracts\ProvidesConsoleCatalog;
use App\Services\Upstream\Contracts\UpstreamBillingRestoreProfile;
use App\Services\Upstream\Data\UpstreamProviderDescriptor;
use App\Services\Upstream\ProviderKey;
use App\Services\Upstream\ProviderRegistry;
use Tests\TestCase;

class MofangServiceProviderTest extends TestCase
{
    public function test_it_registers_mofang_driver_through_integration_provider(): void
    {
        $registry = app(ProviderRegistry::class);
        $driver = $registry->find(ProviderKey::MOFANG_FINANCE_API);

        $this->assertInstanceOf(MofangFinanceDriver::class, $driver);
        $this->assertSame('魔方财务接口', $driver?->label());
        $this->assertInstanceOf(
            MofangFinanceAdapter::class,
            $driver?->resolve(ProvidesConsoleCatalog::class)
        );
    }

    public function test_provider_registry_exports_driver_metadata_without_provider_key_labels(): void
    {
        $registry = app(ProviderRegistry::class);

        $this->assertSame(ProviderKey::MOFANG_FINANCE_API, ProviderKey::label(ProviderKey::MOFANG_FINANCE_API));
        $this->assertContains(ProviderKey::MOFANG_FINANCE_API, $registry->keys());
        $this->assertContains([
            'value' => ProviderKey::MOFANG_FINANCE_API,
            'label' => '魔方财务接口',
        ], $registry->options());

        $descriptor = collect($registry->descriptors())
            ->first(fn (UpstreamProviderDescriptor $item): bool => $item->key === ProviderKey::MOFANG_FINANCE_API);

        $this->assertInstanceOf(UpstreamProviderDescriptor::class, $descriptor);
        $this->assertSame([
            'key' => ProviderKey::MOFANG_FINANCE_API,
            'label' => '魔方财务接口',
            'capabilities' => $descriptor->capabilities,
        ], $descriptor->toArray());
        $this->assertContains(ProvidesConsoleCatalog::class, $descriptor->capabilities);
    }

    public function test_it_registers_mofang_legacy_password_verifier_in_chain(): void
    {
        $needsRehash = false;
        $matched = app(LegacyPasswordVerifier::class)->verify(
            'Secret123',
            '###'.md5('Secret123'),
            $needsRehash
        );

        $this->assertTrue($matched);
        $this->assertTrue($needsRehash);
    }

    public function test_it_registers_mofang_billing_restore_module(): void
    {
        $profile = app(UpstreamBillingRestoreProfile::class);

        $this->assertInstanceOf(MofangBillingRestoreProfile::class, $profile);
        $this->assertInstanceOf(MofangBillingRestoreService::class, app(MofangBillingRestoreService::class));
        $this->assertSame('RESTORE_UPSTREAM_BILLING', $profile->defaultConfirmationPhrase());
        $this->assertContains('RESTORE_MOFANG_BILLING', $profile->confirmationPhrases());
    }
}
