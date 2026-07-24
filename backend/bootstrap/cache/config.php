<?php return array (
  'broadcasting' => 
  array (
    'default' => 'null',
    'connections' => 
    array (
      'reverb' => 
      array (
        'driver' => 'reverb',
        'key' => NULL,
        'secret' => NULL,
        'app_id' => NULL,
        'options' => 
        array (
          'host' => NULL,
          'port' => 443,
          'scheme' => 'https',
          'useTLS' => true,
        ),
        'client_options' => 
        array (
        ),
      ),
      'pusher' => 
      array (
        'driver' => 'pusher',
        'key' => NULL,
        'secret' => NULL,
        'app_id' => NULL,
        'options' => 
        array (
          'cluster' => NULL,
          'host' => 'api-mt1.pusher.com',
          'port' => 443,
          'scheme' => 'https',
          'encrypted' => true,
          'useTLS' => true,
        ),
        'client_options' => 
        array (
        ),
      ),
      'ably' => 
      array (
        'driver' => 'ably',
        'key' => NULL,
      ),
      'log' => 
      array (
        'driver' => 'log',
      ),
      'null' => 
      array (
        'driver' => 'null',
      ),
    ),
  ),
  'services' => 
  array (
    'postmark' => 
    array (
      'token' => NULL,
    ),
    'resend' => 
    array (
      'key' => NULL,
    ),
    'ses' => 
    array (
      'key' => NULL,
      'secret' => NULL,
      'region' => 'us-east-1',
    ),
    'slack' => 
    array (
      'notifications' => 
      array (
        'bot_user_oauth_token' => NULL,
        'channel' => NULL,
      ),
    ),
  ),
  'view' => 
  array (
    'paths' => 
    array (
      0 => '/www/wwwroot/backend/resources/views',
    ),
    'compiled' => '/www/wwwroot/backend/storage/framework/views',
  ),
  'hashing' => 
  array (
    'driver' => 'bcrypt',
    'bcrypt' => 
    array (
      'rounds' => 12,
      'verify' => true,
      'limit' => NULL,
    ),
    'argon' => 
    array (
      'memory' => 65536,
      'threads' => 1,
      'time' => 4,
      'verify' => true,
    ),
    'rehash_on_login' => true,
  ),
  'concurrency' => 
  array (
    'default' => 'process',
  ),
  'account_migration' => 
  array (
    'source_connection' => 'mysql',
    'target_connection' => 'mysql',
  ),
  'alipay' => 
  array (
    'app_id' => '',
    'private_key' => '',
    'alipay_public_key' => '',
    'notify_url' => '',
    'gateway' => 'https://openapi.alipay.com/gateway.do',
    'sign_type' => 'RSA2',
    'charset' => 'utf-8',
    'timeout' => '30m',
    'ssl_verify' => true,
    'ca_bundle' => '',
  ),
  'app' => 
  array (
    'name' => '创欧云',
    'env' => 'production',
    'debug' => false,
    'url' => 'https://api.ntec.asia',
    'frontend_url' => 'https://www.ntec.asia',
    'asset_url' => NULL,
    'timezone' => 'Asia/Shanghai',
    'locale' => 'zh_CN',
    'fallback_locale' => 'zh_CN',
    'faker_locale' => 'en_US',
    'cipher' => 'AES-256-CBC',
    'key' => 'base64:BdcRUnn4Lw2X57DdwYMAgrhh65DJmuW/q7l6WaPe0gc=',
    'previous_keys' => 
    array (
    ),
    'maintenance' => 
    array (
      'driver' => 'file',
      'store' => 'database',
    ),
    'providers' => 
    array (
      0 => 'Illuminate\\Auth\\AuthServiceProvider',
      1 => 'Illuminate\\Broadcasting\\BroadcastServiceProvider',
      2 => 'Illuminate\\Bus\\BusServiceProvider',
      3 => 'Illuminate\\Cache\\CacheServiceProvider',
      4 => 'Illuminate\\Foundation\\Providers\\ConsoleSupportServiceProvider',
      5 => 'Illuminate\\Concurrency\\ConcurrencyServiceProvider',
      6 => 'Illuminate\\Cookie\\CookieServiceProvider',
      7 => 'Illuminate\\Database\\DatabaseServiceProvider',
      8 => 'Illuminate\\Encryption\\EncryptionServiceProvider',
      9 => 'Illuminate\\Filesystem\\FilesystemServiceProvider',
      10 => 'Illuminate\\Foundation\\Providers\\FoundationServiceProvider',
      11 => 'Illuminate\\Hashing\\HashServiceProvider',
      12 => 'Illuminate\\Mail\\MailServiceProvider',
      13 => 'Illuminate\\Notifications\\NotificationServiceProvider',
      14 => 'Illuminate\\Pagination\\PaginationServiceProvider',
      15 => 'Illuminate\\Auth\\Passwords\\PasswordResetServiceProvider',
      16 => 'Illuminate\\Pipeline\\PipelineServiceProvider',
      17 => 'Illuminate\\Queue\\QueueServiceProvider',
      18 => 'Illuminate\\Redis\\RedisServiceProvider',
      19 => 'Illuminate\\Session\\SessionServiceProvider',
      20 => 'Illuminate\\Translation\\TranslationServiceProvider',
      21 => 'Illuminate\\Validation\\ValidationServiceProvider',
      22 => 'Illuminate\\View\\ViewServiceProvider',
      23 => 'App\\Providers\\AppServiceProvider',
      24 => 'App\\Providers\\PluginServiceProvider',
      25 => 'App\\Providers\\IntegrationServiceProvider',
      26 => 'App\\Providers\\UpstreamServiceProvider',
    ),
    'aliases' => 
    array (
      'App' => 'Illuminate\\Support\\Facades\\App',
      'Arr' => 'Illuminate\\Support\\Arr',
      'Artisan' => 'Illuminate\\Support\\Facades\\Artisan',
      'Auth' => 'Illuminate\\Support\\Facades\\Auth',
      'Benchmark' => 'Illuminate\\Support\\Benchmark',
      'Blade' => 'Illuminate\\Support\\Facades\\Blade',
      'Broadcast' => 'Illuminate\\Support\\Facades\\Broadcast',
      'Bus' => 'Illuminate\\Support\\Facades\\Bus',
      'Cache' => 'Illuminate\\Support\\Facades\\Cache',
      'Concurrency' => 'Illuminate\\Support\\Facades\\Concurrency',
      'Config' => 'Illuminate\\Support\\Facades\\Config',
      'Context' => 'Illuminate\\Support\\Facades\\Context',
      'Cookie' => 'Illuminate\\Support\\Facades\\Cookie',
      'Crypt' => 'Illuminate\\Support\\Facades\\Crypt',
      'Date' => 'Illuminate\\Support\\Facades\\Date',
      'DB' => 'Illuminate\\Support\\Facades\\DB',
      'Eloquent' => 'Illuminate\\Database\\Eloquent\\Model',
      'Event' => 'Illuminate\\Support\\Facades\\Event',
      'File' => 'Illuminate\\Support\\Facades\\File',
      'Gate' => 'Illuminate\\Support\\Facades\\Gate',
      'Hash' => 'Illuminate\\Support\\Facades\\Hash',
      'Http' => 'Illuminate\\Support\\Facades\\Http',
      'Js' => 'Illuminate\\Support\\Js',
      'Lang' => 'Illuminate\\Support\\Facades\\Lang',
      'Log' => 'Illuminate\\Support\\Facades\\Log',
      'Mail' => 'Illuminate\\Support\\Facades\\Mail',
      'Notification' => 'Illuminate\\Support\\Facades\\Notification',
      'Number' => 'Illuminate\\Support\\Number',
      'Password' => 'Illuminate\\Support\\Facades\\Password',
      'Process' => 'Illuminate\\Support\\Facades\\Process',
      'Queue' => 'Illuminate\\Support\\Facades\\Queue',
      'RateLimiter' => 'Illuminate\\Support\\Facades\\RateLimiter',
      'Redirect' => 'Illuminate\\Support\\Facades\\Redirect',
      'Request' => 'Illuminate\\Support\\Facades\\Request',
      'Response' => 'Illuminate\\Support\\Facades\\Response',
      'Route' => 'Illuminate\\Support\\Facades\\Route',
      'Schedule' => 'Illuminate\\Support\\Facades\\Schedule',
      'Schema' => 'Illuminate\\Support\\Facades\\Schema',
      'Session' => 'Illuminate\\Support\\Facades\\Session',
      'Storage' => 'Illuminate\\Support\\Facades\\Storage',
      'Str' => 'Illuminate\\Support\\Str',
      'Uri' => 'Illuminate\\Support\\Uri',
      'URL' => 'Illuminate\\Support\\Facades\\URL',
      'Validator' => 'Illuminate\\Support\\Facades\\Validator',
      'View' => 'Illuminate\\Support\\Facades\\View',
      'Vite' => 'Illuminate\\Support\\Facades\\Vite',
    ),
    'client_console_url' => 'https://console.ntec.asia',
    'admin_url' => 'https://admin.ntec.asia',
  ),
  'auth' => 
  array (
    'defaults' => 
    array (
      'guard' => 'web',
      'passwords' => 'users',
    ),
    'guards' => 
    array (
      'web' => 
      array (
        'driver' => 'session',
        'provider' => 'users',
      ),
      'admin' => 
      array (
        'driver' => 'session',
        'provider' => 'admin_users',
      ),
      'sanctum' => 
      array (
        'driver' => 'sanctum',
        'provider' => NULL,
      ),
    ),
    'providers' => 
    array (
      'users' => 
      array (
        'driver' => 'eloquent',
        'model' => 'App\\Models\\User',
      ),
      'admin_users' => 
      array (
        'driver' => 'eloquent',
        'model' => 'App\\Models\\AdminUser',
      ),
    ),
    'passwords' => 
    array (
      'users' => 
      array (
        'provider' => 'users',
        'table' => 'password_reset_tokens',
        'expire' => 60,
        'throttle' => 60,
      ),
    ),
    'password_timeout' => 10800,
  ),
  'blackhole' => 
  array (
    'timeout' => 12,
    'cache_ttl_seconds' => 60,
    'user_agent' => 'mozilla/5.0 (compatible; msie 5.01; windows nt 5.0)',
    'ssl_verify' => true,
    'ca_bundle' => '',
    'upstreams' => 
    array (
      'ningbo' => 
      array (
        'base_url' => 'http://160.202.238.2:81',
        'blackhole_path' => '/api/blackhole.php',
        'whitelist_path' => '/api/gb.php',
      ),
      'shiyan' => 
      array (
        'base_url' => 'http://160.202.238.2:90',
        'blackhole_path' => '/blackhole/blackholeapi.php',
        'layer7_find_path' => '/use/find.php',
        'layer7_toggle_path' => '/use/request.php',
        'layer4_path' => '/through/through.php',
        'flow_path' => '/flow/flowapi.php',
      ),
      'hongkong' => 
      array (
        'api_url' => 'https://mianban.288cloud.com/ddos/api/',
      ),
      'public' => 
      array (
        'base_url' => 'https://blackhole.jdidc.cn',
        'us1_traffic_base_url' => 'https://do.yazzi.net/index/history',
      ),
    ),
  ),
  'cache' => 
  array (
    'default' => 'redis',
    'stores' => 
    array (
      'array' => 
      array (
        'driver' => 'array',
        'serialize' => false,
      ),
      'session' => 
      array (
        'driver' => 'session',
        'key' => '_cache',
      ),
      'database' => 
      array (
        'driver' => 'database',
        'table' => 'cache',
        'connection' => NULL,
      ),
      'file' => 
      array (
        'driver' => 'file',
        'path' => '/www/wwwroot/backend/storage/framework/cache/data',
      ),
      'memcached' => 
      array (
        'driver' => 'memcached',
        'persistent_id' => NULL,
        'sasl' => 
        array (
          0 => NULL,
          1 => NULL,
        ),
        'options' => 
        array (
        ),
        'servers' => 
        array (
          0 => 
          array (
            'host' => '127.0.0.1',
            'port' => 11211,
            'weight' => 100,
          ),
        ),
      ),
      'redis' => 
      array (
        'driver' => 'redis',
        'connection' => 'cache',
        'lock_connection' => 'default',
      ),
      'dynamodb' => 
      array (
        'driver' => 'dynamodb',
        'key' => NULL,
        'secret' => NULL,
        'region' => 'us-east-1',
        'table' => 'cache',
        'endpoint' => NULL,
      ),
      'octane' => 
      array (
        'driver' => 'octane',
      ),
      'failover' => 
      array (
        'driver' => 'failover',
        'stores' => 
        array (
          0 => 'database',
          1 => 'array',
        ),
      ),
      'redis_volatile' => 
      array (
        'driver' => 'redis',
        'connection' => 'volatile',
        'lock_connection' => 'default',
      ),
    ),
    'prefix' => '',
  ),
  'catalog_migration' => 
  array (
    'source_connection' => 'mysql',
    'target_connection' => 'mysql',
  ),
  'content_system_migration' => 
  array (
    'source_connection' => 'mysql',
    'target_connection' => 'mysql',
  ),
  'cors' => 
  array (
    'paths' => 
    array (
      0 => 'api/*',
      1 => 'sanctum/csrf-cookie',
    ),
    'allowed_methods' => 
    array (
      0 => 'GET',
      1 => 'HEAD',
      2 => 'POST',
      3 => 'PUT',
      4 => 'PATCH',
      5 => 'DELETE',
      6 => 'OPTIONS',
    ),
    'allowed_origins' => 
    array (
      0 => 'https://www.ntec.asia',
      1 => 'https://console.ntec.asia',
      2 => 'https://admin.ntec.asia',
    ),
    'allowed_origins_patterns' => 
    array (
    ),
    'allowed_headers' => 
    array (
      0 => '*',
    ),
    'exposed_headers' => 
    array (
      0 => 'Content-Disposition',
      1 => 'Retry-After',
      2 => 'X-Request-Id',
    ),
    'max_age' => 0,
    'supports_credentials' => true,
  ),
  'database' => 
  array (
    'default' => 'mysql',
    'connections' => 
    array (
      'mysql' => 
      array (
        'driver' => 'mysql',
        'url' => NULL,
        'host' => '127.0.0.1',
        'port' => '3306',
        'database' => 'idc_demo',
        'username' => 'idc_demo',
        'password' => '2F8XmH7A8EXYw75D',
        'unix_socket' => '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'timezone' => '+08:00',
        'prefix' => '',
        'prefix_indexes' => true,
        'strict' => true,
        'engine' => NULL,
        'options' => 
        array (
        ),
      ),
    ),
    'migrations' => 
    array (
      'table' => 'migrations',
      'update_date_on_publish' => true,
    ),
    'redis' => 
    array (
      'client' => 'phpredis',
      'options' => 
      array (
        'cluster' => 'redis',
        'prefix' => '',
        'persistent' => false,
      ),
      'default' => 
      array (
        'host' => '127.0.0.1',
        'password' => '',
        'port' => '6379',
        'database' => '0',
        'timeout' => 2.0,
        'read_timeout' => 10.0,
      ),
      'cache' => 
      array (
        'host' => '127.0.0.1',
        'password' => '',
        'port' => '6379',
        'database' => '1',
        'timeout' => 2.0,
        'read_timeout' => 10.0,
      ),
      'volatile' => 
      array (
        'host' => '127.0.0.1',
        'password' => '',
        'port' => '6379',
        'database' => '2',
        'timeout' => 1.0,
        'read_timeout' => 5.0,
      ),
    ),
  ),
  'filesystems' => 
  array (
    'default' => 'local',
    'disks' => 
    array (
      'local' => 
      array (
        'driver' => 'local',
        'root' => '/www/wwwroot/backend/storage/app/private',
        'serve' => true,
        'throw' => false,
      ),
      'public' => 
      array (
        'driver' => 'local',
        'root' => '/www/wwwroot/backend/storage/app/public',
        'url' => 'https://api.ntec.asia/storage',
        'visibility' => 'public',
        'throw' => false,
      ),
      's3' => 
      array (
        'driver' => 's3',
        'key' => NULL,
        'secret' => NULL,
        'region' => NULL,
        'bucket' => NULL,
        'url' => NULL,
        'endpoint' => NULL,
        'use_path_style_endpoint' => false,
        'throw' => false,
        'report' => false,
      ),
    ),
    'links' => 
    array (
      '/www/wwwroot/backend/public/storage' => '/www/wwwroot/backend/storage/app/public',
    ),
  ),
  'health' => 
  array (
    'scheduler_max_age_seconds' => 180,
  ),
  'idc' => 
  array (
    'site_name' => '创欧云',
    'order_auto_cancel_minutes' => 30,
    'invoice_overdue_grace_days' => 3,
    'service_terminate_days' => 30,
    'auto_renew_days_before' => 3,
    'billing_cycle_days' => 
    array (
      'monthly' => 30,
      'quarterly' => 90,
      'semi' => 180,
      'yearly' => 365,
      'biennial' => 730,
      'triennial' => 1095,
    ),
    'payment_gateways' => 
    array (
      'balance' => '余额支付',
      'alipay' => '支付宝',
      'wechat' => '微信支付',
    ),
    'verification' => 
    array (
      'api' => '',
      'key' => '',
      'biz_code' => 'FACE',
      'api_endpoint' => 'https://idc.stay33.cn/realname/certapi.php',
      'ssl_verify' => true,
      'ca_bundle' => '',
      'free_attempts' => 3,
      'retry_fee' => 2.0,
    ),
    'hosting_panel_api' => 
    array (
      'user_agent' => 'mozilla/5.0 (compatible; msie 5.01; windows nt 5.0)',
      'ssl_verify' => true,
      'ca_bundle' => '',
      'allowed_hosts' => '',
      'jwt_cache_store' => 'redis',
      'dns_resolver_timeout' => 3,
      'connect_timeout' => 15,
      'timeout' => 900,
    ),
    'sms' => 
    array (
      'api_endpoint' => 'https://dypnsapi.aliyuncs.com/',
      'ssl_verify' => true,
      'ca_bundle' => '',
    ),
    'geetest' => 
    array (
      'enabled' => false,
      'captcha_id' => '',
      'captcha_key' => '',
      'ssl_verify' => true,
      'ca_bundle' => '',
    ),
    'vnc_relay' => 
    array (
      'host' => '127.0.0.1',
      'port' => 8100,
      'path' => '/ws/vnc',
      'ssl_verify' => true,
      'ca_bundle' => '',
      'connect_timeout' => 10,
    ),
    'frontend' => 
    array (
      'dist_path' => '/www/wwwroot/backend/../frontend-client/dist',
    ),
    'schedule_runtime' => 
    array (
      'mutex' => 
      array (
        'enabled' => true,
        'degraded' => false,
        'mode' => 'without_overlapping',
        'reason' => '',
        'cache_store' => 'redis',
        'os_family' => 'Linux',
      ),
      'automation_config' => 
      array (
        'status' => 'loaded',
        'fallback_reason' => '',
      ),
    ),
  ),
  'identity_migration' => 
  array (
    'source_connection' => 'mysql',
    'target_connection' => 'mysql',
  ),
  'integrations' => 
  array (
    'payments' => 
    array (
      'default' => 'alipay',
      'drivers' => 
      array (
        'alipay' => 
        array (
          'name' => '支付宝当面付',
          'provider' => 'alipay',
        ),
        'yipay' => 
        array (
          'name' => '易支付',
          'provider' => 'yipay',
        ),
      ),
    ),
    'identity' => 
    array (
      'default' => 'stay33',
      'drivers' => 
      array (
        'stay33' => 
        array (
          'name' => 'Stay33 实名认证',
        ),
      ),
    ),
    'sms' => 
    array (
      'default' => 'aliyun',
      'drivers' => 
      array (
        'aliyun' => 
        array (
          'name' => '阿里云短信',
        ),
      ),
    ),
    'mail' => 
    array (
      'default' => 'smtp',
      'drivers' => 
      array (
        'smtp' => 
        array (
          'name' => 'Single SMTP',
        ),
        'multi_smtp_round_robin' => 
        array (
          'name' => 'Multi SMTP Round Robin',
        ),
      ),
    ),
    'upstream' => 
    array (
      'default' => '',
      'preserve_provider_keys' => 
      array (
        0 => 'zjmf_finance_api',
        1 => 'hosting_panel_api',
      ),
    ),
  ),
  'log_archive' => 
  array (
    'retention_days' => 30,
    'file_retention_days' => 180,
    'archive_root' => '/www/wwwroot/backend/storage/app/private/log-archives',
    'report_root' => '/www/wwwroot/backend/storage/logs/log-archive',
    'pt_archiver_binary' => '/usr/bin/pt-archiver',
    'pt_archiver_defaults_file' => '/etc/caiwu/pt-archiver.cnf',
    'concurrency' => 2,
    'batch_size' => 1000,
    'sleep_seconds' => 1,
    'tables' => 
    array (
      'operation_logs' => 'API/后台操作及管理员登录日志',
      'activity_logs' => '系统与业务活动日志',
      'message_logs' => '短信/邮件统一消息日志',
      'automation_logs' => '自动化任务业务日志',
      'schedule_run_logs' => 'Laravel 调度运行日志',
      'schedule_task_runs' => '平台自动任务运行日志',
      'integration_plugin_runtime_logs' => '插件运行日志',
      'gateway_logs' => '支付网关交互日志',
    ),
    'excluded_tables' => 
    array (
      0 => 'archive_audit_logs',
      1 => 'account_transactions',
      2 => 'payments',
      3 => 'payment_callbacks',
      4 => 'invoices',
      5 => 'invoice_items',
      6 => 'failed_jobs',
    ),
  ),
  'logging' => 
  array (
    'default' => 'daily',
    'deprecations' => 
    array (
      'channel' => 'null',
      'trace' => false,
    ),
    'channels' => 
    array (
      'stack' => 
      array (
        'driver' => 'stack',
        'channels' => 
        array (
          0 => 'daily',
        ),
        'ignore_exceptions' => false,
      ),
      'single' => 
      array (
        'driver' => 'single',
        'path' => '/www/wwwroot/backend/storage/logs/laravel.log',
        'level' => 'info',
        'replace_placeholders' => true,
      ),
      'daily' => 
      array (
        'driver' => 'daily',
        'path' => '/www/wwwroot/backend/storage/logs/laravel.log',
        'level' => 'info',
        'days' => 14,
        'replace_placeholders' => true,
      ),
      'slack' => 
      array (
        'driver' => 'slack',
        'url' => NULL,
        'username' => 'Laravel Log',
        'emoji' => ':boom:',
        'level' => 'critical',
        'replace_placeholders' => true,
      ),
      'papertrail' => 
      array (
        'driver' => 'monolog',
        'level' => 'debug',
        'handler' => 'Monolog\\Handler\\SyslogUdpHandler',
        'handler_with' => 
        array (
          'host' => NULL,
          'port' => NULL,
          'connectionString' => 'tls://:',
        ),
        'processors' => 
        array (
          0 => 'Monolog\\Processor\\PsrLogMessageProcessor',
        ),
      ),
      'stderr' => 
      array (
        'driver' => 'monolog',
        'level' => 'info',
        'handler' => 'Monolog\\Handler\\StreamHandler',
        'handler_with' => 
        array (
          'stream' => 'php://stderr',
        ),
        'processors' => 
        array (
          0 => 'Monolog\\Processor\\PsrLogMessageProcessor',
        ),
      ),
      'syslog' => 
      array (
        'driver' => 'syslog',
        'level' => 'info',
        'facility' => 8,
        'replace_placeholders' => true,
      ),
      'errorlog' => 
      array (
        'driver' => 'errorlog',
        'level' => 'info',
        'replace_placeholders' => true,
      ),
      'null' => 
      array (
        'driver' => 'monolog',
        'handler' => 'Monolog\\Handler\\NullHandler',
      ),
      'emergency' => 
      array (
        'path' => '/www/wwwroot/backend/storage/logs/laravel.log',
      ),
      'sentry' => 
      array (
        'driver' => 'sentry',
      ),
      'sentry_logs' => 
      array (
        'driver' => 'sentry_logs',
        'level' => 'debug',
      ),
    ),
  ),
  'mail' => 
  array (
    'default' => 'smtp',
    'mailers' => 
    array (
      'smtp' => 
      array (
        'transport' => 'smtp',
        'host' => '127.0.0.1',
        'port' => 587,
        'encryption' => 'tls',
        'username' => NULL,
        'password' => NULL,
        'timeout' => NULL,
        'local_domain' => NULL,
      ),
      'ses' => 
      array (
        'transport' => 'ses',
      ),
      'postmark' => 
      array (
        'transport' => 'postmark',
      ),
      'resend' => 
      array (
        'transport' => 'resend',
      ),
      'sendmail' => 
      array (
        'transport' => 'sendmail',
        'path' => '/usr/sbin/sendmail -bs -i',
      ),
      'log' => 
      array (
        'transport' => 'log',
        'channel' => NULL,
      ),
      'array' => 
      array (
        'transport' => 'array',
      ),
      'failover' => 
      array (
        'transport' => 'failover',
        'mailers' => 
        array (
          0 => 'smtp',
          1 => 'log',
        ),
        'retry_after' => 60,
      ),
      'roundrobin' => 
      array (
        'transport' => 'roundrobin',
        'mailers' => 
        array (
          0 => 'ses',
          1 => 'postmark',
        ),
        'retry_after' => 60,
      ),
    ),
    'from' => 
    array (
      'address' => 'hello@example.com',
      'name' => '创欧云',
    ),
    'markdown' => 
    array (
      'theme' => 'default',
      'paths' => 
      array (
        0 => '/www/wwwroot/backend/resources/views/vendor/mail',
      ),
      'extensions' => 
      array (
      ),
    ),
  ),
  'queue' => 
  array (
    'default' => 'database',
    'connections' => 
    array (
      'sync' => 
      array (
        'driver' => 'sync',
      ),
      'database' => 
      array (
        'driver' => 'database',
        'connection' => NULL,
        'table' => 'jobs',
        'queue' => 'default',
        'retry_after' => 3900,
        'after_commit' => false,
      ),
      'beanstalkd' => 
      array (
        'driver' => 'beanstalkd',
        'host' => 'localhost',
        'queue' => 'default',
        'retry_after' => 90,
        'block_for' => 0,
        'after_commit' => false,
      ),
      'sqs' => 
      array (
        'driver' => 'sqs',
        'key' => NULL,
        'secret' => NULL,
        'prefix' => 'https://sqs.us-east-1.amazonaws.com/your-account-id',
        'queue' => 'default',
        'suffix' => NULL,
        'region' => 'us-east-1',
        'after_commit' => false,
      ),
      'redis' => 
      array (
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => 'default',
        'retry_after' => 90,
        'block_for' => NULL,
        'after_commit' => false,
      ),
      'deferred' => 
      array (
        'driver' => 'deferred',
      ),
      'failover' => 
      array (
        'driver' => 'failover',
        'connections' => 
        array (
          0 => 'database',
          1 => 'deferred',
        ),
      ),
    ),
    'batching' => 
    array (
      'database' => 'mysql',
      'table' => 'job_batches',
    ),
    'failed' => 
    array (
      'driver' => 'database-uuids',
      'database' => 'mysql',
      'table' => 'failed_jobs',
    ),
    'caiwu_worker_queues' => 'provision,referral,notification,coupon,default',
    'caiwu_worker_timeout' => 1200,
    'caiwu_worker_max_timeout' => 3600,
    'caiwu_worker_tries' => 3,
    'caiwu_worker_drain_lock_ttl' => 3960,
  ),
  'sanctum' => 
  array (
    'stateful' => 
    array (
      0 => 'api.ntec.asia',
      1 => 'www.ntec.asia',
      2 => 'console.ntec.asia',
      3 => 'admin.ntec.asia',
    ),
    'guard' => 
    array (
      0 => 'web',
    ),
    'expiration' => 1440,
    'token_prefix' => '',
    'middleware' => 
    array (
      'authenticate_session' => 'Laravel\\Sanctum\\Http\\Middleware\\AuthenticateSession',
      'encrypt_cookies' => 'Illuminate\\Cookie\\Middleware\\EncryptCookies',
      'validate_csrf_token' => 'Illuminate\\Foundation\\Http\\Middleware\\ValidateCsrfToken',
    ),
    'idle_timeout' => 10800,
  ),
  'schedule_hooks' => 
  array (
    'listeners' => 
    array (
      'before_cron' => 
      array (
      ),
      'after_cron' => 
      array (
      ),
      'before_daily_cron' => 
      array (
      ),
      'after_daily_cron' => 
      array (
      ),
      'after_five_minute_cron' => 
      array (
      ),
      'after_half_hour_minute_cron' => 
      array (
      ),
      'task.before' => 
      array (
      ),
      'task.after' => 
      array (
      ),
      'task.failed' => 
      array (
      ),
      'tick.every_minute' => 
      array (
      ),
      'tick.every_five_minutes' => 
      array (
      ),
      'tick.hourly' => 
      array (
      ),
      'tick.daily' => 
      array (
      ),
    ),
  ),
  'sentry' => 
  array (
    'dsn' => NULL,
    'release' => NULL,
    'environment' => NULL,
    'org_id' => NULL,
    'sample_rate' => 1.0,
    'traces_sample_rate' => NULL,
    'profiles_sample_rate' => NULL,
    'strict_trace_continuation' => false,
    'enable_logs' => false,
    'log_flush_threshold' => NULL,
    'logs_channel_level' => 'debug',
    'send_default_pii' => false,
    'ignore_transactions' => 
    array (
      0 => '/up',
    ),
    'breadcrumbs' => 
    array (
      'logs' => true,
      'cache' => true,
      'livewire' => true,
      'sql_queries' => true,
      'sql_bindings' => false,
      'queue_info' => true,
      'command_info' => true,
      'http_client_requests' => true,
      'notifications' => true,
    ),
    'tracing' => 
    array (
      'queue_job_transactions' => true,
      'queue_jobs' => true,
      'sql_queries' => true,
      'sql_bindings' => false,
      'sql_origin' => true,
      'sql_origin_threshold_ms' => 100,
      'views' => true,
      'livewire' => true,
      'http_client_requests' => true,
      'cache' => true,
      'redis_commands' => false,
      'redis_origin' => true,
      'notifications' => true,
      'missing_routes' => false,
      'continue_after_response' => true,
      'default_integrations' => true,
    ),
  ),
  'service_migration' => 
  array (
    'source_connection' => 'mysql',
    'target_connection' => 'mysql',
    'legacy_db_database' => '',
    'legacy_table_prefix' => '',
  ),
  'session' => 
  array (
    'driver' => 'file',
    'lifetime' => 120,
    'expire_on_close' => false,
    'encrypt' => false,
    'files' => '/www/wwwroot/backend/storage/framework/sessions',
    'connection' => NULL,
    'table' => 'sessions',
    'store' => NULL,
    'lottery' => 
    array (
      0 => 2,
      1 => 100,
    ),
    'cookie' => 'laravel_session',
    'path' => '/',
    'domain' => NULL,
    'secure' => true,
    'http_only' => true,
    'same_site' => 'lax',
    'partitioned' => false,
  ),
  'trade_migration' => 
  array (
    'source_connection' => 'mysql',
    'target_connection' => 'mysql',
  ),
  'tinker' => 
  array (
    'commands' => 
    array (
    ),
    'alias' => 
    array (
    ),
    'dont_alias' => 
    array (
      0 => 'App\\Nova',
    ),
    'trust_project' => 'always',
  ),
);
