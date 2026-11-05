<?php

namespace App\Services\Notification;

use App\Models\User;
use App\Models\UserNotification;
use App\Services\Messaging\MessageConfigurationRepository;
use App\Support\MessageGroups;
use App\Support\MessageTemplateRenderer;
use App\Support\MessageTypeCatalog;
use App\Support\SchemaMetadataCache;
use App\Support\SiteConfigPayload;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 站内信（个性化通知）写入服务。
 *
 * 仅负责往 user_notifications 表写记录；公告类未读由 NoticeReadService 统计。
 * 所有写入都做防御性处理：表不存在或写入异常都不应阻断业务主流程。
 */
class UserNotificationService
{
    private ?bool $tableReady = null;

    public function __construct(
        private readonly ?MessageConfigurationRepository $configurations = null,
    ) {}

    /**
     * 按消息场景写入站内信：标题正文取自场景的 message 模板，受渠道开关控制。
     *
     * 与 create() 的区别：
     * - create() 只负责落库，文案由调用方硬编码；
     * - 本方法先查场景配置，站内消息渠道未启用时直接跳过，标题正文由模板渲染。
     *
     * 场景不存在或未配站内消息模板时回退到调用方给的标题正文，
     * 保证接入过程中不会因为漏配而丢消息。
     *
     * @param  array<string, scalar|null>  $variables  模板变量
     * @param  array<string, mixed>  $data  附加业务数据
     */
    public function createForScene(
        int $userId,
        string $scene,
        string $fallbackTitle,
        ?string $fallbackContent,
        ?string $link,
        array $variables = [],
        array $data = [],
        bool $marketing = false,
    ): ?UserNotification {
        if ($userId <= 0 || ! $this->tableReady()) {
            return null;
        }

        // 站内消息渠道未启用时不写入（管理端开关在这里生效）
        if (! $this->inboxChannelEnabled($scene)) {
            return null;
        }

        [$title, $content] = $this->renderFromTemplate($scene, $variables, $fallbackTitle, $fallbackContent);

        return $this->create($userId, $scene, $title, $content, $link, $data, $marketing);
    }

    /**
     * 站内消息渠道是否启用；场景不存在或未配置时按「启用」处理（保持旧行为）。
     */
    private function inboxChannelEnabled(string $scene): bool
    {
        if ($this->configurations === null) {
            return true;
        }

        try {
            $stored = $this->configurations->get('customer', $scene);
            if ($stored['revision'] === 0) {
                return true;
            }

            $type = MessageTypeCatalog::get('customer', $scene);
            foreach (MessageGroups::fromStored('customer', $type, $stored['configurations']) as $group) {
                $config = $group['configurations']['message'] ?? null;
                if ($config === null) {
                    continue;
                }

                if (($group['enabled'] ?? false) && ($config['enabled'] ?? false)) {
                    return true;
                }
            }

            return false;
        } catch (\Throwable) {
            return true;
        }
    }

    /**
     * 从场景的 message 模板渲染标题正文；没有模板时用调用方兜底文案。
     *
     * 同一场景有多条站内模板时（如续费提醒分「即将到期」与「已到期」），
     * 取占位符与本次变量重合最多的一条，避免不同档位共用同一文案。
     *
     * @param  array<string, scalar|null>  $variables
     * @return array{0: string, 1: string|null}
     */
    private function renderFromTemplate(
        string $scene,
        array $variables,
        string $fallbackTitle,
        ?string $fallbackContent,
    ): array {
        try {
            $templates = DB::table('notification_templates')
                ->where('channel', 'message')
                ->where('scene_code', $scene)
                ->where('is_enabled', 1)
                ->orderBy('sort_order')
                ->get(['subject', 'content'])
                ->all();

            if ($templates === []) {
                return [$fallbackTitle, $fallbackContent];
            }

            $template = $this->pickMessageTemplate($templates, $variables);

            $values = ['site_name' => (string) (SiteConfigPayload::payload()['site_name'] ?? '')];
            foreach ($variables as $key => $value) {
                $values[(string) $key] = (string) $value;
            }

            $title = MessageTemplateRenderer::render((string) $template->subject, $values);
            $content = MessageTemplateRenderer::render((string) $template->content, $values);

            return [
                $title !== '' ? $title : $fallbackTitle,
                $content !== '' ? $content : $fallbackContent,
            ];
        } catch (\Throwable) {
            return [$fallbackTitle, $fallbackContent];
        }
    }

