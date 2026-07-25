<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensureAccountTransactionsTable();
        $this->syncFromBalanceLogs();
        $this->syncFromReferralAccountLogs();
    }

    public function down(): void
    {
        // This repair migration rebuilds a shared read/write table. No automatic rollback.
    }

    private function ensureAccountTransactionsTable(): void
    {
        if (Schema::hasTable('account_transactions')) {
            return;
        }

        Schema::create('account_transactions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('account_type', 30);
            $table->string('event_type', 30);
            $table->decimal('change_amount', 12, 2)->default(0);
            $table->decimal('balance_after', 12, 2)->default(0);
            $table->string('source_type', 30)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('origin_type', 30)->nullable();
            $table->unsignedBigInteger('origin_id')->nullable();
            $table->string('remark', 255)->nullable();
            $table->string('operator', 50)->nullable();
            $table->string('trace_id', 64)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'account_type', 'created_at', 'id'], 'account_transactions_user_account_created_idx');
            $table->index(['user_id', 'event_type', 'created_at'], 'account_transactions_user_event_created_idx');
            $table->index(['origin_type', 'origin_id'], 'account_transactions_origin_idx');
            $table->index('trace_id', 'account_transactions_trace_id_idx');
            $table->index('created_at', 'account_transactions_created_at_idx');
        });
    }

    private function syncFromBalanceLogs(): void
    {
        if (! Schema::hasTable('account_transactions') || ! Schema::hasTable('balance_logs')) {
            return;
        }

        DB::statement(<<<'SQL'
            INSERT INTO account_transactions (
                user_id,
                account_type,
                event_type,
                change_amount,
                balance_after,
                source_type,
                source_id,
                origin_type,
                origin_id,
                remark,
                operator,
                trace_id,
                created_at,
                updated_at
            )
            SELECT
                bl.user_id,
                'cash',
                bl.event_type,
                bl.change_amount,
                bl.balance_after,
                CASE
                    WHEN bl.event_type = 'recharge' THEN 'payment'
                    WHEN bl.event_type IN ('consume', 'refund') THEN 'invoice'
                    WHEN bl.event_type = 'adjust' THEN 'manual_adjustment'
                    ELSE NULL
                END,
                bl.reference_id,
                'balance_log',
                bl.id,
                NULLIF(bl.remark, ''),
                NULL,
                NULL,
                bl.created_at,
                bl.created_at
            FROM balance_logs bl
            LEFT JOIN account_transactions at
                ON at.origin_type = 'balance_log'
               AND at.origin_id = bl.id
            WHERE at.id IS NULL
        SQL);
    }

    private function syncFromReferralAccountLogs(): void
    {
        if (! Schema::hasTable('account_transactions') || ! Schema::hasTable('referral_account_logs')) {
            return;
        }

        DB::statement(<<<'SQL'
            INSERT INTO account_transactions (
                user_id,
                account_type,
                event_type,
                change_amount,
                balance_after,
                source_type,
                source_id,
                origin_type,
                origin_id,
                remark,
                operator,
                trace_id,
                created_at,
                updated_at
            )
            SELECT
                ral.user_id,
                CASE
                    WHEN ral.event_type = 'reward_frozen' THEN 'referral_frozen'
                    WHEN ral.event_type = 'reward_released' THEN 'referral_available'
                    WHEN ral.event_type = 'withdraw_apply' THEN 'referral_pending_withdrawal'
                    WHEN ral.event_type = 'withdraw_approved' THEN 'referral_withdrawn'
                    WHEN ral.event_type = 'withdraw_rejected' THEN 'referral_available'
                    ELSE 'referral_available'
                END,
                ral.event_type,
                ral.change_amount,
                CASE
                    WHEN ral.event_type = 'reward_frozen' THEN ral.frozen_balance
                    WHEN ral.event_type = 'reward_released' THEN ral.available_balance
                    WHEN ral.event_type = 'withdraw_apply' THEN ral.pending_withdrawal_balance
                    WHEN ral.event_type = 'withdraw_approved' THEN ral.withdrawn_balance
                    WHEN ral.event_type = 'withdraw_rejected' THEN ral.available_balance
                    ELSE ral.available_balance
                END,
                ral.reference_type,
                ral.reference_id,
                'referral_account_log',
                ral.id,
                NULLIF(ral.remark, ''),
                NULLIF(ral.operator, ''),
                NULLIF(ral.trace_id, ''),
                ral.created_at,
                ral.created_at
            FROM referral_account_logs ral
            LEFT JOIN account_transactions at
                ON at.origin_type = 'referral_account_log'
               AND at.origin_id = ral.id
            WHERE at.id IS NULL
        SQL);
    }
};
