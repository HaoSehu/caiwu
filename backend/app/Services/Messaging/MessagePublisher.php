<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Exceptions\BusinessException;
use App\Models\AdminUser;
use App\Models\User;
use App\Support\MessageGroups;
use App\Support\MessageTypeCatalog;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * 业务消息发布器：唯一对外入口。
 *
 * 职责：
 * 1. 按场景目录校验受众与变量；
 * 2. 读取该场景的接收组配置，展开接收人 × 渠道；
 * 3. 渲染模板（{{变量}} 简单替换，不解析代码）；
 * 4. 交给 MessageRepository 在业务事务内落库。
 *
 * 验证码不走这里（purpose=verification 会被拒绝），见 VerificationMessageSender。
 */
final class MessagePublisher
{
    public function __construct(
        private readonly MessageConfigurationRepository $configurations,
        private readonly MessageRepository $repository,
    ) {}

    /**
     * @param  array<string, scalar|null>  $variables
     * @param  list<string>  $audiences
     * @return int 消息主键
     */
    public function publish(
        string $eventKey,
        string $scene,
        string $businessType,
        int $businessId,
        int $memberId,
        array $variables,
        array $audiences = ['customer', 'admin'],
    ): int {
        $audiences = array_values(array_unique($audiences));

        if ($audiences === [] || array_diff($audiences, ['customer', 'admin']) !== []) {
            throw new InvalidArgumentException('消息接收组无效');
        }

        if ($eventKey === '' || strlen($eventKey) > 256 || $businessId < 1
            || preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $businessType) !== 1) {
            throw new InvalidArgumentException('消息业务事件标识无效');
        }

        $targets = [];
        $seen = [];
        $known = false;

        foreach ($audiences as $audience) {
            try {
                $type = MessageTypeCatalog::get($audience, $scene);
            } catch (BusinessException) {
                continue;
            }

            $known = true;
            $stored = $this->configurations->get($audience, $scene);
            $values = $this->filterVariables($variables, $type['variables']);

            foreach (MessageGroups::fromStored($audience, $type, $stored['configurations']) as $group) {
                if (! MessageGroups::enabled($group)) {
                    continue;
                }

                foreach (MessageGroups::channelsFor($group['kind']) as $channel) {
                    $config = $group['configurations'][$channel] ?? [];
                    if (($config['enabled'] ?? false) !== true) {
                        continue;
                    }

                    foreach ($this->recipients($audience, $group, $memberId) as $recipient) {
                        $dedupe = ($group['kind'] === 'robot' ? 'robot' : $audience).':'.$recipient['id'].':'.$channel;
                        if (isset($seen[$dedupe])) {
                            continue;
                        }
                        $seen[$dedupe] = true;

                        $targets[] = [
                            'audience' => $audience,
                            'account_id' => $recipient['id'],
                            'target_key' => $recipient['target_key'],
                            'group_key' => (string) ($group['id'] ?? $audience),
                            'group_name' => (string) ($group['name'] ?? ''),
                            'channel' => $channel,
                            'channel_name' => (string) ($config['channel_name'] ?? ''),
                            'payload' => $this->payload($channel, $config, $recipient, $values, $scene),
                        ];
                    }
                }
            }
        }

        if (! $known) {
            throw new InvalidArgumentException('消息场景不存在');
        }

