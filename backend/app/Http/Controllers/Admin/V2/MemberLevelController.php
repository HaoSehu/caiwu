<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V2;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\V2\MemberLevel\CreateMemberLevelRequest;
use App\Http\Requests\Admin\V2\MemberLevel\DeleteMemberLevelRequest;
use App\Http\Requests\Admin\V2\MemberLevel\ListMemberLevelsRequest;
use App\Http\Requests\Admin\V2\MemberLevel\UpdateMemberLevelRequest;
use App\Http\Resources\Admin\V2\AdminMemberLevelDetailResource;
use App\Http\Resources\Admin\V2\AdminMemberLevelListItemResource;
use App\Models\MemberLevel;
use App\Services\Referral\MemberLevelService;
use Illuminate\Pagination\LengthAwarePaginator;

class MemberLevelController extends Controller
{
    public function __construct(private readonly MemberLevelService $memberLevels) {}

    public function index(ListMemberLevelsRequest $request)
    {
        $list = AdminMemberLevelListItemResource::collection($this->memberLevels->list())->resolve();

        // 全量等级列表无真实分页，统一经标准分页器出信封（page=1、page_size=条目数）。
        $total = count($list);

        return $this->paginate(new LengthAwarePaginator($list, $total, max($total, 1), 1));
    }

    public function store(CreateMemberLevelRequest $request)
    {
        $level = $this->memberLevels->create($request->payload());

        return $this->success(AdminMemberLevelDetailResource::make($level)->resolve(), '等级创建成功');
    }

    public function update(UpdateMemberLevelRequest $request, MemberLevel $memberLevel)
    {
        $level = $this->memberLevels->update($memberLevel, $request->payload());

        return $this->success(AdminMemberLevelDetailResource::make($level)->resolve(), '等级更新成功');
    }

    public function destroy(DeleteMemberLevelRequest $request, MemberLevel $memberLevel)
    {
        $this->memberLevels->delete($memberLevel);

        return $this->success(null, '等级删除成功');
    }
}
