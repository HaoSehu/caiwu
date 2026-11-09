<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Constants\CouponStatus;
use App\Exceptions\BusinessException;
use App\Models\AutomationLog;
use App\Models\Coupon;
use App\Models\CouponCampaign;
use App\Models\Product;
use App\Services\ProductCatalog\ProductDisplayNameResolver;
use App\Support\LikeEscape;
use App\Support\Money;
use App\Support\SchemaMetadataCache;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as SimpleLengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CouponCampaignService
{
    private const TASK_KEY = 'coupon-campaign-dispatch';

    private const SUPPORTED_BILLING_CYCLE_LABELS = [
        'monthly' => '月付',
        'quarterly' => '季付',
        'semiannually' => '半年付',
        'annually' => '年付',
    ];

    private const WEEKDAY_LABELS = [
        0 => '周日',
        1 => '周一',
        2 => '周二',
        3 => '周三',
        4 => '周四',
        5 => '周五',
        6 => '周六',
    ];

    public function __construct(
        private CouponService $couponService,
        private readonly ?ProductDisplayNameResolver $productDisplayNameResolver = null,
    ) {}

    public function adminSummary(array $filters = []): array
    {
        if (! SchemaMetadataCache::hasTable('coupon_campaigns')) {
            return [
                'total' => 0,
                'active' => 0,
                'disabled' => 0,
                'generated_today' => 0,
            ];
        }

        $query = $this->buildAdminCampaignQuery($filters, false);
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();

        return [
            'total' => (clone $query)->count(),
            'active' => (clone $query)->where('status', CouponStatus::ACTIVE)->count(),
            'disabled' => (clone $query)->where('status', CouponStatus::DISABLED)->count(),
            'generated_today' => SchemaMetadataCache::hasTable('coupons')
                ? (int) Coupon::query()
                    ->whereNotNull('coupon_campaign_id')
                    ->whereBetween('created_at', [$todayStart, $todayEnd])
                    ->count()
                : 0,
        ];
    }

    public function adminList(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        if (! SchemaMetadataCache::hasTable('coupon_campaigns')) {
            return new SimpleLengthAwarePaginator([], 0, $perPage, 1);
        }

        $paginator = $this->buildAdminCampaignQuery($filters)
            ->with(['lastCoupon:id,name,code,created_at'])
            ->withCount('coupons')
            ->orderByDesc('sort_order')
            ->orderByDesc('id')
            ->paginate($perPage);

        $items = collect($paginator->items());
        $productNameMap = $this->resolveProductNameMapFromCampaigns($items);

        $paginator->setCollection(
            $items
                ->map(fn (CouponCampaign $campaign) => $this->transformCampaignForAdmin($campaign, $productNameMap))
                ->values()
        );

        return $paginator;
    }

    public function createCampaign(array $payload, array $context = []): array
    {
        $campaign = CouponCampaign::query()->create(
            $this->normalizeAdminCampaignPayload($payload, $context)
        );

        $campaign = $campaign->fresh(['lastCoupon'])->loadCount('coupons');

        return $this->transformCampaignForAdmin($campaign, $this->resolveProductNameMapFromCampaigns(collect([$campaign])));
    }

    public function updateCampaign(CouponCampaign $campaign, array $payload, array $context = []): array
    {
        $campaign = DB::transaction(function () use ($campaign, $payload, $context) {
            $lockedCampaign = CouponCampaign::query()
                ->whereKey($campaign->id)
                ->lockForUpdate()
                ->first();

            throw_if(! $lockedCampaign, new BusinessException('活动不存在或已删除'));
            throw_if(
                $this->campaignHasGeneratedCoupons($lockedCampaign),
                new BusinessException('活动已生成优惠券批次，不允许修改')
            );

            $lockedCampaign->fill($this->normalizeAdminCampaignPayload($payload, $context, $lockedCampaign));
            $lockedCampaign->save();

            return $lockedCampaign->fresh(['lastCoupon'])->loadCount('coupons');
        });

        return $this->transformCampaignForAdmin($campaign, $this->resolveProductNameMapFromCampaigns(collect([$campaign])));
    }

    public function toggleCampaignStatus(CouponCampaign $campaign, array $context = []): array
    {
        // 行锁内读-判-写：并发双击时后写者基于翻转后的最新状态再翻转
        DB::transaction(function () use ($campaign, $context): void {
            $locked = CouponCampaign::query()
                ->whereKey($campaign->id)
                ->lockForUpdate()
                ->first();
            throw_if(! $locked, new BusinessException('活动不存在或已删除'));

            $locked->forceFill([
                'status' => (int) $locked->status === CouponStatus::ACTIVE
                    ? CouponStatus::DISABLED
                    : CouponStatus::ACTIVE,
                'operator' => (string) ($context['operator'] ?? $locked->operator ?? ''),
                'trace_id' => (string) ($context['trace_id'] ?? $locked->trace_id ?? ''),
            ])->save();
        });

        $campaign = $campaign->fresh(['lastCoupon'])->loadCount('coupons');

        return $this->transformCampaignForAdmin($campaign, $this->resolveProductNameMapFromCampaigns(collect([$campaign])));
    }

    public function deleteCampaign(CouponCampaign $campaign): void
    {
        DB::transaction(function () use ($campaign): void {
            $lockedCampaign = CouponCampaign::query()
                ->whereKey($campaign->id)
                ->lockForUpdate()
                ->first();

            throw_if(! $lockedCampaign, new BusinessException('活动不存在或已删除'));
            throw_if(
                $this->campaignHasGeneratedCoupons($lockedCampaign),
                new BusinessException('活动已生成优惠券批次，不允许删除')
            );

            $lockedCampaign->delete();
        });
    }

    public function triggerCampaign(CouponCampaign $campaign, array $context = []): array
    {
        throw_if((int) $campaign->status !== CouponStatus::ACTIVE, new BusinessException('请先启用活动后再手动发放'));

        $triggerAt = CarbonImmutable::now(config('app.timezone'));
        $ruleKey = 'manual-'.$triggerAt->format('YmdHis').'-'.Str::lower(Str::random(6));
        $result = $this->dispatchSingleCampaign($campaign, $triggerAt, $ruleKey, $context, true);
        throw_if($result === null, new BusinessException('该活动今日已发放过批次或已被停用，请勿重复操作'));
        $campaign = $campaign->fresh(['lastCoupon'])->loadCount('coupons');

        return [
            'campaign' => $this->transformCampaignForAdmin(
                $campaign,
                $this->resolveProductNameMapFromCampaigns(collect([$campaign]))
            ),
            'coupon' => $result['coupon'],
            'triggered_at' => $triggerAt->format('Y-m-d H:i:s'),
        ];
    }

    public function dispatchDueCampaigns(?CarbonImmutable $now = null, array $context = []): array
    {
        $resolvedNow = $now ?: CarbonImmutable::now(config('app.timezone'));
        $campaigns = CouponCampaign::query()
            ->where('status', CouponStatus::ACTIVE)
            ->orderByDesc('sort_order')
            ->orderByDesc('id')
            ->get();

        $summary = [
            'matched' => $campaigns->count(),
            'triggered' => 0,
            'skipped' => 0,
            'failed' => 0,
            'coupon_ids' => [],
        ];

        foreach ($campaigns as $campaign) {
            // 单活动异常只记日志并跳过：历史脏数据（如非法 trigger_time）曾让
            // resolveDueScheduleAt 在 try 之外抛异常，一个坏活动即中断整批调度，
            // 排序其后的所有活动当日全部停发。
            try {
                $scheduledAt = $this->resolveDueScheduleAt($campaign, $resolvedNow);
                if (! $scheduledAt) {
                    $summary['skipped']++;

                    continue;
                }

                $ruleKey = $scheduledAt->format('YmdHi');
                if (! AutomationLog::recordOnce(self::TASK_KEY, 'dispatch', 'coupon_campaign', (int) $campaign->id, $ruleKey, [
                    'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
                ])) {
                    $summary['skipped']++;

                    continue;
                }

                try {
                    $result = $this->dispatchSingleCampaign($campaign, $scheduledAt, $ruleKey, $context, false);
                    if ($result === null) {
                        $summary['skipped']++;
                        AutomationLog::markExecuted(self::TASK_KEY, 'dispatch', 'coupon_campaign', (int) $campaign->id, $ruleKey, [
                            'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
                            'skipped_reason' => 'already_dispatched_today',
                            'last_dispatched_at' => $campaign->fresh()?->last_dispatched_at?->format('Y-m-d H:i:s'),
                        ]);

                        continue;
                    }

                    $summary['triggered']++;
                    $summary['coupon_ids'][] = (int) ($result['coupon']['id'] ?? 0);
                } catch (\Throwable $exception) {
                    $summary['failed']++;
                    AutomationLog::forgetRecord(self::TASK_KEY, 'dispatch', 'coupon_campaign', (int) $campaign->id, $ruleKey);

                    Log::error('[定时任务] 优惠券活动自动发放失败', [
                        'campaign_id' => (int) $campaign->id,
                        'campaign_name' => (string) $campaign->name,
                        'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
                        'error' => $exception->getMessage(),
                        'exception' => $exception::class,
                    ]);
                }
            } catch (\Throwable $exception) {
                $summary['failed']++;

                Log::error('[定时任务] 优惠券活动调度判定失败，已跳过该活动', [
                    'campaign_id' => (int) $campaign->id,
                    'campaign_name' => (string) $campaign->name,
                    'trigger_time' => (string) ($campaign->trigger_time ?? ''),
                    'error' => $exception->getMessage(),
                    'exception' => $exception::class,
                ]);
            }
        }

        return $summary;
    }

    private function dispatchSingleCampaign(
        CouponCampaign $campaign,
        CarbonImmutable $dispatchAt,
        string $ruleKey,
        array $context = [],
        bool $manual = false,
    ): ?array {
        $couponResult = DB::transaction(function () use ($campaign, $dispatchAt, $context) {
            $lockedCampaign = CouponCampaign::query()
                ->lockForUpdate()
                ->findOrFail((int) $campaign->id);

            // 锁内重读状态：遍历筛选（快照）与锁之间活动可能已被停用，
            // 停用活动不再发出「最后一批」券
            if ((int) $lockedCampaign->status !== CouponStatus::ACTIVE) {
                return null;
            }

            if ($this->campaignDispatchedOnDay($lockedCampaign, $dispatchAt)) {
                return null;
            }

            $payload = $this->buildCouponPayloadFromCampaign($lockedCampaign, $dispatchAt);
            $coupon = $this->couponService->createCoupon($payload, $context);

            $lockedCampaign->forceFill([
                'last_dispatched_at' => $dispatchAt->format('Y-m-d H:i:s'),
                'last_coupon_id' => (int) ($coupon['id'] ?? 0),
                'operator' => (string) ($context['operator'] ?? $lockedCampaign->operator ?? ''),
                'trace_id' => (string) ($context['trace_id'] ?? $lockedCampaign->trace_id ?? ''),
            ])->save();

            return $coupon;
        });

        if ($couponResult === null) {
            return null;
        }

        if (! $manual) {
            AutomationLog::markExecuted(self::TASK_KEY, 'dispatch', 'coupon_campaign', (int) $campaign->id, $ruleKey, [
                'scheduled_at' => $dispatchAt->format('Y-m-d H:i:s'),
                'coupon_id' => (int) ($couponResult['id'] ?? 0),
                'coupon_name' => (string) ($couponResult['name'] ?? ''),
            ]);
        }

        return [
            'coupon' => $couponResult,
        ];
    }

    private function buildAdminCampaignQuery(array $filters = [], bool $applyStatusFilter = true)
    {
        $keyword = trim((string) ($filters['keyword'] ?? ''));
        $status = array_key_exists('status', $filters) ? (string) $filters['status'] : '';

        return CouponCampaign::query()
            ->when($keyword !== '', function ($query) use ($keyword) {
                $query->where(function ($builder) use ($keyword) {
                    $builder
                        ->where('name', 'like', LikeEscape::contains($keyword))
                        ->orWhere('description', 'like', LikeEscape::contains($keyword))
                        ->orWhere('remark', 'like', LikeEscape::contains($keyword));
                });
            })
            ->when($applyStatusFilter && $status !== '', fn ($query) => $query->where('status', (int) $status));
    }

    private function transformCampaignForAdmin(CouponCampaign $campaign, array $productNameMap = []): array
    {
        $weekdays = $this->normalizeWeekdays((array) ($campaign->weekdays ?? []));
        $rawBillingCycles = (array) ($campaign->billing_cycles ?? []);
        $billingCycles = $this->normalizeBillingCycles($rawBillingCycles);
        $productIds = $this->normalizeProductIds((array) ($campaign->product_ids ?? []));
        $coupon = $campaign->lastCoupon;
        $generatedCouponCount = (int) ($campaign->coupons_count ?? 0);
        $canUpdate = $generatedCouponCount === 0;
        $canDelete = $canUpdate;
        $lockReason = $canUpdate ? '' : '活动已生成优惠券批次，不允许修改或删除';

        return [
            'id' => (int) $campaign->id,
            'name' => (string) $campaign->name,
            'description' => (string) ($campaign->description ?? ''),
            'weekdays' => $weekdays,
            'weekdays_text' => $this->formatWeekdaysText($weekdays),
            'trigger_time' => $this->normalizeTimeValue($campaign->trigger_time),
            'trigger_time_text' => substr($this->normalizeTimeValue($campaign->trigger_time), 0, 5),
            'schedule_text' => $this->formatWeekdaysText($weekdays).' '.substr($this->normalizeTimeValue($campaign->trigger_time), 0, 5),
            'issue_quantity' => (int) ($campaign->issue_quantity ?? 0),
            'valid_duration_hours' => $campaign->valid_duration_hours ? (int) $campaign->valid_duration_hours : null,
            'discount_scope' => (string) ($campaign->discount_scope ?? 'first_month'),
            'discount_scope_label' => $this->resolveDiscountScopeLabel((string) ($campaign->discount_scope ?? 'first_month')),
            'discount_type' => (string) ($campaign->discount_type ?? 'fixed'),
            'discount_type_label' => $this->resolveDiscountTypeLabel((string) ($campaign->discount_type ?? 'fixed')),
            'discount_value' => Money::format((float) ($campaign->discount_value ?? 0)),
            'discount_value_raw' => (float) ($campaign->discount_value ?? 0),
            'discount_label' => $this->buildDiscountLabel((string) ($campaign->discount_type ?? 'fixed'), (float) ($campaign->discount_value ?? 0)),
            'min_amount' => Money::format((float) ($campaign->min_amount ?? 0)),
            'min_amount_raw' => (float) ($campaign->min_amount ?? 0),
            'max_discount_amount' => $campaign->max_discount_amount !== null
                ? Money::format((float) $campaign->max_discount_amount)
                : null,
            'max_discount_amount_raw' => $campaign->max_discount_amount !== null
                ? (float) $campaign->max_discount_amount
                : null,
            'billing_cycles' => $billingCycles,
            'billing_cycle_text' => $this->formatBillingCycleText($rawBillingCycles),
            'product_ids' => $productIds,
            'product_scope_text' => $this->formatProductScopeText($productIds, $productNameMap),
            'requires_product_ids' => $this->normalizeProductIds((array) ($campaign->requires_product_ids ?? [])),
            'first_order_only' => (bool) $campaign->first_order_only,
            'only_old_client' => (bool) ($campaign->only_old_client ?? false),
            'is_discount' => (bool) ($campaign->is_discount ?? true),
            'per_user_limit' => $campaign->per_user_limit ? (int) $campaign->per_user_limit : null,
            'status' => (int) ($campaign->status ?? CouponStatus::ACTIVE),
            'status_label' => CouponStatus::$labels[(int) ($campaign->status ?? CouponStatus::ACTIVE)] ?? '未知',
            'display_status' => (int) ($campaign->status ?? CouponStatus::ACTIVE) === CouponStatus::ACTIVE ? 'active' : 'disabled',
            'display_status_label' => (int) ($campaign->status ?? CouponStatus::ACTIVE) === CouponStatus::ACTIVE ? '运行中' : '已停用',
            'sort_order' => (int) ($campaign->sort_order ?? 0),
            'generated_coupon_count' => $generatedCouponCount,
            'next_run_at' => $this->resolveNextRunAt($weekdays, $this->normalizeTimeValue($campaign->trigger_time)),
            'last_dispatched_at' => $campaign->last_dispatched_at?->format('Y-m-d H:i:s'),
            'last_coupon_id' => (int) ($campaign->last_coupon_id ?? 0),
            'last_coupon_name' => (string) ($coupon?->name ?? ''),
            'last_coupon_code' => (string) ($coupon?->code ?? ''),
            'remark' => (string) ($campaign->remark ?? ''),
            'operator' => (string) ($campaign->operator ?? ''),
            'trace_id' => (string) ($campaign->trace_id ?? ''),
            'created_at' => $campaign->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $campaign->updated_at?->format('Y-m-d H:i:s'),
            'can_update' => $canUpdate,
            'can_delete' => $canDelete,
            'lock_reason' => $lockReason,
        ];
    }

    private function campaignHasGeneratedCoupons(CouponCampaign $campaign): bool
    {
        if (array_key_exists('coupons_count', $campaign->getAttributes())) {
            return (int) ($campaign->getAttribute('coupons_count') ?? 0) > 0;
        }

        return $campaign->coupons()->exists();
    }

    private function normalizeAdminCampaignPayload(array $payload, array $context = [], ?CouponCampaign $campaign = null): array
    {
        $discountType = trim((string) ($payload['discount_type'] ?? ''));
        $discountScope = trim((string) ($payload['discount_scope'] ?? 'first_month'));
        $discountValue = round((float) ($payload['discount_value'] ?? 0), 2);
        $minAmount = round((float) ($payload['min_amount'] ?? 0), 2);
        $maxDiscountAmount = $payload['max_discount_amount'] ?? null;
        $weekdays = $this->normalizeWeekdays((array) ($payload['weekdays'] ?? []));
        $rawTriggerTime = trim((string) ($payload['trigger_time'] ?? ''));
        $triggerTime = $this->normalizeTimeValue($rawTriggerTime);
        $issueQuantity = (int) ($payload['issue_quantity'] ?? 0);
        $validDurationHours = $this->normalizePositiveInteger($payload['valid_duration_hours'] ?? null);

        throw_if(trim((string) ($payload['name'] ?? '')) === '', new BusinessException('活动名称不能为空'));
        throw_if($weekdays === [], new BusinessException('至少选择一个发放星期'));
        throw_if($rawTriggerTime === '', new BusinessException('发放时间不能为空'));
        throw_if($triggerTime === '', new BusinessException('触发时间不合法'));
        throw_if(! in_array($discountType, ['fixed', 'percentage', 'override'], true), new BusinessException('优惠类型不正确'));
        throw_if(! in_array($discountScope, ['first_month', 'recurring', 'renew'], true), new BusinessException('优惠阶段不正确'));
        throw_if($discountValue <= 0, new BusinessException('优惠值必须大于 0'));
        throw_if($issueQuantity <= 0, new BusinessException('发放数量必须大于 0'));

        // 首单券 × 仅续费 是静默死配置（与券模板同口径）：活动生成的券同样会被
        // CouponService::normalizeAdminCouponPayload 拦截，提前在活动配置侧报错，
        // 避免到定时发放时才失败（发放失败会导致当日批次丢失）。
        if ((bool) ($payload['first_order_only'] ?? false) && $discountScope === 'renew') {
            throw new BusinessException('首单券不支持「仅续费」优惠阶段：续费必然发生在首单之后，二者组合的券永远无法使用');
        }

        // 仅新客首单 × 仅老客 语义互斥（与券模板同口径）
        if ((bool) ($payload['first_order_only'] ?? false) && (bool) ($payload['only_old_client'] ?? false)) {
            throw new BusinessException('「仅首单」与「仅老客」互斥：首单券面向从未成交的用户，仅老客面向已有成交订单的用户');
        }

        if ($discountType === 'percentage') {
            throw_if($discountValue > 100, new BusinessException('折扣百分比不能大于 100'));
        }

        // 覆盖价券：discount_value 即覆盖价，最低 0.01（与券模板封顶口径一致）
        if ($discountType === 'override') {
            throw_if($discountValue < 0.01, new BusinessException('覆盖价不能小于 0.01'));
        }

        if ($maxDiscountAmount !== null && $maxDiscountAmount !== '') {
            $maxDiscountAmount = round((float) $maxDiscountAmount, 2);
            throw_if($maxDiscountAmount < 0, new BusinessException('最高优惠金额不能小于 0'));
        } else {
            $maxDiscountAmount = null;
        }

        return [
            'name' => trim((string) ($payload['name'] ?? '')),
            'description' => trim((string) ($payload['description'] ?? '')) ?: null,
            'weekdays' => $weekdays,
            'trigger_time' => $triggerTime,
            'issue_quantity' => $issueQuantity,
            'valid_duration_hours' => $validDurationHours,
            'discount_scope' => $discountScope,
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'min_amount' => $minAmount,
            'max_discount_amount' => $maxDiscountAmount,
            'billing_cycles' => $this->normalizeBillingCycles((array) ($payload['billing_cycles'] ?? [])),
            'product_ids' => $this->normalizeProductIds((array) ($payload['product_ids'] ?? [])),
            'requires_product_ids' => $this->normalizeProductIds((array) ($payload['requires_product_ids'] ?? [])),
            'first_order_only' => (bool) ($payload['first_order_only'] ?? false),
            'only_old_client' => (bool) ($payload['only_old_client'] ?? false),
            'is_discount' => (bool) ($payload['is_discount'] ?? true),
            'per_user_limit' => $this->normalizePositiveInteger($payload['per_user_limit'] ?? null),
            'status' => (int) ($payload['status'] ?? ($campaign?->status ?? CouponStatus::ACTIVE)),
            'sort_order' => max((int) ($payload['sort_order'] ?? 0), 0),
            'remark' => trim((string) ($payload['remark'] ?? '')) ?: null,
            'operator' => (string) ($context['operator'] ?? ''),
            'trace_id' => (string) ($context['trace_id'] ?? ''),
        ];
    }

    private function buildCouponPayloadFromCampaign(CouponCampaign $campaign, CarbonImmutable $dispatchAt): array
    {
        $batchLabel = $dispatchAt->format('m-d H:i');
        $remarkParts = array_filter([
            trim((string) ($campaign->remark ?? '')) ?: null,
            '活动批次：'.$dispatchAt->format('Y-m-d H:i'),
        ]);

        // 活动名 120 字符上限 + 批次后缀会超出 coupons.name varchar(120)，MySQL strict
        // 下插入失败会让该活动每轮调度都失败、券永远发不出去。按字符截断（不是字节），
        // 中文名不会被截成半个字符。
        $couponName = mb_substr(trim((string) $campaign->name).' '.$batchLabel, 0, 120);
        $description = trim((string) ($campaign->description ?? ''));

        return [
            'coupon_campaign_id' => (int) $campaign->id,
            'name' => $couponName,
            'description' => $description !== '' ? mb_substr($description, 0, 255) : null,
            'distribution_type' => 'public',
            'discount_scope' => (string) ($campaign->discount_scope ?? 'first_month'),
            'discount_type' => (string) ($campaign->discount_type ?? 'fixed'),
            'discount_value' => (float) ($campaign->discount_value ?? 0),
            'min_amount' => (float) ($campaign->min_amount ?? 0),
            'max_discount_amount' => $campaign->max_discount_amount !== null ? (float) $campaign->max_discount_amount : null,
            'billing_cycles' => $this->normalizeBillingCycles((array) ($campaign->billing_cycles ?? [])),
            'product_ids' => $this->normalizeProductIds((array) ($campaign->product_ids ?? [])),
            'requires_product_ids' => $this->normalizeProductIds((array) ($campaign->requires_product_ids ?? [])),
            'first_order_only' => (bool) ($campaign->first_order_only ?? false),
            'only_old_client' => (bool) ($campaign->only_old_client ?? false),
            'is_discount' => (bool) ($campaign->is_discount ?? true),
            'total_usage_limit' => (int) ($campaign->issue_quantity ?? 0),
            'per_user_limit' => $campaign->per_user_limit ? (int) $campaign->per_user_limit : null,
            'status' => CouponStatus::ACTIVE,
            'sort_order' => (int) ($campaign->sort_order ?? 0),
            'starts_at' => $dispatchAt->format('Y-m-d H:i:s'),
            // 有效期从实际生成时刻起算：调度中断后的当日补发（dispatchAt 为计划时刻）若仍按
            // 计划时刻起算，晚到的批次有效期被压缩，中断超过时长则生成即过期
            'expires_at' => $campaign->valid_duration_hours
                ? CarbonImmutable::now(config('app.timezone'))
                    ->addHours((int) $campaign->valid_duration_hours)
                    ->format('Y-m-d H:i:s')
                : null,
            'remark' => $remarkParts === [] ? null : implode(' / ', $remarkParts),
        ];
    }

    private function resolveDueScheduleAt(CouponCampaign $campaign, CarbonImmutable $now): ?CarbonImmutable
    {
        $weekdays = $this->normalizeWeekdays((array) ($campaign->weekdays ?? []));
        if ($weekdays === [] || ! in_array((int) $now->dayOfWeek, $weekdays, true)) {
            return null;
        }

        $triggerTime = $this->normalizeTimeValue($campaign->trigger_time);
        if ($triggerTime === '') {
            return null;
        }

        $scheduledAt = $now->startOfDay()->setTimeFromTimeString($triggerTime);

        if ($scheduledAt->greaterThan($now)) {
            return null;
        }

        if ($this->campaignDispatchedOnDay($campaign, $now)) {
            return null;
        }

        return $scheduledAt;
    }

    private function campaignDispatchedOnDay(CouponCampaign $campaign, CarbonImmutable $day): bool
    {
        $lastDispatchedAt = $campaign->last_dispatched_at;
        if (! $lastDispatchedAt) {
            return false;
        }

        $lastCarbon = $lastDispatchedAt instanceof \DateTimeInterface
            ? CarbonImmutable::instance($lastDispatchedAt)->setTimezone(config('app.timezone'))
            : CarbonImmutable::parse((string) $lastDispatchedAt, config('app.timezone'));

        return $lastCarbon->isSameDay($day);
    }

    private function resolveNextRunAt(array $weekdays, string $triggerTime): ?string
    {
        if ($weekdays === [] || $triggerTime === '') {
            return null;
        }

        $now = CarbonImmutable::now(config('app.timezone'));

        for ($offset = 0; $offset <= 13; $offset++) {
            $candidateDate = $now->startOfDay()->addDays($offset);
            if (! in_array((int) $candidateDate->dayOfWeek, $weekdays, true)) {
                continue;
            }

            $candidateAt = $candidateDate->setTimeFromTimeString($triggerTime);
            if ($candidateAt->greaterThanOrEqualTo($now)) {
                return $candidateAt->format('Y-m-d H:i:s');
            }
        }

        return null;
    }

    private function formatWeekdaysText(array $weekdays): string
    {
        if ($weekdays === []) {
            return '未配置星期';
        }

        return collect($weekdays)
            ->map(fn (int $weekday) => self::WEEKDAY_LABELS[$weekday] ?? '未知')
            ->implode(' / ');
    }

    private function buildDiscountLabel(string $discountType, float $discountValue): string
    {
        if ($discountType === 'fixed') {
            return '立减 ¥'.Money::format($discountValue);
        }

        if ($discountType === 'percentage') {
            return Money::trimZero(number_format($discountValue / 10, 1, '.', '')).' 折优惠';
        }

        if ($discountType === 'override') {
            return '一口价 ¥'.Money::format($discountValue);
        }

        return '优惠券';
    }

    private function resolveDiscountTypeLabel(string $discountType): string
    {
        return match ($discountType) {
            'fixed' => '满减券',
            'percentage' => '折扣券',
            'override' => '覆盖价券',
            default => '优惠券',
        };
    }

    private function resolveDiscountScopeLabel(string $discountScope): string
    {
        return match ($discountScope) {
            'recurring' => '持续优惠',
            'renew' => '续费优惠',
            default => '新购优惠',
        };
    }

    private function formatBillingCycleText(array $billingCycles): string
    {
        if (! $this->hasConfiguredBillingCycles($billingCycles)) {
            return '全部周期可用';
        }

        $normalizedBillingCycles = $this->normalizeBillingCycles($billingCycles);
        if ($normalizedBillingCycles === []) {
            return '未配置可用周期';
        }

        return collect($normalizedBillingCycles)
            ->map(fn (string $cycle) => self::SUPPORTED_BILLING_CYCLE_LABELS[$cycle])
            ->implode(' / ');
    }

    private function formatProductScopeText(array $productIds, array $productNameMap): string
    {
        if ($productIds === []) {
            return '全站商品可用';
        }

        $names = array_values(array_filter(array_map(
            fn (int $productId) => $productNameMap[$productId] ?? '',
            $productIds
        )));

        if ($names === []) {
            return '指定商品可用';
        }

        return implode(' / ', $names);
    }

    private function resolveProductNameMapFromCampaigns(Collection $campaigns): array
    {
        $productIds = $campaigns
            ->flatMap(fn (CouponCampaign $campaign) => $this->normalizeProductIds((array) ($campaign->product_ids ?? [])))
            ->unique()
            ->values()
            ->all();

        if ($productIds === []) {
            return [];
        }

        // products 表已无 name 列（只有 custom_display_name），旧实现按 name 取值会让
        // 含「适用商品」的活动列表/详情整页 500（create 先落库再回读同样 500）。
        // 这里走与券列表相同的 Product 模型 + 展示名解析；任何解析失败（单行或整批查询）
        // 只降级为兜底文案，绝不向 transform 抛出异常。
        try {
            $selectColumns = array_merge(
                ['id', 'pricing', 'purchase_requires', 'config_options'],
                Product::optionalSelectColumns(['custom_display_name'])
            );
            $products = Product::query()
                ->select($selectColumns)
                ->whereIn('id', $productIds)
                ->get();

            $resolver = $this->resolveProductDisplayNameResolver();
            $resolver->preloadSourceProducts($products);
            $resolver->preloadCustomDisplayNames($products);

            $nameMap = $products
                ->mapWithKeys(function (Product $product) use ($resolver) {
                    $productId = (int) $product->id;

                    try {
                        // resolveForProduct 的返回契约保证 product_display_name 恒为 string，
                        // 不需要 ?? 兜底；异常兜底靠 catch，空串再走下面的降级链。
                        $displayName = trim($resolver->resolveForProduct($product)['product_display_name']);
                    } catch (\Throwable) {
                        $displayName = '';
                    }

                    if ($displayName === '') {
                        $displayName = trim((string) ($product->custom_display_name ?? ''));
                    }

                    return [$productId => $displayName !== '' ? $displayName : '商品#'.$productId];
                })
                ->all();

            // 已删除/不存在的商品 id 也要落到「商品#id」：活动配置里的历史 id 不应静默消失
            foreach ($productIds as $productId) {
                $nameMap[$productId] = $nameMap[$productId] ?? '商品#'.$productId;
            }

            return $nameMap;
        } catch (\Throwable $exception) {
            Log::warning('[优惠券活动] 适用商品名解析失败，已降级为兜底文案', [
                'product_ids' => $productIds,
                'error' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            return [];
        }
    }

    private function resolveProductDisplayNameResolver(): ProductDisplayNameResolver
    {
        // 必须与容器单例一致：预载写在单例上，new 实例会让预载架空、逐行解析各发一次查询
        return $this->productDisplayNameResolver ?? app(ProductDisplayNameResolver::class);
    }

    private function normalizeWeekdays(array $weekdays): array
    {
        return collect($weekdays)
            ->map(fn ($day) => (int) $day)
            ->filter(fn (int $day) => array_key_exists($day, self::WEEKDAY_LABELS))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function normalizeProductIds(array $productIds): array
    {
        return collect($productIds)
            ->map(function ($id) {
                if (is_string($id) && str_starts_with($id, 'group-')) {
                    return null;
                }

                return (int) $id;
            })
            ->filter(fn (?int $id) => $id !== null && $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeBillingCycles(array $billingCycles): array
    {
        return collect($billingCycles)
            ->map(fn ($item) => trim((string) $item))
            ->filter(fn (string $cycle) => $cycle !== '' && array_key_exists($cycle, self::SUPPORTED_BILLING_CYCLE_LABELS))
            ->unique()
            ->values()
            ->all();
    }

    private function hasConfiguredBillingCycles(array $billingCycles): bool
    {
        return collect($billingCycles)
            ->map(fn ($item) => trim((string) $item))
            ->contains(fn (string $cycle) => $cycle !== '');
    }

    private function normalizePositiveInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $number = (int) $value;

        return $number > 0 ? $number : null;
    }

    /**
     * 触发时间归一化：同时用于保存与读取。
     *
     * 除形态外必须校验真实取值——99:99 形态合法但 setTimeFromTimeString 会抛
     * DateMalformedStringException。读取路径（管理端列表、调度判定）遇到历史上
     * 已落库的坏值统一降级为空串，不得抛异常：单条坏配置不能让列表整页 500
     * 或让整批活动停发。
     */
    private function normalizeTimeValue(mixed $value): string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return '';
        }

        if (preg_match('/^\d{2}:\d{2}$/', $text)) {
            $text .= ':00';
        }

        if (preg_match('/^(\d{2}):(\d{2}):(\d{2})$/', $text, $matches) !== 1) {
            return '';
        }

        if ((int) $matches[1] > 23 || (int) $matches[2] > 59 || (int) $matches[3] > 59) {
            return '';
        }

        return $text;
    }
}
