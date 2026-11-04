<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 消息发送体系重构 S1（docs/执行计划/进行中/消息发送体系按howstack重构-2026-11-03.md）：
// 对齐 howstack-bos 的配置与运行时模型。配置按 (受众, 场景) 整条加密存放并用 revision
// 做乐观锁；运行时拆为消息流水、接收对象、投递任务（事务 Outbox）与逐次尝试四层，
// 使「结果未知不自动重发」和「重复领取」有数据库级依据。
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('message_configurations')) {
            Schema::create('message_configurations', function (Blueprint $table): void {
                $table->comment('消息场景配置表，按受众与场景加密保存接收组与渠道策略');
                $table->id();
                $table->string('audience', 16)->comment('接收对象：customer 客户、admin 管理员');
                $table->string('message_type', 64)->comment('核心声明的消息场景编码，不允许后台自由新增');
                $table->mediumText('configuration_encrypted')->comment('该场景全部接收组与渠道配置的加密密文，禁止回显');
                $table->unsignedInteger('revision')->default(1)->comment('整条配置的乐观锁修订号，从 1 递增');
                $table->timestamps();

                $table->unique(['audience', 'message_type'], 'message_configurations_audience_type_unique');
            });
        }

        if (! Schema::hasTable('messages')) {
            Schema::create('messages', function (Blueprint $table): void {
                $table->comment('业务消息流水表，记录幂等事件与业务对象引用');
                $table->id();
                $table->string('event_key', 64)->comment('业务事件幂等摘要，同一事件重复发布只落一条');
                $table->string('scene', 64)->comment('固定消息场景编码');
                $table->string('purpose', 24)->comment('用途：notification、system、verification');
                $table->string('business_type', 64)->comment('业务对象类型');
                $table->unsignedBigInteger('business_id')->comment('关联业务对象主键');
                $table->dateTime('created_at')->comment('事件记录时间');

                $table->unique('event_key', 'messages_event_key_unique');
                $table->index(['scene', 'created_at'], 'messages_scene_created_idx');
            });
        }

        if (! Schema::hasTable('message_recipients')) {
            Schema::create('message_recipients', function (Blueprint $table): void {
                $table->comment('消息接收对象表，记录收件人与站内已读状态');
                $table->id();
                $table->unsignedBigInteger('message_id')->comment('逻辑消息主键');
                $table->string('audience', 16)->comment('customer 客户、admin 管理员、robot 群机器人');
                $table->unsignedBigInteger('account_id')->default(0)->comment('客户或管理员主键；群机器人为 0');
                $table->string('target_key', 64)->comment('账户或机器人渠道唯一标识');
                $table->string('group_key', 64)->default('')->comment('来源接收组标识');
                $table->string('group_name', 128)->default('')->comment('来源接收组名称快照');
                $table->timestamp('read_at')->nullable()->comment('站内消息阅读时间；发送成功不等于已读');
                $table->dateTime('created_at')->comment('接收对象生成时间');

                $table->unique(['message_id', 'audience', 'target_key'], 'message_recipients_message_target_unique');
                $table->index(['audience', 'account_id', 'read_at'], 'message_recipients_audience_account_idx');
            });
        }

        if (! Schema::hasTable('message_deliveries')) {
            Schema::create('message_deliveries', function (Blueprint $table): void {
                $table->comment('消息投递任务表，承载事务 Outbox 与租约状态机');
                $table->id();
                $table->unsignedBigInteger('recipient_id')->comment('消息接收对象主键');
                $table->uuid('request_key')->comment('固定发送请求 UUID；重试保持不变');
                $table->string('channel', 24)->comment('发送渠道编码');
                $table->string('channel_name', 128)->default('')->comment('发送通道名称快照');
                $table->mediumText('payload_encrypted')->comment('收件地址与模板渲染结果的加密快照，禁止写入日志或队列');
                $table->string('status', 24)->default('pending')->comment('pending、processing、accepted、delivered、failed、unknown');
                $table->unsignedInteger('attempts')->default(0)->comment('实际领取次数');
                $table->dateTime('next_attempt_at')->comment('允许再次投递的时间');
                $table->dateTime('queue_after')->comment('队列补投租约到期时间');
                $table->string('lease_key', 64)->default('')->comment('发送进程领取令牌');
                $table->timestamp('lease_until')->nullable()->comment('领取租约截止时间');
                $table->string('error_code', 64)->default('')->comment('不含厂商响应正文的安全错误编码');
                $table->timestamps();

                $table->unique(['recipient_id', 'channel'], 'message_deliveries_recipient_channel_unique');
                $table->unique('request_key', 'message_deliveries_request_key_unique');
                $table->index(['status', 'next_attempt_at', 'queue_after'], 'message_deliveries_due_idx');
            });
        }

        if (! Schema::hasTable('message_delivery_attempt_logs')) {
            Schema::create('message_delivery_attempt_logs', function (Blueprint $table): void {
                $table->comment('消息投递逐次尝试表，记录状态、安全错误码与耗时');
                $table->id();
                $table->unsignedBigInteger('delivery_id')->comment('投递任务主键');
                $table->unsignedInteger('attempt_no')->comment('任务尝试序号');
                $table->string('status', 24)->comment('本次发送状态');
                $table->string('error_code', 64)->default('')->comment('安全错误编码');
                $table->unsignedInteger('duration_ms')->default(0)->comment('发送耗时毫秒');
                $table->dateTime('created_at')->comment('领取开始时间');
                $table->timestamp('finished_at')->nullable()->comment('尝试结束时间');

                $table->unique(['delivery_id', 'attempt_no'], 'message_delivery_attempt_logs_unique');
                $table->index(['delivery_id', 'created_at'], 'message_delivery_attempt_logs_time_idx');
            });
        }

        if (! Schema::hasTable('verification_challenges')) {
            Schema::create('verification_challenges', function (Blueprint $table): void {
                $table->comment('验证码挑战表，仅保存 HMAC 摘要不存明文');
                $table->id();
                $table->char('challenge_id', 32)->comment('挑战标识，随机 16 字节十六进制');
                $table->string('purpose', 32)->comment('用途：register、login、password_reset');
                $table->string('method', 16)->comment('发送方式：sms、email');
                $table->string('target_hash', 64)->comment('目标账号的 HMAC-SHA256 摘要，不存明文');
                $table->string('binding_hash', 64)->comment('会话绑定值的 HMAC-SHA256 摘要');
                $table->string('code_hash', 64)->comment('challenge_id 与验证码拼接后的 HMAC-SHA256 摘要');
                $table->string('status', 16)->default('pending')->comment('pending、active、consumed、expired');
                $table->unsignedInteger('attempts')->default(0)->comment('已校验次数');
                $table->dateTime('expires_at')->comment('过期时间');
                $table->dateTime('created_at')->comment('创建时间');

                $table->unique('challenge_id', 'verification_challenges_challenge_unique');
                $table->index(['status', 'expires_at'], 'verification_challenges_status_expiry_idx');
            });
        }
    }

    public function down(): void
    {
        // 消息流水与投递记录不允许回退删除（与 howstack 一致：只做前向修复）。
        Schema::dropIfExists('verification_challenges');
        Schema::dropIfExists('message_delivery_attempt_logs');
        Schema::dropIfExists('message_deliveries');
        Schema::dropIfExists('message_recipients');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('message_configurations');
    }
};
