<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V2;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\V2\MemberLevel\SyncLevelGroupDiscountsRequest;
use App\Models\MarketingProductGroup;
use App\Models\MemberLevel;
use App\Models\MemberLevelGroupDiscount;
use App\Services\Pricing\MarketingProductGroupService;
use Illuminate\Http\JsonResponse;

class MemberLevelGroupDiscountController extends Controller
{
    public function __construct(
        private readonly MarketingProductGroupService $marketingGroups,
    ) {}

    /**
     * 等级编辑器一次性拉齐：全部营销组 + 当前等级已配置的折扣规则。
     */
    public function index(MemberLevel $memberLevel): JsonResponse
    {
        $groups = $this->marketingGroups->list();
        $rules = MemberLevelGroupDiscount::query()
            ->where('member_level_id', $memberLevel->id)
            ->get()
            ->keyBy('marketing_product_group_id');

        return $this->success([
            'member_level' => [
                'id' => (int) $memberLevel->id,
                'name' => (string) $memberLevel->name,
                'status' => (int) $memberLevel->status,
            ],
            'groups' => collect($groups)->map(function (mixed $group) use ($rules): array {
                /** @var MarketingProductGroup $group */
                $rule = $rules->get($group->id);

                return [
                    'id' => (int) $group->id,
                    'name' => (string) $group->name,
                    'sort_order' => (int) $group->sort_order,
                    'product_count' => isset($group->items_count) ? (int) $group->items_count : null,
                    'discount' => $rule === null ? null : [
                        'discount_type' => (int) $rule->discount_type,
                        'discount_value' => number_format((float) $rule->discount_value, 2, '.', ''),
                    ],
                ];
            })->values()->all(),
        ]);
    }

    /**
     * 整包保存当前等级的折扣矩阵。
     */
    public function sync(SyncLevelGroupDiscountsRequest $request, MemberLevel $memberLevel): JsonResponse
    {
        $this->marketingGroups->syncDiscounts($memberLevel, $request->rulesPayload(), [
            'actor_type' => 'admin',
            'actor_user_id' => (int) ($request->user()->id ?? 0),
            'actor_name' => (string) ($request->user()->username ?? ''),
            'trace_id' => (string) $request->header('X-Request-Id', ''),
            'ip_address' => (string) $request->ip(),
        ]);

        return $this->success(null, '会员折扣矩阵已保存');
    }
}
