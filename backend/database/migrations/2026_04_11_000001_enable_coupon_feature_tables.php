<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensureCouponCampaignsTable();
        $this->ensureCouponsTable();
        $this->ensureUserCouponsTable();
        $this->ensureOrderCouponColumns();
    }

    public function down(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table): void {
                foreach (['coupon_id', 'user_coupon_id', 'coupon_code', 'coupon_snapshot'] as $column) {
                    if (Schema::hasColumn('orders', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('user_coupons');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('coupon_campaigns');
    }

    private function ensureCouponCampaignsTable(): void
    {
        if (Schema::hasTable('coupon_campaigns')) {
            return;
        }

        Schema::create('coupon_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('description', 255)->nullable();
            $table->json('weekdays')->nullable();
            $table->string('trigger_time', 8);
            $table->unsignedInteger('issue_quantity')->default(1);
            $table->unsignedInteger('valid_duration_hours')->nullable();
            $table->string('discount_scope', 20)->default('first_month');
            $table->string('discount_type', 20);
            $table->decimal('discount_value', 12, 2)->default(0);
            $table->decimal('min_amount', 12, 2)->default(0);
            $table->decimal('max_discount_amount', 12, 2)->nullable();
            $table->json('billing_cycles')->nullable();
            $table->json('product_ids')->nullable();
            $table->boolean('first_order_only')->default(false);
            $table->unsignedInteger('per_user_limit')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('last_dispatched_at')->nullable();
            $table->unsignedBigInteger('last_coupon_id')->nullable();
            $table->string('remark', 255)->nullable();
            $table->string('operator', 100)->nullable();
            $table->string('trace_id', 100)->nullable();
            $table->timestamps();

            $table->index(['status', 'sort_order'], 'coupon_campaigns_status_sort_idx');
            $table->index(['trigger_time', 'status'], 'coupon_campaigns_trigger_status_idx');
        });
    }

    private function ensureCouponsTable(): void
    {
        if (Schema::hasTable('coupons')) {
            return;
        }

        Schema::create('coupons', function (Blueprint $table): void {
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

        Schema::create('user_coupons', function (Blueprint $table): void {
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

    private function ensureOrderCouponColumns(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'coupon_id')) {
                $table->unsignedBigInteger('coupon_id')->nullable()->after('type');
                $table->index('coupon_id', 'orders_coupon_id_idx');
            }

            if (! Schema::hasColumn('orders', 'user_coupon_id')) {
                $table->unsignedBigInteger('user_coupon_id')->nullable()->after('coupon_id');
                $table->index('user_coupon_id', 'orders_user_coupon_id_idx');
            }

            if (! Schema::hasColumn('orders', 'coupon_code')) {
                $table->string('coupon_code', 50)->nullable()->after('user_coupon_id');
            }

            if (! Schema::hasColumn('orders', 'coupon_snapshot')) {
                $table->json('coupon_snapshot')->nullable()->after('config_pricing_snapshot');
            }
        });
    }
};
