<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\Setting;

/**
 * M10 登录成功提醒的全局策略设置。
 *
 * settings 新增两个键（group=users）：
 * - login_alert.default：新用户注册时登录提醒开关的默认值（1/0）；
 * - login_alert.channels：登录提醒启用通道，逗号分隔，取值 mail / inbox。
 *
 * 说明：channels 是全局通道开关，与用户级开关（users.login_email_alert /
 * login_notify）组合生效——用户开关开启时，仅在配置的通道上发送。
 */
class LoginAlertSettingService
{
    public const SETTING_GROUP = 'users';

    public const KEY_DEFAULT_ENABLED = 'login_alert.default';

    public const KEY_CHANNELS = 'login_alert.channels';

    public const CHANNEL_MAIL = 'mail';

    public const CHANNEL_INBOX = 'inbox';

    private const AVAILABLE_CHANNELS = [self::CHANNEL_MAIL, self::CHANNEL_INBOX];

    public function defaultUserSwitchEnabled(): bool
    {
        $value = Setting::getValue(self::SETTING_GROUP, self::KEY_DEFAULT_ENABLED, '1');

        return in_array($value, [true, 1, '1', 'true', 'on'], true);
    }

    /**
     * 启用的登录提醒通道（去重、按固定顺序），默认邮件 + 站内信。
     *
     * @return list<string>
     */
    public function channels(): array
    {
        $raw = trim((string) Setting::getValue(self::SETTING_GROUP, self::KEY_CHANNELS, 'mail,inbox'));
        if ($raw === '') {
            return self::AVAILABLE_CHANNELS;
        }

        $parsed = array_values(array_unique(array_intersect(
            array_map(
                static fn (string $item): string => strtolower(trim($item)),
                explode(',', $raw)
            ),
            self::AVAILABLE_CHANNELS
        )));

        return $parsed !== [] ? $parsed : self::AVAILABLE_CHANNELS;
    }

    public function hasChannel(string $channel): bool
    {
        return in_array($channel, $this->channels(), true);
    }
}
