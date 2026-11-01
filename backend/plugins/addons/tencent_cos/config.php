<?php

declare(strict_types=1);

use Caiwu\Plugins\Addons\TencentCos\TencentCosPlugin;

/*
|--------------------------------------------------------------------------
| 腾讯云 COS 媒体库远程存储插件配置
|--------------------------------------------------------------------------
| 字段与顺序严格按《媒体库远程存储插件方案-2026-11-01》COS 参数表。
| 外链公式：public_domain 非空时 rtrim(public_domain,'/').'/'.object_key，
| 否则 https://{bucket}.cos.{region}.myqcloud.com/{object_key}。
*/

return [
    'info' => [
        'domain' => 'addons',
        'slug' => 'tencent_cos',
        'key' => 'tencent_cos',
        'name' => '媒体库腾讯云 COS 存储',
        'version' => '1.0.0',
        'entry' => TencentCosPlugin::class,
        'capabilities' => ['media.storage'],
        'extra' => [
            'requires' => [
                'core' => '>=1.0.0',
            ],
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | 配置字段（顺序即表单渲染顺序，与设计文档参数表一致）
    |----------------------------------------------------------------------
    */
    'config' => [
        'notice' => [
            'type' => 'notice',
            'theme' => 'info',
            'title' => '接入提示',
            'content' => '密钥使用腾讯云访问管理（CAM）的 SecretId/SecretKey；存储桶名称必须带 APPID 后缀（形如 examplebucket-1250000000）。对象默认私有读，外链可直连需桶侧开公有读或选「公有读」上传。',
        ],

        'secret_id' => [
            'type' => 'password',
            'secret' => true,
            'title' => 'SecretId',
            'value' => '',
            'required' => true,
            'description' => '腾讯云 CAM 密钥 SecretId，保存后加密存储。',
            'rules' => ['required', 'string', 'max:100'],
        ],

        'secret_key' => [
            'type' => 'password',
            'secret' => true,
            'title' => 'SecretKey',
            'value' => '',
            'required' => true,
            'description' => '腾讯云 CAM 密钥 SecretKey，保存后加密存储。',
            'rules' => ['required', 'string', 'max:100'],
        ],

        'bucket' => [
            'type' => 'text',
            'title' => '存储桶名称',
            'value' => '',
            'required' => true,
            'description' => '必须含 APPID 后缀，形如 examplebucket-1250000000。',
            'rules' => ['required', 'string', 'regex:/^[a-z0-9-]+-\\d{6,}$/'],
        ],

        'region' => [
            'type' => 'text',
            'title' => '地域',
            'value' => '',
            'required' => true,
            'description' => '地域串，形如 ap-beijing。',
            'rules' => ['required', 'string', 'regex:/^[a-z]{2}-[a-z0-9-]+$/'],
        ],

        'acl' => [
            'type' => 'select',
            'title' => '对象读权限',
            'value' => 'none',
            'required' => true,
            'options' => [
                'none' => '默认（继承桶权限）',
                'public-read' => '公有读（上传带 x-cos-acl: public-read）',
            ],
            'description' => '选公有读时上传带 x-cos-acl 头且参与签名。',
            'rules' => ['required', 'string', 'in:none,public-read'],
        ],

        'public_domain' => [
            'type' => 'url',
            'title' => '自定义外链域名',
            'value' => '',
            'required' => false,
            'description' => '自定义 CDN 域名；留空使用桶默认域名。',
            'rules' => ['nullable', 'url', 'max:500'],
        ],

        'object_prefix' => [
            'type' => 'text',
            'title' => '对象键前缀',
            'value' => 'media',
            'required' => false,
            'description' => '仅当传入裸文件名（不含斜杠）时才拼接的前缀；平台恒传完整对象键 media/xxx，该字段只为兼容手动调用。',
            'rules' => ['nullable', 'string', 'max:200', 'regex:/^([^/].*[^/])?$/'],
        ],

        'timeout' => [
            'type' => 'number',
            'title' => '超时（秒）',
            'value' => 30,
            'required' => false,
            'description' => '网络超时，5-120 秒，默认 30。',
            'rules' => ['nullable', 'integer', 'min:5', 'max:120'],
        ],
    ],
];
