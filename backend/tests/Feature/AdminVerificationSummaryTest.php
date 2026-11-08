<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\Auth\AdminVerificationQueryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 管理端实名认证汇总的状态口径。
 *
 * summary() 用四条 selectRaw 按状态统计，2026-11-09 从硬编码数字改为
 * 常量绑定参数。占位符与绑定数组一旦错位，各分组会静默相互串数，
 * 因此逐状态断言：每个状态只计入自己那一栏。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class AdminVerificationSummaryTest extends TestCase
{
    use DatabaseTransactions;

    public function test_summary_counts_each_status_into_its_own_bucket(): void
    {
        $this->makeUserWithStatus(User::VERIFICATION_STATUS_NONE);
        $this->makeUserWithStatus(User::VERIFICATION_STATUS_PENDING_SUBMIT);
        $verified = $this->makeUserWithStatus(User::VERIFICATION_STATUS_VERIFIED);
        $this->makeUserWithStatus(User::VERIFICATION_STATUS_FAILED);
        $pendingReview = $this->makeUserWithStatus(User::VERIFICATION_STATUS_PENDING_REVIEW);
        $unbound = $this->makeUserWithStatus(User::VERIFICATION_STATUS_UNBOUND);

        $summary = app(AdminVerificationQueryService::class)->summary();
        $stats = $summary['stats'];

        // 计数用的是全表口径，其余用例造的数据也在内；这里断言相对关系，
        // 即每个状态确实落在自己的桶里，且不被相邻状态串味。
        $this->assertGreaterThanOrEqual(1, $stats['verified'], '已认证至少包含本用例造的用户');
        $this->assertGreaterThanOrEqual(1, $stats['pending'], '待认证应统计待审核状态');
        $this->assertGreaterThanOrEqual(1, $stats['failed'], '认证失败至少包含本用例造的用户');
        $this->assertGreaterThanOrEqual(1, $stats['unbound'], '已解绑至少包含本用例造的用户');

        // 关键点：pending 统计的是「待审核(4)」，不是「待提交(1)」。
        // 若占位符错位把 4 绑成了 2，pending 会统计成已认证数。
        $before = $stats['pending'];
        $this->makeUserWithStatus(User::VERIFICATION_STATUS_PENDING_REVIEW);
        $after = app(AdminVerificationQueryService::class)->summary()['stats']['pending'];
        $this->assertSame($before + 1, $after, '新增一个待审核用户，pending 必须 +1');

        // 反向验证：新增已认证用户只会抬 verified，不得影响 pending。
        $pendingBefore = $after;
        $verifiedBefore = $stats['verified'] + 1;
        $this->makeUserWithStatus(User::VERIFICATION_STATUS_VERIFIED);
        $latest = app(AdminVerificationQueryService::class)->summary()['stats'];
        $this->assertSame($pendingBefore, $latest['pending'], '新增已认证用户不得影响 pending');
        $this->assertSame($verifiedBefore, $latest['verified'], '新增已认证用户，verified 必须 +1');

        // 各分桶只覆盖 4 个状态中的一部分（未认证、待提交不计入任何分桶），
        // 因此分桶之和必须小于等于 total；若占位符错位导致重复计数，会突破这个上界。
        $this->assertLessThanOrEqual(
            $latest['total'],
            $latest['verified'] + $latest['pending'] + $latest['failed'] + $latest['unbound'],
            '各分桶之和不得超过总数'
        );
    }

    private function makeUserWithStatus(int $status): User
    {
        return User::query()->create([
            'email' => 'verif-summary-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'verif-summary-'.$status,
            'total_sales_amount' => 0,
            'status' => 1,
            'verification_status' => $status,
        ]);
    }
}
