<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V2;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\V2\PromotionAmbassador\CreatePromotionAmbassadorRequest;
use App\Http\Requests\Admin\V2\PromotionAmbassador\DeletePromotionAmbassadorRequest;
use App\Http\Requests\Admin\V2\PromotionAmbassador\ListPromotionAmbassadorsRequest;
use App\Http\Requests\Admin\V2\PromotionAmbassador\UpdatePromotionAmbassadorRequest;
use App\Http\Resources\Admin\V2\AdminPromotionAmbassadorDetailResource;
use App\Http\Resources\Admin\V2\AdminPromotionAmbassadorListItemResource;
use App\Models\PromotionAmbassador;
use App\Services\Referral\PromotionAmbassadorService;
use Illuminate\Pagination\LengthAwarePaginator;

class PromotionAmbassadorController extends Controller
{
    public function __construct(private readonly PromotionAmbassadorService $ambassadors) {}

    public function index(ListPromotionAmbassadorsRequest $request)
    {
        $list = AdminPromotionAmbassadorListItemResource::collection($this->ambassadors->list())->resolve();

        // 全量档位列表无真实分页，统一经标准分页器出信封（page=1、page_size=条目数）。
        $total = count($list);

        return $this->paginate(new LengthAwarePaginator($list, $total, max($total, 1), 1));
    }

    public function store(CreatePromotionAmbassadorRequest $request)
    {
        $ambassador = $this->ambassadors->create($request->payload());

        return $this->success(AdminPromotionAmbassadorDetailResource::make($ambassador)->resolve(), '大使档位创建成功');
    }

    public function update(UpdatePromotionAmbassadorRequest $request, PromotionAmbassador $promotionAmbassador)
    {
        $ambassador = $this->ambassadors->update($promotionAmbassador, $request->payload());

        return $this->success(AdminPromotionAmbassadorDetailResource::make($ambassador)->resolve(), '大使档位更新成功');
    }

    public function destroy(DeletePromotionAmbassadorRequest $request, PromotionAmbassador $promotionAmbassador)
    {
        $this->ambassadors->delete($promotionAmbassador);

        return $this->success(null, '大使档位删除成功');
    }
}
