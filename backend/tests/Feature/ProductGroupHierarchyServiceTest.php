<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
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

    public function test_it_backfills_three_level_hierarchy_from_legacy_product_groups(): void
    {
        $root = ProductCategory::query()->create([
            'parent_id' => null,
            'product_type' => 'vps',
            'name' => '香港',
            'slug' => 'hong-kong',
            'slogan' => '香港云服务器',
            'is_visible' => 1,
            'sort_order' => 1,
        ]);

        $child = ProductCategory::query()->create([
            'parent_id' => (int) $root->id,
            'product_type' => 'vps',
            'name' => '三网精品',
            'slug' => 'premium',
            'slogan' => '',
            'is_visible' => 1,
            'sort_order' => 2,
        ]);

        $product = Product::query()->create([
            'name' => '8vcpu-16gib',
            'product_type' => 'vps',
            'pricing' => ['monthly' => '88.00'],
            'purchase_requires' => [],
            'config_options' => [],
            'stock' => -1,
            'status' => 1,
            'sort_order' => 1,
            'provision_module' => null,
            'auto_setup' => 0,
        ]);
        DB::table('products')
            ->where('id', (int) $product->id)
            ->update(['product_group_id' => (int) $child->id]);

        $result = app(ProductGroupHierarchyService::class)->syncAllFromLegacy(100);

        $firstGroup = DB::table('first_product_groups')->where('code', 'vps')->first();
        $secondGroup = DB::table('second_product_groups')->where('legacy_product_group_id', (int) $root->id)->first();
        $thirdGroup = DB::table('third_product_groups')->where('legacy_product_group_id', (int) $child->id)->first();

        $this->assertTrue($result['tables_ready']);
        $this->assertNotNull($firstGroup);
        $this->assertNotNull($secondGroup);
        $this->assertNotNull($thirdGroup);
        $this->assertSame((int) $firstGroup->id, (int) $secondGroup->first_product_group_id);
        $this->assertSame((int) $secondGroup->id, (int) $thirdGroup->second_product_group_id);

        $this->assertDatabaseHas('products', [
            'id' => (int) $product->id,
            'first_product_group_id' => (int) $firstGroup->id,
            'second_product_group_id' => (int) $secondGroup->id,
            'third_product_group_id' => (int) $thirdGroup->id,
            'service_type_code' => 'cloud_server',
        ]);

        $this->assertSame([], app(ProductGroupHierarchyService::class)->checkHierarchy()['blocking_errors']);
    }

    public function test_it_archives_products_with_missing_legacy_group(): void
    {
        $product = Product::query()->create([
            'name' => 'missing-legacy-group-product',
            'custom_display_name' => '历史缺失分类商品',
            'product_type' => 'vps',
            'pricing' => ['monthly' => '88.00'],
            'purchase_requires' => [],
            'config_options' => [],
            'stock' => -1,
            'status' => 1,
            'sort_order' => 1,
            'provision_module' => null,
            'auto_setup' => 0,
        ]);
        DB::table('products')
            ->where('id', (int) $product->id)
            ->update(['product_group_id' => 999]);

        $result = app(ProductGroupHierarchyService::class)->syncAllFromLegacy(100);
        $product->refresh();

        $firstGroup = DB::table('first_product_groups')->where('code', 'vps')->first();
        $secondGroup = DB::table('second_product_groups')
            ->where('first_product_group_id', (int) $firstGroup->id)
            ->where('slug', 'legacy-unmapped-vps')
            ->first();

        $this->assertSame(1, $result['products_missing_legacy_group']);
        $this->assertSame(1, $result['products_repaired_missing_legacy_group']);
        $this->assertNull($product->product_group_id);
        $this->assertSame((int) $firstGroup->id, (int) $product->first_product_group_id);
        $this->assertSame((int) $secondGroup->id, (int) $product->second_product_group_id);
        $this->assertNull($product->third_product_group_id);
        $this->assertSame('cloud_server', $product->service_type_code);
        $this->assertSame([], app(ProductGroupHierarchyService::class)->checkHierarchy()['blocking_errors']);
    }

    private function createSchema(): void
    {
        Schema::connection('sqlite')->create('product_groups', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('parent_group_id')->nullable();
            $table->string('product_type')->nullable();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('slogan')->nullable();
            $table->integer('is_visible')->default(1);
            $table->integer('sort_order')->default(0);
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
            $table->unsignedBigInteger('first_product_group_id')->nullable();
            $table->unsignedBigInteger('second_product_group_id')->nullable();
            $table->unsignedBigInteger('third_product_group_id')->nullable();
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
