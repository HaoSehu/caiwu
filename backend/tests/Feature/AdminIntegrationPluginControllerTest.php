<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\IntegrationPlugin;
use App\Models\Role;
use App\Models\Setting;
use App\Support\AdminPermissions;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminIntegrationPluginControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanPluginTables();
    }

    protected function tearDown(): void
    {
        $this->cleanPluginTables();
        parent::tearDown();
    }

    public function test_admin_can_scan_install_configure_and_enable_plugin(): void
    {
        $this->ensurePluginTables();
        Sanctum::actingAs($this->createAdmin());

        $listResponse = $this->getJson('/api/admin/integration-plugins')
            ->assertOk()
            ->assertJsonPath('data.total', fn (int $total): bool => $total >= 6)
            ->assertJsonPath('data.list.0.domain', fn (string $domain): bool => in_array($domain, ['captcha', 'mail', 'payment', 'sms', 'upstream', 'verification'], true));

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

        $this->assertDatabaseHas('integration_plugin_bindings', [
            'domain' => 'verification',
            'plugin_id' => $pluginId,
            'binding_key' => 'verification_driver',
            'provider_key' => 'stay33',
            'status' => 1,
        ]);

        $this->deleteJson("/api/admin/integration-plugins/{$pluginId}")
            ->assertOk();

        $verificationList = $this->getJson('/api/admin/integration-plugins?domain=verification')
            ->assertOk()
            ->json('data.list');

        $stay33 = collect($verificationList)->firstWhere('slug', 'stay33');
        $this->assertIsArray($stay33);
        $this->assertTrue((bool) ($stay33['is_installed'] ?? false));
        $this->assertFalse((bool) ($stay33['is_enabled'] ?? true));
        $this->assertSame('disable_archive', (string) ($stay33['delete_mode'] ?? ''));
        $this->assertGreaterThan(0, (int) ($stay33['business_reference_count'] ?? 0));
        $this->assertNull(collect($verificationList)->firstWhere('slug', 'demo_verification'));

        $this->assertDatabaseHas('integration_plugin_bindings', [
            'domain' => 'verification',
            'plugin_id' => $pluginId,
            'binding_key' => 'verification_driver',
            'provider_key' => 'stay33',
            'status' => 0,
        ]);
    }

    public function test_admin_can_configure_and_enable_captcha_plugin(): void
    {
        $this->ensurePluginTables();
        Sanctum::actingAs($this->createAdmin());

        $installResponse = $this->postJson('/api/admin/integration-plugins/install', [
            'domain' => 'captcha',
            'slug' => 'geetest',
        ])->assertOk();

        $pluginId = (int) $installResponse->json('data.id');
        $this->assertGreaterThan(0, $pluginId);

        $this->putJson("/api/admin/integration-plugins/{$pluginId}/config", [
            'config' => [
                'captcha_id' => 'captcha-id',
                'captcha_key' => 'captcha-secret',
            ],
        ])->assertOk()
            ->assertJsonPath('data.config.captcha_id', 'captcha-id')
            ->assertJsonMissingPath('data.config.captcha_key')
            ->assertJsonPath('data.has_secret_values.captcha_key', true);

        $this->getJson("/api/admin/integration-plugins/{$pluginId}/config-secret/captcha_key")
            ->assertOk()
            ->assertJsonPath('data.key', 'captcha_key')
            ->assertJsonPath('data.value', 'captcha-secret');

        $this->postJson("/api/admin/integration-plugins/{$pluginId}/enable")
            ->assertOk()
            ->assertJsonPath('data.is_enabled', true);

        $this->assertDatabaseHas('integration_plugin_bindings', [
            'domain' => 'captcha',
            'plugin_id' => $pluginId,
            'binding_key' => 'captcha_driver',
            'provider_key' => 'geetest',
            'status' => 1,
        ]);
    }

    public function test_install_does_not_hydrate_captcha_plugin_from_old_settings(): void
    {
        $this->ensurePluginTables();
        Sanctum::actingAs($this->createAdmin());
        $originalCaptchaId = Setting::getValue('system', 'geetest_captcha_id', '');
        $originalCaptchaKey = Setting::getValue('system', 'geetest_captcha_key', '');

        Setting::setValues('system', [
            'geetest_captcha_id' => 'legacy-captcha-id',
            'geetest_captcha_key' => 'legacy-captcha-secret',
        ]);

        try {
            $installResponse = $this->postJson('/api/admin/integration-plugins/install', [
                'domain' => 'captcha',
                'slug' => 'geetest',
            ])->assertOk()
                ->assertJsonPath('data.config', [])
                ->assertJsonPath('data.has_secret_values', []);

            $pluginId = (int) $installResponse->json('data.id');
            $this->assertGreaterThan(0, $pluginId);

            $this->postJson("/api/admin/integration-plugins/{$pluginId}/enable")
                ->assertStatus(422)
                ->assertJsonPath('code', 42200);
        } finally {
            Setting::setValues('system', [
                'geetest_captcha_id' => $originalCaptchaId,
                'geetest_captcha_key' => $originalCaptchaKey,
            ]);
        }
    }

    public function test_test_email_rejects_invalid_email_with_unified_validation_error(): void
    {
        $this->ensurePluginTables();
        Sanctum::actingAs($this->createAdmin());
        $plugin = $this->createPlugin('mail', 'smtp');

        $this->postJson("/api/admin/integration-plugins/{$plugin->id}/test-email", [
            'account_index' => 0,
            'to' => 'not-an-email',
            'subject' => '测试邮件',
            'body' => 'hello',
        ])->assertStatus(422)
            ->assertJsonPath('code', 42200)
            ->assertJsonPath('message', '参数验证失败')
            ->assertJsonStructure(['data' => ['errors' => ['to']]]);
    }

    public function test_test_sms_rejects_invalid_payload_with_unified_validation_error(): void
    {
        $this->ensurePluginTables();
        Sanctum::actingAs($this->createAdmin());
        $plugin = $this->createPlugin('sms', 'aliyun_sms');

        $this->postJson("/api/admin/integration-plugins/{$plugin->id}/test-sms", [
            'phone' => str_repeat('1', 21),
        ])->assertStatus(422)
            ->assertJsonPath('code', 42200)
            ->assertJsonPath('message', '参数验证失败')
            ->assertJsonStructure(['data' => ['errors' => ['phone']]]);
    }

    private function createAdmin(): AdminUser
    {
        $suffix = bin2hex(random_bytes(4));
        $role = Role::query()->create([
            'name' => 'plugin-admin-'.$suffix,
            'label' => 'Plugin Admin',
            'permissions' => [
                AdminPermissions::INTEGRATION_PLUGIN_MANAGE,
                AdminPermissions::INTEGRATION_PLUGIN_TEST,
                AdminPermissions::INTEGRATION_PLUGIN_SECRET_REVEAL,
            ],
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

    private function createPlugin(string $domain, string $slug): IntegrationPlugin
    {
        return IntegrationPlugin::query()->create([
            'domain' => $domain,
            'slug' => $slug,
            'plugin_key' => $slug,
            'name' => 'Test '.$slug,
            'version' => '1.0.0',
            'entry_class' => 'Tests\\Fixtures\\Plugin',
            'capabilities_json' => [],
            'config_schema_json' => [],
            'status' => IntegrationPlugin::STATUS_ENABLED,
            'installed_at' => now(),
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

        if (! Schema::hasTable('integration_plugin_bindings')) {
            Schema::create('integration_plugin_bindings', function (Blueprint $table): void {
                $table->id();
                $table->string('domain', 32);
                $table->unsignedBigInteger('plugin_id');
                $table->string('binding_type', 50);
                $table->string('bindable_type', 120)->default('global');
                $table->unsignedBigInteger('bindable_id')->default(0);
                $table->string('binding_key', 120);
                $table->string('provider_key', 120)->nullable();
                $table->integer('priority')->default(0);
                $table->unsignedTinyInteger('status')->default(1);
                $table->json('config_json')->nullable();
                $table->longText('secret_json')->nullable();
                $table->json('has_secret_json')->nullable();
                $table->json('runtime_policy_json')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->string('backfill_batch_id', 64)->nullable();
                $table->timestamps();
                $table->unique(['domain', 'binding_type', 'bindable_type', 'bindable_id', 'binding_key'], 'plugin_bindings_unique');
            });
        }
    }

    private function cleanPluginTables(): void
    {
        if (! Schema::hasTable('integration_plugins')) {
            return;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        if (Schema::hasTable('integration_plugin_bindings')) {
            DB::table('integration_plugin_bindings')->truncate();
        }
        if (Schema::hasTable('integration_plugin_configs')) {
            DB::table('integration_plugin_configs')->truncate();
        }
        DB::table('integration_plugins')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
}
