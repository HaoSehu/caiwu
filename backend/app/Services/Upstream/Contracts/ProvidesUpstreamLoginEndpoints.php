<?php

declare(strict_types=1);

namespace App\Services\Upstream\Contracts;

/**
 * 上游原生登录端点声明能力（软契约）。
 *
 * 实现类经容器 tag `upstream.login_endpoints` 汇总注册，共享传输层
 * 在发送请求前据此跳过会话 token 注入。
 */
interface ProvidesUpstreamLoginEndpoints
{
    /**
     * @return list<string>
     */
    public function loginEndpointUris(): array;
}
