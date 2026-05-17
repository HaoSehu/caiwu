<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\OrderStatus;
use App\Constants\ProductType;
use App\Constants\ServiceStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientServiceConsoleCategoryTitleFallbackTest extends TestCase
{
    public function test_client_service_endpoints_work_without_product_group_title_column(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $userId = null;
        $rootGroupId = null;
        $childGroupId = null;
        $productId = null;
        $orderId = null;
        $serviceId = null;
        $pendingServiceId = null;
        $suspendedServiceId = null;

        try {
            $user = User::query()->create([
                'email' => 'client-service-title-'.$suffix.'@example.com',
                'password' => 'Temp@123456',
                'phone' => '13'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
                'status' => 1,
                'nickname' => 'Service Client '.$suffix,
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
            $userId = (int) $user->id;

            $rootGroup = ProductCategory::query()->create([
                'parent_id' => null,
                'product_type' => ProductType::VPS,
                'name' => 'Root Group '.$suffix,
                'slug' => 'client-service-root-'.$suffix,
                'slogan' => 'Root slogan '.$suffix,
                'is_visible' => 1,
                'sort_order' => 0,
            ]);
            $rootGroupId = (int) $rootGroup->id;

            $childGroup = ProductCategory::query()->create([
                'parent_id' => (int) $rootGroup->id,
                'product_type' => ProductType::VPS,
                'name' => 'Child Group '.$suffix,
                'slug' => 'client-service-child-'.$suffix,
                'slogan' => 'Child slogan '.$suffix,
                'is_visible' => 1,
                'sort_order' => 0,
            ]);
            $childGroupId = (int) $childGroup->id;

            $product = Product::query()->create([
                'product_group_id' => (int) $childGroup->id,
                'name' => 'Client Service Product '.$suffix,
                'product_type' => ProductType::VPS,
                'description' => '',
                'pricing' => ['monthly' => '19.90'],
                'setup_fee' => '0.00',
                'config_options' => [],
                'purchase_requires' => [],
                'stock' => 8,
                'status' => 1,
                'sort_order' => 0,
                'provision_module' => null,
                'auto_setup' => 0,
            ]);
            $productId = (int) $product->id;

            $order = Order::query()->create([
                'order_no' => 'CLIENT-SVC-'.strtoupper($suffix),
                'user_id' => (int) $user->id,
                'product_id' => (int) $product->id,
                'type' => 'new',
                'amount' => '19.90',
                'discount' => '0.00',
                'paid_amount' => '19.90',
                'billing_cycle' => 'monthly',
                'config_snapshot' => [],
                'config_pricing_snapshot' => [],
                'coupon_snapshot' => [],
                'status' => OrderStatus::COMPLETED,
                'paid_at' => now(),
            ]);
            $orderId = (int) $order->id;

            $service = Service::query()->create([
                'user_id' => (int) $user->id,
                'product_id' => (int) $product->id,
                'order_id' => (int) $order->id,
                'name' => 'Client Service Instance '.$suffix,
                'domain' => '',
                'billing_cycle' => 'monthly',
                'amount' => '19.90',
                'status' => ServiceStatus::ACTIVE,
                'locked_pricing' => ['monthly' => '19.90'],
                'provision_data' => [],
                'expires_at' => now()->addDays(30),
                'auto_renew' => 1,
            ]);
            $serviceId = (int) $service->id;

            $pendingService = Service::query()->create([
                'user_id' => (int) $user->id,
                'product_id' => (int) $product->id,
                'order_id' => (int) $order->id,
                'name' => 'Pending Service Instance '.$suffix,
                'domain' => '',
                'billing_cycle' => 'monthly',
                'amount' => '19.90',
                'status' => ServiceStatus::PENDING,
                'locked_pricing' => ['monthly' => '19.90'],
                'provision_data' => [],
                'expires_at' => now()->addDays(7),
                'auto_renew' => 0,
            ]);
            $pendingServiceId = (int) $pendingService->id;

            $suspendedService = Service::query()->create([
                'user_id' => (int) $user->id,
                'product_id' => (int) $product->id,
                'order_id' => (int) $order->id,
                'name' => 'Suspended Service Instance '.$suffix,
                'domain' => '',
                'billing_cycle' => 'monthly',
                'amount' => '19.90',
                'status' => ServiceStatus::SUSPENDED,
                'locked_pricing' => ['monthly' => '19.90'],
                'provision_data' => [],
                'expires_at' => now()->addDays(14),
                'auto_renew' => 0,
            ]);
            $suspendedServiceId = (int) $suspendedService->id;

            Sanctum::actingAs($user);

            $this->getJson('/api/client/services?page=1&page_size=10&status='.ServiceStatus::ACTIVE)
                ->assertOk()
                ->assertJsonPath('code', 0)
                ->assertJsonPath('data.total', 1)
                ->assertJsonPath('data.list.0.id', $serviceId)
                ->assertJsonPath('data.list.0.product.group_name', 'Child Group '.$suffix)
                ->assertJsonPath('data.list.0.product.root_group_name', 'Root Group '.$suffix);

            $defaultStatusResponse = $this->getJson('/api/client/services?page=1&page_size=10&status_scope=active_pending')
                ->assertOk()
                ->assertJsonPath('code', 0)
                ->assertJsonPath('data.total', 2);

            $this->assertSame(
                [ServiceStatus::PENDING, ServiceStatus::ACTIVE],
                collect($defaultStatusResponse->json('data.list'))
                    ->pluck('status')
                    ->map(fn ($status) => (int) $status)
                    ->all()
            );

            $overviewResponse = $this->getJson('/api/client/services/grouped-overview')
                ->assertOk()
                ->assertJsonPath('code', 0)
                ->assertJsonPath('data.total', 3);

            $typeCard = collect($overviewResponse->json('data.list'))
                ->firstWhere('product_type', ProductType::VPS);

            $this->assertIsArray($typeCard);
            $this->assertSame(3, (int) ($typeCard['count'] ?? 0));
            $this->assertSame('Child Group '.$suffix, data_get($typeCard, 'children.0.name'));
            $this->assertSame('Child Group '.$suffix, data_get($typeCard, 'children.0.title'));
            $this->assertSame('Child slogan '.$suffix, data_get($typeCard, 'children.0.description'));
        } finally {
            if ($suspendedServiceId !== null) {
                DB::table('services')->where('id', $suspendedServiceId)->delete();
            }

            if ($pendingServiceId !== null) {
                DB::table('services')->where('id', $pendingServiceId)->delete();
            }

            if ($serviceId !== null) {
                DB::table('services')->where('id', $serviceId)->delete();
            }

            if ($orderId !== null) {
                DB::table('orders')->where('id', $orderId)->delete();
            }

            if ($productId !== null) {
                DB::table('products')->where('id', $productId)->delete();
            }

            if ($childGroupId !== null) {
                DB::table('product_groups')->where('id', $childGroupId)->delete();
            }

            if ($rootGroupId !== null) {
                DB::table('product_groups')->where('id', $rootGroupId)->delete();
            }

            if ($userId !== null) {
                DB::table('users')->where('id', $userId)->delete();
            }
        }
    }
}
