<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\ProductCatalog\ProductCategoryService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductCategoryVisibilityCascadeTest extends TestCase
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
        Schema::connection('sqlite')->dropIfExists('settings');
        Schema::connection('sqlite')->dropIfExists('products');
        Schema::connection('sqlite')->dropIfExists('product_groups');

        parent::tearDown();
    }

    public function test_category_visibility_cascades_to_child_categories_and_products(): void
    {
        $root = ProductCategory::query()->create([
            'parent_id' => null,
            'product_type' => 'vps',
            'name' => 'Visibility root',
            'slug' => 'visibility-root',
            'slogan' => '',
            'is_visible' => 1,
            'sort_order' => 0,
        ]);

        $child = ProductCategory::query()->create([
            'parent_id' => (int) $root->id,
            'product_type' => 'vps',
            'name' => 'Visibility child',
            'slug' => 'visibility-child',
            'slogan' => '',
            'is_visible' => 1,
            'sort_order' => 1,
        ]);

        $other = ProductCategory::query()->create([
            'parent_id' => null,
            'product_type' => 'vps',
            'name' => 'Visibility other',
            'slug' => 'visibility-other',
            'slogan' => '',
            'is_visible' => 1,
            'sort_order' => 2,
        ]);

        $rootProduct = $this->createProduct((int) $root->id, 'Root product');
        $childProduct = $this->createProduct((int) $child->id, 'Child product');
        $otherProduct = $this->createProduct((int) $other->id, 'Other product');

        app(ProductCategoryService::class)->updateCategory($root, [
            'name' => (string) $root->name,
            'product_type' => 'vps',
            'slogan' => '',
            'sort_order' => 0,
            'is_visible' => 0,
        ]);

        $this->assertDatabaseHas('product_groups', [
            'id' => (int) $root->id,
            'is_visible' => 0,
        ]);
        $this->assertDatabaseHas('product_groups', [
            'id' => (int) $child->id,
            'is_visible' => 0,
        ]);
        $this->assertDatabaseHas('products', [
            'id' => (int) $rootProduct->id,
            'status' => 0,
        ]);
        $this->assertDatabaseHas('products', [
            'id' => (int) $childProduct->id,
            'status' => 0,
        ]);
        $this->assertDatabaseHas('products', [
            'id' => (int) $otherProduct->id,
            'status' => 1,
        ]);
    }

    private function createProduct(int $categoryId, string $name): Product
    {
        return Product::query()->create([
            'product_group_id' => $categoryId,
            'name' => $name,
            'product_type' => 'vps',
            'pricing' => ['monthly' => '10.00'],
            'purchase_requires' => [],
            'config_options' => [],
            'stock' => -1,
            'status' => 1,
            'sort_order' => 1,
            'provision_module' => null,
            'auto_setup' => 0,
        ]);
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

        Schema::connection('sqlite')->create('products', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_group_id')->nullable();
            $table->string('name');
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
