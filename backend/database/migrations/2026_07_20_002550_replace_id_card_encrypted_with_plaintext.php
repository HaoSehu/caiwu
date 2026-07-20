<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // users 表：将加密的 id_card 替换为随机 18 位数字（模拟数据）
        $userRows = DB::table('users')
            ->whereNotNull('id_card')
            ->where('id_card', '!=', '')
            ->get(['id', 'id_card']);

        foreach ($userRows as $row) {
            DB::table('users')
                ->where('id', $row->id)
                ->update(['id_card' => $this->randomIdCard()]);
        }

        // verification_histories 表：同上
        $historyRows = DB::table('verification_histories')
            ->whereNotNull('id_card')
            ->where('id_card', '!=', '')
            ->get(['id', 'id_card']);

        foreach ($historyRows as $row) {
            DB::table('verification_histories')
                ->where('id', $row->id)
                ->update(['id_card' => $this->randomIdCard()]);
        }
    }

    public function down(): void
    {
        // 不可逆：明文数据无法恢复为原始加密值
    }

    private function randomIdCard(): string
    {
        // 6 位地区码 + 8 位生日 + 4 位顺序/校验码 = 18 位
        $area = str_pad((string) random_int(110000, 659004), 6, '0', STR_PAD_LEFT);
        $birth = sprintf('%04d%02d%02d', random_int(1960, 2005), random_int(1, 12), random_int(1, 28));
        $seq = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);

        return $area.$birth.$seq;
    }
};
