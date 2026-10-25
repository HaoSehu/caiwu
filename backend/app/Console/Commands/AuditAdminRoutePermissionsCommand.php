<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Http\Middleware\CheckPermission;
use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;

/**
 * admin 路由权限覆盖审计（B-03）。
 *
 * 项目没有 Policy/Gate，api/v2/admin 的鉴权完全依赖路由上逐个挂载的 CheckPermission
 * 中间件；漏挂不会报错，只会静默变成「只要登录即可访问」。本命令用 Laravel 路由集合
 * 做只读巡检：枚举 api/v2/admin/* 的每条路由，解析中间件链后确认是否含 CheckPermission，
 * 白名单之外存在未覆盖路由即返回失败退出码，供 CI/发布前检查兜底。
 *
 * 白名单是认证自服务端点（登录、登出、当前管理员信息、改密改资料、双因素绑定/解绑），
 * 它们只要求已登录，本就不该被权限点约束；用 method + uri 精确匹配，避免前缀模糊匹配
 * 把将来新增的同类路由一并「洗白」。
 */
class AuditAdminRoutePermissionsCommand extends Command
{
    /**
     * 受审计的 admin 路由前缀（route->uri() 不含前导斜杠）。
     */
    private const string ADMIN_URI_PREFIX = 'api/v2/admin';

    /**
     * 免权限白名单：method + uri 精确匹配。
     *
     * - POST api/v2/admin/login：账号密码登录（未登录态）
     * - POST api/v2/admin/login/mfa：双因素第二步校验（未登录态，凭 mfa_token）
     * - POST api/v2/admin/auth/logout：登出
     * - GET api/v2/admin/auth/info：当前管理员信息
     * - PUT api/v2/admin/auth/profile：修改本人资料
     * - PUT api/v2/admin/auth/password：修改本人密码
     * - POST api/v2/admin/auth/mfa/setup|enable|disable：本人双因素绑定/解绑
     *
     * @var array<int, string>
     */
    private const array WHITELIST = [
        'POST api/v2/admin/login',
        'POST api/v2/admin/login/mfa',
        'POST api/v2/admin/auth/logout',
        'GET api/v2/admin/auth/info',
        'PUT api/v2/admin/auth/profile',
        'PUT api/v2/admin/auth/password',
        'POST api/v2/admin/auth/mfa/setup',
        'POST api/v2/admin/auth/mfa/enable',
        'POST api/v2/admin/auth/mfa/disable',
    ];

    protected $signature = 'audit:admin-routes
        {--json : 以 JSON 输出结果}';

    protected $description = '审计 api/v2/admin 路由是否都挂了 CheckPermission（白名单之外漏挂即失败）';

    public function handle(Router $router): int
    {
        $permissionMiddleware = $this->permissionMiddlewareNames($router);

        $total = 0;
        $covered = 0;
        $whitelisted = 0;
        $uncovered = [];

        foreach ($router->getRoutes()->getRoutes() as $route) {
            if (! $this->isAdminRoute($route)) {
                continue;
            }

            $total++;

            if ($this->hasPermissionMiddleware($router, $route, $permissionMiddleware)) {
                $covered++;

                continue;
            }

            if ($this->isWhitelisted($route)) {
                $whitelisted++;

                continue;
            }

            $uncovered[] = [
                'method' => $this->primaryMethod($route),
                'uri' => $route->uri(),
                'action' => (string) $route->getActionName(),
            ];
        }

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode([
                'total' => $total,
                'covered' => $covered,
                'whitelisted' => $whitelisted,
                'uncovered' => $uncovered,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

            return $uncovered === [] ? self::SUCCESS : self::FAILURE;
        }

        if ($uncovered === []) {
            $this->info(sprintf(
                '审计通过：api/v2/admin 共 %d 条路由，已挂 CheckPermission %d 条，白名单 %d 条。',
                $total,
                $covered,
                $whitelisted,
            ));

            return self::SUCCESS;
        }

        $this->error(sprintf(
            '发现 %d 条白名单之外未挂 CheckPermission 的 admin 路由（共 %d 条，已覆盖 %d 条，白名单 %d 条）：',
            count($uncovered),
            $total,
            $covered,
            $whitelisted,
        ));

        foreach ($uncovered as $item) {
            $this->line(sprintf('- %s %s（%s）', $item['method'], $item['uri'], $item['action']));
        }

        return self::FAILURE;
    }

    /**
     * 收集能代表 CheckPermission 的中间件名：FQCN 本身，以及 bootstrap/app.php 里注册的短别名。
     *
     * @return array<int, string>
     */
    private function permissionMiddlewareNames(Router $router): array
    {
        $names = [CheckPermission::class];

        foreach ($router->getMiddleware() as $alias => $class) {
            if ($class === CheckPermission::class) {
                $names[] = (string) $alias;
            }
        }

        return $names;
    }

    /**
     * 该路由是否属于 api/v2/admin 前缀。
     */
    private function isAdminRoute(Route $route): bool
    {
        return str_starts_with($route->uri(), self::ADMIN_URI_PREFIX.'/');
    }

    /**
     * 中间件链是否含 CheckPermission。
     *
     * 两轮匹配：先看路由上原始声明的中间件（可能写的是短别名 permission），再看
     * gatherRouteMiddleware() 解析后的结果（别名与中间件组都展开成 FQCN）。两轮都覆盖，
     * 这样无论路由定义时用别名还是 FQCN、或把 CheckPermission 塞进中间件组，都能识别。
     *
     * @param  array<int, string>  $permissionMiddleware
     */
    private function hasPermissionMiddleware(Router $router, Route $route, array $permissionMiddleware): bool
    {
        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && $this->isPermissionMiddleware($middleware, $permissionMiddleware)) {
                return true;
            }
        }

        foreach ($router->gatherRouteMiddleware($route) as $middleware) {
            if (is_string($middleware) && $this->isPermissionMiddleware($middleware, $permissionMiddleware)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 单个中间件声明是否命中 CheckPermission；去掉 `:参数` 后缀后比较（如 permission:user.update）。
     *
     * @param  array<int, string>  $permissionMiddleware
     */
    private function isPermissionMiddleware(string $middleware, array $permissionMiddleware): bool
    {
        return in_array(explode(':', $middleware, 2)[0], $permissionMiddleware, true);
    }

    /**
     * 白名单按 method + uri 精确匹配，一次命中即可（GET 路由会带 HEAD）。
     */
    private function isWhitelisted(Route $route): bool
    {
        foreach ($this->methods($route) as $method) {
            if (in_array($method.' '.$route->uri(), self::WHITELIST, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 报告用主方法：跳过 HEAD/OPTIONS 这类自动附带的语义重复项。
     */
    private function primaryMethod(Route $route): string
    {
        foreach ($this->methods($route) as $method) {
            if ($method !== 'HEAD' && $method !== 'OPTIONS') {
                return $method;
            }
        }

        return 'ANY';
    }

    /**
     * @return array<int, string>
     */
    private function methods(Route $route): array
    {
        return array_values(array_map(
            static fn (mixed $method): string => strtoupper((string) $method),
            (array) $route->methods(),
        ));
    }
}
