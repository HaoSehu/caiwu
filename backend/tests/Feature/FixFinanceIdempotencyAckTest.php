<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Models\FinanceOperation;
use App\Services\Finance\Core\Data\FinanceOperationResult;
use App\Services\Finance\Core\FinanceIdempotencyService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 审查修复回归（M-53）：资金已提交但成功终态写入失败时，幂等行不得停在
 * processing——僵死窗口一过同键重放会二次执行资金回调。
 *
 * 覆盖：finish 抛一次异常后重试成功且同键不二次执行；finish 持续失败时
 * 行落到 ack_pending（同键请求被 409 挡住而非重放）；僵死窗口下限不得低于 120s。
 *
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class FixFinanceIdempotencyAckTest extends TestCase
{
    use DatabaseTransactions;

    private int $succeededWriteFailures = 0;

    /** 让「成功终态写入」连续失败指定次数，模拟 DB 抖动；默认 3 次即重试全部耗尽，兜底标记放行。 */
    private function failSucceededWrites(int $times = 3): void
    {
        $this->succeededWriteFailures = $times;

        FinanceOperation::saving(function (FinanceOperation $operation): void {
            if ($this->succeededWriteFailures > 0
                && $operation->status === FinanceOperation::STATUS_SUCCEEDED
                && $operation->isDirty('status')) {
                $this->succeededWriteFailures--;

                throw new \RuntimeException('模拟成功终态写入失败');
            }
        });
    }

    protected function tearDown(): void
    {
        FinanceOperation::flushEventListeners();

        parent::tearDown();
    }

    public function test_finish_failure_is_retried_and_same_key_does_not_rerun_callback(): void
    {
        $service = app(FinanceIdempotencyService::class);
        $calls = 0;

        // 第一次终态写入抛异常、第二次放行：属正常重试路径。
        $this->failSucceededWrites(1);

        $callback = function () use (&$calls): array {
            $calls++;

            return ['entry_id' => 777];
        };

        $first = $service->execute('fix_idem_ack', 'admin', 1, 'idem-key-ack-0001', ['amount' => 10.0], $callback);
        $this->assertSame(1, $calls);
        $this->assertFalse($first->replayed);

        $operation = FinanceOperation::query()->where('request_key', 'idem-key-ack-0001')->firstOrFail();
        $this->assertSame(FinanceOperation::STATUS_SUCCEEDED, $operation->status);
        $this->assertSame(['entry_id' => 777], (array) $operation->result_payload);

        $second = $service->execute('fix_idem_ack', 'admin', 1, 'idem-key-ack-0001', ['amount' => 10.0], $callback);
        $this->assertTrue($second->replayed);
        $this->assertSame($first->payload, $second->payload);
        $this->assertSame(1, $calls, '同键重放不得二次执行资金回调');
    }

    public function test_finish_failure_exhaustion_marks_ack_pending_and_blocks_replay(): void
    {
        $service = app(FinanceIdempotencyService::class);
        $calls = 0;

        // 终态写入连续失败 3 次（重试全部耗尽）后走兜底标记。
        $this->failSucceededWrites(3);

        $callback = function () use (&$calls): array {
            $calls++;

            return ['entry_id' => 888];
        };

        $result = $service->execute('fix_idem_ack', 'admin', 1, 'idem-key-ack-0002', ['amount' => 20.0], $callback);
        $this->assertInstanceOf(FinanceOperationResult::class, $result);
        $this->assertSame(1, $calls);

        $row = DB::table('finance_operations')->where('request_key', 'idem-key-ack-0002')->first();
        $this->assertNotNull($row);
        $this->assertSame('ack_pending', (string) $row->status, '终态重试耗尽必须落成功待确认，不能停在 processing');

        $payload = json_decode((string) $row->result_payload, true);
        $this->assertSame(888, (int) ($payload['entry_id'] ?? 0), '成功结果必须保留，供人工核对与后续重放');
        $this->assertSame('failed', (string) ($payload['_ack']['status'] ?? ''), 'result_payload 必须带 ack 失败标记');

        // 再重试一次同键：资金回调不能被二次执行，且请求必须被明确拒绝。
        try {
            $service->execute('fix_idem_ack', 'admin', 1, 'idem-key-ack-0002', ['amount' => 20.0], $callback);
            $this->fail('终态确认失败的同键请求不得重放资金回调');
        } catch (BusinessException $error) {
            $this->assertSame(40900, $error->getErrorCode());
        }

        $this->assertSame(1, $calls, '资金回调只允许执行一次');
    }

    public function test_stale_window_floor_is_never_below_120_seconds(): void
    {
        config(['finance.idempotency.stale_processing_seconds' => 30]);

        $service = app(FinanceIdempotencyService::class);
        $method = new \ReflectionMethod($service, 'staleSeconds');
        $method->setAccessible(true);

        $this->assertSame(120, $method->invoke($service), '僵死窗口下限必须不低于 120s');
    }
}
