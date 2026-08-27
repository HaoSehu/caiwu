<?php

declare(strict_types=1);

namespace App\Services\Integrations\Payments\Concerns;

/**
 * 支付网关插件的标准动作支持声明。
 *
 * 历史上系统侧适配器靠捕获「Unsupported plugin action」异常文本来探测
 * 插件是否支持某个可选动作，依赖脆弱且把文案变成了协议。本 trait 让
 * 各网关插件集中声明自己的动作清单，供系统侧在执行前做结构化判断；
 * execute() 对未知动作返回的失败结构保持不变，运行时兜底语义不受影响。
 */
trait InteractsWithStandardPaymentActions
{
    /**
     * 本网关插件支持的标准动作清单（payments 域词表）。
     *
     * @return array<int, string>
     */
    abstract protected function supportedActions(): array;

    /**
     * 判断本网关插件是否声明支持给定动作。
     * 调用方（PluginPaymentGateway）据此决定可选动作是否短路，
     * 与 execute() 的 default 分支口径必须保持一致。
     */
    public function supportsAction(string $action): bool
    {
        return in_array(trim($action), $this->supportedActions(), true);
    }
}
