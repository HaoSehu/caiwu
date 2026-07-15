<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('email', 100)->nullable()->change();
            $table->string('phone', 20)->nullable()->change();
        });
    }

    public function down(): void
    {
        $hasNullIdentities = DB::table('users')
            ->whereNull('email')
            ->orWhereNull('phone')
            ->exists();

        if ($hasNullIdentities) {
            throw new RuntimeException('存在空邮箱或手机号，无法恢复 users 身份字段的非空约束');
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->string('email', 100)->nullable(false)->change();
            $table->string('phone', 20)->nullable(false)->change();
        });
    }
};
