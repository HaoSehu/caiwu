<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 管理员登录日志独立表（对齐 howstack-bos 登录审计模型）。
 *
 * 背景：此前管理员登录成功写 activity_logs(module=auth, action=admin.login)，
 * 失败登录只落 Redis 计数、无任何持久记录，且成功登录会同时产生显式 admin.login
 * 行与访问审计行（POST api/v2/admin/login）两条重复记录。
 *
 * 本表成为管理员登录尝试的唯一真源：
 * - 成功与失败都落库，失败记录 failure_reason（invalid_credentials / account_disabled）；
 * - 昵称、角色为登录时快照，未知账号的 nickname 存当时输入的账号，不回查当前资料；
 * - 客户端环境只存归纳后的浏览器与操作系统，不存原始 User-Agent；
 * - 限流中间件提前拒绝（429）、表单校验失败不属于已验证账号密码的登录尝试，不写入。
 *
 * activity_logs 中历史 admin.login 行保留只读，查询接口在新表为空时降级读取。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('admin_login_logs')) {
            return;
        }

        Schema::create('admin_login_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('admin_user_id')->nullable()->comment('管理员ID；未知账号为 NULL');
            $table->string('username', 64)->comment('登录时输入的账号快照');
            $table->string('nickname', 64)->default('')->comment('昵称快照；未知账号存当时输入的账号');
            $table->string('role_name', 64)->default('')->comment('角色名称快照');
            $table->unsignedTinyInteger('status')->default(0)->comment('登录结果: 1成功, 0失败');
            $table->string('failure_reason', 32)->default('')->comment('失败原因: invalid_credentials, account_disabled');
            $table->string('client_ip', 45)->default('')->comment('客户端IP');
            $table->string('browser', 64)->default('')->comment('浏览器归纳结果');
            $table->string('operating_system', 64)->default('')->comment('操作系统归纳结果');
            $table->dateTime('logged_at')->comment('登录尝试时间');
            $table->timestamps();

            $table->index(['logged_at', 'id'], 'admin_login_logs_logged_at_id_index');
            $table->index(['admin_user_id', 'logged_at'], 'admin_login_logs_admin_user_logged_at_index');
            $table->index(['client_ip', 'logged_at'], 'admin_login_logs_client_ip_logged_at_index');
            $table->index(['status', 'logged_at'], 'admin_login_logs_status_logged_at_index');
        });
    }

    public function down(): void
    {
        // 回滚语义显式删除新表；历史审计仍在 activity_logs，不随本迁移回滚。
        Schema::dropIfExists('admin_login_logs');
    }
};
