<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 先清理重复的 trace_id：保留最早的（id 最小），其他置 null
        $duplicates = DB::table('referral_withdrawals')
            ->select('trace_id', DB::raw('COUNT(*) as cnt'))
            ->whereNotNull('trace_id')
            ->where('trace_id', '!=', '')
            ->groupBy('trace_id')
            ->having('cnt', '>', 1)
            ->get();

        foreach ($duplicates as $dup) {
            $ids = DB::table('referral_withdrawals')
                ->where('trace_id', $dup->trace_id)
                ->orderBy('id')
                ->pluck('id');
            $keepId = $ids->first();
            $idsToClear = $ids->skip(1);
            if ($idsToClear->isNotEmpty()) {
                DB::table('referral_withdrawals')
                    ->whereIn('id', $idsToClear->all())
                    ->update(['trace_id' => null]);
            }
        }

        Schema::table('referral_withdrawals', function (Blueprint $table) {
            $table->unique('trace_id', 'referral_withdrawals_trace_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('referral_withdrawals', function (Blueprint $table) {
            $table->dropUnique('referral_withdrawals_trace_id_unique');
        });
    }
};
