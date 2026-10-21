<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Finance\MixPaymentService;
use Illuminate\Console\Command;

/**
 * 重放「预下单失败但预扣余额回收失败」的待回补记录（CMP-P1-2）。
 *
 * 混合支付是「先扣余额、再调网关」：调网关失败会立即同步回补，但回补本身
 * 也可能因 DB 抖动失败，此时预扣余额会滞留在账上，只能等 300 秒过期清理间接兜底。
 * 该命令由心跳任务定期执行，把 finance_reconciliation_diffs 里 open 的
 * mix_balance_pending_restore 记录逐条重放补齐。
 */
class ReplayMixRestoreCommand extends Command
{
    protected $signature = 'finance:replay-mix-restore
        {--limit=100 : 单次最多处理条数}
        {--json : 以 JSON 输出}';

    protected $description = '重放混合支付预扣余额的待回补记录（心跳兜底）';

    public function handle(MixPaymentService $mixPayments): int
    {
        $limit = max((int) $this->option('limit'), 1);
        $restored = $mixPayments->replayPendingMixRestores($limit);

        if ((bool) $this->option('json')) {
            $this->line(json_encode([
                'restored' => $restored,
                'limit' => $limit,
            ], JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->info(sprintf('混合支付待回补重放完成：实际回补 %d 条。', $restored));

        return self::SUCCESS;
    }
}
