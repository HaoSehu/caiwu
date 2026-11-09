<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FinanceOperation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * 财务幂等记录清理口径：ack_pending 是「资金已提交但终态写入失败」的待人工核对行，
 * 不能被 TTL 清理静默删除，否则人工核对线索丢失、同键重放可能二次执行资金操作。
 */
class PruneFinanceOperationsCommandTest extends TestCase
{
    use DatabaseTransactions;

    public function test_prune_keeps_ack_pending_rows_and_deletes_expired_terminal_rows(): void
    {
        $old = Carbon::now()->subDays(31);
        $recent = Carbon::now()->subDay();

        $expiredSucceeded = $this->makeOperation(FinanceOperation::STATUS_SUCCEEDED, 'prune-succeeded', $old);
        $expiredFailed = $this->makeOperation(FinanceOperation::STATUS_FAILED, 'prune-failed', $old);
        $expiredAckPending = $this->makeOperation(FinanceOperation::STATUS_ACK_PENDING, 'prune-ack-pending', $old);
        $recentSucceeded = $this->makeOperation(FinanceOperation::STATUS_SUCCEEDED, 'prune-recent', $recent);

        $this->artisan('finance:prune-operations', ['--execute' => true])->assertSuccessful();

        $this->assertNull(FinanceOperation::query()->find($expiredSucceeded->id), '过期 succeeded 应被清理');
        $this->assertNull(FinanceOperation::query()->find($expiredFailed->id), '过期 failed 应被清理');
        $this->assertNotNull(
            FinanceOperation::query()->find($expiredAckPending->id),
            'ack_pending 必须保留到人工核对，不得按 TTL 删除'
        );
        $this->assertNotNull(FinanceOperation::query()->find($recentSucceeded->id), '未过期记录不得删除');
    }

    private function makeOperation(string $status, string $key, Carbon $createdAt): FinanceOperation
    {
        $operation = FinanceOperation::query()->create([
            'scope' => 'invoice_refund',
            'request_key' => $key.'-'.uniqid(),
            'request_hash' => str_repeat('a', 64),
            'actor_type' => 'admin',
            'actor_id' => 1,
            'status' => $status,
            'started_at' => $createdAt,
        ]);

        $operation->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->saveQuietly();

        return $operation;
    }
}
