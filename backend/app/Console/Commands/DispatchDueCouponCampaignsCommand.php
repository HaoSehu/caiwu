<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Finance\CouponCampaignService;
use App\Support\CacheKey;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 优惠券活动发放命令。
 *
 * 与心跳 15 分钟槽位解耦，由 routes/console.php 的 Laravel Schedule 每分钟驱动；
 * 服务层通过 AutomationLog::recordOnce（分钟级 ruleKey）与按日发放记录双重幂等，
 * 重复执行安全，可让活动 trigger_time 的分钟级配置精确生效。
 *
 * 并发阻塞：命令内部持有分布式锁（对齐 queue:drain 的命令内锁模式，不依赖
 * Laravel Schedule mutex，Windows + file 缓存环境同样有效）。上一轮执行未结束
 * 时本轮直接跳过；锁后端故障时降级为无锁执行，正确性由服务层幂等兜底。
 */
final class DispatchDueCouponCampaignsCommand extends Command
{
    private const LOCK_NAME = 'coupon-dispatch-due-campaigns';

    /**
     * 锁 TTL 只作为持有者崩溃后的自然过期上界；正常路径在 finally 中主动释放。
     * 取值需覆盖最慢一次发放（大量活动同时到期），5 分钟远超正常耗时。
     */
    private const LOCK_TTL_SECONDS = 300;

    protected $signature = 'coupon:dispatch-due-campaigns {--at= : 指定触发时间，供测试和排查使用}';

    protected $description = '按优惠券活动配置的星期与时间自动发放公开优惠券（幂等，每分钟调度）';

    public function handle(CouponCampaignService $service): int
    {
        $lock = $this->acquireDispatchLock();

        if ($lock === false) {
            $this->line((string) json_encode([
                'status' => 'skipped',
                'reason' => 'previous_dispatch_in_progress',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        try {
            $at = trim((string) $this->option('at'));
            $now = $at !== ''
                ? CarbonImmutable::parse($at, (string) config('app.timezone', date_default_timezone_get()))
                : null;

            $summary = $service->dispatchDueCampaigns($now);

            $this->line((string) json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        } finally {
            $lock?->release();
        }
    }

    /**
     * @return Lock|null|false 持有的锁；false 表示上一轮仍在执行（本轮跳过）；null 表示锁后端不可用，降级为无锁执行
     */
    private function acquireDispatchLock(): Lock|null|false
    {
        try {
            $lock = Cache::lock(CacheKey::lock('scheduler', self::LOCK_NAME), self::LOCK_TTL_SECONDS);

            return $lock->get() ? $lock : false;
        } catch (Throwable $exception) {
            Log::warning('[优惠券活动] 并发锁不可用，本轮降级为无锁执行（服务层幂等兜底）', [
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            return null;
        }
    }
}
