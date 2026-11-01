<?php

declare(strict_types=1);

use Caiwu\Plugins\Addons\AliyunOss\AliyunOssPlugin;

/*
|--------------------------------------------------------------------------
| 阿里云 OSS 媒体库远程存储插件配置
|--------------------------------------------------------------------------
| 字段与顺序严格按《媒体库远程存储插件方案-2026-11-01》OSS 参数表。
| 外链公式：public_domain 非空时 rtrim(public_domain,'/').'/'.object_key，
| 否则 {scheme}://{bucket}.{endpoint}/{object_key}。
*/

return [
    'info' => [
        'domain' => 'addons',
        'slug' => 'aliyun_oss',
        'key' => 'aliyun_oss',
        'name' => '媒体库阿里云 OSS 存储',
        'version' => '1.0.0',
        'entry' => AliyunOssPlugin::class,
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
            'content' => '密钥使用阿里云 RAM 的 AccessKeyId/AccessKeySecret；endpoint 填地域节点域名（形如 oss-cn-hangzhou.aliyuncs.com，不含协议与 bucket 前缀）。对象默认私有读，外链可直连需桶侧开公有读或选「公有读」上传。',
        ],

        'access_key_id' => [
            'type' => 'password',
            'secret' => true,
            'title' => 'AccessKeyId',
            'value' => '',
            'required' => true,
            'description' => '阿里云 RAM 密钥 AccessKeyId，保存后加密存储。',
            'rules' => ['required', 'string', 'max:100'],
        ],

        'access_key_secret' => [
            'type' => 'password',
            'secret' => true,
            'title' => 'AccessKeySecret',
            'value' => '',
            'required' => true,
            'description' => '阿里云 RAM 密钥 AccessKeySecret，保存后加密存储。',
            'rules' => ['required', 'string', 'max:100'],
        ],

        'bucket' => [
            'type' => 'text',
            'title' => '存储空间名称',
            'value' => '',
            'required' => true,
            'description' => '3-63 位小写字母数字或短横线，首尾为字母数字。',
            'rules' => ['required', 'string', 'regex:/^[a-z0-9][a-z0-9-]{1,61}[a-z0-9]$/'],
        ],

        'endpoint' => [
            'type' => 'text',
            'title' => '地域节点',
            'value' => '',
            'required' => true,
            'description' => '形如 oss-cn-hangzhou.aliyuncs.com，不含协议与 bucket 前缀。',
            'rules' => ['required', 'string', 'regex:/^oss-[a-z0-9-]+\\.aliyuncs\\.com$/'],
        ],

        'scheme' => [
            'type' => 'select',
            'title' => '访问协议',
            'value' => 'https',
            'required' => true,
            'options' => [
                'https' => 'HTTPS（默认）',
                'http' => 'HTTP',
            ],
            'description' => '与 OSS 通信及外链使用的协议。',
            'rules' => ['required', 'string', 'in:https,http'],
        ],

        'acl' => [
            'type' => 'select',
            'title' => '对象读权限',
            'value' => 'none',
            'required' => true,
            'options' => [
                'none' => '默认（继承桶权限）',
                'public-read' => '公有读（上传带 x-oss-object-acl: public-read）',
            ],
            'description' => '选公有读时上传带 x-oss-object-acl 头且参与签名。',
            'rules' => ['required', 'string', 'in:none,public-read'],
        ],

        'public_domain' => [
            'type' => 'url',
            'title' => '自定义外链域名',
            'value' => '',
            'required' => false,
            'description' => '自定义域名/CDN；留空使用 {scheme}://{bucket}.{endpoint}。',
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
