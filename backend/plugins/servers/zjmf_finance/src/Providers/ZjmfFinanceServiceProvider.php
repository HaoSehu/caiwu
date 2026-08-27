<?php

declare(strict_types=1);

namespace Caiwu\Plugins\Servers\ZjmfFinance\Providers;

use App\Services\Upstream\Contracts\UpstreamBillingRestoreProfile;
use Caiwu\Plugins\Servers\ZjmfFinance\Commands\RestoreZjmfBillingCommand;
use Caiwu\Plugins\Servers\ZjmfFinance\Lib\ZjmfAuthManager;
use Caiwu\Plugins\Servers\ZjmfFinance\Lib\ZjmfBillingRestoreProfile;
use Caiwu\Plugins\Servers\ZjmfFinance\Lib\ZjmfBillingRestoreService;
use Caiwu\Plugins\Servers\ZjmfFinance\Lib\ZjmfCatalogService;
use Caiwu\Plugins\Servers\ZjmfFinance\Lib\ZjmfCloudConfigTemplate;
use Caiwu\Plugins\Servers\ZjmfFinance\Lib\ZjmfConsoleService;
use Caiwu\Plugins\Servers\ZjmfFinance\Lib\ZjmfCredentialParser;
use Caiwu\Plugins\Servers\ZjmfFinance\Lib\ZjmfFinanceAdapter;
use Caiwu\Plugins\Servers\ZjmfFinance\Lib\ZjmfFinanceDriver;
use Caiwu\Plugins\Servers\ZjmfFinance\Lib\ZjmfFinanceTransport;
use Caiwu\Plugins\Servers\ZjmfFinance\Lib\ZjmfInventoryAndServiceSyncHook;
use Caiwu\Plugins\Servers\ZjmfFinance\Lib\ZjmfInventoryAndServiceSyncTask;
use Caiwu\Plugins\Servers\ZjmfFinance\Lib\ZjmfLegacyPasswordVerifier;
use Caiwu\Plugins\Servers\ZjmfFinance\Lib\ZjmfLoginEndpointPolicy;
use Caiwu\Plugins\Servers\ZjmfFinance\Lib\ZjmfNetworkService;
use Caiwu\Plugins\Servers\ZjmfFinance\Lib\ZjmfProductTypeMapper;
use Caiwu\Plugins\Servers\ZjmfFinance\Lib\ZjmfProvisionService;
use Caiwu\Plugins\Servers\ZjmfFinance\Lib\ZjmfRenewService;
use Caiwu\Plugins\Servers\ZjmfFinance\Lib\ZjmfScheduledAuthRefreshHook;
use Caiwu\Plugins\Servers\ZjmfFinance\Lib\ZjmfScheduledAuthRefreshTask;
use Caiwu\Plugins\Servers\ZjmfFinance\Lib\ZjmfSecurityService;
use Caiwu\Plugins\Servers\ZjmfFinance\Lib\ZjmfStatusService;
use Illuminate\Support\ServiceProvider;

final class ZjmfFinanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ZjmfCredentialParser::class);
        $this->app->singleton(ZjmfLegacyPasswordVerifier::class);
        $this->app->singleton(ZjmfBillingRestoreProfile::class);
        $this->app->singleton(UpstreamBillingRestoreProfile::class, ZjmfBillingRestoreProfile::class);
        $this->app->singleton(ZjmfBillingRestoreService::class);

        $this->registerUpstreamGraph();
        $this->app->singleton(ZjmfLoginEndpointPolicy::class);

        $this->app->tag([ZjmfCredentialParser::class], 'upstream.web_session_credential_parsers');
        $this->app->tag([ZjmfLegacyPasswordVerifier::class], 'auth.legacy_password_verifiers');
        $this->app->tag([ZjmfLoginEndpointPolicy::class], 'upstream.login_endpoints');
    }

    /**
     * 显式声明上游驱动依赖图，容器优先注入，构造 fallback 仅服务直接 new 场景。
     */
    private function registerUpstreamGraph(): void
    {
        $this->app->singleton(ZjmfAuthManager::class);
        $this->app->singleton(ZjmfFinanceTransport::class);
        $this->app->singleton(ZjmfProductTypeMapper::class);
        $this->app->singleton(ZjmfCloudConfigTemplate::class);
        $this->app->singleton(ZjmfCatalogService::class);
        $this->app->singleton(ZjmfProvisionService::class);
        $this->app->singleton(ZjmfRenewService::class);
        $this->app->singleton(ZjmfStatusService::class);
        $this->app->singleton(ZjmfConsoleService::class);
        $this->app->singleton(ZjmfNetworkService::class);
        $this->app->singleton(ZjmfSecurityService::class);
        $this->app->singleton(ZjmfFinanceAdapter::class);
        $this->app->singleton(ZjmfFinanceDriver::class);
        $this->app->singleton(ZjmfInventoryAndServiceSyncHook::class);
        $this->app->singleton(ZjmfScheduledAuthRefreshHook::class);
        $this->app->singleton(ZjmfInventoryAndServiceSyncTask::class);
        $this->app->singleton(ZjmfScheduledAuthRefreshTask::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                RestoreZjmfBillingCommand::class,
            ]);
        }
    }
}
