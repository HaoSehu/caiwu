<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Constants\CouponStatus;
use App\Constants\InvoiceStatus;
use App\Constants\OrderStatus;
use App\Constants\UserCouponStatus;
use App\Exceptions\BusinessException;
use App\Jobs\SyncInvoiceCouponUsageJob;
use App\Models\Coupon;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\UserCoupon;
use App\Services\Finance\Concerns\ResolvesCouponDisplayStatus;
use App\Services\Finance\Concerns\ResolvesCouponProductDisplay;
use App\Services\Finance\Coupons\CouponAdminGuardService;
use App\Services\Finance\Coupons\CouponNormalizer;
use App\Services\Finance\Coupons\CouponOwnershipService;
use App\Services\ProductCatalog\InstanceSpecCatalogService;
use App\Services\ProductCatalog\ProductDisplayNameResolver;
use App\Support\LikeEscape;
use App\Support\Money;
use App\Support\SchemaMetadataCache;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CouponService
{
    use ResolvesCouponDisplayStatus;
    use ResolvesCouponProductDisplay;

    public function __construct(
        private readonly ?ProductDisplayNameResolver $productDisplayNameResolver = null,
        private readonly ?InstanceSpecCatalogService $instanceSpecCatalogService = null,
    ) {}

    /**
     * 视为「已消耗」的账单状态：已支付 = 核销完成；已退款 = 全额退款落地后券不返还
     * （防「购-付-退」循环薅券，取消路径不受影响仍回落可复用）。
     * 占用拦截、展示状态、核销计数（used_count）、删除拦截共用本判定。
     */
    private const USED_INVOICE_STATUSES = [
        InvoiceStatus::PAID,
        InvoiceStatus::REFUNDED,
    ];

    /**
     * 使持有券不可再次使用的账单状态：待支付=结算占用（挂账）；已支付=已核销，
     * 或已核销但异步同步尚未完成；已退款=退款后券已消耗（同步未完成的窗口内同样拦截）。
     * 列表排除、分组归属、展示状态、占用拦截四处共用。
     */
    private const COUPON_BLOCKING_INVOICE_STATUSES = [
        InvoiceStatus::UNPAID,
        InvoiceStatus::PAID,
        InvoiceStatus::REFUNDED,
    ];

    /**
     * 视为「已消耗」的订单状态：与 USED_INVOICE_STATUSES 同口径（券按账单核销，订单侧只做汇总展示）。
     */
    private const USED_ORDER_STATUSES = [
        OrderStatus::PAID,
        OrderStatus::REFUNDED,
    ];

    public function adminSummary(array $filters = []): array
    {
        if (! CouponAdminGuardService::hasCouponsTable()) {
            return [
                'total' => 0,
                'active' => 0,
                'expired' => 0,
                'disabled' => 0,
                'public_total' => 0,
                'private_total' => 0,
                'total_used' => 0,
            ];
        }

        $query = $this->buildAdminCouponQuery($filters, false);
        $now = now();

        return [
            'total' => (clone $query)->count(),
            'active' => (clone $query)
                ->where('status', CouponStatus::ACTIVE)
                ->where(function ($builder) use ($now) {
                    $builder->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
                })
                ->where(function ($builder) use ($now) {
                    $builder->whereNull('expires_at')->orWhere('expires_at', '>=', $now);
                })
                ->count(),
            'expired' => (clone $query)
                ->where('status', CouponStatus::ACTIVE)
                ->whereNotNull('expires_at')
                ->where('expires_at', '<', $now)
                ->count(),
            'disabled' => (clone $query)
                ->where('status', CouponStatus::DISABLED)
                ->count(),
            'public_total' => (clone $query)->where('distribution_type', 'public')->count(),
            'private_total' => (clone $query)->where('distribution_type', 'private')->count(),
            'total_used' => (int) ((clone $query)->sum('used_count') ?? 0),
        ];
    }

    public function isCouponFeatureEnabled(): bool
    {
        return CouponAdminGuardService::hasCouponsTable();
    }

    public function adminList(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        if (! CouponAdminGuardService::hasCouponsTable()) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage, 1);
        }

        $paginator = $this->buildAdminCouponQuery($filters)
            // D1B-03：列表页不再全量加载 userCoupons 关系，user_ids 由 resolveGrantUserIdsMapByCouponIds 按需单列批量获取
            ->with(['couponCampaign:id,name'])
            ->withCount([
                'orders',
                'userCoupons',
                'orders as used_orders_count' => fn ($query) => $query->whereIn('status', self::USED_ORDER_STATUSES),
                'invoices as used_invoices_count' => fn ($query) => $query->whereIn('status', self::USED_INVOICE_STATUSES),
                'userCoupons as used_user_coupons_count' => fn ($query) => $query->whereNotNull('last_used_at'),
                'orders as deletion_blocking_orders_count' => fn ($query) => $query->where('status', '!=', OrderStatus::CANCELLED),
                'invoices as deletion_blocking_invoices_count' => fn ($query) => $query->where('status', '!=', InvoiceStatus::CANCELLED),
            ])
            ->orderByDesc('sort_order')
            ->orderByDesc('id')
            ->paginate($perPage);

        $items = collect($paginator->items());
        $productNameMap = $this->resolveProductNameMapFromCoupons($items);
        $grantUserIdsMap = $this->resolveGrantUserIdsMapByCouponIds(
            $items->map(fn (Coupon $coupon) => (int) $coupon->id)->all()
        );

        $paginator->setCollection(
            $items
                ->map(fn (Coupon $coupon) => $this->transformCouponForAdmin($coupon, $productNameMap, $grantUserIdsMap))
                ->values()
        );

        return $paginator;
    }

    public function createCoupon(array $payload, array $context = []): array
    {
        CouponAdminGuardService::assertCouponsTableAvailable();

        $coupon = DB::transaction(function () use ($payload, $context) {
            $coupon = Coupon::query()->create(
                CouponNormalizer::normalizeAdminCouponPayload($payload, $context)
            );

            if (($coupon->distribution_type ?? 'public') === 'private') {
                $this->grantCouponToUsers(
                    $coupon,
                    CouponNormalizer::normalizeUserIds((array) ($payload['user_ids'] ?? [])),
                    $context,
                    false
                );
            }

            return CouponAdminGuardService::loadCouponForAdmin($coupon->fresh());
        });

        return $this->transformCouponForAdmin($coupon, $this->resolveProductNameMapFromCoupons(collect([$coupon])));
    }

    public function updateCoupon(Coupon $coupon, array $payload, array $context = []): array
    {
        CouponAdminGuardService::assertCouponsTableAvailable();

        $updatedCoupon = DB::transaction(function () use ($coupon, $payload, $context) {
            $lockedCoupon = Coupon::query()
                ->whereKey($coupon->id)
                ->lockForUpdate()
                ->first();

            throw_if(! $lockedCoupon, new BusinessException('优惠券不存在或已删除'));
            CouponAdminGuardService::assertCouponCanBeUpdated($lockedCoupon, $payload);

            $lockedCoupon->fill(
                CouponNormalizer::normalizeAdminCouponPayload($payload, $context, $lockedCoupon)
            );
            $lockedCoupon->save();

            if (($lockedCoupon->distribution_type ?? 'public') === 'private') {
                // D5B-01：部分更新语义——未提交 user_ids 时保持现有发放对象不变，仅显式提交时才重新发放
                if (array_key_exists('user_ids', $payload)) {
                    $this->grantCouponToUsers(
                        $lockedCoupon,
                        CouponNormalizer::normalizeUserIds((array) ($payload['user_ids'] ?? [])),
                        $context,
                        true
                    );
                }
            }

            return CouponAdminGuardService::loadCouponForAdmin($lockedCoupon->fresh());
        });

        return $this->transformCouponForAdmin($updatedCoupon, $this->resolveProductNameMapFromCoupons(collect([$updatedCoupon])));
    }

    public function toggleCouponStatus(Coupon $coupon, array $context = []): array
    {
        CouponAdminGuardService::assertCouponsTableAvailable();

        // 行锁内读-判-写：并发双击启用/停用时后写者基于翻转后的最新状态再翻转，
        // 避免两个请求都读到旧状态把最终状态翻回目标反面
        DB::transaction(function () use ($coupon, $context): void {
            $locked = Coupon::query()
                ->whereKey($coupon->id)
                ->lockForUpdate()
                ->first();
            throw_if(! $locked, new BusinessException('优惠券不存在或已删除'));

            $locked->forceFill([
                'status' => (int) $locked->status === CouponStatus::ACTIVE
                    ? CouponStatus::DISABLED
                    : CouponStatus::ACTIVE,
                'operator' => (string) ($context['operator'] ?? $locked->operator ?? ''),
                'trace_id' => (string) ($context['trace_id'] ?? $locked->trace_id ?? ''),
            ])->save();
        });

        return $this->transformCouponForAdmin($coupon->fresh(), $this->resolveProductNameMapFromCoupons(collect([$coupon])));
    }

    public function deleteCoupon(Coupon $coupon, array $context = []): void
    {
        CouponAdminGuardService::assertCouponsTableAvailable();

        DB::transaction(function () use ($coupon, $context) {
            $lockedCoupon = Coupon::query()
                ->whereKey($coupon->id)
                ->lockForUpdate()
                ->first();

            throw_if(! $lockedCoupon, new BusinessException('优惠券不存在或已删除'));
            throw_if(
                CouponAdminGuardService::couponHasRecordedUsage($lockedCoupon),
                new BusinessException('该优惠券已有人使用，不能删除')
            );
            // forUpdate 当前读：与结账预留/建单并发时看到最新已提交引用，消除快照读竞态
            throw_if(
                CouponAdminGuardService::couponHasDeletionBlockingReferences($lockedCoupon, true),
                new BusinessException('该优惠券仍有关联订单或账单，不能删除')
            );

            // 软删（CMP-P1-7）：券模板与用户领取记录都是业务历史，物理删除会让
            // 「谁在什么时候领过/用过」永久消失，也无法回溯运营误删。
            // 券本身走 SoftDeletes；未使用/已过期的领取记录改为 REVOKED 状态回收，
            // 保留领取时间与来源（已使用、已核销的记录一律不动）。
            if (CouponAdminGuardService::hasUserCouponsTable()) {
                UserCoupon::query()
                    ->where('coupon_id', $lockedCoupon->id)
                    // 只回收「未使用」的领取记录（=1）。已使用(2) 是核销历史必须保留；
                    // 3 是已回收终态，无需重复处理。
                    ->where('status', UserCouponStatus::OWNED)
                    ->update([
                        'status' => UserCouponStatus::REVOKED,
                        'reserved_until' => null,
                        'updated_at' => now(),
                    ]);
            }

            // 必须走模型实例删除：Builder::delete() 不触发 Eloquent 事件，
            // ReleasesUniqueKeysOnDelete 不会执行，coupons.code 唯一键会被永久占用。
            $lockedCoupon->delete();

            $campaignId = (int) ($lockedCoupon->coupon_campaign_id ?? 0);
            if ($campaignId > 0 && SchemaMetadataCache::hasTable('coupon_campaigns')) {
                $nextLastCouponId = Coupon::query()
                    ->where('coupon_campaign_id', $campaignId)
                    ->max('id');

                DB::table('coupon_campaigns')
                    ->where('id', $campaignId)
                    ->where('last_coupon_id', $lockedCoupon->id)
                    ->update([
                        'last_coupon_id' => $nextLastCouponId ?: null,
                        'operator' => (string) ($context['operator'] ?? ''),
                        'trace_id' => (string) ($context['trace_id'] ?? ''),
                        'updated_at' => now(),
                    ]);
            }
        });
    }

    public function paginateForUser(User $user, array $filters, int $page = 1, int $pageSize = 15): array
    {
        if (! CouponAdminGuardService::hasCouponsTable()) {
            return [
                'list' => [],
                'total' => 0,
                'page' => max($page, 1),
                'page_size' => max($pageSize, 1),
            ];
        }

        $currentPage = max($page, 1);
        $pageSize = max($pageSize, 1);
        $hasPlacedOrder = CouponOwnershipService::hasUserPlacedOrder((int) $user->id);
        $paginator = $this->buildOwnedCouponPageQuery($user, $filters)
            ->orderByDesc('user_coupons.id')
            ->paginate($pageSize, ['user_coupons.*'], 'page', $currentPage);
        $items = $this->transformOwnedCouponCollection($paginator->getCollection(), $hasPlacedOrder);

        return [
            'list' => $items->values()->all(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'page_size' => $paginator->perPage(),
        ];
    }

    public function summaryForUser(User $user, array $filters = []): array
    {
        if (! CouponAdminGuardService::hasCouponsTable()) {
            return [
                'total' => 0,
                'available' => 0,
                'used_up' => 0,
                'expired' => 0,
            ];
        }

        $hasPlacedOrder = CouponOwnershipService::hasUserPlacedOrder((int) $user->id);
        $baseQuery = $this->buildOwnedCouponPageQuery($user, [
            'keyword' => $filters['keyword'] ?? null,
        ], false);
        $userId = (int) $user->id;

        return [
            'total' => (clone $baseQuery)->count(),
            'available' => $this->countOwnedCouponsByStatus(clone $baseQuery, 'available', $hasPlacedOrder, $userId),
            'used_up' => $this->countOwnedCouponsByStatus(clone $baseQuery, 'used_up', $hasPlacedOrder, $userId),
            'expired' => $this->countOwnedCouponsByStatus(clone $baseQuery, 'expired', $hasPlacedOrder, $userId),
        ];
    }

    public function paginatePublicForUser(User $user, array $filters, int $page = 1, int $pageSize = 15): array
    {
        if (! CouponAdminGuardService::hasCouponsTable()) {
            return [
                'list' => [],
                'total' => 0,
                'page' => max($page, 1),
                'page_size' => max($pageSize, 1),
            ];
        }

        $currentPage = max($page, 1);
        $pageSize = max($pageSize, 1);
        $paginator = $this->buildPublicClaimableCouponQuery($user, $filters)
            ->orderByDesc('sort_order')
            ->orderByDesc('id')
            ->paginate($pageSize, ['coupons.*'], 'page', $currentPage);
        $items = $this->transformPublicCouponCollection($paginator->getCollection());

        return [
            'list' => $items->values()->all(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'page_size' => $paginator->perPage(),
        ];
    }

    public function summaryPublicForUser(User $user, array $filters = []): array
    {
        if (! CouponAdminGuardService::hasCouponsTable()) {
            return [
                'total' => 0,
                'available' => 0,
                'used_up' => 0,
                'expired' => 0,
            ];
        }

        $baseQuery = $this->buildPublicClaimableCouponQuery($user, [
            'keyword' => $filters['keyword'] ?? null,
        ], false);

        return [
            'total' => (clone $baseQuery)->count(),
            'available' => $this->countPublicCouponsByStatus(clone $baseQuery, 'available'),
            'used_up' => $this->countPublicCouponsByStatus(clone $baseQuery, 'used_up'),
            'expired' => $this->countPublicCouponsByStatus(clone $baseQuery, 'expired'),
        ];
    }

    /**
     * 客户端领取入口：先验券表可用，再取券模板（V5-02：查询与闸门自控制器下沉）。
     */
    public function resolveClaimableCoupon(int $couponId): Coupon
    {
        CouponAdminGuardService::assertCouponsTableAvailable();

        return Coupon::query()->findOrFail($couponId);
    }

    public function claimPublicCoupon(User $user, Coupon $coupon): array
    {
        CouponAdminGuardService::assertCouponsTableAvailable();
        throw_if(! CouponAdminGuardService::hasUserCouponsTable(), new BusinessException('当前系统未启用已领优惠券功能'));

        $userCoupon = DB::transaction(function () use ($user, $coupon) {
            $lockedCoupon = Coupon::query()
                ->whereKey($coupon->id)
                ->lockForUpdate()
                ->first();

            throw_if(! $lockedCoupon, new BusinessException('优惠券不存在或已失效'));

            $this->assertCouponCanBeClaimedByUser($lockedCoupon, (int) $user->id);

            return UserCoupon::query()->create([
                'coupon_id' => (int) $lockedCoupon->id,
                'user_id' => (int) $user->id,
                'receive_type' => 'claim',
                'status' => 1,
                'claimed_at' => now(),
            ]);
        });

        $ownedItem = $this->transformOwnedCouponCollection(
            $this->buildOwnedCouponPageQuery($user, [], false)
                ->where('user_coupons.id', (int) $userCoupon->id)
                ->get(),
            CouponOwnershipService::hasUserPlacedOrder((int) $user->id)
        )->first(fn (array $item) => (int) ($item['id'] ?? 0) === (int) $userCoupon->id);

        return $ownedItem ?: [
            'id' => (int) $userCoupon->id,
            'coupon_id' => (int) $userCoupon->coupon_id,
        ];
    }

    public function availableCouponsForCheckout(int $userId, Product $product, string $billingCycle, float $amount, string $orderType = 'new', ?bool $memberDiscountApplied = null): array
    {
        if (! CouponAdminGuardService::hasCouponsTable() || ! CouponAdminGuardService::hasUserCouponsTable()) {
            return [];
        }

        // 可选项必须与 reserveOwnedCouponForInvoice 的拦截条件逐条对齐，否则会出现
        // 「列表可选但结账必败」：预留中、同券已有待支付账单、同券已有已支付账单
        // （核销同步未完成）、已有未付首单券订单时占用都会被拒，这里必须同步排除。
        $query = UserCoupon::query()
            ->with('coupon')
            ->where('user_id', $userId)
            ->whereHas('coupon')
            ->where('status', UserCouponStatus::OWNED)
            ->where(function ($query): void {
                $query->whereNull('reserved_until')
                    ->orWhere('reserved_until', '<', now());
            })
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('invoices')
                    ->whereColumn('invoices.user_coupon_id', 'user_coupons.id')
                    ->whereIn('invoices.status', self::COUPON_BLOCKING_INVOICE_STATUSES);
            });

        if (CouponOwnershipService::hasPendingFirstOrderCouponUsage($userId)) {
            $query->whereHas('coupon', fn ($couponQuery) => $couponQuery->where('first_order_only', 0));
        }

        $userCoupons = $query->orderByDesc('id')->get();

        if ($userCoupons->isEmpty()) {
            return [];
        }

        return $userCoupons
            ->map(function (UserCoupon $userCoupon) use ($product, $billingCycle, $amount, $userId, $orderType, $memberDiscountApplied) {
                try {
                    $payload = $this->buildOwnedCouponPayload($userCoupon, $product, $billingCycle, $amount, $userId, $orderType, $memberDiscountApplied);

                    return [
                        'id' => (int) $userCoupon->id,
                        'coupon_id' => (int) ($payload['coupon_id'] ?? 0),
                        'name' => (string) ($payload['name'] ?? ''),
                        'description' => (string) ($payload['description'] ?? ''),
                        'discount_type' => (string) ($payload['discount_type'] ?? ''),
                        'discount_label' => (string) ($payload['discount_label'] ?? ''),
                        'discount_amount' => (string) ($payload['discount_amount'] ?? '0.00'),
                        'final_amount' => (string) ($payload['final_amount'] ?? '0.00'),
                        'product_scope_text' => (string) ($payload['product_scope_text'] ?? ''),
                        'billing_cycle_text' => (string) ($payload['billing_cycle_text'] ?? ''),
                        'validity_text' => (string) ($payload['validity_text'] ?? ''),
                    ];
                } catch (BusinessException) {
                    // 业务性不适用（门槛/周期/范围/首单限制等）：静默剔除属预期行为
                    return null;
                } catch (\Throwable $exception) {
                    // 非业务异常（DB/数据异常等）不得静默吞掉成「无可用券」，留告警排查线索
                    Log::warning('[优惠券] 结账可用券构建失败，已跳过该券', [
                        'user_coupon_id' => (int) $userCoupon->id,
                        'coupon_id' => (int) ($userCoupon->coupon_id ?? 0),
                        'user_id' => $userId,
                        'error' => $exception->getMessage(),
                        'exception' => $exception::class,
                    ]);

                    return null;
                }
            })
            ->filter()
            ->values()
            ->all();
    }

    public function previewOwnedCoupon(?int $userCouponId, int $userId, Product $product, string $billingCycle, float $amount, string $orderType = 'new', ?bool $memberDiscountApplied = null): ?array
    {
        if (! CouponAdminGuardService::hasCouponsTable() || ! CouponAdminGuardService::hasUserCouponsTable()) {
            return null;
        }

        $resolvedUserCouponId = (int) ($userCouponId ?? 0);
        if ($resolvedUserCouponId <= 0) {
            return null;
        }

        $userCoupon = UserCoupon::query()
            ->with('coupon')
            ->where('user_id', $userId)
            ->whereHas('coupon')
            ->find($resolvedUserCouponId);

        throw_if(! $userCoupon, new BusinessException('优惠券不存在或已失效'));

        return $this->buildOwnedCouponPayload($userCoupon, $product, $billingCycle, $amount, $userId, $orderType, $memberDiscountApplied);
    }

    public function reserveOwnedCouponForInvoice(?int $userCouponId, int $userId, Product $product, string $billingCycle, float $amount, string $orderType = 'new', ?bool $memberDiscountApplied = null): ?array
    {
        if (! CouponAdminGuardService::hasUserCouponsTable()) {
            return null;
        }

        $resolvedUserCouponId = (int) ($userCouponId ?? 0);
        if ($resolvedUserCouponId <= 0) {
            return null;
        }

        $userCoupon = UserCoupon::query()
            ->with('coupon')
            ->where('user_id', $userId)
            ->whereHas('coupon')
            ->where('status', UserCouponStatus::OWNED)
            ->where(function ($query): void {
                $query->whereNull('reserved_until')
                    ->orWhere('reserved_until', '<', now());
            })
            ->lockForUpdate()
            ->find($resolvedUserCouponId);

        throw_if(! $userCoupon, new BusinessException('优惠券不存在或已失效'));

        // 防双花拦截：同券已有待支付账单（含预留已过期场景）时拒绝再占用，否则会建立
        // 并存可支付账单，两张都支付即同一张券双重抵扣。预留过期后账单仍可能存活
        // （续费账单豁免 5 分钟清理），只按 reserved_until 过滤会漏掉这种长期滞留。
        // 与结账可用券列表、持有券展示状态同一判定，三处必须同步修改。
        throw_if(
            CouponOwnershipService::userCouponHasPendingInvoice($resolvedUserCouponId, $userId),
            new BusinessException('该优惠券已有待支付账单，请先完成支付或取消后再使用')
        );

        // 首单券并发拦截：已有未付首单券订单时不得再占首单券，否则两张都支付即各享一次
        // 首单优惠。只放在占用路径（不放 buildOwnedCouponPayload）——previewOwnedCoupon 被
        // 续费复用校验直接调用，在那里抛错会让续费重提交整单失败。
        $selectedCoupon = $userCoupon->coupon;
        throw_if(
            $selectedCoupon instanceof Coupon
                && (bool) $selectedCoupon->first_order_only
                && CouponOwnershipService::hasPendingFirstOrderCouponUsage($userId),
            new BusinessException('该优惠券仅限首单使用')
        );

        // 防双花兜底：该券已有已支付账单但异步同步尚未完成（reserved_until 已过期的窗口），
        // 直接拒绝再次占用，避免同一张券重复抵扣。与列表/分组/展示的「已使用」同一判定。
        throw_if(
            CouponOwnershipService::userCouponHasUsedInvoice($resolvedUserCouponId, $userId),
            new BusinessException('优惠券已使用')
        );

        $payload = $this->buildOwnedCouponPayload($userCoupon, $product, $billingCycle, $amount, $userId, $orderType, $memberDiscountApplied);
        $userCoupon->forceFill([
            'reserved_until' => now()->addMinutes(10),
        ])->save();

        return $payload;
    }

    public function releaseInvoiceCoupon(Invoice $invoice): void
    {
        $this->syncInvoiceCouponUsage($invoice);
    }

    public function syncInvoiceCouponUsageAfterResponse(Invoice $invoice): void
    {
        $invoiceId = (int) $invoice->id;

        if ($invoiceId <= 0) {
            return;
        }

        SyncInvoiceCouponUsageJob::dispatch($invoiceId)->onQueue('coupon');
    }

    /**
     * 按账单重算持有券占用（幂等）：
     * - 名下存在 PAID/REFUNDED 账单 → 券置 USED（退款不返还，防「购-付-退」循环薅券）；
     * - 取消（CANCELLED）/无有效账单 → 回落 OWNED 可复用（取消不消耗优惠资格）。
     */
    public function syncInvoiceCouponUsage(Invoice $invoice): void
    {
        $couponId = (int) ($invoice->coupon_id ?? 0);
        $userCouponId = (int) ($invoice->user_coupon_id ?? 0);

        if ($couponId <= 0 && $userCouponId <= 0) {
            return;
        }

        DB::transaction(function () use ($couponId, $userCouponId, $invoice) {
            if ($couponId > 0) {
                $coupon = Coupon::query()
                    ->whereKey($couponId)
                    ->lockForUpdate()
                    ->first();

                if ($coupon instanceof Coupon) {
                    $coupon->forceFill([
                        'used_count' => CouponOwnershipService::countCouponUsedInvoices($couponId),
                    ])->save();
                }
            }

            if ($userCouponId > 0 && CouponAdminGuardService::hasUserCouponsTable()) {
                $userCoupon = UserCoupon::query()
                    ->with('coupon')
                    ->whereKey($userCouponId)
                    ->lockForUpdate()
                    ->first();

                if ($userCoupon instanceof UserCoupon) {
                    if ((int) $userCoupon->status === UserCouponStatus::REVOKED) {
                        return;
                    }

                    $lastUsedAt = CouponOwnershipService::resolveUserCouponLastUsedAt($userCouponId);
                    // USED_INVOICE_STATUSES 含 PAID 与 REFUNDED：已支付=核销；已退款=退款后券
                    // 视为已消耗不返还（取消 CANCELLED 不在此列，取消账单仍可回落复用）
                    $hasActivePaidInvoice = Invoice::query()
                        ->where('user_coupon_id', $userCouponId)
                        ->whereIn('status', self::USED_INVOICE_STATUSES)
                        ->exists();

                    if ($hasActivePaidInvoice) {
                        $resolvedUsedAt = $userCoupon->used_at ?? $lastUsedAt ?? now();
                        $userCoupon->forceFill([
                            'status' => UserCouponStatus::USED,
                            'used_at' => $resolvedUsedAt,
                            'last_used_at' => $lastUsedAt ?? $resolvedUsedAt,
                            'reserved_until' => null,
                        ])->save();

                        return;
                    }

                    if (CouponOwnershipService::shouldKeepFirstOrderUserCouponUsed($userCoupon)) {
                        $resolvedUsedAt = $userCoupon->used_at ?? $userCoupon->last_used_at ?? now();
                        $userCoupon->forceFill([
                            'status' => UserCouponStatus::USED,
                            'used_at' => $resolvedUsedAt,
                            'last_used_at' => $userCoupon->last_used_at ?? $resolvedUsedAt,
                            'reserved_until' => null,
                        ])->save();

                        return;
                    }

                    // 回落 OWNED 时若同券还有其他待支付账单（并发窗口内新账单刚写入的预留），
                    // 保留 reserved_until，避免本次释放把活跃预留清掉、重开双花窗口
                    $keepsReservation = Invoice::query()
                        ->where('user_coupon_id', $userCouponId)
                        ->where('id', '!=', (int) $invoice->id)
                        ->where('status', InvoiceStatus::UNPAID)
                        ->exists();

                    $userCoupon->forceFill([
                        'status' => UserCouponStatus::OWNED,
                        'used_at' => null,
                        'last_used_at' => null,
                        'reserved_until' => $keepsReservation ? $userCoupon->reserved_until : null,
                    ])->save();
                }
            }
        });
    }

    /**
     * 按优惠参数快照计算减免额（券模板与服务端持续优惠绑定的单一折算真源）。
     *
     * 实现已迁至 CouponNormalizer::computeDiscountFromSnapshot，此处保留同名公开方法，
     * 因为 ServiceRenewService 等服务通过注入的 CouponService 实例调用它。
     *
     * @param  array<string, mixed>  $snapshot  discount_type/discount_value/max_discount_amount
     */
    public function computeDiscountFromSnapshot(array $snapshot, float $amount): float
    {
        return CouponNormalizer::computeDiscountFromSnapshot($snapshot, $amount);
    }

    private function buildAdminCouponQuery(array $filters = [], bool $applyStatusFilter = true)
    {
        $keyword = trim((string) ($filters['keyword'] ?? ''));
        $discountType = trim((string) ($filters['discount_type'] ?? ''));
        $distributionType = trim((string) ($filters['distribution_type'] ?? ''));
        $discountScope = trim((string) ($filters['discount_scope'] ?? ''));
        $statusFilter = array_key_exists('status', $filters) ? (string) $filters['status'] : '';
        $now = now();

        return Coupon::query()
            ->when($keyword !== '', function ($query) use ($keyword) {
                $query->where(function ($builder) use ($keyword) {
                    $builder
                        ->where('name', 'like', LikeEscape::contains($keyword))
                        ->orWhere('description', 'like', LikeEscape::contains($keyword));
                });
            })
            ->when($discountType !== '', fn ($query) => $query->where('discount_type', $discountType))
            ->when($distributionType !== '', fn ($query) => $query->where('distribution_type', $distributionType))
            ->when($discountScope !== '', fn ($query) => $query->where('discount_scope', $discountScope))
            ->when($applyStatusFilter && $statusFilter !== '', function ($query) use ($statusFilter, $now) {
                if ($statusFilter === 'expired') {
                    $query
                        ->where('status', CouponStatus::ACTIVE)
                        ->whereNotNull('expires_at')
                        ->where('expires_at', '<', $now);

                    return;
                }

                if ($statusFilter === '1') {
                    $query
                        ->where('status', CouponStatus::ACTIVE)
                        ->where(function ($builder) use ($now) {
                            $builder->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
                        })
                        ->where(function ($builder) use ($now) {
                            $builder->whereNull('expires_at')->orWhere('expires_at', '>=', $now);
                        });

                    return;
                }

                $query->where('status', (int) $statusFilter);
            });
    }

    private function buildOwnedCouponPageQuery(User $user, array $filters = [], bool $applyStatusFilter = true)
    {
        $userId = (int) $user->id;
        $keyword = trim((string) ($filters['keyword'] ?? ''));
        $statusFilter = trim((string) ($filters['status'] ?? 'all'));
        $hasPlacedOrder = CouponOwnershipService::hasUserPlacedOrder($userId);

        $query = UserCoupon::query()
            ->select('user_coupons.*')
            ->join('coupons', 'coupons.id', '=', 'user_coupons.coupon_id')
            // 原生 join 不享受 Eloquent 的 SoftDeletes 全局作用域，必须显式排除已删券
            ->whereNull('coupons.deleted_at')
            ->with('coupon')
            ->where('user_coupons.user_id', $userId)
            ->when($keyword !== '', function ($builder) use ($keyword) {
                $builder->where(function ($query) use ($keyword) {
                    $query->where('coupons.name', 'like', LikeEscape::contains($keyword))
                        ->orWhere('coupons.description', 'like', LikeEscape::contains($keyword));
                });
            })
            ->selectRaw('('.CouponOwnershipService::ownedCouponUsageCountSql().') as user_used_count', [$userId, InvoiceStatus::CANCELLED]);

        if ($applyStatusFilter && in_array($statusFilter, ['available', 'used_up', 'expired'], true)) {
            $this->applyOwnedCouponStatusFilter($query, $statusFilter, $hasPlacedOrder, $userId);
        }

        return $query;
    }

    private function transformOwnedCouponCollection(Collection $userCoupons, bool $hasPlacedOrder): Collection
    {
        if ($userCoupons->isEmpty()) {
            return collect();
        }

        $productNameMap = $this->resolveProductNameMapFromCoupons(
            $userCoupons->map(fn (UserCoupon $userCoupon) => $userCoupon->coupon)->filter()
        );
        $occupancy = $this->resolveOwnedCouponOccupancy($userCoupons);

        return $userCoupons->map(function (UserCoupon $userCoupon) use ($productNameMap, $hasPlacedOrder, $occupancy) {
            return $this->transformOwnedCouponForUser(
                $userCoupon,
                (int) ($userCoupon->getAttribute('user_used_count') ?? 0),
                $hasPlacedOrder,
                $productNameMap,
                in_array((int) $userCoupon->id, $occupancy[0], true),
                in_array((int) $userCoupon->id, $occupancy[1], true),
                $occupancy[2],
            );
        });
    }

    /**
     * 批量解析持有券占用判定，避免逐行查询：返回存在待支付账单的持有券 id、
     * 存在已支付账单（核销同步未完成）的持有券 id，以及用户是否存在未付首单券订单。
     *
     * @param  Collection<int, UserCoupon>  $userCoupons
     * @return array{0: array<int, int>, 1: array<int, int>, 2: bool}
     */
    private function resolveOwnedCouponOccupancy(Collection $userCoupons): array
    {
        $userId = (int) $userCoupons->first()->user_id;

        $blockingInvoices = Invoice::query()
            ->whereIn('user_coupon_id', $userCoupons->pluck('id')->map(fn ($id) => (int) $id)->all())
            ->whereIn('status', self::COUPON_BLOCKING_INVOICE_STATUSES)
            ->get(['user_coupon_id', 'status']);

        $userCouponIdsByStatus = fn (int $status): array => $blockingInvoices
            ->where('status', $status)
            ->pluck('user_coupon_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        return [
            $userCouponIdsByStatus(InvoiceStatus::UNPAID),
            $userCouponIdsByStatus(InvoiceStatus::PAID),
            CouponOwnershipService::hasPendingFirstOrderCouponUsage($userId),
        ];
    }

    private function buildPublicClaimableCouponQuery(User $user, array $filters = [], bool $applyStatusFilter = true)
    {
        $keyword = trim((string) ($filters['keyword'] ?? ''));
        $statusFilter = trim((string) ($filters['status'] ?? 'all'));
        $userId = (int) $user->id;

        $query = Coupon::query()
            ->where('distribution_type', 'public')
            ->whereNotExists(function ($builder) use ($userId) {
                $builder->selectRaw('1')
                    ->from('user_coupons')
                    ->whereColumn('user_coupons.coupon_id', 'coupons.id')
                    ->where('user_coupons.user_id', $userId);
            })
            ->when($keyword !== '', function ($builder) use ($keyword) {
                $builder->where(function ($query) use ($keyword) {
                    $query->where('name', 'like', LikeEscape::contains($keyword))
                        ->orWhere('description', 'like', LikeEscape::contains($keyword));
                });
            })
            ->withCount('userCoupons');

        if ($applyStatusFilter && in_array($statusFilter, ['available', 'used_up', 'expired'], true)) {
            $this->applyPublicCouponStatusFilter($query, $statusFilter);
        }

        return $query;
    }

    private function transformPublicCouponCollection(Collection $coupons): Collection
    {
        if ($coupons->isEmpty()) {
            return collect();
        }

        $productNameMap = $this->resolveProductNameMapFromCoupons($coupons);

        return $coupons->map(fn (Coupon $coupon) => $this->transformPublicCouponForUser($coupon, $productNameMap));
    }

    private function applyOwnedCouponStatusFilter($query, string $statusFilter, bool $hasPlacedOrder, int $userId): void
    {
        $now = now();

        if ($statusFilter === 'expired') {
            // 已使用（status=2）属于「已用完」分组；过期分组只收已回收（status=3）
            // 与自身失效（停用/未开始/已过期/私发被移除）的持有券
            $query->where(function ($builder) use ($now) {
                $builder->where('user_coupons.status', 3)
                    ->orWhere(function ($ownedQuery) use ($now) {
                        $ownedQuery->where('user_coupons.status', 1)
                            ->where(function ($invalidQuery) use ($now) {
                                $invalidQuery->where('coupons.status', '!=', CouponStatus::ACTIVE)
                                    ->orWhere(function ($receiveTypeQuery) {
                                        $receiveTypeQuery->where('coupons.distribution_type', 'private')
                                            ->where(function ($innerQuery) {
                                                $innerQuery->whereNull('user_coupons.receive_type')
                                                    ->orWhere('user_coupons.receive_type', '!=', 'grant');
                                            });
                                    })
                                    ->orWhere(function ($subQuery) use ($now) {
                                        $subQuery->whereNotNull('coupons.starts_at')->where('coupons.starts_at', '>', $now);
                                    })
                                    ->orWhere(function ($subQuery) use ($now) {
                                        $subQuery->whereNotNull('coupons.expires_at')->where('coupons.expires_at', '<', $now);
                                    });
                            });
                    });
            });

            return;
        }

        if ($statusFilter === 'used_up') {
            // 已核销（status=2）无条件归「已用完」（不受券自身停用/过期限制，与展示层
            // resolveOwnedCouponStatus 对 USED 最先判 used_up 的顺序一致，保证三分组
            // 与 total 完备不重不漏）；持有中但资格不可用（首单已用/达限/已占用）的一并归入。
            // 「已占用」= 预留仍在窗口内或同券存在待支付账单，与展示层同一判定，
            // 否则预留/挂账期间该券会掉出全部三个分组（分组之和 < total）。
            $firstOrderBlocked = $hasPlacedOrder || CouponOwnershipService::hasPendingFirstOrderCouponUsage($userId);
            $query->where(function ($builder) use ($now, $firstOrderBlocked, $userId) {
                $builder->where('user_coupons.status', 2)
                    ->orWhere(function ($ownedQuery) use ($now, $firstOrderBlocked, $userId) {
                        $ownedQuery->where('user_coupons.status', 1)
                            ->where('coupons.status', CouponStatus::ACTIVE)
                            ->where(function ($windowQuery) use ($now) {
                                $windowQuery->whereNull('coupons.starts_at')->orWhere('coupons.starts_at', '<=', $now);
                            })
                            ->where(function ($windowQuery) use ($now) {
                                $windowQuery->whereNull('coupons.expires_at')->orWhere('coupons.expires_at', '>=', $now);
                            })
                            ->where(function ($grantQuery) {
                                $grantQuery->where('coupons.distribution_type', '!=', 'private')
                                    ->orWhere('user_coupons.receive_type', 'grant');
                            })
                            ->where(function ($blockedQuery) use ($now, $firstOrderBlocked, $userId) {
                                if ($firstOrderBlocked) {
                                    $blockedQuery->where('coupons.first_order_only', 1);
                                }

                                $blockedQuery->orWhere(function ($subQuery) use ($userId) {
                                    $subQuery->where('coupons.per_user_limit', '>', 0)
                                        ->whereRaw('('.CouponOwnershipService::ownedCouponUsageCountSql().') >= coupons.per_user_limit', [$userId, InvoiceStatus::CANCELLED]);
                                });

                                $blockedQuery->orWhere(function ($subQuery) use ($now) {
                                    $subQuery->whereNotNull('user_coupons.reserved_until')
                                        ->where('user_coupons.reserved_until', '>=', $now);
                                });

                                $blockedQuery->orWhereExists(function ($subQuery) {
                                    $subQuery->selectRaw('1')
                                        ->from('invoices')
                                        ->whereColumn('invoices.user_coupon_id', 'user_coupons.id')
                                        ->whereIn('invoices.status', self::COUPON_BLOCKING_INVOICE_STATUSES);
                                });
                            });
                    });
            });

            return;
        }

        $query->where('user_coupons.status', 1)
            ->where('coupons.status', CouponStatus::ACTIVE)
            ->where(function ($builder) use ($now) {
                $builder->whereNull('coupons.starts_at')->orWhere('coupons.starts_at', '<=', $now);
            })
            ->where(function ($builder) use ($now) {
                $builder->whereNull('coupons.expires_at')->orWhere('coupons.expires_at', '>=', $now);
            })
            ->where(function ($builder) {
                $builder->where('coupons.distribution_type', '!=', 'private')
                    ->orWhere('user_coupons.receive_type', 'grant');
            })
            // 与结账可用券列表同口径：预留中、或同券已有待支付/已支付账单的券不可立即结账，不归入可用分组
            ->where(function ($builder) {
                $builder->whereNull('user_coupons.reserved_until')
                    ->orWhere('user_coupons.reserved_until', '<', now());
            })
            ->whereNotExists(function ($builder) {
                $builder->selectRaw('1')
                    ->from('invoices')
                    ->whereColumn('invoices.user_coupon_id', 'user_coupons.id')
                    ->whereIn('invoices.status', self::COUPON_BLOCKING_INVOICE_STATUSES);
            });

        if ($hasPlacedOrder || CouponOwnershipService::hasPendingFirstOrderCouponUsage($userId)) {
            $query->where('coupons.first_order_only', 0);
        }

        $query->where(function ($builder) use ($userId) {
            $builder->whereNull('coupons.per_user_limit')
                ->orWhere('coupons.per_user_limit', '<=', 0)
                ->orWhereRaw('('.CouponOwnershipService::ownedCouponUsageCountSql().') < coupons.per_user_limit', [$userId, InvoiceStatus::CANCELLED]);
        });
    }

    private function applyPublicCouponStatusFilter($query, string $statusFilter): void
    {
        $now = now();

        if ($statusFilter === 'expired') {
            $query->where(function ($builder) use ($now) {
                $builder->where('status', '!=', CouponStatus::ACTIVE)
                    ->orWhere(function ($subQuery) use ($now) {
                        $subQuery->whereNotNull('starts_at')->where('starts_at', '>', $now);
                    })
                    ->orWhere(function ($subQuery) use ($now) {
                        $subQuery->whereNotNull('expires_at')->where('expires_at', '<', $now);
                    });
            });

            return;
        }

        $query->where('status', CouponStatus::ACTIVE)
            ->where(function ($builder) use ($now) {
                $builder->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($builder) use ($now) {
                $builder->whereNull('expires_at')->orWhere('expires_at', '>=', $now);
            });

        if ($statusFilter === 'used_up') {
            $query->where('total_usage_limit', '>', 0)
                ->whereRaw('('.CouponOwnershipService::publicCouponClaimCountSql().') >= coupons.total_usage_limit');

            return;
        }

        $query->where(function ($builder) {
            $builder->whereNull('total_usage_limit')
                ->orWhere('total_usage_limit', '<=', 0)
                ->orWhereRaw('('.CouponOwnershipService::publicCouponClaimCountSql().') < coupons.total_usage_limit');
        });
    }

    private function countOwnedCouponsByStatus($query, string $statusFilter, bool $hasPlacedOrder, int $userId): int
    {
        $this->applyOwnedCouponStatusFilter($query, $statusFilter, $hasPlacedOrder, $userId);

        return (int) $query->count();
    }

    private function countPublicCouponsByStatus($query, string $statusFilter): int
    {
        $this->applyPublicCouponStatusFilter($query, $statusFilter);

        return (int) $query->count();
    }

    private function transformCouponForAdmin(Coupon $coupon, array $productNameMap, ?array $grantUserIdsMap = null): array
    {
        $productIds = CouponNormalizer::normalizeProductIds((array) ($coupon->product_ids ?? []));
        $rawBillingCycles = (array) ($coupon->billing_cycles ?? []);
        $billingCycles = CouponLabelFormatter::normalizeBillingCycles($rawBillingCycles);
        $displayStatus = $this->resolveAdminDisplayStatus($coupon);
        $canUpdate = CouponAdminGuardService::couponCanBeUpdatedForAdmin($coupon);
        $canDelete = CouponAdminGuardService::couponCanBeDeletedForAdmin($coupon);
        $lockReason = CouponAdminGuardService::resolveCouponLockReason($coupon);

        return [
            'id' => (int) $coupon->id,
            'coupon_campaign_id' => (int) ($coupon->coupon_campaign_id ?? 0),
            'coupon_campaign_name' => (string) ($coupon->couponCampaign?->name ?? ''),
            'name' => (string) $coupon->name,
            'description' => (string) ($coupon->description ?? ''),
            'distribution_type' => (string) ($coupon->distribution_type ?? 'public'),
            'distribution_type_label' => CouponLabelFormatter::resolveDistributionTypeLabel((string) ($coupon->distribution_type ?? 'public')),
            'discount_scope' => (string) ($coupon->discount_scope ?? 'first_month'),
            'discount_scope_label' => CouponLabelFormatter::resolveDiscountScopeLabel((string) ($coupon->discount_scope ?? 'first_month')),
            'discount_type' => (string) $coupon->discount_type,
            'discount_type_label' => CouponLabelFormatter::resolveDiscountTypeLabel((string) $coupon->discount_type),
            'discount_value' => Money::format((float) ($coupon->discount_value ?? 0)),
            'discount_value_raw' => (float) ($coupon->discount_value ?? 0),
            'discount_label' => CouponLabelFormatter::buildDiscountLabel($coupon),
            'min_amount' => Money::format((float) ($coupon->min_amount ?? 0)),
            'min_amount_raw' => (float) ($coupon->min_amount ?? 0),
            'max_discount_amount' => $coupon->max_discount_amount !== null
                ? Money::format((float) $coupon->max_discount_amount)
                : null,
            'max_discount_amount_raw' => $coupon->max_discount_amount !== null
                ? (float) $coupon->max_discount_amount
                : null,
            'billing_cycles' => $billingCycles,
            'billing_cycle_text' => CouponLabelFormatter::formatBillingCycleText($rawBillingCycles),
            'product_ids' => $productIds,
            'product_names' => array_values(array_filter(array_map(
                fn (int $productId) => $productNameMap[$productId] ?? '',
                $productIds
            ))),
            'product_scope_text' => CouponLabelFormatter::formatProductScopeText($productIds, $productNameMap),
            'requires_product_ids' => CouponNormalizer::normalizeProductIds((array) ($coupon->requires_product_ids ?? [])),
            'first_order_only' => (bool) $coupon->first_order_only,
            'only_old_client' => (bool) ($coupon->only_old_client ?? false),
            'is_discount' => (bool) ($coupon->is_discount ?? true),
            'total_usage_limit' => $coupon->total_usage_limit ? (int) $coupon->total_usage_limit : null,
            'per_user_limit' => $coupon->per_user_limit ? (int) $coupon->per_user_limit : null,
            'used_count' => (int) ($coupon->used_count ?? 0),
            'recipient_count' => (int) ($coupon->user_coupons_count ?? 0),
            'user_ids' => $grantUserIdsMap !== null
                ? ($grantUserIdsMap[(int) $coupon->id] ?? [])
                : $this->resolveGrantUserIdsForCoupon($coupon),
            'remaining_stock' => $coupon->total_usage_limit
                ? max((int) $coupon->total_usage_limit - (int) ($coupon->user_coupons_count ?? 0), 0)
                : null,
            'status' => (int) $coupon->status,
            'status_label' => CouponStatus::$labels[(int) $coupon->status] ?? '未知',
            'display_status' => $displayStatus['status'],
            'display_status_label' => $displayStatus['label'],
            'display_status_reason' => $displayStatus['reason'],
            'sort_order' => (int) ($coupon->sort_order ?? 0),
            'starts_at' => $coupon->starts_at?->format('Y-m-d H:i:s'),
            'expires_at' => $coupon->expires_at?->format('Y-m-d H:i:s'),
            'validity_text' => CouponLabelFormatter::formatValidityText($coupon),
            'remark' => (string) ($coupon->remark ?? ''),
            'operator' => (string) ($coupon->operator ?? ''),
            'trace_id' => (string) ($coupon->trace_id ?? ''),
            'created_at' => $coupon->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $coupon->updated_at?->format('Y-m-d H:i:s'),
            'can_update' => $canUpdate,
            'can_delete' => $canDelete,
            'lock_reason' => $lockReason,
            'locked_fields' => CouponAdminGuardService::resolveCouponLockedFields($coupon),
            'delete_reason' => $canDelete ? '' : CouponAdminGuardService::resolveCouponDeleteBlockReason($coupon),
        ];
    }

    /**
     * D1B-03：列表页按券批量单列获取「发放型」user_coupons 的 user_id，替代全量加载 userCoupons 关系。
     *
     * @param  array<int, int>  $couponIds
     * @return array<int, array<int, int>> coupon_id => user_id 列表
     */
    private function resolveGrantUserIdsMapByCouponIds(array $couponIds): array
    {
        if ($couponIds === []) {
            return [];
        }

        return UserCoupon::query()
            ->whereIn('coupon_id', $couponIds)
            ->where('receive_type', 'grant')
            ->get(['coupon_id', 'user_id'])
            ->groupBy('coupon_id')
            ->map(fn ($rows) => $rows->map(fn ($row) => (int) $row->user_id)->values()->all())
            ->all();
    }

    /**
     * D1B-03：单券场景按需单列获取发放用户 id，避免全量加载 userCoupons 关系。
     *
     * @return array<int, int>
     */
    private function resolveGrantUserIdsForCoupon(Coupon $coupon): array
    {
        return UserCoupon::query()
            ->where('coupon_id', (int) $coupon->id)
            ->where('receive_type', 'grant')
            ->pluck('user_id')
            ->map(fn ($userId) => (int) $userId)
            ->all();
    }

    private function transformOwnedCouponForUser(
        UserCoupon $userCoupon,
        int $userUsedCount,
        bool $hasPlacedOrder,
        array $productNameMap,
        bool $hasPendingInvoice = false,
        bool $hasPaidInvoice = false,
        bool $hasPendingFirstOrderUsage = false,
    ): array {
        $coupon = $userCoupon->coupon;
        if (! $coupon instanceof Coupon) {
            return [
                'id' => (int) $userCoupon->id,
                'uid' => (string) ($userCoupon->uid ?? ''),
                'coupon_id' => (int) ($userCoupon->coupon_id ?? 0),
                'name' => '已失效优惠券',
                'description' => '关联优惠券记录不存在，已自动忽略展示',
                'distribution_type' => 'public',
                'distribution_type_label' => '公开优惠券',
                'discount_scope' => 'first_month',
                'discount_scope_label' => '新购优惠',
                'receive_type' => (string) ($userCoupon->receive_type ?? 'claim'),
                'receive_type_label' => CouponLabelFormatter::resolveReceiveTypeLabel((string) ($userCoupon->receive_type ?? 'claim')),
                'discount_type' => '',
                'discount_value' => '0.00',
                'discount_label' => '不可用',
                'min_amount' => '0.00',
                'max_discount_amount' => null,
                'status' => 'expired',
                'status_label' => '已失效',
                'status_reason' => '关联优惠券记录不存在',
                'can_use' => false,
                'used_times' => 0,
                'per_user_limit' => null,
                'remaining_times' => null,
                'first_order_only' => false,
                'product_ids' => [],
                'target_product_id' => 0,
                'product_names' => [],
                'product_scope_text' => '不可用',
                'billing_cycles' => [],
                'billing_cycle_text' => '不可用',
                'validity_text' => '已失效',
                'claimed_at' => $userCoupon->claimed_at?->format('Y-m-d H:i:s'),
                'used_at' => null,
                'revoked_at' => $userCoupon->revoked_at?->format('Y-m-d H:i:s'),
                'granted_at' => $userCoupon->granted_at?->format('Y-m-d H:i:s'),
                'created_at' => $userCoupon->created_at?->format('Y-m-d H:i:s'),
                'expires_at' => null,
            ];
        }

        $productIds = CouponNormalizer::normalizeProductIds((array) ($coupon->product_ids ?? []));
        $rawBillingCycles = (array) ($coupon->billing_cycles ?? []);
        $billingCycles = CouponLabelFormatter::normalizeBillingCycles($rawBillingCycles);
        $statusMeta = $this->resolveOwnedCouponStatus(
            $coupon,
            $userCoupon,
            $userUsedCount,
            $hasPlacedOrder,
            $hasPendingInvoice,
            $hasPaidInvoice,
            $hasPendingFirstOrderUsage
        );
        $remainingTimes = $coupon->per_user_limit
            ? max((int) $coupon->per_user_limit - $userUsedCount, 0)
            : null;

        return [
            'id' => (int) $userCoupon->id,
            'uid' => (string) ($userCoupon->uid ?? ''),
            'coupon_id' => (int) $coupon->id,
            'name' => (string) $coupon->name,
            'description' => (string) ($coupon->description ?? ''),
            'distribution_type' => (string) ($coupon->distribution_type ?? 'public'),
            'distribution_type_label' => CouponLabelFormatter::resolveDistributionTypeLabel((string) ($coupon->distribution_type ?? 'public')),
            'discount_scope' => (string) ($coupon->discount_scope ?? 'first_month'),
            'discount_scope_label' => CouponLabelFormatter::resolveDiscountScopeLabel((string) ($coupon->discount_scope ?? 'first_month')),
            'receive_type' => (string) ($userCoupon->receive_type ?? 'claim'),
            'receive_type_label' => CouponLabelFormatter::resolveReceiveTypeLabel((string) ($userCoupon->receive_type ?? 'claim')),
            'discount_type' => (string) $coupon->discount_type,
            'discount_value' => Money::format((float) ($coupon->discount_value ?? 0)),
            'discount_label' => CouponLabelFormatter::buildDiscountLabel($coupon),
            'min_amount' => Money::format((float) ($coupon->min_amount ?? 0)),
            'max_discount_amount' => $coupon->max_discount_amount !== null
                ? Money::format((float) $coupon->max_discount_amount)
                : null,
            'status' => $statusMeta['status'],
            'status_label' => $statusMeta['label'],
            'status_reason' => $statusMeta['reason'],
            'can_use' => $statusMeta['status'] === 'available',
            'used_times' => $userUsedCount,
            'per_user_limit' => $coupon->per_user_limit ? (int) $coupon->per_user_limit : null,
            'remaining_times' => $remainingTimes,
            'first_order_only' => (bool) $coupon->first_order_only,
            'product_ids' => $productIds,
            'target_product_id' => count($productIds) === 1 ? (int) $productIds[0] : 0,
            'product_names' => array_values(array_filter(array_map(
                fn (int $productId) => $productNameMap[$productId] ?? '',
                $productIds
            ))),
            'product_scope_text' => CouponLabelFormatter::formatProductScopeText($productIds, $productNameMap),
            'products' => $this->resolveProductHierarchyList($productIds),
            'billing_cycles' => $billingCycles,
            'billing_cycle_text' => CouponLabelFormatter::formatBillingCycleText($rawBillingCycles),
            'validity_text' => CouponLabelFormatter::formatValidityText($coupon),
            'claimed_at' => $userCoupon->claimed_at?->format('Y-m-d H:i:s'),
            'used_at' => $userCoupon->used_at?->format('Y-m-d H:i:s'),
            'revoked_at' => $userCoupon->revoked_at?->format('Y-m-d H:i:s'),
            'granted_at' => $userCoupon->granted_at?->format('Y-m-d H:i:s'),
            'created_at' => $userCoupon->created_at?->format('Y-m-d H:i:s'),
            'expires_at' => $coupon->expires_at?->format('Y-m-d H:i:s'),
        ];
    }

    private function transformPublicCouponForUser(Coupon $coupon, array $productNameMap): array
    {
        $productIds = CouponNormalizer::normalizeProductIds((array) ($coupon->product_ids ?? []));
        $rawBillingCycles = (array) ($coupon->billing_cycles ?? []);
        $statusMeta = $this->resolvePublicCouponStatus($coupon, (int) ($coupon->user_coupons_count ?? 0));

        return [
            'id' => (int) $coupon->id,
            'name' => (string) $coupon->name,
            'description' => (string) ($coupon->description ?? ''),
            'discount_scope' => (string) ($coupon->discount_scope ?? 'first_month'),
            'discount_scope_label' => CouponLabelFormatter::resolveDiscountScopeLabel((string) ($coupon->discount_scope ?? 'first_month')),
            'discount_type' => (string) $coupon->discount_type,
            'discount_label' => CouponLabelFormatter::buildDiscountLabel($coupon),
            'min_amount' => Money::format((float) ($coupon->min_amount ?? 0)),
            'max_discount_amount' => $coupon->max_discount_amount !== null
                ? Money::format((float) $coupon->max_discount_amount)
                : null,
            'status' => $statusMeta['status'],
            'status_label' => $statusMeta['label'],
            'status_reason' => $statusMeta['reason'],
            'can_claim' => $statusMeta['status'] === 'available',
            'claimed_count' => (int) ($coupon->user_coupons_count ?? 0),
            'total_usage_limit' => $coupon->total_usage_limit ? (int) $coupon->total_usage_limit : null,
            'remaining_stock' => $coupon->total_usage_limit
                ? max((int) $coupon->total_usage_limit - (int) ($coupon->user_coupons_count ?? 0), 0)
                : null,
            'first_order_only' => (bool) $coupon->first_order_only,
            'product_ids' => $productIds,
            'products' => $this->resolveProductHierarchyList($productIds),
            'product_scope_text' => CouponLabelFormatter::formatProductScopeText($productIds, $productNameMap),
            'billing_cycle_text' => CouponLabelFormatter::formatBillingCycleText($rawBillingCycles),
            'validity_text' => CouponLabelFormatter::formatValidityText($coupon),
            'expires_at' => $coupon->expires_at?->format('Y-m-d H:i:s'),
        ];
    }

    private function buildOwnedCouponPayload(UserCoupon $userCoupon, Product $product, string $billingCycle, float $amount, ?int $userId = null, string $orderType = 'new', ?bool $memberDiscountApplied = null): array
    {
        $coupon = $userCoupon->coupon;
        throw_if(! $coupon instanceof Coupon, new BusinessException('优惠券不存在或已失效'));
        throw_if((int) $userCoupon->status !== UserCouponStatus::OWNED, new BusinessException('优惠券已失效'));

        $now = now();
        throw_if((int) $coupon->status !== CouponStatus::ACTIVE, new BusinessException('优惠券已停用'));
        throw_if(
            (string) ($coupon->distribution_type ?? 'public') === 'private'
                && (string) ($userCoupon->receive_type ?? 'claim') !== 'grant',
            new BusinessException('当前优惠券仅对指定用户发放')
        );
        throw_if($coupon->starts_at && $coupon->starts_at->gt($now), new BusinessException('优惠券尚未开始使用'));
        throw_if($coupon->expires_at && $coupon->expires_at->lt($now), new BusinessException('优惠券已过期'));
        throw_if(
            ! CouponLabelFormatter::isCouponScopeAllowedForOrderType((string) ($coupon->discount_scope ?? 'first_month'), $orderType),
            new BusinessException($orderType === 'renew' ? '该优惠券不支持续费使用' : '该优惠券仅支持续费使用')
        );

        // 仅老客：需已有成交订单（购买/续费账单已支付，与首单资格同口径）
        if ((bool) $coupon->only_old_client) {
            throw_if(
                ! $userId || ! CouponOwnershipService::hasUserPlacedOrder((int) $userId),
                new BusinessException('该优惠券仅限老客户使用')
            );
        }

        // 前置持有条件：用户名下须持有任一指定产品的运行中服务
        $requiresProductIds = CouponNormalizer::normalizeProductIds((array) ($coupon->requires_product_ids ?? []));
        if ($requiresProductIds !== []) {
            throw_if(
                ! $userId || ! CouponOwnershipService::userHoldsAnyProduct((int) $userId, $requiresProductIds),
                new BusinessException('需持有指定产品后才能使用该优惠券')
            );
        }

        // 与会员折扣互斥（is_discount=0 且本单已享受会员折扣减免 → 券不应用，对齐 ZJMF is_discount=0）
        if ($memberDiscountApplied === true && ! (bool) ($coupon->is_discount ?? true)) {
            throw new BusinessException('该优惠券不可与会员折扣同时使用，请二选一');
        }

        $normalizedProductIds = CouponNormalizer::normalizeProductIds((array) ($coupon->product_ids ?? []));
        if ($normalizedProductIds !== []) {
            throw_if(! in_array((int) $product->id, $normalizedProductIds, true), new BusinessException('该商品不可使用当前优惠券'));
        }

        $rawBillingCycles = (array) ($coupon->billing_cycles ?? []);
        $normalizedBillingCycles = CouponLabelFormatter::normalizeBillingCycles($rawBillingCycles);
        if (CouponLabelFormatter::hasConfiguredBillingCycles($rawBillingCycles) && $normalizedBillingCycles === []) {
            throw new BusinessException('当前优惠券未配置可用计费周期');
        }

        if ($normalizedBillingCycles !== []) {
            throw_if(! in_array($billingCycle, $normalizedBillingCycles, true), new BusinessException('当前计费周期不可使用此优惠券'));
        }

        throw_if($amount < (float) ($coupon->min_amount ?? 0), new BusinessException('未达到优惠券最低消费门槛'));

        $userUsedCount = 0;
        if ($userId) {
            if ($coupon->first_order_only) {
                throw_if(CouponOwnershipService::hasUserPlacedOrder($userId), new BusinessException('该优惠券仅限首单使用'));
            }

            if ($coupon->per_user_limit) {
                $userUsedCount = Invoice::query()
                    ->where('user_id', $userId)
                    ->where('coupon_id', $coupon->id)
                    ->where('status', '!=', InvoiceStatus::CANCELLED)
                    ->count();

                throw_if($userUsedCount >= (int) $coupon->per_user_limit, new BusinessException('该优惠券已达到使用上限'));
            }
        }

        $discountAmount = CouponNormalizer::calculateDiscountAmount($coupon, $amount);
        throw_if($discountAmount <= 0, new BusinessException('当前优惠券不可用'));

        return [
            'id' => (int) $coupon->id,
            'coupon_id' => (int) $coupon->id,
            'user_coupon_id' => (int) $userCoupon->id,
            'code' => (string) $coupon->code,
            'name' => (string) $coupon->name,
            'description' => (string) ($coupon->description ?? ''),
            'discount_scope' => (string) ($coupon->discount_scope ?? 'first_month'),
            'discount_scope_label' => CouponLabelFormatter::resolveDiscountScopeLabel((string) ($coupon->discount_scope ?? 'first_month')),
            'discount_type' => (string) $coupon->discount_type,
            'discount_value' => Money::format((float) ($coupon->discount_value ?? 0)),
            'discount_label' => CouponLabelFormatter::buildDiscountLabel($coupon),
            'discount_amount' => Money::format($discountAmount),
            'final_amount' => Money::format(max($amount - $discountAmount, 0)),
            'min_amount' => Money::format((float) ($coupon->min_amount ?? 0)),
            'max_discount_amount' => $coupon->max_discount_amount !== null
                ? Money::format((float) $coupon->max_discount_amount)
                : null,
            'first_order_only' => (bool) $coupon->first_order_only,
            'per_user_limit' => $coupon->per_user_limit ? (int) $coupon->per_user_limit : null,
            'used_times' => $userUsedCount,
            'product_scope_text' => CouponLabelFormatter::formatProductScopeText(
                $normalizedProductIds,
                $this->resolveProductNameMapFromCoupons(collect([$coupon]))
            ),
            'billing_cycle_text' => CouponLabelFormatter::formatBillingCycleText($rawBillingCycles),
            'validity_text' => CouponLabelFormatter::formatValidityText($coupon),
            'expires_at' => $coupon->expires_at?->format('Y-m-d H:i:s'),
        ];
    }

    private function assertCouponCanBeClaimedByUser(Coupon $coupon, int $userId): void
    {
        throw_if(! CouponAdminGuardService::hasUserCouponsTable(), new BusinessException('当前系统未启用已领优惠券功能'));

        $now = now();

        throw_if((string) ($coupon->distribution_type ?? 'public') !== 'public', new BusinessException('该优惠券不是公开领取类型'));
        throw_if((int) $coupon->status !== CouponStatus::ACTIVE, new BusinessException('优惠券已停用'));
        throw_if($coupon->starts_at && $coupon->starts_at->gt($now), new BusinessException('优惠券尚未开始领取'));
        throw_if($coupon->expires_at && $coupon->expires_at->lt($now), new BusinessException('优惠券已过期'));

        // 仅老客：需已有成交订单（与使用侧同口径，防领了用不了）
        if ((bool) $coupon->only_old_client) {
            throw_if(
                ! CouponOwnershipService::hasUserPlacedOrder($userId),
                new BusinessException('该优惠券仅限老客户领取')
            );
        }

        // 前置持有条件：与使用侧同口径
        $requiresProductIds = CouponNormalizer::normalizeProductIds((array) ($coupon->requires_product_ids ?? []));
        if ($requiresProductIds !== []) {
            throw_if(
                ! CouponOwnershipService::userHoldsAnyProduct($userId, $requiresProductIds),
                new BusinessException('需持有指定产品后才能领取该优惠券')
            );
        }

        // 领取张数的「一人一份」约束来自表结构 UK(coupon_id,user_id)，与 per_user_limit 无关：
        // per_user_limit 的真实语义是「同一张券对该用户的核销次数上限」（见 ownedCouponUsageCountSql），
        // 领取侧不读取该字段，因此无需在保存时做兼容性校验。
        throw_if(
            UserCoupon::query()->where('coupon_id', $coupon->id)->where('user_id', $userId)->exists(),
            new BusinessException('你已经领取过这张优惠券')
        );

        if ($coupon->total_usage_limit) {
            $receivedCount = UserCoupon::query()->where('coupon_id', $coupon->id)->count();
            throw_if($receivedCount >= (int) $coupon->total_usage_limit, new BusinessException('优惠券已被领完'));
        }
    }

    private function grantCouponToUsers(Coupon $coupon, array $userIds, array $context = [], bool $replace = false): void
    {
        throw_if(! CouponAdminGuardService::hasUserCouponsTable(), new BusinessException('当前系统未启用已领优惠券功能'));

        if ($userIds === []) {
            throw new BusinessException('私有优惠券必须选择至少一个用户');
        }

        // 锁券模板行：与 claimPublicCoupon 的锁对象对齐（claim 锁同一行），串行化
        // 「管理端定向发放」与「用户广场领取」的 total_usage_limit 计数，消除两事务
        // 分别锁不同行导致的快照计数超发窗口。
        Coupon::query()->whereKey($coupon->id)->lockForUpdate()->firstOrFail();

        $existing = UserCoupon::query()
            ->where('coupon_id', $coupon->id)
            ->where('receive_type', 'grant')
            ->lockForUpdate()
            ->get()
            ->keyBy(fn (UserCoupon $userCoupon) => (int) $userCoupon->user_id);

        $existingUserIds = $existing->keys()->map(fn ($id) => (int) $id)->all();
        $toCreate = array_values(array_diff($userIds, $existingUserIds));
        $toDelete = $replace ? array_values(array_diff($existingUserIds, $userIds)) : [];
        $toReactivate = $existing
            ->filter(fn (UserCoupon $userCoupon, int|string $userId) => in_array((int) $userId, $userIds, true))
            ->filter(fn (UserCoupon $userCoupon) => (int) ($userCoupon->status ?? 0) !== UserCouponStatus::OWNED);

        if ($coupon->total_usage_limit) {
            $receivedCount = UserCoupon::query()->where('coupon_id', $coupon->id)->count();
            $hardDeleteCount = 0;

            if ($toDelete !== []) {
                $recordsToDelete = $existing
                    ->filter(fn (UserCoupon $userCoupon, int|string $userId) => in_array((int) $userId, $toDelete, true))
                    ->values();

                $boundUserCouponIds = $this->boundUserCouponIdSet(
                    $recordsToDelete->pluck('id')->map(fn ($id) => (int) $id)->all()
                );

                $hardDeleteCount = $recordsToDelete
                    ->reject(function (UserCoupon $record) use ($boundUserCouponIds) {
                        $recordId = (int) $record->id;

                        return $boundUserCouponIds->has($recordId) || $record->last_used_at !== null;
                    })
                    ->count();
            }
            throw_if(
                max($receivedCount - $hardDeleteCount, 0) + count($toCreate) > (int) $coupon->total_usage_limit,
                new BusinessException('超过优惠券总发放上限')
            );
        }

        if ($toDelete !== []) {
            $recordsToDelete = $existing
                ->filter(fn (UserCoupon $userCoupon, int|string $userId) => in_array((int) $userId, $toDelete, true))
                ->values();

            $boundUserCouponIds = $this->boundUserCouponIdSet(
                $recordsToDelete->pluck('id')->map(fn ($id) => (int) $id)->all()
            );

            foreach ($recordsToDelete as $record) {
                $recordId = (int) $record->id;
                $shouldPreserve = $boundUserCouponIds->has($recordId) || $record->last_used_at !== null;

                if ($shouldPreserve) {
                    $record->forceFill([
                        'status' => UserCouponStatus::REVOKED,
                        'revoked_at' => now(),
                        'operator' => (string) ($context['operator'] ?? $record->operator ?? ''),
                        'trace_id' => (string) ($context['trace_id'] ?? $record->trace_id ?? ''),
                    ])->save();

                    continue;
                }

                $record->delete();
            }
        }

        foreach ($toReactivate as $userCoupon) {
            $userCoupon->forceFill([
                'status' => UserCouponStatus::OWNED,
                'revoked_at' => null,
                'granted_at' => now(),
                'operator' => (string) ($context['operator'] ?? $userCoupon->operator ?? ''),
                'trace_id' => (string) ($context['trace_id'] ?? $userCoupon->trace_id ?? ''),
            ])->save();
        }

        $validUserIds = User::query()
            ->whereIn('id', $toCreate)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($validUserIds as $userId) {
            UserCoupon::query()->create([
                'coupon_id' => (int) $coupon->id,
                'user_id' => $userId,
                'receive_type' => 'grant',
                'status' => UserCouponStatus::OWNED,
                'granted_at' => now(),
                'operator' => (string) ($context['operator'] ?? ''),
                'trace_id' => (string) ($context['trace_id'] ?? ''),
            ]);
        }
    }

    private function boundUserCouponIdSet(array $userCouponIds): Collection
    {
        $ids = collect($userCouponIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return collect();
        }

        $orderUserCouponIds = Order::query()
            ->whereIn('user_coupon_id', $ids)
            ->pluck('user_coupon_id');

        $invoiceUserCouponIds = Invoice::query()
            ->whereIn('user_coupon_id', $ids)
            ->pluck('user_coupon_id');

        return $orderUserCouponIds
            ->merge($invoiceUserCouponIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->flip();
    }
}
