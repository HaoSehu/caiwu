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
        Schema::table('services', function (Blueprint $table): void {
            if (Schema::hasColumn('services', 'order_id')) {
                $table->unsignedBigInteger('order_id')->nullable()->change();
            }
        });

        DB::transaction(function (): void {
            if (Schema::hasTable('services') && Schema::hasColumn('services', 'order_id')) {
                DB::table('services')->where('order_id', 0)->update(['order_id' => null]);
            }

            if (Schema::hasTable('payments')) {
                if (Schema::hasColumn('payments', 'order_id')) {
                    DB::table('payments')->where('order_id', 0)->update(['order_id' => null]);
                }

                if (Schema::hasColumn('payments', 'invoice_id')) {
                    DB::table('payments')->where('invoice_id', 0)->update(['invoice_id' => null]);
                }
            }

            if (Schema::hasTable('invoices') && Schema::hasColumn('invoices', 'order_id')) {
                DB::table('invoices')->where('order_id', 0)->update(['order_id' => null]);
            }

            $this->deleteOrphans('invoice_items', 'invoice_id', 'invoices', 'id');
            $this->deleteOrphans('payment_callbacks', 'payment_id', 'payments', 'id');
            $this->deleteOrphans('user_accounts', 'user_id', 'users', 'id', 'user_id');
            $this->deleteOrphans('ticket_replies', 'ticket_id', 'tickets', 'id');
            $this->deleteOrphans('services', 'user_id', 'users', 'id');
            $this->deleteOrphans('services', 'product_id', 'products', 'id');
            $this->clearOrphansToNull('services', 'invoice_id', 'invoices', 'id');
            $this->deleteOrphans('invoices', 'user_id', 'users', 'id');
            $this->deleteOrphans('invoices', 'product_id', 'products', 'id');
            $this->deleteOrphans('payments', 'user_id', 'users', 'id');
            $this->deleteOrphans('payments', 'invoice_id', 'invoices', 'id');

            $this->backfillTraceId('invoices');
            $this->backfillTraceId('payments');
            $this->backfillTraceId('services');
            $this->backfillTraceId('account_transactions');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('services') && Schema::hasColumn('services', 'order_id')) {
            DB::table('services')->whereNull('order_id')->update(['order_id' => 0]);
        }

        Schema::table('services', function (Blueprint $table): void {
            if (Schema::hasColumn('services', 'order_id')) {
                $table->unsignedBigInteger('order_id')->nullable(false)->default(0)->change();
            }
        });
    }

    private function deleteOrphans(
        string $table,
        string $column,
        string $parentTable,
        string $parentColumn,
        string $primaryColumn = 'id'
    ): void {
        if (! Schema::hasTable($table) || ! Schema::hasTable($parentTable) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        $ids = DB::table($table)
            ->leftJoin($parentTable.' as parent', "{$table}.{$column}", '=', "parent.{$parentColumn}")
            ->whereNotNull("{$table}.{$column}")
            ->whereNull("parent.{$parentColumn}")
            ->pluck("{$table}.{$primaryColumn}");

        if ($ids->isNotEmpty()) {
            DB::table($table)->whereIn($primaryColumn, $ids->all())->delete();
        }
    }

    private function clearOrphansToNull(string $table, string $column, string $parentTable, string $parentColumn): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasTable($parentTable) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        $ids = DB::table($table)
            ->leftJoin($parentTable.' as parent', "{$table}.{$column}", '=', "parent.{$parentColumn}")
            ->whereNotNull("{$table}.{$column}")
            ->whereNull("parent.{$parentColumn}")
            ->pluck("{$table}.id");

        if ($ids->isNotEmpty()) {
            DB::table($table)->whereIn('id', $ids->all())->update([$column => null]);
        }
    }

    private function backfillTraceId(string $table): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'trace_id')) {
            return;
        }

        $rows = DB::table($table)
            ->select('id')
            ->where(function ($query) {
                $query->whereNull('trace_id')
                    ->orWhere('trace_id', '');
            })
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            DB::table($table)
                ->where('id', $row->id)
                ->update(['trace_id' => "legacy-{$table}-{$row->id}"]);
        }
    }
};
