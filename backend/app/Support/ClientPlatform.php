<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

/**
 * 客户端类型识别。
 *
 * 原生 App 只提供验证码登录，且无法内嵌网页行为验证（Flutter 端不加载 Geetest SDK），
 * 因此后端在发验证码环节需要知道请求是否来自 App。识别依据是 App 在 `ApiClient`
 * 里固定注入的 `X-Client-Platform: android`。
 *
 * 该请求头可被任意原生客户端伪造，所以它**不能**单独承担安全边界：发验证码接口
 * 仍受路由 throttle 与 MessageRateLimitService（channel+目标+IP）限流，登录/重置
 * 场景对未注册账号静默不发码。密码登录不读取该标识，网页端行为完全不变。
 */
class ClientPlatform
{
    public const HEADER = 'X-Client-Platform';

    public const APP = 'android';

    /**
     * 是否来自原生 App。
     *
     * 浏览器无法为跨域请求设置该请求头（除非服务端 CORS 显式放行，而 CORS 白名单里
     * 没有放行它），因此网页端即使伪造宽度也走不到这条分支。
     */
    public static function isApp(Request $request): bool
    {
        return strtolower(trim((string) $request->header(self::HEADER, ''))) === self::APP;
    }
}
