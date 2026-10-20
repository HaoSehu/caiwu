<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\PaymentGatewayCode;
use App\Constants\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * 维护命令安全护栏（B5：V7-07 命令层覆盖缺口，BACKEND §9「维护命令不会删除 payments」）：
 * 1. db:prune-stale-records 默认 dry-run 不删除任何行；
 * 2. --execute 仅清理过期即无价值记录（personal_access_tokens / password_reset_tokens / failed_jobs）；
 * 3. payments 与 balance_logs 等财务审计链路不被维护命令触碰。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class MaintenanceCommandSafetyTest extends TestCase
{
    use DatabaseTransactions;

    public function test_prune_command_only_removes_stale_records_and_never_financial_rows(): void
    {
        $user = User::query()->create([
            'email' => 'prune-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'prune-tester',
            'total_sales_amount' => 0,
        ]);

        $invoice = Invoice::query()->create([
            'invoice_no' => 'INV-PRUNE-'.uniqid(),
            'user_id' => $user->id,
            'type' => 'new',
            'amount' => '100.00',
            'paid_amount' => '100.00',
            'status' => 1,
            'paid_at' => now(),
        ]);

        $payment = Payment::query()->create([
            'payment_no' => 'PY-PRUNE-'.uniqid(),
            'user_id' => $user->id,
            'invoice_id' => $invoice->id,
            'gateway' => PaymentGatewayCode::ALIPAY,
            'amount' => 100.00,
            'status' => PaymentStatus::SUCCESS,
            'paid_at' => now(),
        ]);

        $staleTokenId = DB::table('personal_access_tokens')->insertGetId([
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
            'name' => 'stale-'.uniqid(),
            'token' => hash('sha256', uniqid('stale', true)),
            'abilities' => json_encode(['*']),
            'expires_at' => now()->subDays(2),
            'last_used_at' => now()->subDays(2),
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ]);

        $staleJobId = DB::table('failed_jobs')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'stale failure',
            'failed_at' => now()->subDays(40),
        ]);

        $staleResetEmail = 'stale-reset-'.uniqid().'@example.test';
        DB::table('password_reset_tokens')->insert([
            'email' => $staleResetEmail,
            'token' => 'stale-token',
            'created_at' => now()->subHours(3),
        ]);

        $paymentsBefore = DB::table('payments')->count();
        $accountTransactionsBefore = DB::table('finance_ledger_entries')->count();

        // 默认 dry-run：只统计，不删除
        $this->artisan('db:prune-stale-records')->assertExitCode(0);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $staleTokenId]);
        $this->assertDatabaseHas('failed_jobs', ['id' => $staleJobId]);

        // execute：仅清理过期即无价值记录
        $this->artisan('db:prune-stale-records --execute')->assertExitCode(0);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $staleTokenId]);
        $this->assertDatabaseMissing('failed_jobs', ['id' => $staleJobId]);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $staleResetEmail]);

        // 财务审计链路不被触碰
        $this->assertDatabaseHas('payments', ['id' => $payment->id]);
        $this->assertSame($paymentsBefore, DB::table('payments')->count(), '维护命令不得删除 payments');
        $this->assertSame($accountTransactionsBefore, DB::table('finance_ledger_entries')->count(), '维护命令不得删除资金流水');
    }
}
