<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V2;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\V2\AdminCarePlanDetailResource;
use App\Http\Resources\Admin\V2\AdminCarePlanListResource;
use App\Http\Resources\Admin\V2\AdminCareTaskRunResource;
use App\Models\CarePlan;
use App\Services\Care\CarePlanAutomationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CarePlanController extends Controller
{
    public function __construct(
        private readonly CarePlanAutomationService $carePlans,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'keyword' => 'nullable|string|max:100',
            'type' => 'nullable|integer|in:1,2,3,4',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = CarePlan::query()->with('template:id,channel,code,name');

        if (isset($validated['type'])) {
            $query->where('type', (int) $validated['type']);
        }

        if (isset($validated['keyword']) && trim((string) $validated['keyword']) !== '') {
            $query->where('name', 'like', '%'.trim((string) $validated['keyword']).'%');
        }

        return $this->paginate(
            $query->orderByDesc('id')->paginate(max(1, min(100, (int) ($validated['per_page'] ?? 20)))),
            AdminCarePlanListResource::class
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return $this->success(
            AdminCarePlanDetailResource::make($this->findOrFail($id))->resolve()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);

        $plan = CarePlan::create($validated + [
            'is_enabled' => (int) ($validated['is_enabled'] ?? 0),
        ]);

        return $this->success([
            'care_plan' => AdminCarePlanDetailResource::make($plan)->resolve(),
        ], '关怀计划已创建');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $plan = $this->findOrFail($id);
        $validated = $this->validatePayload($request);

        $plan->fill($validated)->save();

        return $this->success([
            'care_plan' => AdminCarePlanDetailResource::make($plan->refresh())->resolve(),
        ], '关怀计划已更新');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->findOrFail($id)->delete();

        return $this->success(null, '关怀计划已删除');
    }

    public function runs(Request $request, int $id): JsonResponse
    {
        $plan = $this->findOrFail($id);

        $validated = $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        return $this->paginate(
            $plan->runs()->orderByDesc('id')
                ->paginate(max(1, min(100, (int) ($validated['per_page'] ?? 20)))),
            AdminCareTaskRunResource::class
        );
    }

    /**
     * 手动触发；可选 dry_run=1 仅试算目标数（不建 run、不落日志、不发送）。
     */
    public function trigger(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'dry_run' => 'nullable|boolean',
        ]);

        $dryRun = filter_var($validated['dry_run'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return $this->success(
            $this->carePlans->trigger($id, $dryRun),
            $dryRun ? '试算完成（未发送）' : '关怀计划已触发'
        );
    }

    private function findOrFail(int $id): CarePlan
    {
        $plan = CarePlan::query()->with('template:id,channel,code,name')->find($id);

        if (! $plan instanceof CarePlan) {
            throw new BusinessException('关怀计划不存在', 40400, 404);
        }

        return $plan;
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:100',
            'type' => 'required|integer|in:1,2,3,4',
            'offset_days' => 'nullable|integer|min:-365|max:365',
            'channel' => 'required|integer|in:1,2,3',
            'template_id' => 'nullable|integer|min:1',
            'subject' => 'nullable|string|max:200',
            'content' => 'nullable|string',
            'filter_json' => 'nullable|array',
            'is_enabled' => 'nullable|integer|in:0,1',
        ]);
    }
}
