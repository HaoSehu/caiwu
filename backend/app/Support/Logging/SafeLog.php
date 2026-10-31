<?php

declare(strict_types=1);

namespace App\Support\Logging;

use Illuminate\Support\Facades\Log;

/**
 * 旁路日志：日志写入失败不得拖垮主流程。
 *
 * 吞异常是刻意的——缓存或日志通道同时故障时，若日志写失败再抛出，
 * 会把「缓存降级」升级成「请求失败」。但吞异常不等于放弃可定位性：
 * 调用方必须让这条日志自带定位线索。具体线索随日志主题而定——
 * 与缓存/存储相关的日志要带 store 名与键族前缀（不含完整键值，给前缀或哈希）；
 * 上游请求类日志要带供应商与接口标识；耗时类日志要带阶段名。
 * 唯一要求是：故障发生后能凭 context 聚合到具体组件，而不是只剩一句业务失败。
 */
final class SafeLog
{
    /**
     * @param  array<string, mixed>  $context
     */
    public static function log(string $level, string $message, array $context = []): void
    {
        try {
            Log::log($level, $message, $context);
        } catch (\Throwable) {
        }
    }
}
