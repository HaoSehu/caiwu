<?php

declare(strict_types=1);

namespace App\Integrations\Mofang;

use App\Integrations\Mofang\Adapters\MofangFinanceAdapter;
use App\Integrations\Mofang\Billing\MofangBillingRestoreProfile;
use App\Integrations\Mofang\Billing\MofangBillingRestoreService;
use App\Integrations\Mofang\Drivers\MofangFinanceDriver;
use App\Integrations\Mofang\Support\MofangCloudConfigTemplate;
use App\Integrations\Mofang\Support\MofangCredentialParser;
use App\Integrations\Mofang\Support\MofangLegacyPasswordVerifier;
use App\Integrations\Mofang\Support\MofangProductTypeMapper;
use App\Services\Upstream\Contracts\UpstreamBillingRestoreProfile;
use Illuminate\Support\ServiceProvider;

final class MofangServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MofangCloudConfigTemplate::class);
        $this->app->singleton(MofangProductTypeMapper::class);
        $this->app->singleton(MofangFinanceAdapter::class);
        $this->app->singleton(MofangFinanceDriver::class);
        $this->app->singleton(MofangCredentialParser::class);
        $this->app->singleton(MofangLegacyPasswordVerifier::class);
        $this->app->singleton(MofangBillingRestoreProfile::class);
        $this->app->singleton(UpstreamBillingRestoreProfile::class, MofangBillingRestoreProfile::class);
        $this->app->singleton(MofangBillingRestoreService::class);

        $this->app->tag([MofangFinanceDriver::class], 'upstream.drivers');
        $this->app->tag([MofangCredentialParser::class], 'upstream.web_session_credential_parsers');
        $this->app->tag([MofangLegacyPasswordVerifier::class], 'auth.legacy_password_verifiers');
    }
}
