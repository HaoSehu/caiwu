<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\CareSendChunkJob;
use App\Models\CarePlan;
use App\Models\CareSendLog;
use App\Models\CareTaskRun;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Services\Care\CarePlanAutomationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * M12 客户关怀计划契约测试。
 *
 * 覆盖：生日 offset 与闰年简化、未填生日 skipped 计数、cycle_key 防重
 * （unique(care_plan_id,user_id,cycle_key) + insertOrIgnore）、流失召回口径
 * （沉默 N 天 + 名下无 active 服务 + 从未登录视为满足）、run 统计、
 * dry_run 不建 run 不落日志不发送。Queue::fake 阻断真实发送。
 */
class CarePlanTest extends TestCase
{
    use DatabaseTransactions;

    private CarePlanAutomationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(CarePlanAutomationService::class);
        Queue::fake();
    }

    // ---------------------------------------------------------------- 生日祝福

    public function test_birthday_offset_targets_expected_date(): void
    {
        // 今天 + 3 天过生日的用户（offset=3 提前三天祝福）。
        $target = $this->makeUserWithBirthDate(now()->copy()->addDays(3));
        $miss = $this->makeUserWithBirthDate(now()->copy()->addDays(4));

        $plan = CarePlan::create([
            'name' => '生日提前3天'.uniqid(),
            'type' => CarePlan::TYPE_BIRTHDAY,
            'offset_days' => 3,
            'channel' => CarePlan::CHANNEL_INBOX,
            'subject' => '生日快乐 {{nickname}}',
            'content' => '祝 {{nickname}} 生日快乐',
            'filter_json' => [],
            'is_enabled' => 1,
        ]);

        $summary = $this->service->runForDate(now(), null, true);
        $planSummary = $this->planSummary($summary, (int) $plan->id);

        $this->assertSame(1, $planSummary['total_count']);

        // 落地执行后仅目标用户持有日志。
        Queue::fake();
        $this->service->runForDate(now(), (int) $plan->id, false);

        $logs = CareSendLog::query()->where('care_plan_id', $plan->id)->get();
        $this->assertSame(1, $logs->count());
        $this->assertSame((int) $target->id, (int) $logs->first()->user_id);
        $this->assertSame(now()->toDateString(), (string) $logs->first()->cycle_key);
        $this->assertNotContains((int) $miss->id, $logs->pluck('user_id')->all());
    }

    public function test_birthday_users_without_birth_date_are_counted_skipped(): void
    {
        $withBirthday = $this->makeUserWithBirthDate(now());
        $this->makeUser([]); // 未填生日

        $plan = CarePlan::create([
            'name' => '生日跳过'.uniqid(),
            'type' => CarePlan::TYPE_BIRTHDAY,
            'offset_days' => 0,
            'channel' => CarePlan::CHANNEL_INBOX,
            'subject' => '生日快乐',
            'content' => '正文',
            'filter_json' => [],
            'is_enabled' => 1,
        ]);

        $summary = $this->service->runForDate(now(), (int) $plan->id, false);

        $this->assertSame(1, $summary['plans'][0]['total_count']);
        $this->assertSame(1, $summary['plans'][0]['skipped_count']);
    }

    public function test_birthday_feb29_simplified_to_feb28_in_common_years(): void
    {
        // 闰日用户在平年 2/28（目标日落在 2/29 时）应命中（简化口径见服务注释）。
        $plan = CarePlan::create([
            'name' => '闰日简化'.uniqid(),
            'type' => CarePlan::TYPE_BIRTHDAY,
            'offset_days' => 0,
            'channel' => CarePlan::CHANNEL_INBOX,
            'subject' => '生日快乐',
            'content' => '正文',
            'filter_json' => [],
            'is_enabled' => 1,
        ]);

        // 固定 2027-02-28 执行（2027 非闰年），生日 2/29 → 目标日 2/29 简化匹配 2/28。
        $leapUser = $this->makeUserWithBirthDate('1996-02-29');

        $summary = $this->service->runForDate(Carbon::parse('2027-02-28'), (int) $plan->id, true);

        $this->assertSame(1, $summary['plans'][0]['total_count'], '平年 2/28 应匹配 2/29 生日（简化口径）');
        $this->assertNotNull($leapUser->birth_date);
    }

    // ---------------------------------------------------------------- 到期提醒（口径验证）

    public function test_expire_reminder_matches_service_expiry_date(): void
    {
        $user = $this->makeUser([]);
        $service = Service::query()->create([
            'user_id' => $user->id,
            'product_id' => (int) $this->makeProduct()->id,
            'name' => '到期关怀服务',
            'billing_cycle' => 'monthly',
            'amount' => '10.00',
            'status' => 1,
            'expires_at' => now()->copy()->addDays(7),
        ]);

        $plan = CarePlan::create([
            'name' => '到期提前7天'.uniqid(),
            'type' => CarePlan::TYPE_EXPIRE,
            'offset_days' => 7,
            'channel' => CarePlan::CHANNEL_INBOX,
            'subject' => '{{service_name}} 将于 {{expires_at}} 到期',
            'content' => '正文',
            'filter_json' => [],
            'is_enabled' => 1,
        ]);

        $summary = $this->service->runForDate(now(), (int) $plan->id, false);

        $this->assertSame(1, $summary['plans'][0]['total_count']);

        $log = CareSendLog::query()->where('care_plan_id', $plan->id)->first();
        $this->assertNotNull($log);
        $this->assertSame('svc:'.$service->id.':'.now()->format('Y-m'), (string) $log->cycle_key);
        $this->assertSame((int) $service->id, (int) $log->service_id);
    }

    // ---------------------------------------------------------------- 流失召回

    public function test_recall_matches_silent_users_without_active_services(): void
    {
        $silent = $this->makeUser(['last_login_at' => now()->subDays(60)]);
        $activeUser = $this->makeUser(['last_login_at' => now()->subDays(60)]);
        $freshUser = $this->makeUser(['last_login_at' => now()->subDays(1)]);
        $neverLogged = $this->makeUser(['last_login_at' => null]);

        // activeUser 有 active 服务 → 不属于流失。
        Service::query()->create([
            'user_id' => $activeUser->id,
            'product_id' => (int) $this->makeProduct()->id,
            'name' => '活跃服务',
            'billing_cycle' => 'monthly',
            'amount' => '10.00',
            'status' => 1,
            'expires_at' => now()->addMonth(),
        ]);

        $plan = CarePlan::create([
            'name' => '沉默30天召回'.uniqid(),
            'type' => CarePlan::TYPE_RECALL,
            'offset_days' => 30,
            'channel' => CarePlan::CHANNEL_INBOX,
            'subject' => '好久不见 {{nickname}}',
            'content' => '正文',
            'filter_json' => [],
            'is_enabled' => 1,
        ]);

        $summary = $this->service->runForDate(now(), (int) $plan->id, false);

        $targetUserIds = CareSendLog::query()->where('care_plan_id', $plan->id)->pluck('user_id')->all();

        $this->assertContains((int) $silent->id, $targetUserIds);
        // 从未登录视为满足沉默口径。
        $this->assertContains((int) $neverLogged->id, $targetUserIds);
        $this->assertNotContains((int) $activeUser->id, $targetUserIds, '名下有 active 服务不属于流失');
        $this->assertNotContains((int) $freshUser->id, $targetUserIds, '未达沉默天数不属于流失');

        // 召回 cycle_key 按月粒度。
        $this->assertSame(now()->format('Y-m'), (string) CareSendLog::query()->where('care_plan_id', $plan->id)->first()->cycle_key);
        $this->assertSame(2, $summary['plans'][0]['total_count'] ?? $summary['plans'][0]['total_count']);
    }

    // ---------------------------------------------------------------- cycle_key 防重

    public function test_cycle_key_uniqueness_prevents_duplicate_send(): void
    {
        $user = $this->makeUserWithBirthDate(now());

        $plan = CarePlan::create([
            'name' => '防重'.uniqid(),
            'type' => CarePlan::TYPE_BIRTHDAY,
            'offset_days' => 0,
            'channel' => CarePlan::CHANNEL_INBOX,
            'subject' => '生日快乐',
            'content' => '正文',
            'filter_json' => [],
            'is_enabled' => 1,
        ]);

        $this->service->runForDate(now(), (int) $plan->id, false);
        // 同日重触发：cycle_key 相同 → 唯一键防重，日志不翻倍。
        $this->service->runForDate(now(), (int) $plan->id, false);

        $this->assertSame(1, CareSendLog::query()->where('care_plan_id', $plan->id)->count());
        $this->assertSame(1, CareTaskRun::query()->where('care_plan_id', $plan->id)->count(), '同计划同日只建一个 run');
    }

    // ---------------------------------------------------------------- run 统计与 dry_run

    public function test_run_records_counts_and_last_planned_date(): void
    {
        $user = $this->makeUserWithBirthDate(now());

        $plan = CarePlan::create([
            'name' => '统计'.uniqid(),
            'type' => CarePlan::TYPE_BIRTHDAY,
            'offset_days' => 0,
            'channel' => CarePlan::CHANNEL_INBOX,
            'subject' => '生日快乐',
            'content' => '正文',
            'filter_json' => [],
            'is_enabled' => 1,
        ]);

        $this->service->runForDate(now(), (int) $plan->id, false);

        $run = CareTaskRun::query()->where('care_plan_id', $plan->id)->first();
        $plan->refresh();

        $this->assertNotNull($run);
        $this->assertSame(1, (int) $run->total_count);
        $this->assertSame(0, (int) $run->sent_count, 'Queue::fake 下 chunk 未执行，sent_count 为 0');
        $this->assertSame(0, (int) $run->failed_count);
        $this->assertSame(CareTaskRun::STATUS_PENDING, (int) $run->status);
        $this->assertSame(now()->toDateString(), $plan->last_planned_date?->toDateString());

        Queue::assertPushed(CareSendChunkJob::class);
    }

    public function test_dry_run_creates_no_run_no_log_and_dispatches_nothing(): void
    {
        $this->makeUserWithBirthDate(now());

        $plan = CarePlan::create([
            'name' => 'dry-run'.uniqid(),
            'type' => CarePlan::TYPE_BIRTHDAY,
            'offset_days' => 0,
            'channel' => CarePlan::CHANNEL_INBOX,
            'subject' => '生日快乐',
            'content' => '正文',
            'filter_json' => [],
            'is_enabled' => 1,
        ]);

        $summary = $this->service->runForDate(now(), (int) $plan->id, true);

        $this->assertTrue($summary['dry_run']);
        $this->assertSame(1, $summary['plans'][0]['total_count']);
        $this->assertSame(0, CareTaskRun::query()->where('care_plan_id', $plan->id)->count());
        $this->assertSame(0, CareSendLog::query()->where('care_plan_id', $plan->id)->count());
        Queue::assertNothingPushed();
    }

    public function test_disabled_plan_is_ignored(): void
    {
        $this->makeUserWithBirthDate(now());

        $plan = CarePlan::create([
            'name' => '停用'.uniqid(),
            'type' => CarePlan::TYPE_BIRTHDAY,
            'offset_days' => 0,
            'channel' => CarePlan::CHANNEL_INBOX,
            'subject' => '生日快乐',
            'content' => '正文',
            'filter_json' => [],
            'is_enabled' => 0,
        ]);

        $summary = $this->service->runForDate(now(), (int) $plan->id, true);

        $this->assertSame([], $summary['plans']);
    }

    // ---------------------------------------------------------------- helpers

    /**
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    private function planSummary(array $summary, int $planId): array
    {
        foreach ($summary['plans'] as $planSummary) {
            if ((int) $planSummary['care_plan_id'] === $planId) {
                return $planSummary;
            }
        }

        $this->fail("runForDate 未返回计划 {$planId} 的结果");
    }

    private function makeUser(array $overrides): User
    {
        return User::query()->create($overrides + [
            'email' => 'care-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'care-tester',
            'status' => 1,
            'total_sales_amount' => 0,
        ]);
    }

    /**
     * services.product_id NOT NULL 且带 FK → 每次先造父行商品。
     */
    private function makeProduct(): Product
    {
        return Product::query()->create([
            'name' => '关怀测试商品'.uniqid(),
            'product_type' => 'cloud_host',
            'status' => 1,
            'pricing' => ['monthly' => '10.00'],
        ]);
    }

    private function makeUserWithBirthDate(string|Carbon $birthDate): User
    {
        return $this->makeUser(['birth_date' => $birthDate instanceof Carbon ? $birthDate->toDateString() : $birthDate]);
    }
}
