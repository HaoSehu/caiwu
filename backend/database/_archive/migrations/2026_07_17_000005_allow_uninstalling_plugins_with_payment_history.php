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
        $this->replacePaymentsPluginForeignKey('nullOnDelete');
    }

    public function down(): void
    {
        $this->replacePaymentsPluginForeignKey('restrictOnDelete');
    }

    private function replacePaymentsPluginForeignKey(string $deleteRule): void
    {
        if (! Schema::hasTable('payments') || ! Schema::hasColumn('payments', 'plugin_id')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table): void {
            if ($this->foreignKeyExists('payments', 'payments_plugin_fk')) {
                $table->dropForeign('payments_plugin_fk');
            }
        });

        Schema::table('payments', function (Blueprint $table) use ($deleteRule): void {
            $foreign = $table->foreign('plugin_id', 'payments_plugin_fk')
                ->references('id')
                ->on('integration_plugins');

            if ($deleteRule === 'nullOnDelete') {
                $foreign->nullOnDelete();

                return;
            }

            $foreign->restrictOnDelete();
        });
    }

    private function foreignKeyExists(string $tableName, string $constraintName): bool
    {
        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $tableName)
            ->where('CONSTRAINT_NAME', $constraintName)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();
    }
};