        return $this->repository->record(
            hash('sha256', $eventKey.':'.$scene.':'.$businessType.':'.$businessId),
            $scene,
            'notification',
            $businessType,
            $businessId,
            $targets,
        );
    }

    /**
     * 解析组内接收人。关联客户固定为业务关联会员，内部人员按接收策略展开。
     *
     * @param  array<string, mixed>  $group
     * @return list<array{id: int, target_key: string, email: string, mobile: string, name: string}>
     */
    private function recipients(string $audience, array $group, int $memberId): array
    {
        if (($group['kind'] ?? '') === 'customer') {
            $user = User::query()->find($memberId, ['id', 'email', 'phone', 'nickname']);

            return $user instanceof User ? [[
                'id' => (int) $user->id,
                'target_key' => 'user:'.$user->id,
                'email' => (string) ($user->email ?? ''),
                'mobile' => (string) ($user->phone ?? ''),
                'name' => (string) ($user->nickname ?: $user->email),
            ]] : [];
        }

        if (($group['kind'] ?? '') === 'admin') {
            return $this->adminRecipients((array) ($group['recipients'] ?? []));
        }

        // robot：本期不实现投递（计划决策日志 2026-11-03）
        return [];
    }

    /**
     * @param  array<string, mixed>  $policy
     * @return list<array{id: int, target_key: string, email: string, mobile: string, name: string}>
     */
    private function adminRecipients(array $policy): array
    {
        $mode = (string) ($policy['mode'] ?? 'founder');
        $ids = array_values(array_filter(array_map('intval', (array) ($policy['ids'] ?? []))));

        $query = AdminUser::query()->where('status', 1)->select(['id', 'username', 'nickname', 'email']);

        $admins = match ($mode) {
            'members' => $query->whereIn('id', $ids)->get(),
            'roles' => $query->whereIn('role_id', $ids)->get(),
            default => $this->founderAdmins(),
        };

        return $admins->map(static fn (AdminUser $admin): array => [
            'id' => (int) $admin->id,
            'target_key' => 'admin:'.$admin->id,
            'email' => (string) ($admin->email ?? ''),
            'mobile' => '',
            'name' => (string) ($admin->nickname ?: $admin->username),
        ])->values()->all();
    }

    /** @return Collection<int, AdminUser> */
    private function founderAdmins()
    {
        // 创始管理员：最小 id 的启用管理员；部门模式需组织架构表，本期未接入，
        // 统一回落创始管理员并在管理端标注（见 S5 接收策略校验）。
        return AdminUser::query()->where('status', 1)->select(['id', 'username', 'nickname', 'email'])
            ->orderBy('id')->limit(1)->get();
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array{id: int, target_key: string, email: string, mobile: string, name: string}  $recipient
     * @param  array<string, string>  $values
     * @return array<string, mixed>
     */
    private function payload(string $channel, array $config, array $recipient, array $values, string $scene = ''): array
    {
        $parameters = [];
        foreach ((array) json_decode((string) ($config['variables'] ?? '{}'), true, 8, JSON_THROW_ON_ERROR) as $key => $template) {
            $parameters[(string) $key] = $this->render((string) $template, $values);
        }

        return [
            'address' => $channel === 'sms' ? $recipient['mobile'] : $recipient['email'],
            'title' => $this->render((string) ($config['title'] ?? ''), $values),
            'body' => $this->render((string) ($config['body'] ?? ''), $values),
            'template_id' => (string) ($config['template_id'] ?? ''),
            'parameters' => $parameters,
            'channel_id' => (int) ($config['channel_id'] ?? 0),
            'channel_revision' => (int) ($config['channel_revision'] ?? 0),
            // 站内消息写入 user_notifications 所需：收件人主键、场景与跳转
            'account_id' => (int) $recipient['id'],
            'scene' => $scene,
            'link' => (string) ($config['link'] ?? ''),
            'data' => [],
        ];
    }

    /** @param  array<string, scalar|null>  $variables */
    private function filterVariables(array $variables, array $allowed): array
    {
        $filtered = [];
        foreach ($allowed as $key) {
            $filtered[$key] = (string) ($variables[$key] ?? '');
        }

        return $filtered;
    }

    /** @param  array<string, string>  $values */
    private function render(string $template, array $values): string
    {
        return (string) preg_replace_callback(
            '/\{\{([a-zA-Z][a-zA-Z0-9_]*)\}\}/',
            static fn (array $match): string => $values[$match[1]] ?? '',
            $template
        );
    }
}
