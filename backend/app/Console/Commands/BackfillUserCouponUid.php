<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Constants\UserCouponStatus;
use App\Models\UserCoupon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillUserCouponUid extends Command
{
    protected $signature = 'coupons:backfill-uid {--dry-run : 仅统计待回填数量，不写库} {--force : 跳过交互确认，直接执行}';

    protected $description = '回填用户优惠券 uid 与历史已使用状态（批量写库，需显式确认）。';

    public function handle(): int
    {
        if (! $this->option('dry-run') && ! $this->confirmDestructiveOperation()) {
            return self::FAILURE;
        }

        $uidPending = UserCoupon::query()->whereNull('uid')->count();
        $usedPending = UserCoupon::query()
            ->where('status', UserCouponStatus::OWNED)
            ->whereNotNull('last_used_at')
            ->count();

        if ($this->option('dry-run')) {
            $this->info("待回填 uid: {$uidPending} 条；待回填已使用状态: {$usedPending} 条（dry-run 未写库）");

            return self::SUCCESS;
        }

        $uidCount = 0;

        UserCoupon::query()
            ->whereNull('uid')
            ->lazyById(500)
            ->each(function (UserCoupon $userCoupon) use (&$uidCount): void {
                UserCoupon::withoutTimestamps(function () use ($userCoupon): void {
                    $userCoupon->forceFill([
                        'uid' => $this->generateUid(),
                    ])->save();
                });

                $uidCount++;
            });

        $usedCount = UserCoupon::query()
            ->where('status', UserCouponStatus::OWNED)
            ->whereNotNull('last_used_at')
            ->update([
                'status' => UserCouponStatus::USED,
                'used_at' => DB::raw('last_used_at'),
            ]);

        $this->info("uid backfilled: {$uidCount}");
        $this->info("used status backfilled: {$usedCount}");

        return self::SUCCESS;
    }

    /**
     * 生产库批量改写优惠券状态前必须显式确认：该命令会把 OWNED+last_used_at 的行整批改成 USED。
     */
    private function confirmDestructiveOperation(): bool
    {
        if ($this->option('force')) {
            return true;
        }

        if (! $this->input->isInteractive()) {
            $this->error('非交互环境必须显式传入 --force 才能执行批量回填。');

            return false;
        }

        return $this->confirm('将批量改写 user_coupons.uid 与历史使用状态，确认继续？', false);
    }

    private function generateUid(): string
    {
        do {
            $uid = 'uc_'.bin2hex(random_bytes(6));
        } while (UserCoupon::query()->where('uid', $uid)->exists());

        return $uid;
    }
}
