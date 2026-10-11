<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V2;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Resources\SalesCommission\V2\SalesCommissionPlanResource;
use App\Models\SalesCommissionPlan;
use App\Models\SalesCommissionProductRate;
use App\Models\SalesCommissionTier;
use App\Services\System\OperationLogService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class SalesCommissionPlanController extends Controller
{
    public function __construct(
        private readonly OperationLogService $operationLogService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = SalesCommissionPlan::query()->with(['tiers', 'productRates.product:id,name']);

        if ($request->filled('is_enabled')) {
            $query->where('is_enabled', (int) $request->input('is_enabled'));
        }

        $plans = $query->orderByDesc('id')->get();

        return $this->success(SalesCommissionPlanResource::collection($plans)->resolve());
    }

    public function show(SalesCommissionPlan $plan): JsonResponse
    {
        $plan->load(['tiers', 'productRates.product:id,name']);

        return $this->success(SalesCommissionPlanResource::make($plan)->resolve());
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $this->validated($request);

        $plan = DB::transaction(function () use ($payload): SalesCommissionPlan {
            $plan = SalesCommissionPlan::query()->create($payload['plan']);
            $this->syncChildren($plan, $payload);

            return $plan;
        });

        $this->logPlanAction($request, 'sales_commission.plan.created', (int) $plan->id);

        return $this->success(SalesCommissionPlanResource::make($plan->fresh(['tiers', 'productRates.product:id,name']))->resolve());
    }

    public function update(Request $request, SalesCommissionPlan $plan): JsonResponse
    {
        $payload = $this->validated($request);

        DB::transaction(function () use ($plan, $payload): void {
            $plan->update($payload['plan']);
            $this->syncChildren($plan, $payload);
        });

        $this->logPlanAction($request, 'sales_commission.plan.updated', (int) $plan->id);

        return $this->success(SalesCommissionPlanResource::make($plan->fresh(['tiers', 'productRates.product:id,name']))->resolve());
    }

    public function destroy(Request $request, SalesCommissionPlan $plan): JsonResponse
    {
        $plan->delete();

        $this->logPlanAction($request, 'sales_commission.plan.deleted', (int) $plan->id);

        return $this->success(null, '删除成功');
    }

    /**
     * 校验并组装方案负载：方案主体 + 阶梯 + 产品费率（级联保存）。
     *
     * @return array{plan: array<string, mixed>, tiers: array<int, array<string, mixed>>, product_rates: array<int, array<string, mixed>>}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'basis' => ['nullable', 'integer', 'in:1,2'],
            'is_enabled' => ['nullable', 'integer', 'in:0,1'],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date'],
            'remark' => ['nullable', 'string', 'max:255'],
            'tiers' => ['present', 'array'],
            'tiers.*.min_amount' => ['required', 'numeric', 'min:0'],
            'tiers.*.max_amount' => ['nullable', 'numeric', 'min:0'],
            'tiers.*.rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'product_rates' => ['present', 'array'],
            'product_rates.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'product_rates.*.rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'product_rates.*.fixed_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $validFrom = $data['valid_from'] ?? null;
        $validTo = $data['valid_to'] ?? null;
        if ($validFrom !== null && $validTo !== null && $validFrom > $validTo) {
            throw new BusinessException('方案有效期不合法：开始日期不能晚于结束日期');
        }

        $plan = [
            'name' => trim((string) $data['name']),
            'basis' => (int) ($data['basis'] ?? SalesCommissionPlan::BASIS_PAID_AMOUNT),
            'is_enabled' => (int) ($data['is_enabled'] ?? 1),
            'valid_from' => $validFrom,
            'valid_to' => $validTo,
            'remark' => trim((string) ($data['remark'] ?? '')) !== '' ? trim((string) $data['remark']) : null,
        ];

        $tiers = [];
        foreach ((array) ($data['tiers'] ?? []) as $tier) {
            $minAmount = Money::round($tier['min_amount']);
            $maxAmount = isset($tier['max_amount']) && $tier['max_amount'] !== ''
                ? Money::round($tier['max_amount'])
                : null;

            if ($maxAmount !== null && $minAmount >= $maxAmount) {
                throw new BusinessException('阶梯区间不合法：下限必须小于上限');
            }

            $tiers[] = [
                'min_amount' => Money::format($minAmount),
                'max_amount' => $maxAmount !== null ? Money::format($maxAmount) : null,
                'rate' => Money::format($tier['rate']),
            ];
        }

        $this->assertNoTierOverlap($tiers);

        $productRates = [];
        foreach ((array) ($data['product_rates'] ?? []) as $rate) {
            $rateValue = isset($rate['rate']) && $rate['rate'] !== '' ? Money::format($rate['rate']) : null;
            $fixedValue = isset($rate['fixed_amount']) && $rate['fixed_amount'] !== ''
                ? Money::format($rate['fixed_amount'])
                : null;

            if ($rateValue === null && $fixedValue === null) {
                throw new BusinessException('产品费率必须填写比例或固定金额（二选一）');
            }

            $productRates[] = [
                'product_id' => (int) $rate['product_id'],
                'rate' => $rateValue,
                'fixed_amount' => $fixedValue,
            ];
        }

        $this->assertNoDuplicateProduct($productRates);

        return ['plan' => $plan, 'tiers' => $tiers, 'product_rates' => $productRates];
    }

    /**
     * @param  array<int, array<string, mixed>>  $tiers
     */
    private function assertNoTierOverlap(array $tiers): void
    {
        $sorted = collect($tiers)
            ->sortBy('min_amount')
            ->values()
            ->all();

        for ($i = 1, $iMax = count($sorted); $i < $iMax; $i++) {
            $previousMax = $sorted[$i - 1]['max_amount'];
            if ($previousMax === null) {
                throw new BusinessException('阶梯区间重叠：上一阶梯上限为 ∞ 时不能再追加阶梯');
            }

            if (Money::compare($sorted[$i]['min_amount'], $previousMax) < 0) {
                throw new BusinessException('阶梯区间重叠，请调整区间后保存');
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $productRates
     */
    private function assertNoDuplicateProduct(array $productRates): void
    {
        $productIds = array_map(static fn (array $rate): int => (int) $rate['product_id'], $productRates);

        if (count($productIds) !== count(array_unique($productIds))) {
            throw new BusinessException('同一商品在产品费率中重复配置');
        }
    }

    /**
     * 阶梯与产品费率级联保存（全量替换语义）。
     *
     * @param  array{plan: array<string, mixed>, tiers: array<int, array<string, mixed>>, product_rates: array<int, array<string, mixed>>}  $payload
     */
    private function syncChildren(SalesCommissionPlan $plan, array $payload): void
    {
        SalesCommissionTier::query()->where('plan_id', (int) $plan->id)->delete();

        if ($payload['tiers'] !== []) {
            SalesCommissionTier::query()->insert(array_map(static fn (array $tier): array => array_merge($tier, [
                'plan_id' => (int) $plan->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]), $payload['tiers']));
        }

        SalesCommissionProductRate::query()->where('plan_id', (int) $plan->id)->delete();

        if ($payload['product_rates'] !== []) {
            SalesCommissionProductRate::query()->insert(array_map(static fn (array $rate): array => array_merge($rate, [
                'plan_id' => (int) $plan->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]), $payload['product_rates']));
        }
    }

    private function logPlanAction(Request $request, string $action, int $planId): void
    {
        $operator = $request->user();

        try {
            $this->operationLogService->write(
                userId: $operator?->id,
                userType: 'admin',
                action: $action,
                module: 'sales_commission',
                targetId: $planId,
                detail: ['plan_name' => (string) $request->input('name', '')],
            );
        } catch (Throwable) {
            // 日志失败不阻断主流程
        }
    }
}
