<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FirstProductGroup;
use App\Models\Product;
use App\Models\SecondProductGroup;
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
        Schema::connection('sqlite')->dropIfExists('third_product_groups');
        Schema::connection('sqlite')->dropIfExists('second_product_groups');
        Schema::connection('sqlite')->dropIfExists('first_product_groups');
        Schema::connection('sqlite')->dropIfExists('product_groups');

        parent::tearDown();
    }

    public function test_category_visibility_cascades_to_child_categories_and_products(): void
    {
        $root = FirstProductGroup::query()->create([
            'code' => 'vps',
            'name' => 'Visibility root',
            'slug' => 'visibility-root',
            'is_visible' => 1,
            'sort_order' => 0,
        ]);

        $child = SecondProductGroup::query()->create([
            'first_product_group_id' => (int) $root->id,
            'name' => 'Visibility child',
            'slug' => 'visibility-child',
            'is_visible' => 1,
            'sort_order' => 1,
        ]);

        $other = FirstProductGroup::query()->create([
            'code' => 'vps-other',
            'name' => 'Visibility other',
            'slug' => 'visibility-other',
            'is_visible' => 1,
            'sort_order' => 2,
        ]);

        $rootProduct = $this->createProduct((int) $root->id, null, 'Root product');
        $childProduct = $this->createProduct((int) $root->id, (int) $child->id, 'Child product');
        $otherProduct = $this->createProduct((int) $other->id, null, 'Other product');

        app(ProductCategoryService::class)->updateCategory((int) $root->id, [
            'level' => 1,
            'name' => (string) $root->name,
            'service_type_code' => 'vps',
            'sort_order' => 0,
            'is_visible' => 0,
        ]);

        $this->assertDatabaseHas('first_product_groups', [
            'id' => (int) $root->id,
            'is_visible' => 0,
        ]);
        $this->assertDatabaseHas('second_product_groups', [
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

    private function createProduct(int $firstGroupId, ?int $secondGroupId, string $name): Product
    {
        return Product::query()->create([
            'first_product_group_id' => $firstGroupId,
            'second_product_group_id' => $secondGroupId,
            'service_type_code' => 'vps',
            'name' => $name,
            'custom_display_name' => $name,
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
