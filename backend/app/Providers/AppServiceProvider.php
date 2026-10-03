<?php

namespace App\Providers;

use App\Listeners\HeartbeatTaskTimedOutListener;
use App\Models\Setting;
use App\Services\Auth\LegacyPasswordVerifier;
use App\Services\Automation\Heartbeat\HeartbeatTaskRegistry;
use App\Services\ProductCatalog\ProductDisplayNameResolver;
use App\Services\ProductCatalog\ProductFullPathResolver;
use App\Services\System\UploadedAssetReferenceService;
use Carbon\CarbonInterface;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobTimedOut;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * 收敛数据库连接并注册若干单例。
     *
     * 连接部分把 database.connections 收敛到 mysql，防止误用其他驱动；
     * 但队列若被配置到独立连接（.env.example 公开的 DB_QUEUE_CONNECTION），
     * 必须把该连接定义一并保留：连接名被抹掉后 Schema::connection() 抛异常，
     * QueueDrainService 会误判为 jobs 表缺失并跳过消费，导致队列静默停摆。
     */
    public function register(): void
    {
        $connections = (array) config('database.connections', []);
        $keptConnections = ['mysql' => (array) ($connections['mysql'] ?? [])];

        // 队列被配置到独立数据库连接时保留其定义，避免「填了一个文档里公开的
        // 合法配置项 → 队列整体静默停摆」；未配置或指向 mysql 时行为不变。
        $queueConnection = trim((string) config('queue.connections.database.connection', ''));
        if ($queueConnection !== '' && $queueConnection !== 'mysql' && isset($connections[$queueConnection])) {
            $keptConnections[$queueConnection] = (array) $connections[$queueConnection];
        }

        config([
            'database.default' => 'mysql',
            'database.connections' => $keptConnections,
        ]);

        $this->app->singleton(UploadedAssetReferenceService::class);
        $this->app->singleton(
            LegacyPasswordVerifier::class,
            fn (): LegacyPasswordVerifier => new LegacyPasswordVerifier($this->app->tagged('auth.legacy_password_verifiers'))
        );
        // 任务注册表跨请求/跨 Job 复用：避免每个心跳 Job 重复扫描全部 Provider（插件清单、任务类实例化、契约校验）。
        $this->app->singleton(HeartbeatTaskRegistry::class);

        $this->app->singleton(ProductDisplayNameResolver::class);
        $this->app->singleton(ProductFullPathResolver::class);
    }

    public function boot(): void
    {
        $this->loadSiteNameFromSettings();

        // 公开询价接口按 IP 收敛阈值：默认 throttle:60,1 不足以限制竞品批量抓取价格。
        RateLimiter::for('product-quote', fn (Request $request) => Limit::perMinute(10)->by('product-quote:'.$request->ip()));

        // 公开目录/详情/库存接口按 IP 收敛：这些端点会联动上游实时库存拉取（浏览覆盖），
        // 过高的单 IP 频率会放大为对供应商接口的请求压力。60/分钟远高于正常浏览节奏。
        RateLimiter::for('site-product-browse', fn (Request $request) => Limit::perMinute(60)->by('site-product-browse:'.$request->ip()));

        // api 组全局限流兜底：细粒度限流（登录、询价等）之外，防止公开端点被滥用时绕过缓存直击 DB。
        // 按 IP 计数；throttle 中间件在 auth 之前执行，此处不依赖登录态。
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by('api:'.$request->ip()));

        // 心跳任务超时被杀时，Worker 在 SIGKILL 前同步派发 JobTimedOut；
        // 监听器把运行台账收敛为 retrying/failed，避免队列重试被状态 CAS 永久拒绝。
        Event::listen(JobTimedOut::class, HeartbeatTaskTimedOutListener::class);

        Sanctum::authenticateAccessTokensUsing(function (PersonalAccessToken $accessToken, bool $isValid): bool {
            if (! $isValid) {
                return false;
            }

            $idleTimeout = max((int) config('sanctum.idle_timeout', 0), 0);
            if ($idleTimeout <= 0) {
                return true;
            }

            $lastActiveAt = $accessToken->last_used_at ?? $accessToken->created_at;
            if (! $lastActiveAt instanceof CarbonInterface) {
                return true;
            }

            if ($lastActiveAt->lt(now()->subSeconds($idleTimeout))) {
                $accessToken->delete();

                return false;
            }

            return true;
        });
    }

    /**
     * 从数据库 settings 表加载管理员设置的站点名称，覆盖 config('app.name')。
     * Setting::getGroupRawValues 内部已有表存在性检查与进程内缓存（首迁移前静默返回空），
     * 这里不再预查 information_schema，避免每个请求固定多付一次元数据查询。
     */
    private function loadSiteNameFromSettings(): void
    {
        $siteName = trim((string) Setting::getValue('basic', 'site_name', ''));
        if ($siteName === '') {
            return;
        }

        config(['app.name' => $siteName]);
    }
}
