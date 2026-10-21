<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\FinanceCutoverInbox;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * 切换窗口回调收件箱。
 *
 * 从公开回调控制器下沉（P1-8）：控制器只负责协议应答，不承载「唯一键拼装 +
 * updateOrCreate 幂等 + 撞键降级」这类业务策略。该端点未认证、可被外部任意打，
 * 逻辑必须与协议层解耦，才能单独测试并发与故障路径。
 */
final class FinanceCutoverInboxService
{
    /**
     * 窗口未开启时返回 false；开启时保存报文并返回 true（调用方按网关约定 ack 成功）。
     */
    public function capture(string $gateway, Request $request): bool
    {
        if (! (bool) config('finance.cutover.inbox_enabled')) {
            return false;
        }

        $payload = $request->all();
        // 无原生通知 ID 的渠道用「商户单号:渠道单号:报文摘要」拼唯一键；
        // 同一回调在窗口内被渠道重试时 updateOrCreate 幂等覆盖，不产生重复行。
        $notificationId = (string) ($payload['out_trade_no'] ?? '')
            .':'.(string) ($payload['trade_no'] ?? '')
            .':'.substr(md5((string) $request->getContent()), 0, 16);

        $attributes = [
            'request_uri' => (string) $request->getRequestUri(),
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'headers' => collect($request->headers->all())
                ->only(['user-agent', 'content-type', 'x-request-id'])
                ->map(static fn ($values) => $values[0] ?? '')
                ->filter(static fn ($value) => (string) $value !== '')
                ->all(),
            'status' => FinanceCutoverInbox::STATUS_PENDING,
            'received_at' => now(),
            'replayed_at' => null,
            'error_msg' => null,
        ];

        $keys = ['channel' => $gateway, 'notification_id' => $notificationId];

        // updateOrCreate 存在并发竞态（两请求同时 INSERT 撞唯一键），
        // 公开端点不允许 500：撞键时降级为 update 重试一次。
        try {
            FinanceCutoverInbox::query()->updateOrCreate($keys, $attributes);
        } catch (QueryException $exception) {
            if ((int) ($exception->errorInfo[1] ?? 0) !== 1062) {
                throw $exception;
            }

            FinanceCutoverInbox::query()->where($keys)->update($attributes);
        }

        Log::warning('[切换窗口] 支付回调已入收件箱，待窗口解除后重放', [
            'gateway' => $gateway,
            'notification_id' => $notificationId,
            'out_trade_no' => (string) ($payload['out_trade_no'] ?? ''),
        ]);

        return true;
    }
}
