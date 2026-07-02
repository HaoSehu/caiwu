<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MemberLevel\SaveRequest;
use App\Http\Resources\User\MemberLevelResource;
use App\Models\MemberLevel;
use App\Services\Referral\MemberLevelService;

class MemberLevelController extends Controller
{
    public function __construct(private MemberLevelService $memberLevelService) {}

    public function index()
    {
        return $this->success(MemberLevelResource::collection($this->memberLevelService->list())->resolve());
    }

    public function store(SaveRequest $request)
    {
        $level = $this->memberLevelService->create($request->validated());

        return $this->success(new MemberLevelResource($level), '等级创建成功');
    }

    public function update(SaveRequest $request, MemberLevel $memberLevel)
    {
        $level = $this->memberLevelService->update($memberLevel, $request->validated());

        return $this->success(new MemberLevelResource($level), '等级更新成功');
    }

    public function destroy(MemberLevel $memberLevel)
    {
        $this->memberLevelService->delete($memberLevel);

        return $this->success(null, '等级删除成功');
    }
}
