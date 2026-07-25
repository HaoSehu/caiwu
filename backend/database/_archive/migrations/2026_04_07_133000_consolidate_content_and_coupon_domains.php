<?php

use App\Models\ContentArticle;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->consolidateContentDomain();
        $this->consolidateCouponDomain();
    }

    public function down(): void
    {
        // 本次为高集中化收敛迁移，回滚需按备份或人工迁移方案处理。
    }

    private function consolidateContentDomain(): void
    {
        if (! Schema::hasTable('content_articles')) {
            return;
        }

        Schema::table('content_articles', function (Blueprint $table) {
            if (! Schema::hasColumn('content_articles', 'node_type')) {
                $table->string('node_type', 20)->default(ContentArticle::NODE_TYPE_ARTICLE)->after('content_type');
            }
        });

        DB::table('content_articles')
            ->whereNull('node_type')
            ->update(['node_type' => ContentArticle::NODE_TYPE_ARTICLE]);

        $this->dropIndexIfExists('content_articles', 'content_articles_slug_unique');
        $this->dropIndexIfExists('content_articles', 'uniq_content_articles_type_node_slug');
        $this->dropIndexIfExists('content_articles', 'idx_content_type_node_status_publish');

        DB::statement('CREATE UNIQUE INDEX uniq_content_articles_type_node_slug ON content_articles (content_type, node_type, slug)');
        DB::statement('CREATE INDEX idx_content_type_node_status_publish ON content_articles (content_type, node_type, status, publish_at)');

        if (! Schema::hasTable('content_categories')) {
            return;
        }

        $legacyCategories = DB::table('content_categories')
            ->orderBy('id')
            ->get();

        $categoryIdMap = [];

        foreach ($legacyCategories as $legacyCategory) {
            $newCategoryId = DB::table('content_articles')->insertGetId([
                'content_type' => (string) $legacyCategory->content_type,
                'node_type' => ContentArticle::NODE_TYPE_CATEGORY,
                'category_id' => null,
                'title' => (string) $legacyCategory->name,
                'slug' => (string) $legacyCategory->slug,
                'summary' => $legacyCategory->description,
                'content' => '',
                'category_name' => null,
                'keywords' => null,
                'status' => (int) ($legacyCategory->status ?? 1),
                'is_pinned' => 0,
                'is_recommended' => 0,
                'sort_order' => (int) ($legacyCategory->sort_order ?? 0),
                'view_count' => 0,
                'publish_at' => null,
                'last_published_at' => null,
                'created_by' => $legacyCategory->created_by,
                'updated_by' => $legacyCategory->updated_by,
                'operator' => 'content-domain-merge',
                'remark' => sprintf('merged from content_categories#%d', (int) $legacyCategory->id),
                'trace_id' => sprintf('content-category-legacy-%d', (int) $legacyCategory->id),
                'created_at' => $legacyCategory->created_at,
                'updated_at' => $legacyCategory->updated_at,
                'deleted_at' => null,
            ]);

            $categoryIdMap[(int) $legacyCategory->id] = [
                'id' => $newCategoryId,
                'name' => (string) $legacyCategory->name,
            ];
        }

        foreach ($categoryIdMap as $legacyCategoryId => $categoryMeta) {
            DB::table('content_articles')
                ->where('node_type', ContentArticle::NODE_TYPE_ARTICLE)
                ->where('category_id', $legacyCategoryId)
                ->update([
                    'category_id' => (int) $categoryMeta['id'],
                    'category_name' => (string) $categoryMeta['name'],
                ]);
        }

        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('content_categories');
        Schema::enableForeignKeyConstraints();
    }

    private function consolidateCouponDomain(): void
    {
        $this->ensureCouponsTable();
        $this->ensureUserCouponsTable();

        $couponIdMapByCodeId = [];

        if (Schema::hasTable('coupon_codes') && Schema::hasTable('coupon_templates')) {
            $templateRows = DB::table('coupon_templates')->get()->keyBy('id');
            $templateProductMap = Schema::hasTable('coupon_template_products')
                ? DB::table('coupon_template_products')
                    ->orderBy('template_id')
                    ->get()
                    ->groupBy('template_id')
                    ->map(fn ($items) => $items->pluck('product_id')->map(fn ($id) => (int) $id)->values()->all())
                    ->all()
                : [];
            $templateBillingCycleMap = Schema::hasTable('coupon_template_billing_cycles')
                ? DB::table('coupon_template_billing_cycles')
                    ->orderBy('template_id')
                    ->get()
                    ->groupBy('template_id')
                    ->map(fn ($items) => $items->pluck('billing_cycle')->map(fn ($cycle) => (string) $cycle)->values()->all())
                    ->all()
                : [];

            $codeRows = DB::table('coupon_codes')
                ->orderBy('id')
                ->get();

            foreach ($codeRows as $codeRow) {
                $template = $templateRows->get((int) $codeRow->template_id);
                if (! $template) {
                    continue;
                }

                $preferredCouponId = (int) ($codeRow->legacy_coupon_id ?? 0);
                $couponPayload = [
                    'coupon_campaign_id' => $this->resolveNullableInt($codeRow->campaign_id ?? null),
                    'name' => (string) $template->name,
                    'code' => (string) $codeRow->code,
                    'description' => $template->description,
                    'distribution_type' => (string) ($template->distribution_type ?? 'public'),
                    'discount_scope' => (string) ($template->discount_scope ?? 'first_month'),
                    'discount_type' => (string) ($template->discount_type ?? 'fixed'),
                    'discount_value' => $this->normalizeDecimal($template->discount_value ?? 0),
                    'min_amount' => $this->normalizeDecimal($template->min_amount ?? 0),
                    'max_discount_amount' => $this->nullableDecimal($template->max_discount_amount ?? null),
                    'billing_cycles' => json_encode($templateBillingCycleMap[(int) $template->id] ?? [], JSON_UNESCAPED_UNICODE),
                    'product_ids' => json_encode($templateProductMap[(int) $template->id] ?? [], JSON_UNESCAPED_UNICODE),
                    'first_order_only' => (int) (($template->first_order_only ?? 0) ? 1 : 0),
                    'total_usage_limit' => $this->resolveNullableInt($template->total_usage_limit ?? null),
                    'per_user_limit' => $this->resolveNullableInt($template->per_user_limit ?? null),
                    'used_count' => 0,
                    'status' => (int) ($template->status ?? 1),
                    'sort_order' => (int) ($template->sort_order ?? 0),
                    'starts_at' => $template->starts_at ?? $codeRow->issued_at,
                    'expires_at' => $codeRow->expires_at ?? $template->expires_at,
                    'remark' => $template->remark,
                    'operator' => $template->operator,
                    'trace_id' => $template->trace_id,
                    'created_at' => $codeRow->created_at ?? $template->created_at ?? now(),
                    'updated_at' => $codeRow->updated_at ?? $template->updated_at ?? now(),
                ];

                if ($preferredCouponId > 0 && ! DB::table('coupons')->where('id', $preferredCouponId)->exists()) {
                    DB::table('coupons')->insert([
                        'id' => $preferredCouponId,
                        ...$couponPayload,
                    ]);
                    $couponId = $preferredCouponId;
                } else {
                    $existingCouponId = DB::table('coupons')
                        ->where('code', (string) $codeRow->code)
                        ->value('id');

                    if ($existingCouponId) {
                        DB::table('coupons')
                            ->where('id', (int) $existingCouponId)
                            ->update($couponPayload);
                        $couponId = (int) $existingCouponId;
                    } else {
                        $couponId = (int) DB::table('coupons')->insertGetId($couponPayload);
                    }
                }

                $couponIdMapByCodeId[(int) $codeRow->id] = $couponId;
            }
        }

        if (Schema::hasTable('coupon_assignments')) {
            $assignmentRows = DB::table('coupon_assignments')
                ->orderBy('id')
                ->get();

            foreach ($assignmentRows as $assignmentRow) {
                $couponId = $couponIdMapByCodeId[(int) $assignmentRow->coupon_code_id] ?? null;
                if (! $couponId) {
                    continue;
                }

                $preferredUserCouponId = (int) ($assignmentRow->legacy_user_coupon_id ?? 0);
                $userCouponPayload = [
                    'coupon_id' => (int) $couponId,
                    'user_id' => (int) $assignmentRow->user_id,
                    'receive_type' => (string) ($assignmentRow->receive_type ?? 'claim'),
                    'status' => (int) (($assignmentRow->assignment_status ?? 1) === 1 ? 1 : 0),
                    'claimed_at' => $assignmentRow->claimed_at,
                    'granted_at' => $assignmentRow->granted_at,
                    'last_used_at' => $assignmentRow->used_at,
                    'remark' => $assignmentRow->remark,
                    'operator' => $assignmentRow->operator,
                    'trace_id' => $assignmentRow->trace_id,
                    'created_at' => $assignmentRow->created_at ?? now(),
                    'updated_at' => $assignmentRow->updated_at ?? now(),
                ];

                if ($preferredUserCouponId > 0 && ! DB::table('user_coupons')->where('id', $preferredUserCouponId)->exists()) {
                    DB::table('user_coupons')->insert([
                        'id' => $preferredUserCouponId,
                        ...$userCouponPayload,
                    ]);
                    $userCouponId = $preferredUserCouponId;
                } else {
                    $existingUserCouponId = DB::table('user_coupons')
                        ->where('coupon_id', (int) $couponId)
                        ->where('user_id', (int) $assignmentRow->user_id)
                        ->value('id');

                    if ($existingUserCouponId) {
                        DB::table('user_coupons')
                            ->where('id', (int) $existingUserCouponId)
                            ->update($userCouponPayload);
                        $userCouponId = (int) $existingUserCouponId;
                    } else {
                        $userCouponId = (int) DB::table('user_coupons')->insertGetId($userCouponPayload);
                    }
                }

                if (
                    Schema::hasTable('orders')
                    && Schema::hasColumn('orders', 'user_coupon_id')
                    && (int) ($assignmentRow->used_order_id ?? 0) > 0
                ) {
                    DB::table('orders')
                        ->where('id', (int) $assignmentRow->used_order_id)
                        ->update(['user_coupon_id' => $userCouponId]);
                }
            }
        }

        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'coupon_id')) {
            foreach ($couponIdMapByCodeId as $legacyCodeId => $couponId) {
                $codeRow = DB::table('coupon_codes')->where('id', $legacyCodeId)->first();
                if (! $codeRow || (int) ($codeRow->used_order_id ?? 0) <= 0) {
                    continue;
                }

                $updatePayload = ['coupon_id' => (int) $couponId];
                if (Schema::hasColumn('orders', 'coupon_code')) {
                    $updatePayload['coupon_code'] = (string) $codeRow->code;
                }

                DB::table('orders')
                    ->where('id', (int) $codeRow->used_order_id)
                    ->update($updatePayload);
            }
        }

        if (Schema::hasTable('coupon_assignments')) {
            $usedCountMap = DB::table('coupon_assignments')
                ->selectRaw('coupon_code_id, COUNT(*) as aggregate_count')
                ->where('assignment_status', 2)
                ->groupBy('coupon_code_id')
                ->pluck('aggregate_count', 'coupon_code_id');

            foreach ($couponIdMapByCodeId as $legacyCodeId => $couponId) {
                DB::table('coupons')
                    ->where('id', (int) $couponId)
                    ->update(['used_count' => (int) ($usedCountMap[$legacyCodeId] ?? 0)]);
            }
        }

        if (Schema::hasTable('coupon_campaigns') && Schema::hasTable('coupon_campaign_weekdays')) {
            $campaignWeekdays = DB::table('coupon_campaign_weekdays')
                ->orderBy('campaign_id')
                ->orderBy('weekday')
                ->get()
                ->groupBy('campaign_id');

            foreach ($campaignWeekdays as $campaignId => $weekdayRows) {
                DB::table('coupon_campaigns')
                    ->where('id', (int) $campaignId)
                    ->update([
                        'weekdays' => json_encode(
                            $weekdayRows->pluck('weekday')->map(fn ($weekday) => (int) $weekday)->values()->all(),
                            JSON_UNESCAPED_UNICODE
                        ),
                    ]);
            }
        }

        if (Schema::hasTable('coupon_campaigns') && Schema::hasTable('coupons')) {
            $campaignLastCouponMap = DB::table('coupons')
                ->selectRaw('coupon_campaign_id, MAX(id) as last_coupon_id')
                ->whereNotNull('coupon_campaign_id')
                ->groupBy('coupon_campaign_id')
                ->pluck('last_coupon_id', 'coupon_campaign_id');

            foreach ($campaignLastCouponMap as $campaignId => $lastCouponId) {
                DB::table('coupon_campaigns')
                    ->where('id', (int) $campaignId)
                    ->update(['last_coupon_id' => (int) $lastCouponId]);
            }
        }

        Schema::disableForeignKeyConstraints();

        if (Schema::hasTable('coupon_campaigns') && Schema::hasColumn('coupon_campaigns', 'template_id')) {
            try {
                Schema::table('coupon_campaigns', function (Blueprint $table) {
                    $table->dropForeign(['template_id']);
                });
            } catch (Throwable) {
            }

            Schema::table('coupon_campaigns', function (Blueprint $table) {
                $table->dropColumn('template_id');
            });
        }

        Schema::dropIfExists('coupon_assignments');
        Schema::dropIfExists('coupon_codes');
        Schema::dropIfExists('coupon_template_billing_cycles');
        Schema::dropIfExists('coupon_template_products');
        Schema::dropIfExists('coupon_templates');
        Schema::dropIfExists('coupon_campaign_weekdays');

        Schema::enableForeignKeyConstraints();
    }

    private function ensureCouponsTable(): void
    {
        if (Schema::hasTable('coupons')) {
            return;
        }

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coupon_campaign_id')->nullable();
            $table->string('name', 120);
            $table->string('code', 50)->unique();
            $table->string('description', 255)->nullable();
            $table->string('distribution_type', 20)->default('public');
            $table->string('discount_scope', 20)->default('first_month');
            $table->string('discount_type', 20);
            $table->decimal('discount_value', 12, 2)->default(0);
            $table->decimal('min_amount', 12, 2)->default(0);
            $table->decimal('max_discount_amount', 12, 2)->nullable();
            $table->json('billing_cycles')->nullable();
            $table->json('product_ids')->nullable();
            $table->boolean('first_order_only')->default(false);
            $table->unsignedInteger('total_usage_limit')->nullable();
            $table->unsignedInteger('per_user_limit')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->tinyInteger('status')->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('remark', 255)->nullable();
            $table->string('operator', 100)->nullable();
            $table->string('trace_id', 100)->nullable();
            $table->timestamps();

            $table->index(['coupon_campaign_id', 'status'], 'coupons_campaign_status_idx');
            $table->index(['status', 'sort_order'], 'coupons_status_sort_idx');
        });
    }

    private function ensureUserCouponsTable(): void
    {
        if (Schema::hasTable('user_coupons')) {
            return;
        }

        Schema::create('user_coupons', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coupon_id');
            $table->unsignedBigInteger('user_id');
            $table->string('receive_type', 20)->default('claim');
            $table->tinyInteger('status')->default(1);
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->string('remark', 255)->nullable();
            $table->string('operator', 100)->nullable();
            $table->string('trace_id', 100)->nullable();
            $table->timestamps();

            $table->unique(['coupon_id', 'user_id'], 'user_coupons_coupon_user_unique');
            $table->index(['user_id', 'status'], 'user_coupons_user_status_idx');
            $table->index(['coupon_id', 'status'], 'user_coupons_coupon_status_idx');
        });
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        try {
            DB::statement(sprintf('DROP INDEX %s ON %s', $indexName, $table));
        } catch (Throwable) {
        }
    }

    private function resolveNullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        $resolved = (int) $value;

        return $resolved > 0 ? $resolved : null;
    }

    private function normalizeDecimal(mixed $value): string
    {
        if (! is_numeric($value)) {
            return '0.00';
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function nullableDecimal(mixed $value): ?string
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }
};
