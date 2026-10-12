<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Console\Commands\DispatchDueCouponCampaignsCommand;
use App\Services\Finance\CouponCampaignService;
use App\Support\CacheKey;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Tests\TestCase;

/**
 * 优惠券活动发放命令的并发阻塞回归：
 * 每分钟调度下同一命令串行执行（锁被占用即跳过本轮），
 * 锁后端故障时降级为无锁执行（正确性由服务层幂等兜底，不阻断发放）。
 */
class DispatchDueCouponCampaignsCommandTest extends TestCase
{
    public function test_runs_dispatch_while_holding_lock(): void
    {
        $service = $this->mock(CouponCampaignService::class);
        $service->shouldReceive('dispatchDueCampaigns')
            ->once()
            ->with(null)
            ->andReturn(['matched' => 2, 'triggered' => 1, 'skipped' => 1, 'failed' => 0]);

        $this->artisan(DispatchDueCouponCampaignsCommand::class)
            ->expectsOutputToContain('"triggered":1')
            ->assertExitCode(0);
    }

    public function test_skips_when_previous_dispatch_still_holds_lock(): void
    {
        // 在同一缓存存储中预占命令锁，模拟上一轮执行尚未结束。
        $preoccupied = Cache::lock(CacheKey::lock('scheduler', 'coupon-dispatch-due-campaigns'), 300);
        $this->assertTrue($preoccupied->get());

        $service = $this->mock(CouponCampaignService::class);
        $service->shouldNotReceive('dispatchDueCampaigns');

        $this->artisan(DispatchDueCouponCampaignsCommand::class)
            ->expectsOutputToContain('previous_dispatch_in_progress')
            ->assertExitCode(0);
    }

    public function test_degrades_to_lockless_execution_when_lock_backend_fails(): void
    {
        Cache::shouldReceive('lock')->andThrow(new RuntimeException('lock backend unavailable'));

        $service = $this->mock(CouponCampaignService::class);
        $service->shouldReceive('dispatchDueCampaigns')
            ->once()
            ->with(null)
            ->andReturn(['matched' => 0]);

        $this->artisan(DispatchDueCouponCampaignsCommand::class)
            ->expectsOutputToContain('"matched":0')
            ->assertExitCode(0);
    }
}
