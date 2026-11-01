<?php

declare(strict_types=1);

use Caiwu\Plugins\Addons\Qiniu\QiniuPlugin;

/*
|--------------------------------------------------------------------------
| 七牛云对象存储插件配置
|--------------------------------------------------------------------------
| 字段与顺序严格按《媒体库远程存储插件方案》七牛参数表。
| 上传走表单直传（multipart 上传接口），删除走 RS 管理接口，
| 均由 AccessKey/SecretKey 签名，凭证保存后加密存储。
*/

return [
    'info' => [
        'domain' => 'addons',
        'slug' => 'qiniu',
        'key' => 'qiniu',
        'name' => '七牛云存储',
        'version' => '1.0.0',
        'entry' => QiniuPlugin::class,
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
            'title' => '权限提示',
            'content' => 'AccessKey/SecretKey 需具备该空间的上传与删除权限。',
        ],

        'access_key' => [
            'type' => 'password',
            'secret' => true,
            'title' => 'AccessKey',
            'value' => '',
            'required' => true,
            'description' => '七牛 AccessKey，保存后加密存储。',
            'rules' => ['required', 'string', 'max:100'],
        ],

        'secret_key' => [
            'type' => 'password',
            'secret' => true,
            'title' => 'SecretKey',
            'value' => '',
            'required' => true,
            'description' => '七牛 SecretKey，保存后加密存储。',
            'rules' => ['required', 'string', 'max:100'],
        ],

        'bucket' => [
            'type' => 'text',
            'title' => '空间名',
            'value' => '',
            'description' => '七牛存储空间名，不含冒号。',
            'rules' => ['required', 'string', 'max:100', 'regex:/^[^:]+$/'],
        ],

        'region' => [
            'type' => 'select',
            'title' => '存储区域',
            'value' => 'z0',
            'required' => true,
            'options' => [
                'z0' => '华东（浙江）',
                'cn-east-2' => '华东（浙江2）',
                'z1' => '华北（河北）',
                'z2' => '华南（广东）',
                'na0' => '北美',
                'as0' => '东南亚（新加坡）',
                'fog-cn-east' => '华东-渊亭',
            ],
            'description' => '空间所在区域，决定默认上传域名。',
            'rules' => ['required', 'string'],
        ],

        'upload_host' => [
            'type' => 'text',
            'title' => '上传域名',
            'value' => '',
            'required' => false,
            'description' => '留空按区域自动推导（如 https://up-z0.qiniup.com）。',
            'rules' => ['nullable', 'string', 'max:500'],
        ],

        'api_host' => [
            'type' => 'text',
            'title' => '管理域名',
            'value' => 'https://rs.qiniuapi.com',
            'required' => false,
            'description' => 'RS 管理接口域名，默认 https://rs.qiniuapi.com。',
            'rules' => ['nullable', 'string', 'max:500'],
        ],

        'public_domain' => [
            'type' => 'url',
            'title' => '外链域名',
            'value' => '',
            'required' => true,
            'description' => '空间绑定的 HTTP(S) 外链域名，无协议时自动补 https://。',
            'rules' => ['required', 'string', 'max:500'],
        ],

        'object_prefix' => [
            'type' => 'text',
            'title' => '对象键前缀',
            'value' => 'media',
            'required' => false,
            'description' => '仅当传入裸文件名（不含斜杠）时才拼接的前缀；平台恒传完整对象键 media/xxx，该字段只为兼容手动调用。',
            'rules' => ['nullable', 'string', 'max:200', 'regex:/^([^\/].*[^\/])?$/'],
        ],

        'private_bucket' => [
            'type' => 'switch',
            'title' => '私有空间',
            'value' => false,
            'required' => false,
            'description' => '默认关闭。开启后外链带 e/token 过期下载凭证（短期有效）。',
            'rules' => ['nullable', 'boolean'],
        ],

        'timeout' => [
            'type' => 'number',
            'title' => '超时（秒）',
            'value' => 30,
            'required' => false,
            'description' => '网络超时，5-120 秒，最大 120。',
            'rules' => ['nullable', 'integer', 'min:5', 'max:120'],
        ],
    ],
];
