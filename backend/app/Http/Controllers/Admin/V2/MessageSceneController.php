<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V2;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Services\Messaging\MessageConfigurationRepository;
use App\Services\Messaging\MessageSceneQueryService;
use App\Services\System\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 管理端「消息配置」场景接口（对齐 howstack /api/admin/v1/message-scenes）。
 *
 * 权限沿用 settings.view / settings.manage：拆新权限码会即时剥夺现有管理员访问权。
 */
class MessageSceneController extends Controller
{
    public function __construct(
        private readonly MessageSceneQueryService $scenes,
        private readonly MessageConfigurationRepository $configurations,
        private readonly ActivityLogService $activityLogService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $audience = $this->audience($request);

        return $this->success([
            'data' => $this->scenes->list($audience),
            'audience' => $audience,
        ]);
    }

    public function show(Request $request, string $code): JsonResponse
    {
        $audience = $this->audience($request);

        return $this->success($this->scenes->detail($audience, $code));
    }

    public function update(Request $request, string $code): JsonResponse
    {
        $audience = $this->audience($request);
        $groups = $this->scenes->validateGroups($audience, $code, $request->all());

        $revisions = (array) $request->input('revisions', []);
        $revision = (int) ($revisions[$audience] ?? 0);

        $newRevision = $this->configurations->save($audience, $code, $revision, ['_groups' => $groups]);

        $this->activityLogService->logSafely(
            'message',
            'message.configuration.update',
            '消息配置已保存：'.$code,
            'message_scene',
            null,
            ['scene' => $code, 'audience' => $audience, 'revision' => $newRevision, 'groups' => count($groups)]
        );

        return $this->success($this->scenes->detail($audience, $code), '消息配置已保存');
    }

    /**
     * 批量启用/禁用指定渠道：只改配置里的 enabled 开关，不触碰模板正文。
     */
    public function batch(Request $request): JsonResponse
    {
        $audience = $this->audience($request);
        $channel = (string) $request->input('channel', '');
        $enabled = (bool) $request->input('enabled', false);
        $items = (array) $request->input('items', []);

        if (! in_array($channel, ['email', 'sms', 'message'], true)) {
            throw new BusinessException('消息渠道无效', 42200, 422);
        }

        if ($items === [] || count($items) > 100) {
            throw new BusinessException('请选择需要批量配置的消息', 42200, 422);
        }

        // 整批要么全成功、要么全回滚：中途某条失败（非法场景、修订号冲突）
        // 不能留下前几条已保存、后几条未处理的半更新状态。
        $updated = DB::transaction(function () use ($audience, $channel, $enabled, $items): int {
            $count = 0;

            foreach ($items as $item) {
                $code = (string) ($item['code'] ?? '');
                $revision = (int) ($item['revision'] ?? 0);
                if ($code === '') {
                    continue;
                }

                $detail = $this->scenes->detail($audience, $code);
                $groups = $detail['groups'];
                foreach ($groups as $index => $group) {
                    if (! array_key_exists($channel, $group['configurations'])) {
                        continue;
                    }
                    $groups[$index]['configurations'][$channel]['enabled'] = $enabled;
                }

                $this->configurations->save($audience, $code, $revision, ['_groups' => $groups]);
                $count++;
            }

            return $count;
        });

        $this->activityLogService->logSafely(
            'message',
            'message.configuration.batch',
            '消息配置批量保存',
            'message_scene',
            null,
            ['audience' => $audience, 'channel' => $channel, 'enabled' => $enabled, 'updated' => $updated]
        );

        return $this->success(['updated' => $updated], '批量配置已保存');
    }

    private function audience(Request $request): string
    {
        $audience = (string) $request->input('audience', 'customer');

        return $audience === 'admin' ? 'admin' : 'customer';
    }
}
