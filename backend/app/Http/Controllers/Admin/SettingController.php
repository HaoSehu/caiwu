<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\System\SettingService;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function __construct(
        private SettingService $settingService,
    ) {}

    /**
     * 获取配置
     */
    public function index(Request $request)
    {
        $data = $request->validate([
            'group' => ['nullable', 'string', 'max:50'],
        ]);

        $group = trim((string) ($data['group'] ?? 'system'));

        return $this->success($this->settingService->getGroupSettings($group));
    }

    /**
     * 更新配置
     */
    public function update(Request $request)
    {
        $data = $request->validate([
            'group' => ['nullable', 'string', 'max:50'],
            'settings' => ['required', 'array'],
        ]);

        $group = trim((string) ($data['group'] ?? 'system'));
        $this->settingService->saveGroupSettings($group, (array) $data['settings']);

        return $this->success(null, '配置已更新');
    }
}
