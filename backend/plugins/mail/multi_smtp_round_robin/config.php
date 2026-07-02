<?php

declare(strict_types=1);
use Caiwu\Plugins\Mail\MultiSmtpRoundRobin\MultiSmtpRoundRobinPlugin;

return [
    'info' => [
        'domain' => 'mail',
        'slug' => 'multi_smtp_round_robin',
        'key' => 'multi_smtp_round_robin',
        'name' => '多 SMTP 轮询',
        'version' => '1.0.0',
        'entry' => MultiSmtpRoundRobinPlugin::class,
        'capabilities' => ['smtp', 'round_robin', 'cooldown'],
        'extra' => [
            'selection_setting' => [
                'group' => 'notification',
                'key' => 'mail_driver',
                'value' => 'multi_smtp_round_robin',
            ],
        ],
    ],
    'config' => [
        'accounts_notice' => ['title' => '配置说明', 'type' => 'notice', 'theme' => 'info', 'content' => '支持配置多个 SMTP 账号，发送失败后自动轮询到下一个可用账号。'],
        'accounts' => ['title' => 'SMTP 账号列表', 'type' => 'json', 'value' => [], 'required' => true, 'secret' => true, 'description' => '请通过账号管理器维护 SMTP 主机、端口、账号和密码。'],
        'cooldown_seconds' => ['title' => '失败冷却秒数', 'type' => 'number', 'value' => 60, 'min' => 1, 'step' => 1, 'description' => '账号发送失败后进入冷却的秒数。'],
    ],
];
