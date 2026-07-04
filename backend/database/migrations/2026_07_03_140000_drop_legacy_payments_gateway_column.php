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
        if (! Schema::hasTable('payments') || ! Schema::hasColumn('payments', 'gateway')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table): void {
            if ($this->indexExists('payments', 'payments_gateway_trade_no_unique')) {
                $table->dropUnique('payments_gateway_trade_no_unique');
            }
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn('gateway');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('payments') || Schema::hasColumn('payments', 'gateway')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table): void {
            $table->string('gateway', 50)->nullable()->after('invoice_id');
        });

        if (Schema::hasColumn('payments', 'gateway_key')) {
            DB::table('payments')
                ->whereNull('gateway')
                ->update(['gateway' => DB::raw('gateway_key')]);
        }

        Schema::table('payments', function (Blueprint $table): void {
            if (! $this->indexExists('payments', 'payments_gateway_trade_no_unique')) {
                $table->unique(['gateway', 'trade_no'], 'payments_gateway_trade_no_unique');
            }
        });
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        try {
            return collect(DB::select("SHOW INDEX FROM `{$tableName}`"))
                ->contains(fn (object $row): bool => (string) ($row->Key_name ?? '') === $indexName);
        } catch (Throwable) {
            return false;
        }
    }
};
