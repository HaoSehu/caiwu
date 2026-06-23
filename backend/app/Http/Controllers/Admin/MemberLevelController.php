<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MemberLevel\DestroyRequest;
use App\Http\Resources\User\MemberLevelResource;
use App\Models\MemberLevel;
use App\Services\Referral\MemberLevelService;
use Illuminate\Http\Request;

class MemberLevelController extends Controller
{
    public function __construct(private MemberLevelService $memberLevelService) {}

    public function index()
    {
        return $this->success(MemberLevelResource::collection($this->memberLevelService->list())->resolve());
    }

    public function store(Request $request)
    {
        $payload = $this->validatedPayload($request);
        $level = $this->memberLevelService->create($payload);

        return $this->success(new MemberLevelResource($level), '等级创建成功');
    }

    public function update(Request $request, MemberLevel $memberLevel)
    {
        $payload = $this->validatedPayload($request);
        $level = $this->memberLevelService->update($memberLevel, $payload);

        return $this->success(new MemberLevelResource($level), '等级更新成功');
    }

    public function destroy(MemberLevel $memberLevel)
    {
        $this->memberLevelService->delete($memberLevel);

        return $this->success(null, '等级删除成功');
    }

    private function validatedPayload(Request $request): array
    {
        // validation handled by DestroyRequest
    }
}
