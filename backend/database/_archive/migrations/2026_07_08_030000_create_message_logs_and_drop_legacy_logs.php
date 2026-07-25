<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createMessageLogsTable();
        $this->copyNotificationLogs();
        $this->copyEmailLogsWithoutProjection();
        $this->copySmsLogsWithoutProjection();

        Schema::dropIfExists('email_logs');
        Schema::dropIfExists('sms_logs');
        Schema::dropIfExists('notification_logs');
    }

    public function down(): void
    {
        $this->createNotificationLogsTable();
        $this->createEmailLogsTable();
        $this->createSmsLogsTable();
        $this->restoreLegacyLogsFromMessageLogs();

        Schema::dropIfExists('message_logs');
    }

    private function createMessageLogsTable(): void
    {
        if (Schema::hasTable('message_logs')) {
            return;
        }

        Schema::create('message_logs', function (Blueprint $table): void {
            $table->id()->comment('消息日志ID');
            $table->unsignedBigInteger('plugin_id')->nullable()->comment('插件ID');
            $table->string('driver_key', 120)->nullable()->comment('驱动标识');
            $table->string('trace_id', 64)->nullable()->comment('链路追踪ID');
            $table->string('channel', 20)->comment('消息渠道：email/sms');
            $table->string('recipient', 255)->comment('接收人邮箱或手机号');
            $table->string('template_code', 120)->nullable()->comment('业务模板编码或供应商模板ID');
            $table->string('subject', 255)->nullable()->comment('邮件主题');
            $table->mediumText('content')->comment('发送内容快照');
            $table->json('params_json')->nullable()->comment('渲染参数快照');
            $table->string('provider', 120)->nullable()->comment('供应商或驱动');
            $table->string('request_id', 100)->nullable()->comment('供应商请求ID');
            $table->string('status', 20)->default('pending')->comment('发送状态');
            $table->text('error_msg')->nullable()->comment('失败原因');
            $table->timestamp('sent_at')->nullable()->comment('发送完成时间');
            $table->string('origin_type', 50)->nullable()->comment('来源类型快照');
            $table->unsignedBigInteger('origin_id')->nullable()->comment('来源ID快照');
            $table->timestamps();

            $table->index(['channel', 'created_at'], 'message_logs_channel_created_at_idx');
            $table->index(['recipient', 'created_at'], 'message_logs_recipient_created_at_idx');
            $table->index(['driver_key', 'created_at'], 'message_logs_driver_created_idx');
            $table->index(['plugin_id', 'created_at'], 'message_logs_plugin_created_idx');
            $table->index(['channel', 'driver_key', 'created_at'], 'message_logs_channel_driver_idx');
            $table->index(['origin_type', 'origin_id'], 'message_logs_origin_idx');
            $table->index('trace_id', 'message_logs_trace_idx');
            $table->index('request_id', 'message_logs_request_id_idx');
        });

        if ($this->tableExists('integration_plugins') && ! $this->foreignKeyExists('message_logs', 'message_logs_plugin_fk')) {
            DB::statement('ALTER TABLE `message_logs` ADD CONSTRAINT `message_logs_plugin_fk` FOREIGN KEY (`plugin_id`) REFERENCES `integration_plugins` (`id`) ON DELETE SET NULL ON UPDATE NO ACTION');
        }
    }

    private function copyNotificationLogs(): void
    {
        if (! $this->tableExists('notification_logs')) {
            return;
        }

        DB::statement("
            INSERT INTO `message_logs` (
                `plugin_id`, `driver_key`, `trace_id`, `channel`, `recipient`,
                `template_code`, `subject`, `content`, `params_json`, `provider`,
                `request_id`, `status`, `error_msg`, `sent_at`, `origin_type`,
                `origin_id`, `created_at`, `updated_at`
            )
            SELECT
                `plugin_id`, `driver_key`, `trace_id`, `channel`, `recipient`,
                `template_code`, `subject`, COALESCE(`content`, ''), `params_json`, `provider`,
                `request_id`, COALESCE(`status`, 'pending'), `error_msg`, `sent_at`, `origin_type`,
                `origin_id`, `created_at`, `updated_at`
            FROM `notification_logs`
        ");
    }

    private function copyEmailLogsWithoutProjection(): void
    {
        if (! $this->tableExists('email_logs')) {
            return;
        }

        DB::statement("
            INSERT INTO `message_logs` (
                `plugin_id`, `driver_key`, `trace_id`, `channel`, `recipient`,
                `template_code`, `subject`, `content`, `status`, `error_msg`,
                `sent_at`, `origin_type`, `origin_id`, `created_at`, `updated_at`
            )
            SELECT
                e.`plugin_id`, e.`driver_key`, e.`trace_id`, 'email', e.`to_email`,
                e.`template_code`, e.`subject`, COALESCE(e.`content`, ''), COALESCE(e.`status`, 'pending'), e.`error_msg`,
                e.`sent_at`, 'email_log', e.`id`, e.`created_at`, e.`updated_at`
            FROM `email_logs` e
            WHERE NOT EXISTS (
                SELECT 1
                FROM `message_logs` m
                WHERE m.`channel` = 'email'
                  AND m.`origin_type` = 'email_log'
                  AND m.`origin_id` = e.`id`
            )
        ");
    }

    private function copySmsLogsWithoutProjection(): void
    {
        if (! $this->tableExists('sms_logs')) {
            return;
        }

        DB::statement("
            INSERT INTO `message_logs` (
                `plugin_id`, `driver_key`, `trace_id`, `channel`, `recipient`,
                `template_code`, `content`, `params_json`, `provider`, `request_id`,
                `status`, `error_msg`, `sent_at`, `origin_type`, `origin_id`,
                `created_at`, `updated_at`
            )
            SELECT
                s.`plugin_id`, s.`driver_key`, s.`trace_id`, 'sms', s.`phone`,
                s.`template_code`, COALESCE(s.`content`, ''), s.`params`, s.`provider`, s.`request_id`,
                COALESCE(s.`status`, 'pending'), s.`error_msg`, s.`sent_at`, 'sms_log', s.`id`,
                s.`created_at`, s.`updated_at`
            FROM `sms_logs` s
            WHERE NOT EXISTS (
                SELECT 1
                FROM `message_logs` m
                WHERE m.`channel` = 'sms'
                  AND m.`origin_type` = 'sms_log'
                  AND m.`origin_id` = s.`id`
            )
        ");
    }

    private function createNotificationLogsTable(): void
    {
        if (Schema::hasTable('notification_logs')) {
            return;
        }

        Schema::create('notification_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('plugin_id')->nullable();
            $table->string('driver_key', 120)->nullable();
            $table->string('trace_id', 64)->nullable();
            $table->string('channel', 20);
            $table->string('recipient', 191);
            $table->string('template_code', 50)->nullable();
            $table->string('subject')->nullable();
            $table->mediumText('content');
            $table->json('params_json')->nullable();
            $table->string('provider', 50)->nullable();
            $table->string('request_id', 100)->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('error_msg')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('origin_type', 30)->nullable();
            $table->unsignedBigInteger('origin_id')->nullable();
            $table->timestamps();

            $table->index(['channel', 'created_at'], 'notification_logs_channel_created_at_idx');
            $table->index(['recipient', 'created_at'], 'notification_logs_recipient_created_at_idx');
            $table->index(['driver_key', 'created_at'], 'notification_logs_driver_created_idx');
            $table->index(['plugin_id', 'created_at'], 'notification_logs_plugin_created_idx');
            $table->index(['channel', 'driver_key', 'created_at'], 'notification_logs_channel_driver_idx');
            $table->index(['origin_type', 'origin_id'], 'notification_logs_origin_idx');
            $table->index('trace_id', 'notification_logs_trace_idx');
            $table->index('request_id', 'notification_logs_request_id_idx');
        });
    }

    private function createEmailLogsTable(): void
    {
        if (Schema::hasTable('email_logs')) {
            return;
        }

        Schema::create('email_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('plugin_id')->nullable();
            $table->string('driver_key', 120)->nullable();
            $table->string('trace_id', 64)->nullable();
            $table->string('template_code', 20)->nullable();
            $table->string('to_email');
            $table->string('subject');
            $table->mediumText('content')->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('error_msg')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['to_email', 'created_at'], 'email_logs_to_email_created_at_idx');
            $table->index(['status', 'created_at'], 'email_logs_status_created_at_idx');
            $table->index(['driver_key', 'created_at'], 'email_logs_driver_created_idx');
            $table->index(['plugin_id', 'created_at'], 'email_logs_plugin_created_idx');
            $table->index('trace_id', 'email_logs_trace_idx');
            $table->index('template_code', 'email_logs_template_code_index');
            $table->index('to_email', 'email_logs_to_email_index');
        });
    }

    private function createSmsLogsTable(): void
    {
        if (Schema::hasTable('sms_logs')) {
            return;
        }

        Schema::create('sms_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('plugin_id')->nullable();
            $table->string('driver_key', 120)->nullable();
            $table->string('trace_id', 64)->nullable();
            $table->string('phone', 20);
            $table->string('template_code', 50);
            $table->json('params')->nullable();
            $table->string('content', 500);
            $table->string('status', 20)->default('pending');
            $table->string('provider', 50)->default('aliyun');
            $table->string('request_id')->nullable();
            $table->text('error_msg')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['phone', 'created_at'], 'sms_logs_phone_created_at_idx');
            $table->index(['status', 'created_at'], 'sms_logs_status_created_at_idx');
            $table->index(['driver_key', 'created_at'], 'sms_logs_driver_created_idx');
            $table->index(['plugin_id', 'created_at'], 'sms_logs_plugin_created_idx');
            $table->index('trace_id', 'sms_logs_trace_idx');
        });
    }

    private function restoreLegacyLogsFromMessageLogs(): void
    {
        if (! $this->tableExists('message_logs')) {
            return;
        }

        DB::statement('
            INSERT INTO `notification_logs` (
                `plugin_id`, `driver_key`, `trace_id`, `channel`, `recipient`,
                `template_code`, `subject`, `content`, `params_json`, `provider`,
                `request_id`, `status`, `error_msg`, `sent_at`, `origin_type`,
                `origin_id`, `created_at`, `updated_at`
            )
            SELECT
                `plugin_id`, `driver_key`, `trace_id`, `channel`, LEFT(`recipient`, 191),
                LEFT(`template_code`, 50), `subject`, `content`, `params_json`, LEFT(`provider`, 50),
                `request_id`, `status`, `error_msg`, `sent_at`, LEFT(`origin_type`, 30),
                `origin_id`, `created_at`, `updated_at`
            FROM `message_logs`
        ');

        DB::statement("
            INSERT INTO `email_logs` (
                `plugin_id`, `driver_key`, `trace_id`, `template_code`, `to_email`,
                `subject`, `content`, `status`, `error_msg`, `sent_at`, `created_at`, `updated_at`
            )
            SELECT
                `plugin_id`, `driver_key`, `trace_id`, LEFT(`template_code`, 20), `recipient`,
                COALESCE(`subject`, ''), `content`, `status`, `error_msg`, `sent_at`, `created_at`, `updated_at`
            FROM `message_logs`
            WHERE `channel` = 'email'
        ");

        DB::statement("
            INSERT INTO `sms_logs` (
                `plugin_id`, `driver_key`, `trace_id`, `phone`, `template_code`,
                `params`, `content`, `status`, `provider`, `request_id`,
                `error_msg`, `sent_at`, `created_at`, `updated_at`
            )
            SELECT
                `plugin_id`, `driver_key`, `trace_id`, LEFT(`recipient`, 20), LEFT(COALESCE(`template_code`, ''), 50),
                `params_json`, LEFT(`content`, 500), `status`, LEFT(COALESCE(`provider`, 'aliyun'), 50), `request_id`,
                `error_msg`, `sent_at`, `created_at`, `updated_at`
            FROM `message_logs`
            WHERE `channel` = 'sms'
        ");
    }

    private function tableExists(string $table): bool
    {
        return DB::table('information_schema.tables')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->exists();
    }

    private function foreignKeyExists(string $table, string $constraint): bool
    {
        return DB::table('information_schema.table_constraints')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('constraint_name', $constraint)
            ->where('constraint_type', 'FOREIGN KEY')
            ->exists();
    }
};