    /**
     * 多条站内模板时选最贴近本次变量的一条。
     *
     * 排序口径（依次比较，全部相同才保留先出现的，即 sort_order 靠前者）：
     * 1. 命中本次变量的占位符数量越多越贴近；
     * 2. 占位符总数越多，信息越完整；
     * 3. 模板正文越短，说明越聚焦。
     *
     * @param  list<object>  $templates
     * @param  array<string, scalar|null>  $variables
     */
    private function pickMessageTemplate(array $templates, array $variables): object
    {
        if (count($templates) === 1) {
            return $templates[0];
        }

        $keys = array_keys($variables);
        $best = $templates[0];
        $bestRank = null;

        foreach ($templates as $template) {
            $text = (string) ($template->subject ?? '').' '.(string) ($template->content ?? '');
            preg_match_all('/\{\{([a-zA-Z][a-zA-Z0-9_]*)\}\}/', $text, $matches);
            $placeholders = array_values(array_unique($matches[1]));

            $matched = 0;
            foreach ($placeholders as $placeholder) {
                if (in_array($placeholder, $keys, true)) {
                    $matched++;
                }
            }

            $rank = [$matched, count($placeholders), -strlen($text)];

            if ($bestRank === null || $rank > $bestRank) {
                $best = $template;
                $bestRank = $rank;
            }
        }

        return $best;
    }

    /**
     * 创建一条站内信。
     *
     * @param  array<string,mixed>  $data  附加业务数据
     * @param  bool  $marketing  是否营销/推广类通知：受用户 marketing_alert 偏好控制，关闭则不写入
     */
    public function create(
        int $userId,
        string $type,
        string $title,
        ?string $content = null,
        ?string $link = null,
        array $data = [],
        bool $marketing = false
    ): ?UserNotification {
        if ($userId <= 0 || ! $this->tableReady()) {
            return null;
        }

        // 通知偏好生效：营销类站内信按用户偏好过滤，业务必要提醒不受影响。
        if ($marketing && ! $this->userAcceptsMarketing($userId)) {
            return null;
        }

        try {
            return UserNotification::create([
                'user_id' => $userId,
                'type' => $type,
                'title' => mb_substr(trim($title), 0, 191),
                'content' => $content !== null ? trim($content) : null,
                'link' => $link,
                'data' => $data === [] ? null : $data,
            ]);
        } catch (\Throwable $exception) {
            Log::warning('[站内信] 写入失败，已忽略以避免阻断业务', [
                'user_id' => $userId,
                'type' => $type,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * 用户是否接受营销类通知（marketing_alert 默认关闭）。
     */
    private function userAcceptsMarketing(int $userId): bool
    {
        $user = User::query()->find($userId);

        return $user instanceof User && (bool) ($user->marketing_alert ?? 0);
    }

    public function unreadCount(int $userId): int
    {
        if ($userId <= 0 || ! $this->tableReady()) {
            return 0;
        }

        return (int) UserNotification::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->count();
    }

    public function markRead(int $userId, int $id): void
    {
        if (! $this->tableReady()) {
            return;
        }

        UserNotification::query()
            ->where('user_id', $userId)
            ->whereKey($id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function markAllRead(int $userId): void
    {
        if (! $this->tableReady()) {
            return;
        }

        UserNotification::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    private function tableReady(): bool
    {
        if ($this->tableReady === null) {
            try {
                $this->tableReady = SchemaMetadataCache::hasTable('user_notifications');
            } catch (\Throwable) {
                $this->tableReady = false;
            }
        }

        return $this->tableReady;
    }
}
