<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\FinanceCutoverInbox;
use App\Services\Finance\GatewayInvoicePaymentService;
use Illuminate\Console\Command;
use Throwable;

/**
 * 切换窗口收件箱重放：窗口解除后把 captureDuringCutover 存下的回调
 * 逐条送回 handleGatewayNotify 正常入账。
 *
 * 失败语义：单条失败保留 pending 并写 error_msg（可修数后重跑），
 * 不因个别脏报文中断整批；只有全批失败才返回非零退出码提醒人工介入。
 */
final class ReplayCutoverInboxCommand extends Command
{
    protected $signature = 'finance:replay-cutover-inbox
        {--limit=200 : 单次最多重放的条数}';

    protected $description = '重放切换窗口期收件箱中的支付回调（finance_cutover_inbox）';

    public function handle(GatewayInvoicePaymentService $paymentService): int
    {
        if ((bool) config('finance.cutover.inbox_enabled')) {
            $this->error('切换窗口仍在开启（FINANCE_CUTOVER_INBOX=true）；先解除窗口再重放，避免重放与新回调互相穿插。');

            return self::FAILURE;
        }

        $rows = FinanceCutoverInbox::query()
            ->where('status', FinanceCutoverInbox::STATUS_PENDING)
            ->orderBy('id')
            ->limit(max(1, (int) $this->option('limit')))
            ->get();

        if ($rows->isEmpty()) {
            $this->info('收件箱无待重放回调。');

            return self::SUCCESS;
        }

        $replayed = 0;
        $failed = 0;
        foreach ($rows as $row) {
            $payload = [];
            try {
                $payload = (array) json_decode((string) $row->payload, true, 512, JSON_THROW_ON_ERROR);
            } catch (Throwable $exception) {
                $row->forceFill([
                    'error_msg' => 'payload 不是合法 JSON：'.mb_substr($exception->getMessage(), 0, 200),
                ])->save();
                $failed++;

                continue;
            }

            try {
                $success = $paymentService->handleGatewayNotify((string) $row->channel, $payload);
            } catch (Throwable $exception) {
                $success = false;
                $row->forceFill([
                    'error_msg' => mb_substr($exception->getMessage(), 0, 500),
                ])->save();
                $failed++;

                continue;
            }

            if ($success) {
                $row->forceFill([
                    'status' => FinanceCutoverInbox::STATUS_REPLAYED,
                    'replayed_at' => now(),
                    'error_msg' => null,
                ])->save();
                $replayed++;

                continue;
            }

            // handleGatewayNotify 返回 false = 验签/商户/金额闸未过或支付单不匹配；
            // 保留 pending 与错误摘要，由人工核数后决定重跑或作废。
            $row->forceFill([
                'error_msg' => '入账未通过（验签/商户/金额/支付单状态不匹配），报文保留待人工核对',
            ])->save();
            $failed++;
        }

        $this->info("重放完成：成功 {$replayed}，失败 {$failed}（失败行保留 pending，可修复后重跑本命令）");

        return $replayed > 0 || $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
