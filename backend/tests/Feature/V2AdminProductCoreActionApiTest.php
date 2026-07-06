<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\FirstProductGroup;
use App\Models\Product;
use App\Models\Role;
use App\Models\SecondProductGroup;
use App\Services\Admin\V2\AdminCatalogActionV2Service;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class V2AdminProductCoreActionApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_product_summary_and_owners_use_v2_validation_and_projection(): void
    {
        $product = $this->createProduct();

        $this->getJson('/api/v2/admin/products/summary')
            ->assertUnauthorized()
            ->assertJsonPath('code', 40100);

        Sanctum::actingAs($this->createAdmin([]));

        $this->getJson('/api/v2/admin/products/summary')
            ->assertForbidden()
            ->assertJsonPath('code', 40300);

        Sanctum::actingAs($this->createAdmin([AdminPermissions::PRODUCT_LIST]));

        $this->getJson('/api/v2/admin/products/summary?pageSize=20')
            ->assertUnprocessable()
            ->assertJsonPath('code', 42200)
            ->assertJsonStructure(['data' => ['errors' => ['pageSize']]]);

        $summaryResponse = $this->getJson('/api/v2/admin/products/summary')
            ->assertOk()
            ->assertJsonPath('code', 0);

        $this->assertSame([
            'first_product_groups_total',
            'second_product_groups_total',
            'third_product_groups_total',
            'products_total',
            'products_deleted',
            'products_active',
            'products_low_stock',
        ], array_keys($summaryResponse->json('data')));
        $this->assertLessThan(100 * 1024, strlen((string) $summaryResponse->getContent()));

        $listResponse = $this->getJson('/api/v2/admin/products?keyword=&status=&lifecycle_status=active&page=1&page_size=20')
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.page', 1)
            ->assertJsonPath('data.page_size', 20);

        $this->assertSame(['list', 'total', 'page', 'page_size'], array_keys($listResponse->json('data')));
        $this->assertNoSensitiveKeys($listResponse->json());
        $this->assertLessThan(100 * 1024, strlen((string) $listResponse->getContent()));

        $this->getJson('/api/v2/admin/products/'.$product->id.'/owners?per_page=20')
            ->assertUnprocessable()
            ->assertJsonPath('code', 42200)
            ->assertJsonStructure(['data' => ['errors' => ['per_page']]]);

        $ownersResponse = $this->getJson('/api/v2/admin/products/'.$product->id.'/owners?page=1&page_size=10')
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.page', 1)
            ->assertJsonPath('data.page_size', 10);

        $this->assertSame(['list', 'summary', 'total', 'page', 'page_size'], array_keys($ownersResponse->json('data')));
        $this->assertSame(['owners_total', 'services_total', 'active_services_total', 'latest_service_created_at'], array_keys($ownersResponse->json('data.summary')));
        $this->assertNoSensitiveKeys($ownersResponse->json());
        $this->assertLessThan(100 * 1024, strlen((string) $ownersResponse->getContent()));
    }

    public function test_product_crud_delete_restore_and_force_delete_use_v2_contracts(): void
    {
        $secondGroup = $this->createSecondGroup();

        Sanctum::actingAs($this->createAdmin([AdminPermissions::PRODUCT_LIST]));

        $this->postJson('/api/v2/admin/products', $this->productPayload($secondGroup))
            ->assertForbidden()
            ->assertJsonPath('code', 40300);

        Sanctum::actingAs($this->createAdmin([AdminPermissions::PRODUCT_MANAGE]));

        $this->postJson('/api/v2/admin/products?per_page=20', $this->productPayload($secondGroup))
            ->assertUnprocessable()
            ->assertJsonPath('code', 42200)
            ->assertJsonStructure(['data' => ['errors' => ['per_page']]]);

        $createResponse = $this->postJson('/api/v2/admin/products', $this->productPayload($secondGroup))
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('message', '商品创建成功')
            ->assertJsonPath('data.product.classification.second_product_group_id', $secondGroup->id)
            ->assertJsonMissingPath('data.product.configuration.config_options.0.api_key');

        $productId = (int) $createResponse->json('data.product.id');
        $this->assertSame($this->productDetailWhitelist(), array_keys($createResponse->json('data.product')));
        $this->assertNoSensitiveKeys($createResponse->json());

        $updateResponse = $this->putJson('/api/v2/admin/products/'.$productId, array_merge(
            $this->productPayload($secondGroup),
            ['custom_display_name' => 'V2 Updated Product']
        ))
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('message', '商品更新成功')
            ->assertJsonPath('data.product.display.custom_display_name', 'V2 Updated Product');

        $this->assertSame($this->productDetailWhitelist(), array_keys($updateResponse->json('data.product')));
        $this->assertNoSensitiveKeys($updateResponse->json());

        $this->deleteJson('/api/v2/admin/products/'.$productId)
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.detail.product.lifecycle_status', 'deleted');

        $this->assertSoftDeleted('products', ['id' => $productId]);

        $restoreResponse = $this->postJson('/api/v2/admin/products/'.$productId.'/restorations')
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('message', '商品已恢复')
            ->assertJsonPath('data.product.lifecycle.lifecycle_status', 'active');

        $this->assertSame($this->productDetailWhitelist(), array_keys($restoreResponse->json('data.product')));

        $this->deleteJson('/api/v2/admin/products/'.$productId)->assertOk();

        $this->deleteJson('/api/v2/admin/products/'.$productId.'/force')
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.detail.product.lifecycle_status', 'purged');

        $this->assertDatabaseMissing('products', ['id' => $productId]);
    }

    public function test_product_batch_actions_use_v2_routes_validation_and_sanitized_payloads(): void
    {
        $product = $this->createProduct();
        $secondGroup = $product->secondProductGroup ?: SecondProductGroup::query()->findOrFail((int) $product->second_product_group_id);

        $this->app->instance(AdminCatalogActionV2Service::class, new class extends AdminCatalogActionV2Service
        {
            public function __construct() {}

            public function reorderProduct(array $payload): array
            {
                return [
                    'product_id' => (int) $payload['product_id'],
                    'target_second_product_group_id' => (int) $payload['target_second_product_group_id'],
                    'position' => (string) $payload['position'],
                    'raw_response' => 'must-not-leak',
                ];
            }

            public function splitPreview(array $payload): array
            {
                return ['requested_count' => count($payload['product_ids'] ?? []), 'items' => [['source_product_id' => (int) ($payload['product_ids'][0] ?? 0), 'secret' => 'must-not-leak']]];
            }

            public function splitProducts(array $payload): array
            {
                return ['requested_count' => count($payload['product_ids'] ?? []), 'created_count' => 1, 'api_key' => 'must-not-leak'];
            }

            public function batchUpdateCategory(array $payload): array
            {
                return ['updated_count' => count($payload['product_ids'] ?? []), 'target_second_product_group_id' => (int) $payload['target_second_product_group_id']];
            }

            public function batchUpdateProvisionHostname(array $payload, array $context): array
            {
                return ['updated_count' => count($payload['product_ids'] ?? []), 'provision_hostname' => $payload['provision_hostname'], 'operator_name' => $context['operator_name'] ?? ''];
            }

            public function pullTrafficPackageCatalog(array $payload): array
            {
                return ['items' => [['name' => 'Traffic 100G', 'token' => 'must-not-leak']], 'source_product_id' => (int) ($payload['source_product_id'] ?? 0)];
            }
        });

        Sanctum::actingAs($this->createAdmin([AdminPermissions::PRODUCT_MANAGE]));

        $reorderPayload = [
            'product_id' => (int) $product->id,
            'target_second_product_group_id' => (int) $secondGroup->id,
            'position' => 'append',
        ];

        $this->postJson('/api/v2/admin/products/reorders?per_page=20', $reorderPayload)
            ->assertUnprocessable()
            ->assertJsonPath('code', 42200)
            ->assertJsonStructure(['data' => ['errors' => ['per_page']]]);

        $reorderResponse = $this->postJson('/api/v2/admin/products/reorders', $reorderPayload)
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.product_id', $product->id)
            ->assertJsonMissingPath('data.raw_response');

        $splitPreviewResponse = $this->postJson('/api/v2/admin/products/split-previews', ['product_ids' => [(int) $product->id]])
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.requested_count', 1)
            ->assertJsonMissingPath('data.items.0.secret');

        $splitResponse = $this->postJson('/api/v2/admin/products/splits', ['product_ids' => [(int) $product->id]])
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.created_count', 1)
            ->assertJsonMissingPath('data.api_key');

        $categoryResponse = $this->postJson('/api/v2/admin/products/category-batches', [
            'product_ids' => [(int) $product->id],
            'target_second_product_group_id' => (int) $secondGroup->id,
        ])
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.updated_count', 1);

        $hostnameResponse = $this->postJson('/api/v2/admin/products/provision-hostname-batches', [
            'product_ids' => [(int) $product->id],
            'provision_hostname' => ['mode' => 'system', 'length' => 12],
        ])
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.updated_count', 1);

        Sanctum::actingAs($this->createAdmin([AdminPermissions::PRODUCT_SYNC]));

        $trafficResponse = $this->postJson('/api/v2/admin/products/traffic-package-pulls', [
            'second_product_group_id' => (int) $secondGroup->id,
            'source_product_id' => (int) $product->id,
        ])
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.items.0.name', 'Traffic 100G')
            ->assertJsonMissingPath('data.items.0.token');

        foreach ([$reorderResponse, $splitPreviewResponse, $splitResponse, $categoryResponse, $hostnameResponse, $trafficResponse] as $response) {
            $this->assertNoSensitiveKeys($response->json());
            $this->assertLessThan(100 * 1024, strlen((string) $response->getContent()));
        }
    }

    private function createProduct(): Product
    {
        $secondGroup = $this->createSecondGroup();

        return Product::query()->create($this->productPayload($secondGroup))->refresh()->load('secondProductGroup');
    }

    private function createSecondGroup(): SecondProductGroup
    {
        $suffix = bin2hex(random_bytes(4));
        $firstGroup = FirstProductGroup::query()->create([
            'code' => 'v2-core-'.$suffix,
            'name' => 'V2 Core '.$suffix,
            'slug' => 'v2-core-'.$suffix,
            'description' => 'V2 Core',
            'sort_order' => 1,
            'is_visible' => 1,
            'is_system' => 0,
            'legacy_product_type' => 'v2-core-'.$suffix,
        ]);

        return SecondProductGroup::query()->create([
            'first_product_group_id' => (int) $firstGroup->id,
            'name' => 'V2 Core Second '.$suffix,
            'slug' => 'v2-core-second-'.$suffix,
            'description' => 'V2 Core Second',
            'sort_order' => 1,
            'is_visible' => 1,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function productPayload(SecondProductGroup $secondGroup): array
    {
        $firstGroup = $secondGroup->firstProductGroup ?: FirstProductGroup::query()->findOrFail((int) $secondGroup->first_product_group_id);

        return [
            'first_product_group_id' => (int) $firstGroup->id,
            'second_product_group_id' => (int) $secondGroup->id,
            'service_type_code' => (string) $firstGroup->code,
            'custom_display_name' => 'V2 Core Product',
            'product_type' => (string) $firstGroup->code,
            'pricing' => ['monthly' => '19.00', 'quarterly' => '57.00'],
            'setup_fee' => '0.00',
            'purchase_requires' => ['require_phone' => true],
            'config_options' => [['field' => 'cpu', 'name' => 'CPU', 'api_key' => 'must-not-leak']],
            'stock' => -1,
            'status' => 1,
            'sort_order' => 1,
            'auto_setup' => 1,
        ];
    }

    private function createAdmin(array $permissions): AdminUser
    {
        $suffix = bin2hex(random_bytes(4));
        $role = Role::query()->create([
            'name' => 'v2-product-core-'.$suffix,
            'label' => 'V2 Product Core',
            'permissions' => $permissions,
        ]);

        return AdminUser::query()->create([
            'username' => 'v2-product-core-'.$suffix,
            'password' => 'Temp@123456',
            'role_id' => (int) $role->id,
            'nickname' => 'V2 Product Core',
            'email' => 'v2-product-core-'.$suffix.'@example.com',
            'status' => 1,
        ]);
    }

    /**
     * @return list<string>
     */
    private function productDetailWhitelist(): array
    {
        return [
            'id',
            'display',
            'classification',
            'pricing',
            'configuration',
            'purchase_requirements',
            'provisioning',
            'upstream_binding',
            'statistics',
            'lifecycle',
            'timestamps',
        ];
    }

    private function assertNoSensitiveKeys(mixed $payload): void
    {
        if (! is_array($payload)) {
            return;
        }

        foreach ($payload as $key => $value) {
            if (is_string($key)) {
                foreach (['password', 'secret', 'api_key', 'token', 'raw_response', 'third_party_response'] as $needle) {
                    $this->assertStringNotContainsString($needle, strtolower($key));
                }
            }

            $this->assertNoSensitiveKeys($value);
        }
    }
}
