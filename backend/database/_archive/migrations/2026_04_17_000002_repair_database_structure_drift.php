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
        $this->ensureAdminUserRolesTable();
        $this->ensureUserAccountsTable();
        $this->syncUserAccountsFromUsers();

        $this->ensurePaymentCallbacksTable();
        $this->syncPaymentCallbacks();

        $this->ensureNotificationLogsTable();
        $this->syncNotificationLogs();

        $this->ensureInvoiceItemsTable();
        $this->repairOrdersTable();
        $this->syncInvoiceItems();

        $this->ensureUsersPhoneUnique();
        $this->makeProductsGroupIdNullable();
    }

    public function down(): void
    {
        // This is an online repair migration with data backfill. No automatic rollback.
    }

    private function ensureAdminUserRolesTable(): void
    {
        if (Schema::hasTable('admin_user_roles')) {
            return;
        }

        Schema::create('admin_user_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('admin_user_id');
            $table->unsignedBigInteger('role_id');

            $table->unique(['admin_user_id', 'role_id'], 'admin_user_roles_admin_role_unique');
            $table->index('role_id', 'admin_user_roles_role_id_idx');
        });
    }

    private function ensureUserAccountsTable(): void
    {
        if (Schema::hasTable('user_accounts')) {
            return;
        }

        Schema::create('user_accounts', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->primary();
            $table->decimal('cash_balance', 12, 2)->default(0);
            $table->decimal('credit_limit', 12, 2)->default(0);
            $table->decimal('referral_frozen_balance', 12, 2)->default(0);
            $table->decimal('referral_available_balance', 12, 2)->default(0);
            $table->decimal('referral_pending_withdrawal_balance', 12, 2)->default(0);
            $table->decimal('referral_withdrawn_balance', 12, 2)->default(0);
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();
        });
    }

    private function syncUserAccountsFromUsers(): void
    {
        if (! Schema::hasTable('user_accounts') || ! Schema::hasTable('users')) {
            return;
        }

        DB::statement(<<<'SQL'
            INSERT INTO user_accounts (
                user_id,
                cash_balance,
                credit_limit,
                referral_frozen_balance,
                referral_available_balance,
                referral_pending_withdrawal_balance,
                referral_withdrawn_balance,
                version,
                created_at,
                updated_at
            )
            SELECT
                u.id,
                COALESCE(u.balance, 0),
                COALESCE(u.credit_limit, 0),
                COALESCE(u.referral_frozen_amount, 0),
                COALESCE(u.referral_available_amount, 0),
                COALESCE(u.referral_withdrawing_amount, 0),
                COALESCE(u.referral_withdrawn_amount, 0),
                0,
                COALESCE(u.created_at, CURRENT_TIMESTAMP),
                CURRENT_TIMESTAMP
            FROM users u
            LEFT JOIN user_accounts ua ON ua.user_id = u.id
            WHERE ua.user_id IS NULL
        SQL);

        DB::statement(<<<'SQL'
            UPDATE user_accounts ua
            INNER JOIN users u ON u.id = ua.user_id
            SET
                ua.cash_balance = COALESCE(u.balance, ua.cash_balance),
                ua.credit_limit = COALESCE(u.credit_limit, ua.credit_limit),
                ua.referral_frozen_balance = COALESCE(u.referral_frozen_amount, ua.referral_frozen_balance),
                ua.referral_available_balance = COALESCE(u.referral_available_amount, ua.referral_available_balance),
                ua.referral_pending_withdrawal_balance = COALESCE(u.referral_withdrawing_amount, ua.referral_pending_withdrawal_balance),
                ua.referral_withdrawn_balance = COALESCE(u.referral_withdrawn_amount, ua.referral_withdrawn_balance),
                ua.updated_at = CURRENT_TIMESTAMP
        SQL);
    }

    private function ensurePaymentCallbacksTable(): void
    {
        if (Schema::hasTable('payment_callbacks')) {
            return;
        }

        Schema::create('payment_callbacks', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('payment_id');
            $table->string('callback_type', 20);
            $table->string('gateway_trade_no', 100)->nullable();
            $table->json('payload_json')->nullable();
            $table->tinyInteger('is_verified')->default(0);
            $table->timestamp('received_at')->nullable();
            $table->string('remark', 255)->nullable();
            $table->string('operator', 50)->nullable();
            $table->string('trace_id', 64)->nullable();
            $table->timestamps();

            $table->unique(['payment_id', 'callback_type'], 'payment_callbacks_payment_type_unique');
            $table->index(['is_verified', 'received_at'], 'payment_callbacks_verified_received_idx');
            $table->index('gateway_trade_no', 'payment_callbacks_gateway_trade_no_idx');
            $table->index('trace_id', 'payment_callbacks_trace_id_idx');
        });
    }

    private function syncPaymentCallbacks(): void
    {
        if (! Schema::hasTable('payment_callbacks') || ! Schema::hasTable('payments')) {
            return;
        }

        DB::table('payments')
            ->select(['id', 'trade_no', 'callback_raw', 'paid_at', 'created_at', 'updated_at'])
            ->orderBy('id')
            ->chunkById(100, function ($payments): void {
                foreach ($payments as $payment) {
                    $callbackRaw = $this->decodeJsonArray($payment->callback_raw ?? null);
                    if ($callbackRaw === []) {
                        continue;
                    }

                    DB::table('payment_callbacks')
                        ->where('payment_id', (int) $payment->id)
                        ->delete();

                    $rows = [[
                        'payment_id' => (int) $payment->id,
                        'callback_type' => 'payment',
                        'gateway_trade_no' => $this->nullableString($callbackRaw['trade_no'] ?? ($payment->trade_no ?? null)),
                        'payload_json' => $this->encodeJson($callbackRaw),
                        'is_verified' => $this->resolvePaymentCallbackVerified($callbackRaw),
                        'received_at' => $callbackRaw['send_pay_date'] ?? ($payment->paid_at ?? $payment->updated_at ?? now()),
                        'remark' => null,
                        'operator' => null,
                        'trace_id' => $this->nullableString($callbackRaw['trace_id'] ?? null),
                        'created_at' => $payment->created_at ?? now(),
                        'updated_at' => $payment->updated_at ?? now(),
                    ]];

                    $refundPayload = is_array($callbackRaw['refund'] ?? null) ? $callbackRaw['refund'] : [];
                    if ($refundPayload !== []) {
                        $rows[] = [
                            'payment_id' => (int) $payment->id,
                            'callback_type' => 'refund',
                            'gateway_trade_no' => $this->nullableString($refundPayload['trade_no'] ?? null),
                            'payload_json' => $this->encodeJson($refundPayload),
                            'is_verified' => 1,
                            'received_at' => $refundPayload['refunded_at'] ?? ($payment->updated_at ?? now()),
                            'remark' => null,
                            'operator' => null,
                            'trace_id' => $this->nullableString($refundPayload['trace_id'] ?? ($callbackRaw['trace_id'] ?? null)),
                            'created_at' => $payment->created_at ?? now(),
                            'updated_at' => $payment->updated_at ?? now(),
                        ];
                    }

                    DB::table('payment_callbacks')->insert($rows);
                }
            }, 'id');
    }

    private function ensureNotificationLogsTable(): void
    {
        if (Schema::hasTable('notification_logs')) {
            return;
        }

        Schema::create('notification_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('channel', 20);
            $table->string('recipient', 191);
            $table->string('template_code', 50)->nullable();
            $table->string('subject', 255)->nullable();
            $table->text('content');
            $table->json('params_json')->nullable();
            $table->string('provider', 50)->nullable();
            $table->string('request_id', 100)->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('error_msg')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('origin_type', 30)->nullable();
            $table->unsignedBigInteger('origin_id')->nullable();
            $table->timestamps();

            $table->unique(['origin_type', 'origin_id'], 'notification_logs_origin_unique');
            $table->index(['channel', 'created_at'], 'notification_logs_channel_created_at_idx');
            $table->index(['recipient', 'created_at'], 'notification_logs_recipient_created_at_idx');
            $table->index('request_id', 'notification_logs_request_id_idx');
        });
    }

    private function syncNotificationLogs(): void
    {
        if (! Schema::hasTable('notification_logs')) {
            return;
        }

        if (Schema::hasTable('sms_logs')) {
            DB::table('sms_logs')
                ->select(['id', 'phone', 'template_code', 'content', 'params', 'status', 'provider', 'request_id', 'error_msg', 'sent_at', 'created_at', 'updated_at'])
                ->orderBy('id')
                ->chunkById(100, function ($logs): void {
                    foreach ($logs as $log) {
                        DB::table('notification_logs')->updateOrInsert(
                            [
                                'origin_type' => 'sms_log',
                                'origin_id' => (int) $log->id,
                            ],
                            [
                                'channel' => 'sms',
                                'recipient' => trim((string) ($log->phone ?? '')),
                                'template_code' => $this->nullableString($log->template_code ?? null),
                                'subject' => null,
                                'content' => (string) ($log->content ?? ''),
                                'params_json' => $this->encodeJson($this->decodeJsonArray($log->params ?? null)),
                                'provider' => $this->nullableString($log->provider ?? null),
                                'request_id' => $this->nullableString($log->request_id ?? null),
                                'status' => trim((string) ($log->status ?? 'pending')) ?: 'pending',
                                'error_msg' => $this->nullableString($log->error_msg ?? null),
                                'sent_at' => $log->sent_at,
                                'created_at' => $log->created_at ?? now(),
                                'updated_at' => $log->updated_at ?? now(),
                            ]
                        );
                    }
                }, 'id');
        }

        if (Schema::hasTable('email_logs')) {
            DB::table('email_logs')
                ->select(['id', 'to_email', 'template_code', 'subject', 'content', 'status', 'error_msg', 'sent_at', 'created_at', 'updated_at'])
                ->orderBy('id')
                ->chunkById(100, function ($logs): void {
                    foreach ($logs as $log) {
                        DB::table('notification_logs')->updateOrInsert(
                            [
                                'origin_type' => 'email_log',
                                'origin_id' => (int) $log->id,
                            ],
                            [
                                'channel' => 'email',
                                'recipient' => trim((string) ($log->to_email ?? '')),
                                'template_code' => $this->nullableString($log->template_code ?? null),
                                'subject' => $this->nullableString($log->subject ?? null),
                                'content' => (string) ($log->content ?? ''),
                                'params_json' => null,
                                'provider' => null,
                                'request_id' => null,
                                'status' => trim((string) ($log->status ?? 'pending')) ?: 'pending',
                                'error_msg' => $this->nullableString($log->error_msg ?? null),
                                'sent_at' => $log->sent_at,
                                'created_at' => $log->created_at ?? now(),
                                'updated_at' => $log->updated_at ?? now(),
                            ]
                        );
                    }
                }, 'id');
        }
    }

    private function ensureInvoiceItemsTable(): void
    {
        if (Schema::hasTable('invoice_items')) {
            return;
        }

        Schema::create('invoice_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('invoice_id');
            $table->string('item_name', 200);
            $table->string('item_type', 30)->default('normal');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('line_amount', 12, 2)->default(0);
            $table->json('meta_json')->nullable();
            $table->timestamps();

            $table->index('invoice_id', 'invoice_items_invoice_id_index');
        });
    }

    private function repairOrdersTable(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'product_name_snapshot')) {
                $table->string('product_name_snapshot', 200)->nullable()->after('product_id');
            }

            if (! Schema::hasColumn('orders', 'product_type_snapshot')) {
                $table->string('product_type_snapshot', 50)->nullable()->after('product_name_snapshot');
            }

            if (! Schema::hasColumn('orders', 'quantity')) {
                $table->unsignedInteger('quantity')->default(1)->after('billing_cycle');
            }
        });

        DB::statement(<<<'SQL'
            UPDATE orders o
            LEFT JOIN products p ON p.id = o.product_id
            SET
                o.product_name_snapshot = COALESCE(NULLIF(o.product_name_snapshot, ''), NULLIF(p.name, '')),
                o.product_type_snapshot = COALESCE(NULLIF(o.product_type_snapshot, ''), NULLIF(p.product_type, '')),
                o.quantity = COALESCE(o.quantity, 1)
        SQL);
    }

    private function syncInvoiceItems(): void
    {
        if (! Schema::hasTable('invoice_items') || ! Schema::hasTable('invoices')) {
            return;
        }

        DB::statement(<<<'SQL'
            INSERT INTO invoice_items (
                invoice_id,
                item_name,
                item_type,
                quantity,
                unit_price,
                discount_amount,
                line_amount,
                meta_json,
                created_at,
                updated_at
            )
            SELECT
                i.id,
                COALESCE(NULLIF(o.product_name_snapshot, ''), '账单项目'),
                COALESCE(NULLIF(i.type, ''), 'normal'),
                GREATEST(COALESCE(o.quantity, 1), 1),
                CASE
                    WHEN GREATEST(COALESCE(o.quantity, 1), 1) > 0
                        THEN ROUND(COALESCE(o.amount, i.amount, 0) / GREATEST(COALESCE(o.quantity, 1), 1), 2)
                    ELSE COALESCE(o.amount, i.amount, 0)
                END,
                ROUND(COALESCE(o.discount, 0), 2),
                ROUND(COALESCE(i.amount, 0), 2),
                JSON_OBJECT(
                    'invoice_no', i.invoice_no,
                    'order_no', o.order_no,
                    'product_name', NULLIF(o.product_name_snapshot, ''),
                    'quantity', GREATEST(COALESCE(o.quantity, 1), 1)
                ),
                COALESCE(i.created_at, CURRENT_TIMESTAMP),
                COALESCE(i.updated_at, CURRENT_TIMESTAMP)
            FROM invoices i
            LEFT JOIN orders o ON o.id = i.order_id
            LEFT JOIN invoice_items ii ON ii.invoice_id = i.id
            WHERE ii.id IS NULL
        SQL);
    }

    private function ensureUsersPhoneUnique(): void
    {
        if (! Schema::hasTable('users') || Schema::hasIndex('users', 'users_phone_unique')) {
            return;
        }

        DB::statement("UPDATE users SET phone = NULLIF(TRIM(phone), '') WHERE phone IS NOT NULL");

        Schema::table('users', function (Blueprint $table): void {
            $table->unique('phone', 'users_phone_unique');
        });
    }

    private function makeProductsGroupIdNullable(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasColumn('products', 'product_group_id')) {
            return;
        }

        $column = DB::table('information_schema.columns')
            ->select('IS_NULLABLE')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'products')
            ->where('COLUMN_NAME', 'product_group_id')
            ->first();

        if (($column->IS_NULLABLE ?? null) === 'YES') {
            return;
        }

        DB::statement('ALTER TABLE products MODIFY product_group_id BIGINT UNSIGNED NULL');
    }

    private function decodeJsonArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function encodeJson(?array $value): ?string
    {
        if (! is_array($value) || $value === []) {
            return null;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    private function resolvePaymentCallbackVerified(array $callbackRaw): int
    {
        if (($callbackRaw['code'] ?? null) === '10000') {
            return 1;
        }

        if (trim((string) ($callbackRaw['trade_status'] ?? '')) === 'TRADE_SUCCESS') {
            return 1;
        }

        return 0;
    }
};
