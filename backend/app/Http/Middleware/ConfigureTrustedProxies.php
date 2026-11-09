<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 请求期声明可信反代（M-42）。
 *
 * 不能在 bootstrap/app.php 的 withMiddleware 回调里读 env()：该回调在容器
 * make(HttpKernel) 时执行，.env 尚未加载，env('TRUSTED_PROXIES') 恒为 null，
 * 任何部署都配不上可信代理。这里在 handle 期读 config，config:cache 后同样生效。
 */
class ConfigureTrustedProxies
{
    public function handle(Request $request, Closure $next): Response
    {
        // 空配置必须显式清空：可信代理是 Symfony Request 的静态状态，常驻进程
        // （队列 worker、测试同进程）里会从上一次请求残留，遗留名单等于信任任意 XFF。
        $request::setTrustedProxies(
            $this->trustedProxies(),
            Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT,
        );

        return $next($request);
    }

    /**
     * 支持 .env 逗号分隔串与 config 数组两种写法，只信任显式列出的地址。
     *
     * @return list<string>
     */
    private function trustedProxies(): array
    {
        $configured = config('app.trusted_proxies', '');
        $items = is_array($configured) ? $configured : explode(',', (string) $configured);

        return array_values(array_filter(array_map(
            static fn (mixed $ip): string => trim((string) $ip),
            $items,
        ), static fn (string $ip): bool => $ip !== ''));
    }
}
