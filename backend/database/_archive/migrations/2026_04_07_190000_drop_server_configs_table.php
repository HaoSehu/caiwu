<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('servers')) {
            return;
        }

        if (! Schema::hasColumn('servers', 'module_config')) {
            Schema::table('servers', function (Blueprint $table): void {
                $table->json('module_config')->nullable()->after('module');
            });
        }

        if (! Schema::hasTable('server_configs')) {
            return;
        }

        $serverIds = DB::table('server_configs')
            ->select('server_id')
            ->distinct()
            ->pluck('server_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->values();

        foreach ($serverIds as $serverId) {
            $config = DB::table('server_configs')
                ->where('server_id', $serverId)
                ->orderBy('id')
                ->get(['config_key', 'config_value'])
                ->reduce(function (array $carry, object $row): array {
                    $key = trim((string) ($row->config_key ?? ''));
                    if ($key === '') {
                        return $carry;
                    }

                    $rawValue = (string) ($row->config_value ?? '');
                    $decoded = json_decode($rawValue, true);
                    $carry[$key] = json_last_error() === JSON_ERROR_NONE ? $decoded : $rawValue;

                    return $carry;
                }, []);

            DB::table('servers')
                ->where('id', $serverId)
                ->update([
                    'module_config' => $config === [] ? null : json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'updated_at' => now(),
                ]);
        }

        Schema::drop('server_configs');
    }

    public function down(): void
    {
        if (Schema::hasTable('server_configs') || ! Schema::hasTable('servers')) {
            return;
        }

        Schema::create('server_configs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('server_id');
            $table->string('config_key', 100);
            $table->text('config_value')->nullable();
            $table->unsignedTinyInteger('is_encrypted')->default(0);
            $table->timestamps();

            $table->index('server_id', 'server_configs_server_id_idx');
            $table->index(['server_id', 'config_key'], 'server_configs_server_key_idx');
        });

        DB::table('servers')
            ->orderBy('id')
            ->get(['id', 'module_config', 'created_at', 'updated_at'])
            ->each(function (object $server): void {
                $moduleConfig = json_decode((string) ($server->module_config ?? ''), true);
                if (! is_array($moduleConfig) || $moduleConfig === []) {
                    return;
                }

                foreach ($moduleConfig as $configKey => $configValue) {
                    $key = trim((string) $configKey);
                    if ($key === '') {
                        continue;
                    }

                    DB::table('server_configs')->insert([
                        'server_id' => (int) $server->id,
                        'config_key' => $key,
                        'config_value' => is_scalar($configValue) || $configValue === null
                            ? (string) $configValue
                            : json_encode($configValue, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'is_encrypted' => 0,
                        'created_at' => $server->created_at ?? now(),
                        'updated_at' => $server->updated_at ?? now(),
                    ]);
                }
            });
    }
};
