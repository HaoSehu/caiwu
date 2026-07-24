<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 将 products 表的 product_type / service_type_code 从旧码统一收敛到 8 种业务类型。
     */
    public function up(): void
    {
        $map = [
            // 旧码 => 业务类型
            'vps' => 'cloud_server',
            'dedicated' => 'game_cloud',
            'domain' => 'cloud_desktop',
            'type_iwjqnj' => 'bare_metal',
            'other' => 'cdn',
            'type_ipragu' => 'other',
            'type_tgynng' => 'physical_machine',
            'hosting' => 'web_hosting',
            'type_1' => 'web_hosting',
        ];

        $known = [
            'cloud_server', 'game_cloud', 'cloud_desktop', 'bare_metal',
            'cdn', 'other', 'physical_machine', 'web_hosting',
        ];

        // 更新 product_type
        foreach ($map as $old => $new) {
            DB::table('products')->where('product_type', $old)->update(['product_type' => $new]);
        }
        // 不在已知业务类型也不在映射中的，统一归为 other
        DB::table('products')
            ->whereNotIn('product_type', $known)
            ->update(['product_type' => 'other']);

        // 更新 service_type_code（只处理非空值）
        foreach ($map as $old => $new) {
            DB::table('products')->where('service_type_code', $old)->update(['service_type_code' => $new]);
        }
        DB::table('products')
            ->whereNotNull('service_type_code')
            ->where('service_type_code', '!=', '')
            ->whereNotIn('service_type_code', $known)
            ->update(['service_type_code' => 'other']);
    }

    /**
     * 无法可靠回滚（多旧码映射到同一业务类型），不回滚。
     */
    public function down(): void
    {
        // 不提供回滚
    }
};
