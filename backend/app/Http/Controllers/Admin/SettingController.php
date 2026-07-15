<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Setting\IndexSettingRequest;
use App\Http\Requests\Admin\Setting\UpdateSettingRequest;
use App\Services\System\SettingService;

class SettingController extends Controller
{
    public function __construct(
        private SettingService $settingService,
    ) {}

    /**
     * 获取配置
     */
    public function index(IndexSettingRequest $request)
    {
        $data = $request->validated();

        $group = trim((string) ($data['group'] ?? 'system'));

        return $this->success($this->settingService->getGroupSettings($group));
    }

    /**
     * 更新配置
     */
    public function update(UpdateSettingRequest $request)
    {
        $data = $request->validated();

        $group = trim((string) ($data['group'] ?? 'system'));
        $this->settingService->saveGroupSettings($group, (array) $data['settings']);

        return $this->success(null, '配置已更新');
    }
}
