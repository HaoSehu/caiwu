<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Services\ProductCatalog\ProductGroupHierarchyService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductGroupHierarchyServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');
        app('db')->setDefaultConnection('sqlite');

        $this->createSchema();
    }

    protected function tearDown(): void
    {
        foreach ([
            'settings',
            'products',
            'third_product_groups',
            'second_product_groups',
            'first_product_groups',
            'product_groups',
        ] as $table) {
            Schema::connection('sqlite')->dropIfExists($table);
        }

        parent::tearDown();
    }

    public function test_it_reports_current_product_group_hierarchy_as_clean(): void
    {
        $rootId = $this->insertProductGroup(null, 1, 'vps', '云服务器');
        $secondId = $this->insertProductGroup($rootId, 2, null, '香港');
        $thirdId = $this->insertProductGroup($secondId, 3, null, '三网精品');

        $product = Product::query()->create([
            'product_group_id' => $thirdId,
            'name' => '8vcpu-16gib',
            'product_type' => 'cloud_server',
            'service_type_code' => 'cloud_server',
            'pricing' => ['monthly' => '88.00'],
            'purchase_requires' => [],
            'config_options' => [],
            'stock' => -1,
            'status' => 1,
            'sort_order' => 1,
            'provision_module' => null,
            'auto_setup' => 0,
        ]);

        $result = app(ProductGroupHierarchyService::class)->checkHierarchy();

        $this->assertDatabaseHas('products', [
            'id' => (int) $product->id,
            'product_group_id' => $thirdId,
            'service_type_code' => 'cloud_server',
        ]);
        $this->assertSame([], $result['blocking_errors']);
        $this->assertSame(1, $result['counts']['root_product_groups']);
        $this->assertSame(1, $result['counts']['second_product_groups']);
        $this->assertSame(1, $result['counts']['third_product_groups']);
    }

    public function test_it_reports_products_with_missing_product_group(): void
    {
        $this->insertProductGroup(null, 1, 'vps', '云服务器');

        $product = Product::query()->create([
            'product_group_id' => 999,
            'name' => 'missing-legacy-group-product',
            'custom_display_name' => '历史缺失分类商品',
            'product_type' => 'cloud_server',
            'service_type_code' => 'cloud_server',
            'pricing' => ['monthly' => '88.00'],
            'purchase_requires' => [],
            'config_options' => [],
            'stock' => -1,
            'status' => 1,
            'sort_order' => 1,
            'provision_module' => null,
            'auto_setup' => 0,
        ]);

        $result = app(ProductGroupHierarchyService::class)->checkHierarchy();

        $this->assertSame(1, $result['counts']['orphan_product_group_products']);
        $this->assertNotSame([], $result['blocking_errors']);
        $this->assertDatabaseHas('products', [
            'id' => (int) $product->id,
            'product_group_id' => 999,
        ]);
    }

    private function insertProductGroup(?int $parentId, int $level, ?string $code, string $name): int
    {
        return (int) DB::table('product_groups')->insertGetId([
            'parent_id' => $parentId,
            'level' => $level,
            'code' => $code,
            'product_type' => 'cloud_server',
            'name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)).'-'.$level,
            'sort_order' => $level,
            'is_visible' => 1,
            'is_system' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createSchema(): void
    {
        Schema::connection('sqlite')->create('product_groups', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->integer('level');
            $table->string('code', 50)->nullable();
            $table->string('product_type', 50)->nullable();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('description')->nullable();
            $table->string('icon')->nullable();
            $table->string('banner_image')->nullable();
            $table->integer('is_visible')->default(1);
            $table->integer('is_system')->default(0);
            $table->integer('sort_order')->default(0);
            $table->string('legacy_product_type', 50)->nullable();
            $table->unsignedBigInteger('legacy_product_group_id')->nullable();
            $table->timestamps();
        });

        Schema::connection('sqlite')->create('first_product_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->string('slug', 100)->nullable()->unique();
            $table->string('description', 255)->nullable();
            $table->string('icon', 100)->nullable();
            $table->string('banner_image', 255)->nullable();
            $table->integer('sort_order')->default(0);
            $table->integer('is_visible')->default(1);
            $table->integer('is_system')->default(0);
            $table->string('legacy_product_type', 50)->nullable();
            $table->timestamps();
        });

        Schema::connection('sqlite')->create('second_product_groups', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('first_product_group_id');
            $table->string('name', 100);
            $table->string('slug', 100)->nullable();
            $table->string('description', 255)->nullable();
            $table->string('banner_image', 255)->nullable();
            $table->integer('sort_order')->default(0);
            $table->integer('is_visible')->default(1);
            $table->unsignedBigInteger('legacy_product_group_id')->nullable()->unique();
            $table->timestamps();
        });

        Schema::connection('sqlite')->create('third_product_groups', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('second_product_group_id');
            $table->string('name', 100);
            $table->string('slug', 100)->nullable();
            $table->string('description', 255)->nullable();
            $table->string('banner_image', 255)->nullable();
            $table->integer('sort_order')->default(0);
            $table->integer('is_visible')->default(1);
            $table->unsignedBigInteger('legacy_product_group_id')->nullable()->unique();
            $table->timestamps();
        });

        Schema::connection('sqlite')->create('products', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_group_id')->nullable();
            $table->string('service_type_code', 50)->nullable();
            $table->string('name')->nullable();
            $table->string('custom_display_name')->nullable();
            $table->string('product_type')->nullable();
            $table->json('pricing')->nullable();
            $table->json('purchase_requires')->nullable();
            $table->json('config_options')->nullable();
            $table->integer('stock')->default(-1);
            $table->integer('status')->default(1);
            $table->integer('sort_order')->default(0);
            $table->string('provision_module')->nullable();
            $table->integer('auto_setup')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::connection('sqlite')->create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('group_key')->nullable();
            $table->string('item_key')->nullable();
            $table->text('item_value')->nullable();
            $table->timestamps();
        });
    }
}
