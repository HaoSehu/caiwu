<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * 实例开通租约被其他执行者占用（FUL-1）。
 *
 * 它是一次「没提交」而不是一次「提交失败」：另一条路径（支付后同步履约、失败补偿命令、
 * 管理员手动重试、上游回调）正在开通同一实例，本次必须原样退出，不得改服务状态、
 * 不得重复 checkout。调用方要据此区分「已提交」与「已跳过」，不能把跳过当成功回报。
 */
class ProvisionLeaseBusyException extends BusinessException
{
    public const MESSAGE = '该实例正在由其他任务开通，本次未提交上游，请稍后重试';

    /**
     * 业务码 40901：409 区间是「资源状态冲突」，与 422 区间（参数/业务校验不通过）语义不同。
     * 不复用通用的 40900——那是并发写冲突的通用码，被调用方用作乐观锁判据，
     * 租约占用需要独立可识别码，避免被通用冲突处理逻辑误判。
     */
    public function __construct(string $message = self::MESSAGE, int $errorCode = 40901, int $httpStatus = 409, ?array $data = null)
    {
        parent::__construct($message, $errorCode, $httpStatus, $data);
    }
}
