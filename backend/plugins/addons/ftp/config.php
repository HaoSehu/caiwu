<?php

declare(strict_types=1);

use Caiwu\Plugins\Addons\Ftp\FtpPlugin;

/*
|--------------------------------------------------------------------------
| FTP 媒体库远程存储插件配置
|--------------------------------------------------------------------------
| 字段与顺序严格按《媒体库远程存储插件方案》FTP 参数表。
| FTP 本身没有 HTTP 外链能力，必须同时配置一个能访问到
| root_dir 对应目录的 HTTP 域名，外链公式：
| rtrim(public_domain, '/') . url_prefix . '/' . object_key
*/

return [
    'info' => [
        'domain' => 'addons',
        'slug' => 'ftp',
        'key' => 'ftp',
        'name' => 'FTP 存储',
        'version' => '1.0.0',
        'entry' => FtpPlugin::class,
        'capabilities' => ['media.storage'],
        'extra' => [
            'requires' => [
                'core' => '>=1.0.0',
            ],
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | 配置字段（顺序即表单渲染顺序）
    |----------------------------------------------------------------------
    */
    'config' => [
        'notice' => [
            'type' => 'notice',
            'theme' => 'warning',
            'title' => '外链域名提示',
            'content' => 'FTP 无 HTTP 外链能力，必须同时配置可访问的 HTTP 域名，否则上传成功也无法生成可访问的文件外链。',
        ],

        'host' => [
            'type' => 'text',
            'title' => '服务器地址',
            'value' => '',
            'required' => true,
            'description' => 'FTP 服务器地址，仅主机名或 IP，不含协议与路径。',
            'rules' => ['required', 'string', 'max:190', 'regex:/^[a-zA-Z0-9.\-]+$/'],
        ],

        'port' => [
            'type' => 'number',
            'title' => '端口',
            'value' => 21,
            'required' => false,
            'description' => 'FTP 端口，1-65535，默认 21。',
            'rules' => ['nullable', 'integer', 'min:1', 'max:65535'],
        ],

        'username' => [
            'type' => 'text',
            'title' => '用户名',
            'value' => '',
            'required' => true,
            'description' => 'FTP 登录用户名。',
            'rules' => ['required', 'string', 'max:100'],
        ],

        'password' => [
            'type' => 'password',
            'secret' => true,
            'title' => '密码',
            'value' => '',
            'required' => true,
            'description' => 'FTP 登录密码，保存后加密存储。',
            'rules' => ['required', 'string'],
        ],

        'root_dir' => [
            'type' => 'text',
            'title' => '根目录',
            'value' => '/',
            'required' => false,
            'description' => 'FTP 上的根目录，须以 / 开头且不含 ..，对象键会拼接在其后。',
            'rules' => ['nullable', 'string', 'max:500', 'regex:/^\/[^.]*$/'],
        ],

        'passive' => [
            'type' => 'switch',
            'title' => '被动模式',
            'value' => true,
            'required' => false,
            'description' => '默认开启。多数 FTP 服务在防火墙/NAT 后需要被动模式。',
            'rules' => ['nullable', 'boolean'],
        ],

        'use_ssl' => [
            'type' => 'switch',
            'title' => 'SSL/FTPS 连接',
            'value' => false,
            'required' => false,
            'description' => '默认关闭。开启后使用 ftp_ssl_connect（FTP over TLS）。',
            'rules' => ['nullable', 'boolean'],
        ],

        'timeout' => [
            'type' => 'number',
            'title' => '超时（秒）',
            'value' => 30,
            'required' => false,
            'description' => '网络超时，5-120 秒，默认 30。',
            'rules' => ['nullable', 'integer', 'min:5', 'max:120'],
        ],

        'public_domain' => [
            'type' => 'url',
            'title' => '外链域名',
            'value' => '',
            'required' => true,
            'description' => 'HTTP(S) 外链域名，须以 http(s):// 开头，无查询串、无结尾斜杠。',
            'rules' => ['required', 'url', 'max:500', 'regex:/^https?:\/\//'],
        ],

        'url_prefix' => [
            'type' => 'text',
            'title' => '外链路径前缀',
            'value' => '',
            'required' => false,
            'description' => '外链域名映射到 root_dir 后剩余的路径前缀，默认为空。',
            'rules' => ['nullable', 'string', 'max:500'],
        ],

        'object_prefix' => [
            'type' => 'text',
            'title' => '对象键前缀',
            'value' => 'media',
            'required' => false,
            'description' => '仅当传入裸文件名（不含斜杠）时才拼接的前缀；平台恒传完整对象键 media/xxx，该字段只为兼容手动调用。',
            'rules' => ['nullable', 'string', 'max:200', 'regex:/^([^\/].*[^\/])?$/'],
        ],
    ],
];
