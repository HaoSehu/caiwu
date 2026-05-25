<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\ServiceStatus;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ServiceInstance;
use App\Models\User;
use App\Services\ProductCatalog\ProductAdminService;
use App\Services\System\DashboardService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IdcServiceInstanceOnlyReadRegressionTest extends TestCase
{
    public function test_dashboard_stats_count_active_services_from_service_instances_only(): void
    {
        $suffix = bin2hex(random_bytes(4));

        $user = User::query()->create([
            'email' => 'dashboard-service-instance-'.$suffix.'@example.com',
            'password' => 'Temp@123456',
            'phone' => '13'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'status' => 1,
            'nickname' => 'Dashboard IDC',
            'real_name' => '',
            'id_card' => '',
            'verification_status' => 0,
            'verification_message' => '',
            'verification_certify_id' => null,
            'member_level_id' => null,
            'total_sales_amount' => '0.00',
            'referrer_user_id' => null,
            'verified_at' => null,
        ]);

        $group = ProductCategory::query()->create([
            'parent_id' => null,
            'product_type' => 'server',
            'name' => 'Dashboard Group '.$suffix,
            'slug' => 'dashboard-group-'.$suffix,
            'slogan' => '',
            'is_visible' => 1,
            'sort_order' => 0,
        ]);

        $product = Product::query()->create([
            'product_group_id' => (int) $group->id,
            'name' => 'Dashboard Product '.$suffix,
            'product_type' => 'server',
            'pricing' => ['monthly' => '12.00'],
            'setup_fee' => '0.00',
            'config_options' => [],
            'purchase_requires' => [],
            'stock' => 10,
            'status' => 1,
            'sort_order' => 0,
            'auto_setup' => 0,
        ]);

        ServiceInstance::query()->create([
            'user_id' => (int) $user->id,
            'product_id' => (int) $product->id,
            'service_no' => 'DASH-'.$suffix,
            'name' => 'Dashboard Active Service '.$suffix,
            'instance_identifier' => 'dashboard-'.$suffix.'.example.com',
            'billing_cycle' => 'monthly',
            'renewal_price' => '12.00',
            'status' => ServiceStatus::ACTIVE,
            'provision_snapshot_json' => [],
            'expires_at' => now()->addMonth(),
        ]);

        $payload = app(DashboardService::class)->stats();

        $this->assertGreaterThanOrEqual(1, (int) ($payload['counts']['active_services'] ?? 0));
    }

    public function test_admin_product_owners_reads_service_instances_without_legacy_services_rows(): void
    {
        $suffix = bin2hex(random_bytes(4));

        $user = User::query()->create([
            'email' => 'product-owner-service-instance-'.$suffix.'@example.com',
            'password' => 'Temp@123456',
            'phone' => '13'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'status' => 1,
            'nickname' => 'Owner IDC',
            'real_name' => '',
            'id_card' => '',
            'verification_status' => 0,
            'verification_message' => '',
            'verification_certify_id' => null,
            'member_level_id' => null,
            'total_sales_amount' => '0.00',
            'referrer_user_id' => null,
            'verified_at' => null,
        ]);

        DB::connection('idc')->table('users')->updateOrInsert(
            ['id' => (int) $user->id],
            [
                'email' => $user->email,
                'phone' => $user->phone,
                'password' => bcrypt('Temp@123456'),
                'status' => 1,
                'nickname' => (string) $user->nickname,
                'real_name' => '',
                'id_card' => '',
                'verification_status' => 0,
                'verification_message' => '',
                'verification_certify_id' => null,
                'member_level_id' => null,
                'referrer_user_id' => null,
                'total_sales_amount' => '0.00',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $group = ProductCategory::query()->create([
            'parent_id' => null,
            'product_type' => 'server',
            'name' => 'Owner Group '.$suffix,
            'slug' => 'owner-group-'.$suffix,
            'slogan' => '',
            'is_visible' => 1,
            'sort_order' => 0,
        ]);

        $product = Product::query()->create([
            'product_group_id' => (int) $group->id,
            'name' => 'Owner Product '.$suffix,
            'product_type' => 'server',
            'pricing' => ['monthly' => '23.00'],
            'setup_fee' => '0.00',
            'config_options' => [],
            'purchase_requires' => [],
            'stock' => 10,
            'status' => 1,
            'sort_order' => 0,
            'auto_setup' => 0,
        ]);

        ServiceInstance::query()->create([
            'user_id' => (int) $user->id,
            'product_id' => (int) $product->id,
            'service_no' => 'OWN-'.$suffix,
            'name' => 'Owner Service '.$suffix,
            'instance_identifier' => 'owner-'.$suffix.'.example.com',
            'billing_cycle' => 'monthly',
            'renewal_price' => '23.00',
            'status' => ServiceStatus::ACTIVE,
            'provision_snapshot_json' => [],
            'expires_at' => now()->addMonth(),
        ]);

        $payload = app(ProductAdminService::class)->adminProductOwners($product->fresh(), [], 20);

        $this->assertSame(1, (int) ($payload['summary']['owners_total'] ?? 0));
        $this->assertSame(1, (int) ($payload['summary']['services_total'] ?? 0));
        $this->assertCount(1, $payload['list'] ?? []);
        $this->assertSame((int) $user->id, (int) ($payload['list'][0]['id'] ?? 0));
    }
}
