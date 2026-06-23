<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('first_product_groups')) {
            Schema::create('first_product_groups', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 50)->unique();
                $table->string('name', 100);
                $table->string('slug', 100)->nullable()->unique();
                $table->string('description', 255)->nullable();
                $table->string('icon', 100)->nullable();
                $table->string('banner_image', 255)->nullable();
                $table->integer('sort_order')->default(0);
                $table->unsignedTinyInteger('is_visible')->default(1);
                $table->unsignedTinyInteger('is_system')->default(0);
                $table->string('legacy_product_type', 50)->nullable()->index();
                $table->timestamps();

                $table->index(['is_visible', 'sort_order'], 'idx_first_product_groups_visible_sort');
            });
        }

        if (! Schema::hasTable('second_product_groups')) {
            Schema::create('second_product_groups', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('first_product_group_id');
                $table->string('name', 100);
                $table->string('slug', 100)->nullable();
                $table->string('description', 255)->nullable();
                $table->string('banner_image', 255)->nullable();
                $table->integer('sort_order')->default(0);
                $table->unsignedTinyInteger('is_visible')->default(1);
                $table->unsignedBigInteger('legacy_product_group_id')->nullable()->unique();
                $table->timestamps();

                $table->index(['first_product_group_id', 'sort_order'], 'idx_second_product_groups_first_sort');
                $table->index(['first_product_group_id', 'is_visible', 'sort_order'], 'idx_second_product_groups_first_visible_sort');
                $table->unique(['first_product_group_id', 'slug'], 'uniq_second_product_groups_first_slug');
            });
        }

        if (! Schema::hasTable('third_product_groups')) {
            Schema::create('third_product_groups', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('second_product_group_id');
                $table->string('name', 100);
                $table->string('slug', 100)->nullable();
                $table->string('description', 255)->nullable();
                $table->string('banner_image', 255)->nullable();
                $table->integer('sort_order')->default(0);
                $table->unsignedTinyInteger('is_visible')->default(1);
                $table->unsignedBigInteger('legacy_product_group_id')->nullable()->unique();
                $table->timestamps();

                $table->index(['second_product_group_id', 'sort_order'], 'idx_third_product_groups_second_sort');
                $table->index(['second_product_group_id', 'is_visible', 'sort_order'], 'idx_third_product_groups_second_visible_sort');
                $table->unique(['second_product_group_id', 'slug'], 'uniq_third_product_groups_second_slug');
            });
        }

        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table): void {
                if (! Schema::hasColumn('products', 'first_product_group_id')) {
                    $table->unsignedBigInteger('first_product_group_id')->nullable()->after('product_group_id');
                }

                if (! Schema::hasColumn('products', 'second_product_group_id')) {
                    $table->unsignedBigInteger('second_product_group_id')->nullable()->after('first_product_group_id');
                }

                if (! Schema::hasColumn('products', 'third_product_group_id')) {
                    $table->unsignedBigInteger('third_product_group_id')->nullable()->after('second_product_group_id');
                }

                if (! Schema::hasColumn('products', 'service_type_code')) {
                    $table->string('service_type_code', 50)->nullable()->after('third_product_group_id');
                }
            });

            if (
                Schema::hasColumn('products', 'first_product_group_id')
                && Schema::hasColumn('products', 'second_product_group_id')
                && Schema::hasColumn('products', 'third_product_group_id')
                && Schema::hasColumn('products', 'status')
                && Schema::hasColumn('products', 'sort_order')
            ) {
                try {
                    Schema::table('products', function (Blueprint $table): void {
                        $table->index(
                            ['first_product_group_id', 'second_product_group_id', 'third_product_group_id', 'status', 'sort_order'],
                            'idx_products_product_groups'
                        );
                    });
                } catch (Throwable) {
                    // Index may already exist on partially migrated databases.
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table): void {
                try {
                    $table->dropIndex('idx_products_product_groups');
                } catch (Throwable) {
                    // Index may not exist on partially migrated databases.
                }

                foreach ([
                    'service_type_code',
                    'third_product_group_id',
                    'second_product_group_id',
                    'first_product_group_id',
                ] as $column) {
                    if (Schema::hasColumn('products', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('third_product_groups');
        Schema::dropIfExists('second_product_groups');
        Schema::dropIfExists('first_product_groups');
    }
};
