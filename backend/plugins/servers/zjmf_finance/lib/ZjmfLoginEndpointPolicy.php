<?php

declare(strict_types=1);

namespace Caiwu\Plugins\Servers\ZjmfFinance\Lib;

use App\Services\Upstream\Contracts\ProvidesUpstreamLoginEndpoints;

/**
 * 声明 ZJMF 上游的原生登录端点，供共享传输层在发送前跳过会话 token 注入。
 */
final class ZjmfLoginEndpointPolicy implements ProvidesUpstreamLoginEndpoints
{
    /**
     * @return list<string>
     */
    public function loginEndpointUris(): array
    {
        return ['/zjmf_api_login'];
    }
}
