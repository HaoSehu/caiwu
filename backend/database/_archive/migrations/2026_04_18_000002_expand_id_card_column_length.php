<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 把 users.id_card 与 verification_histories.id_card 扩到 varchar(512)。
 *
 * 背景：LegacyEncrypted cast 生成的密文长度约 228 字节，当前 varchar(255) 勉强能装。
 * 一旦 Laravel 版本升级或 cipher 变更导致密文变长，LegacyEncrypted::set 会触发
 * "超长静默回退明文" 分支，形成隐蔽的 PII 泄漏。
 * 把列宽放大到 512 预留缓冲，后续再配合修改 cast 将静默回退改为抛异常。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'id_card')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('id_card', 512)->nullable(false)->default('')->change();
            });
        }

        if (Schema::hasTable('verification_histories') && Schema::hasColumn('verification_histories', 'id_card')) {
            Schema::table('verification_histories', function (Blueprint $table) {
                $table->string('id_card', 512)->nullable(false)->default('')->change();
            });
        }
    }

    public function down(): void
    {
        // 不主动缩回 varchar(255)：旧列宽会把已加密的 228 字节密文截断，数据不可恢复。
        // 若确有需要回滚，请人工确认所有记录长度均在 255 以内后再执行手写 SQL。
    }
};
