<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if ($this->canAddUserCouponCouponForeignKey()) {
            Schema::table('user_coupons', function (Blueprint $table): void {
                $table->foreign('coupon_id', 'fk_user_coupons_coupon_id')
                    ->references('id')
                    ->on('coupons')
                    ->restrictOnDelete();
            });
        }

        if ($this->canAddInvoiceUserCouponForeignKey()) {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->foreign('user_coupon_id', 'fk_invoices_user_coupon_id')
                    ->references('id')
                    ->on('user_coupons')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if ($this->hasForeign('invoices', 'fk_invoices_user_coupon_id')) {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->dropForeign('fk_invoices_user_coupon_id');
            });
        }

        if ($this->hasForeign('user_coupons', 'fk_user_coupons_coupon_id')) {
            Schema::table('user_coupons', function (Blueprint $table): void {
                $table->dropForeign('fk_user_coupons_coupon_id');
            });
        }
    }

    private function canAddUserCouponCouponForeignKey(): bool
    {
        if (
            ! Schema::hasTable('user_coupons')
            || ! Schema::hasTable('coupons')
            || ! Schema::hasColumn('user_coupons', 'coupon_id')
            || $this->hasForeign('user_coupons', 'fk_user_coupons_coupon_id')
        ) {
            return false;
        }

        $orphans = DB::table('user_coupons as uc')
            ->leftJoin('coupons as c', 'c.id', '=', 'uc.coupon_id')
            ->whereNull('c.id')
            ->count();

        if ($orphans > 0) {
            logger()->warning('[优惠券外键迁移] user_coupons 存在孤儿 coupon_id，已跳过外键创建', [
                'orphans' => $orphans,
            ]);

            return false;
        }

        return true;
    }

    private function canAddInvoiceUserCouponForeignKey(): bool
    {
        if (
            ! Schema::hasTable('invoices')
            || ! Schema::hasTable('user_coupons')
            || ! Schema::hasColumn('invoices', 'user_coupon_id')
            || $this->hasForeign('invoices', 'fk_invoices_user_coupon_id')
        ) {
            return false;
        }

        $orphans = DB::table('invoices as i')
            ->leftJoin('user_coupons as uc', 'uc.id', '=', 'i.user_coupon_id')
            ->whereNotNull('i.user_coupon_id')
            ->whereNull('uc.id')
            ->count();

        if ($orphans > 0) {
            logger()->warning('[优惠券外键迁移] invoices 存在孤儿 user_coupon_id，已跳过外键创建', [
                'orphans' => $orphans,
            ]);

            return false;
        }

        return true;
    }

    private function hasForeign(string $tableName, string $constraintName): bool
    {
        $database = DB::getDatabaseName();
        $result = DB::selectOne(
            'SELECT COUNT(*) AS aggregate
             FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = ?
               AND TABLE_NAME = ?
               AND CONSTRAINT_NAME = ?
               AND CONSTRAINT_TYPE = ?',
            [$database, $tableName, $constraintName, 'FOREIGN KEY']
        );

        return (int) ($result->aggregate ?? 0) > 0;
    }
};
