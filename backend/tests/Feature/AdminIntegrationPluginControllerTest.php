<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Role;
use App\Models\Setting;
use App\Support\AdminPermissions;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminIntegrationPluginControllerTest extends TestCase
{
    public function test_admin_can_scan_install_configure_and_enable_plugin(): void
    {
        $this->ensurePluginTables();
        Sanctum::actingAs($this->createAdmin());

        $listResponse = $this->getJson('/api/admin/integration-plugins')
            ->assertOk()
            ->assertJsonPath('data.total', fn (int $total): bool => $total >= 5)
            ->assertJsonPath('data.list.0.domain', fn (string $domain): bool => in_array($domain, ['mail', 'payment', 'sms', 'upstream', 'verification'], true));

        $this->assertFalse(collect($listResponse->json('data.list'))->contains(
            fn (array $plugin): bool => str_starts_with((string) ($plugin['slug'] ?? ''), 'demo_')
        ));

        $installResponse = $this->postJson('/api/admin/integration-plugins/install', [
            'domain' => 'verification',
            'slug' => 'stay33',
        ])->assertOk();

        $pluginId = (int) $installResponse->json('data.id');
        $this->assertGreaterThan(0, $pluginId);
        $installResponse
            ->assertJsonPath('data.config_schema.0.type', 'notice')
            ->assertJsonPath('data.config_schema.1.placeholder', '请输入 API 标识')
            ->assertJsonPath('data.config_schema.2.type', 'password')
            ->assertJsonPath('data.config_schema.7.visible_when.field', 'charge_enabled');

        $this->putJson("/api/admin/integration-plugins/{$pluginId}/config", [
            'config' => [
                'api' => 'stay33-api',
                'key' => 'stay33-secret',
                'biz_code' => 'FACE',
                'charge_enabled' => true,
                'amount' => '2.00',
                'free_times' => 1,
            ],
        ])->assertOk()
            ->assertJsonPath('data.config.api', 'stay33-api')
            ->assertJsonMissingPath('data.config.basic_notice')
            ->assertJsonPath('data.has_secret_values.key', true);

        $this->postJson("/api/admin/integration-plugins/{$pluginId}/enable")
            ->assertOk()
            ->assertJsonPath('data.is_enabled', true);

        $this->getJson("/api/admin/integration-plugins/{$pluginId}")
            ->assertOk()
            ->assertJsonPath('data.domain', 'verification')
            ->assertJsonPath('data.slug', 'stay33')
            ->assertJsonPath('data.is_enabled', true)
            ->assertJsonMissingPath('data.config.key');

        $this->assertSame('stay33-api', Setting::getValue('verification', 'verification_api', ''));
        $this->assertSame('FACE', Setting::getValue('verification', 'verification_biz_code', ''));
        $this->assertSame('stay33', Setting::getValue('verification', 'verification_driver', ''));

        $this->deleteJson("/api/admin/integration-plugins/{$pluginId}")
            ->assertOk();

        $verificationList = $this->getJson('/api/admin/integration-plugins?domain=verification')
            ->assertOk()
            ->json('data.list');

        $stay33 = collect($verificationList)->firstWhere('slug', 'stay33');
        $this->assertIsArray($stay33);
        $this->assertFalse((bool) ($stay33['is_installed'] ?? true));
        $this->assertFalse((bool) ($stay33['is_enabled'] ?? true));
        $this->assertNull(collect($verificationList)->firstWhere('slug', 'demo_verification'));

        $this->assertSame('', Setting::getValue('verification', 'verification_driver', ''));
    }

    private function createAdmin(): AdminUser
    {
        $suffix = bin2hex(random_bytes(4));
        $role = Role::query()->create([
            'name' => 'plugin-admin-'.$suffix,
            'label' => 'Plugin Admin',
            'permissions' => [AdminPermissions::SETTINGS_MANAGE],
        ]);

        return AdminUser::query()->create([
            'username' => 'plugin-admin-'.$suffix,
            'password' => 'Temp@123456',
            'role_id' => (int) $role->id,
            'nickname' => 'Plugin Admin',
            'email' => 'plugin-admin-'.$suffix.'@example.com',
            'status' => 1,
        ]);
    }

    private function ensurePluginTables(): void
    {
        if (! Schema::hasTable('integration_plugins')) {
            Schema::create('integration_plugins', function (Blueprint $table): void {
                $table->id();
                $table->string('domain', 32);
                $table->string('slug', 120);
                $table->string('plugin_key', 120);
                $table->string('name', 120);
                $table->string('version', 32)->default('1.0.0');
                $table->string('provider_class', 255)->nullable();
                $table->string('entry_class', 255);
                $table->json('capabilities_json')->nullable();
                $table->json('config_schema_json')->nullable();
                $table->unsignedTinyInteger('status')->default(0);
                $table->timestamp('installed_at')->nullable();
                $table->timestamps();
                $table->unique(['domain', 'slug']);
                $table->unique(['domain', 'plugin_key']);
            });
        }

        if (! Schema::hasTable('integration_plugin_configs')) {
            Schema::create('integration_plugin_configs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('plugin_id');
                $table->json('config_json')->nullable();
                $table->longText('secret_json')->nullable();
                $table->json('has_secret_json')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->unique('plugin_id');
            });
        }
    }
}
