<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Support\PublicUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * web.php 的非 API 兜底路由。
 * 独立成控制器是 route:cache 的硬性要求：闭包路由无法序列化，会让生产路由缓存永远生成失败。
 */
class WebController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['message' => '创欧云 API']);
    }

    /**
     * API 域名下的注册路径统一跳转官网域名，避免在 API 域名直接暴露注册表单。
     * 官网地址未配置或就是当前域名时按 404 处理，防止形成重定向环。
     */
    public function registerRedirect(Request $request): RedirectResponse
    {
        $frontendUrl = PublicUrl::website();
        $currentRoot = rtrim($request->getSchemeAndHttpHost(), '/');

        if ($frontendUrl === '' || $frontendUrl === $currentRoot) {
            abort(404);
        }

        $target = PublicUrl::website('/client/register');
        $queryString = $request->getQueryString();

        if ($queryString) {
            $target .= '?'.$queryString;
        }

        return redirect()->away($target);
    }
}
