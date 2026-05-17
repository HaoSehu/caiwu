<?php

declare(strict_types=1);

use App\Services\Upstream\ProviderKey;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('suppliers')
            ->where('interface_type', 'mofang_finance_api')
            ->update([
                'interface_type' => ProviderKey::HOSTING_PANEL_API,
            ]);

        DB::table('products')
            ->where('provision_module', 'mofang_finance_api')
            ->update([
                'provision_module' => ProviderKey::HOSTING_PANEL_API,
            ]);

        DB::table('services')
            ->select(['id', 'provision_data'])
            ->orderBy('id')
            ->chunkById(100, function ($services): void {
                foreach ($services as $service) {
                    $provisionData = json_decode((string) ($service->provision_data ?? ''), true);
                    if (! is_array($provisionData) || ($provisionData['provider'] ?? null) !== 'mofang_finance_api') {
                        continue;
                    }

                    $provisionData['provider'] = ProviderKey::HOSTING_PANEL_API;

                    DB::table('services')
                        ->where('id', (int) $service->id)
                        ->update([
                            'provision_data' => json_encode($provisionData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        ]);
                }
            });
    }

    public function down(): void
    {
        DB::table('suppliers')
            ->where('interface_type', ProviderKey::HOSTING_PANEL_API)
            ->update([
                'interface_type' => 'mofang_finance_api',
            ]);

        DB::table('products')
            ->where('provision_module', ProviderKey::HOSTING_PANEL_API)
            ->update([
                'provision_module' => 'mofang_finance_api',
            ]);

        DB::table('services')
            ->select(['id', 'provision_data'])
            ->orderBy('id')
            ->chunkById(100, function ($services): void {
                foreach ($services as $service) {
                    $provisionData = json_decode((string) ($service->provision_data ?? ''), true);
                    if (! is_array($provisionData) || ($provisionData['provider'] ?? null) !== ProviderKey::HOSTING_PANEL_API) {
                        continue;
                    }

                    $provisionData['provider'] = 'mofang_finance_api';

                    DB::table('services')
                        ->where('id', (int) $service->id)
                        ->update([
                            'provision_data' => json_encode($provisionData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        ]);
                }
            });
    }
};
