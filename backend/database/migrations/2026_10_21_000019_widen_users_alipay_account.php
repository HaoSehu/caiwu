<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * users.alipay_account 由 varchar(20) 扩到 varchar(254)（数据库结构分析报告 P1-12）。
 *
 * 该列存的是支付宝账号，历史上同时支持「手机号」与「邮箱」两种形态；
 * varchar(20) 只够手机号，邮箱形态会被静默截断。支付宝账号本身不含索引，扩长无锁表风险。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'alipay_account')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->string('alipay_account', 254)->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'alipay_account')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->string('alipay_account', 20)->nullable()->change();
        });
    }
};
