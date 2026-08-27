<?php

use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\NullHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Processor\PsrLogMessageProcessor;

$production = env('APP_ENV') === 'production';
$testing = env('APP_ENV') === 'testing';
$defaultLevel = $production ? 'info' : 'debug';

return [
    'default' => env('LOG_CHANNEL', $production ? 'daily' : 'stack'),

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => env('LOG_DEPRECATIONS_TRACE', false),
    ],

    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => explode(',', (string) env('LOG_STACK', $production ? 'daily' : 'single')),
            'ignore_exceptions' => false,
        ],

        // 部署约定 storage 为 775/www（组可写）；日志文件必须组可写，
        // 否则后续启动的 PHP-FPM worker 或 cron 无法写入当日文件。
        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', $defaultLevel),
            'replace_placeholders' => true,
            'permission' => 0664,
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', $defaultLevel),
            'days' => (int) env('LOG_DAILY_DAYS', 14),
            'replace_placeholders' => true,
            'permission' => 0664,
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', $defaultLevel),
            'handler' => StreamHandler::class,
            'handler_with' => [
                'stream' => 'php://stderr',
            ],
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => env('LOG_LEVEL', $defaultLevel),
            'facility' => env('LOG_SYSLOG_FACILITY', LOG_USER),
            'replace_placeholders' => true,
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => env('LOG_LEVEL', $defaultLevel),
            'replace_placeholders' => true,
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'api-json' => $testing ? [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ] : [
            'driver' => 'monolog',
            'handler' => RotatingFileHandler::class,
            'handler_with' => [
                'filename' => storage_path('logs/api-json.log'),
                'maxFiles' => (int) env('API_LOG_DAYS', 31),
                'filePermission' => 0664,
            ],
            'formatter' => JsonFormatter::class,
            'level' => 'info',
            'replace_placeholders' => true,
        ],

        // 测试环境写 NullHandler，避免单元测试向 gateway-json 日志文件灌入噪音
        'gateway-json' => $testing ? [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ] : [
            'driver' => 'monolog',
            'handler' => RotatingFileHandler::class,
            'handler_with' => [
                'filename' => storage_path('logs/gateway-json.log'),
                'maxFiles' => (int) env('GATEWAY_LOG_DAYS', 90),
                'filePermission' => 0664,
            ],
            'formatter' => JsonFormatter::class,
            'level' => 'info',
            'replace_placeholders' => true,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],
    ],
];
