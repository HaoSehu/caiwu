<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_groups')) {
            Schema::create('product_groups', function (Blueprint $table): void {
                $table->id()->comment('商品分组ID');
                $table->unsignedBigInteger('parent_id')->nullable()->comment('上级商品分组ID，顶级为NULL');
                $table->unsignedTinyInteger('level')->comment('层级：1=一级，2=二级，3=三级');
                $table->string('code', 50)->nullable()->comment('顶级分组编码，沿用业务类型编码');
                $table->string('product_type', 50)->nullable()->comment('业务商品类型');
                $table->string('name', 100)->comment('分组名称');
                $table->string('slug', 100)->nullable()->comment('同一上级下的URL标识');
                $table->string('description', 255)->nullable()->comment('分组说明');
                $table->string('icon', 100)->nullable()->comment('分组图标');
                $table->string('banner_image', 255)->nullable()->comment('分组横幅图');
                $table->integer('sort_order')->default(0)->comment('排序值');
                $table->unsignedTinyInteger('is_visible')->default(1)->comment('是否前台可见：1=可见，0=隐藏');
                $table->unsignedTinyInteger('is_system')->default(0)->comment('是否系统内置：1=是，0=否');
                $table->string('legacy_product_type', 50)->nullable()->comment('历史product_type值');
                $table->unsignedBigInteger('legacy_product_group_id')->nullable()->comment('历史product_groups ID映射');
                $table->timestamps();

                $table->foreign('parent_id', 'product_groups_parent_fk')
                    ->references('id')
                    ->on('product_groups')
                    ->restrictOnDelete();
                $table->index(['parent_id', 'is_visible', 'sort_order'], 'product_groups_parent_visible_sort_idx');
                $table->index(['level', 'sort_order'], 'product_groups_level_sort_idx');
                $table->index(['code'], 'product_groups_code_idx');
                $table->index(['product_type'], 'product_groups_product_type_idx');
                $table->index(['legacy_product_group_id'], 'product_groups_legacy_group_idx');
                $table->unique(['parent_id', 'slug'], 'product_groups_parent_slug_unique');
            });
        }

        $firstIdMap = [];
        $secondIdMap = [];
        $thirdIdMap = [];

        if (Schema::hasTable('first_product_groups')) {
            foreach (DB::table('first_product_groups')->orderBy('id')->get() as $row) {
                $firstIdMap[(int) $row->id] = DB::table('product_groups')->insertGetId([
                    'parent_id' => null,
                    'level' => 1,
                    'code' => $row->code,
                    'product_type' => $row->product_type ?? $row->code,
                    'name' => $row->name,
                    'slug' => $row->slug,
                    'description' => $row->description,
                    'icon' => $row->icon,
                    'banner_image' => $row->banner_image,
                    'sort_order' => (int) $row->sort_order,
                    'is_visible' => (int) $row->is_visible,
                    'is_system' => (int) $row->is_system,
                    'legacy_product_type' => $row->legacy_product_type,
                    'legacy_product_group_id' => null,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            }
        }

        if (Schema::hasTable('second_product_groups')) {
            foreach (DB::table('second_product_groups')->orderBy('id')->get() as $row) {
                $parentId = $firstIdMap[(int) $row->first_product_group_id] ?? null;
                if ($parentId === null) {
                    throw new RuntimeException('二级商品分组缺失一级映射：'.$row->id);
                }

                $secondIdMap[(int) $row->id] = DB::table('product_groups')->insertGetId([
                    'parent_id' => $parentId,
                    'level' => 2,
                    'code' => null,
                    'product_type' => DB::table('product_groups')->where('id', $parentId)->value('product_type'),
                    'name' => $row->name,
                    'slug' => $row->slug,
                    'description' => $row->description,
                    'icon' => null,
                    'banner_image' => $row->banner_image,
                    'sort_order' => (int) $row->sort_order,
                    'is_visible' => (int) $row->is_visible,
                    'is_system' => 0,
                    'legacy_product_type' => null,
                    'legacy_product_group_id' => $row->legacy_product_group_id,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            }
        }

        if (Schema::hasTable('third_product_groups')) {
            foreach (DB::table('third_product_groups')->orderBy('id')->get() as $row) {
                $parentId = $secondIdMap[(int) $row->second_product_group_id] ?? null;
                if ($parentId === null) {
                    throw new RuntimeException('三级商品分组缺失二级映射：'.$row->id);
                }

                $thirdIdMap[(int) $row->id] = DB::table('product_groups')->insertGetId([
                    'parent_id' => $parentId,
                    'level' => 3,
                    'code' => null,
                    'product_type' => DB::table('product_groups')->where('id', $parentId)->value('product_type'),
                    'name' => $row->name,
                    'slug' => $row->slug,
                    'description' => $row->description,
                    'icon' => null,
                    'banner_image' => $row->banner_image,
                    'sort_order' => (int) $row->sort_order,
                    'is_visible' => (int) $row->is_visible,
                    'is_system' => 0,
                    'legacy_product_type' => null,
                    'legacy_product_group_id' => $row->legacy_product_group_id,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            }
        }

        if (Schema::hasTable('products') && ! Schema::hasColumn('products', 'product_group_id')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->unsignedBigInteger('product_group_id')->nullable()->after('id')->comment('当前所属商品分组ID');
            });
        }

        if (Schema::hasTable('products') && Schema::hasColumn('products', 'product_group_id')) {
            foreach (DB::table('products')->select(['id', 'first_product_group_id', 'second_product_group_id', 'third_product_group_id'])->orderBy('id')->get() as $row) {
                $targetId = null;
                if ((int) ($row->third_product_group_id ?? 0) > 0) {
                    $targetId = $thirdIdMap[(int) $row->third_product_group_id] ?? null;
                }
                if ($targetId === null && (int) ($row->second_product_group_id ?? 0) > 0) {
                    $targetId = $secondIdMap[(int) $row->second_product_group_id] ?? null;
                }
                if ($targetId === null && (int) ($row->first_product_group_id ?? 0) > 0) {
                    $targetId = $firstIdMap[(int) $row->first_product_group_id] ?? null;
                }

                DB::table('products')->where('id', $row->id)->update(['product_group_id' => $targetId]);
            }

            $this->backfillUnmappedProductsToFallbackGroups();

            $missingProducts = DB::table('products')
                ->whereNull('deleted_at')
                ->whereNull('product_group_id')
                ->count();
            if ($missingProducts > 0) {
                throw new RuntimeException('存在未映射商品分组的商品：'.$missingProducts);
            }
        }

        $this->dropProductGroupForeignKeys();

        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table): void {
                foreach ([
                    'idx_products_product_groups',
                    'idx_products_second_product_group_id',
                    'products_group_status_sort_id_idx',
                ] as $index) {
                    if ($this->indexExists('products', $index)) {
                        $table->dropIndex($index);
                    }
                }

                foreach (['first_product_group_id', 'second_product_group_id', 'third_product_group_id'] as $column) {
                    if (Schema::hasColumn('products', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });

            Schema::table('products', function (Blueprint $table): void {
                if (! $this->indexExists('products', 'products_group_status_sort_id_idx')) {
                    $table->index(['product_group_id', 'status', 'sort_order', 'id'], 'products_group_status_sort_id_idx');
                }

                if (! $this->hasForeign('products', 'products_product_group_fk')) {
                    $table->foreign('product_group_id', 'products_product_group_fk')
                        ->references('id')
                        ->on('product_groups')
                        ->restrictOnDelete();
                }
            });
        }

        Schema::dropIfExists('third_product_groups');
        Schema::dropIfExists('second_product_groups');
        Schema::dropIfExists('first_product_groups');

        $this->createCompatibilityViews();
    }

    public function down(): void
    {
        $this->dropCompatibilityViews();

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
                $table->string('product_type', 50)->nullable()->index();
                $table->timestamps();
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
            });
        }

        if (Schema::hasTable('products') && ! Schema::hasColumn('products', 'first_product_group_id')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->unsignedBigInteger('first_product_group_id')->nullable()->after('product_group_id');
                $table->unsignedBigInteger('second_product_group_id')->nullable()->after('first_product_group_id');
                $table->unsignedBigInteger('third_product_group_id')->nullable()->after('second_product_group_id');
            });
        }
    }

    private function dropProductGroupForeignKeys(): void
    {
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table): void {
                foreach ([
                    'fk_products_product_group_id',
                    'fk_products_first_product_group_id',
                    'fk_products_second_product_group_id',
                    'fk_products_third_product_group_id',
                    'products_product_group_fk',
                ] as $foreign) {
                    if ($this->hasForeign('products', $foreign)) {
                        $table->dropForeign($foreign);
                    }
                }
            });
        }

        foreach ([
            'third_product_groups' => ['fk_third_product_groups_second_product_group_id'],
            'second_product_groups' => ['fk_second_product_groups_first_product_group_id'],
        ] as $tableName => $foreignKeys) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName, $foreignKeys): void {
                foreach ($foreignKeys as $foreign) {
                    if ($this->hasForeign($tableName, $foreign)) {
                        $table->dropForeign($foreign);
                    }
                }
            });
        }
    }

    private function backfillUnmappedProductsToFallbackGroups(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasTable('product_groups')) {
            return;
        }

        foreach (DB::table('products')
            ->select(['id', 'product_type', 'service_type_code'])
            ->whereNull('product_group_id')
            ->orderBy('id')
            ->get() as $product) {
            $groupId = $this->fallbackProductGroupId(
                $this->normalizeProductTypeCode($product->product_type ?? $product->service_type_code ?? null)
            );

            DB::table('products')
                ->where('id', $product->id)
                ->update(['product_group_id' => $groupId]);
        }
    }

    private function fallbackProductGroupId(string $code): int
    {
        $rootId = $this->fallbackRootProductGroupId($code);
        $slug = 'legacy-unmapped';

        $existingId = DB::table('product_groups')
            ->where('level', 2)
            ->where('parent_id', $rootId)
            ->where('slug', $slug)
            ->value('id');
        if ($existingId !== null) {
            return (int) $existingId;
        }

        return (int) DB::table('product_groups')->insertGetId([
            'parent_id' => $rootId,
            'level' => 2,
            'code' => null,
            'product_type' => DB::table('product_groups')->where('id', $rootId)->value('product_type') ?: $code,
            'name' => '历史未归档商品',
            'slug' => $slug,
            'description' => '迁移时缺少有效历史分组的商品兜底归档',
            'sort_order' => 9999,
            'is_visible' => 0,
            'is_system' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function fallbackRootProductGroupId(string $code): int
    {
        $existingId = DB::table('product_groups')
            ->where('level', 1)
            ->where(function ($query) use ($code): void {
                $query->where('code', $code)->orWhere('product_type', $code);
            })
            ->value('id');
        if ($existingId !== null) {
            return (int) $existingId;
        }

        return (int) DB::table('product_groups')->insertGetId([
            'parent_id' => null,
            'level' => 1,
            'code' => $code,
            'product_type' => $code,
            'name' => $code === 'other' ? '其他' : $code,
            'slug' => $code,
            'description' => '迁移时自动创建的商品类型兜底分组',
            'sort_order' => 9999,
            'is_visible' => 0,
            'is_system' => 0,
            'legacy_product_type' => $code,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function normalizeProductTypeCode(mixed $value): string
    {
        $code = trim((string) $value);

        return $code !== '' ? $code : 'other';
    }

    private function createCompatibilityViews(): void
    {
        $this->dropCompatibilityViews();

        DB::statement(<<<'SQL'
            CREATE VIEW first_product_groups AS
            SELECT
                id,
                code,
                name,
                slug,
                description,
                icon,
                banner_image,
                sort_order,
                is_visible,
                is_system,
                legacy_product_type,
                product_type,
                level,
                created_at,
                updated_at
            FROM product_groups
            WHERE level = 1
            WITH CASCADED CHECK OPTION
        SQL);

        DB::statement(<<<'SQL'
            CREATE VIEW second_product_groups AS
            SELECT
                id,
                parent_id AS first_product_group_id,
                name,
                slug,
                description,
                banner_image,
                sort_order,
                is_visible,
                legacy_product_group_id,
                product_type,
                level,
                created_at,
                updated_at
            FROM product_groups
            WHERE level = 2
            WITH CASCADED CHECK OPTION
        SQL);

        DB::statement(<<<'SQL'
            CREATE VIEW third_product_groups AS
            SELECT
                id,
                parent_id AS second_product_group_id,
                name,
                slug,
                description,
                banner_image,
                sort_order,
                is_visible,
                legacy_product_group_id,
                product_type,
                level,
                created_at,
                updated_at
            FROM product_groups
            WHERE level = 3
            WITH CASCADED CHECK OPTION
        SQL);
    }

    private function dropCompatibilityViews(): void
    {
        DB::statement('DROP VIEW IF EXISTS third_product_groups');
        DB::statement('DROP VIEW IF EXISTS second_product_groups');
        DB::statement('DROP VIEW IF EXISTS first_product_groups');
    }

    private function hasForeign(string $table, string $foreign): bool
    {
        return ! empty(DB::select('
            SELECT 1
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND CONSTRAINT_NAME = ?
              AND CONSTRAINT_TYPE = "FOREIGN KEY"
            LIMIT 1
        ', [$table, $foreign]));
    }

    private function indexExists(string $table, string $index): bool
    {
        return ! empty(DB::select('
            SELECT 1
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = ?
              AND index_name = ?
            LIMIT 1
        ', [$table, $index]));
    }
};
