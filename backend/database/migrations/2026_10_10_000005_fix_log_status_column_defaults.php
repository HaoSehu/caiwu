<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * M5 显式化日志状态列默认值（结构整改批 2026-10-10）。
 *
 * 复核更正：information_schema 中这三列的 COLUMN_DEFAULT='' 是 MySQL 8.0 对
 * 「NOT NULL 无显式默认」的表示，原库实际没有默认值（SHOW CREATE 无 DEFAULT 子句）。
 * 本迁移为两列补上与代码首个状态一致的显式默认，使缺省写入落合法值而非报错：
 * - archive_audit_logs.status → 'running'（LogArchiveService::createAuditLog 首值）
 * - integration_plugin_runtime_logs.status → 'success'
 *   （PluginRuntimeRegistry::recordRuntimeLog，值域 {success,failed}）
 * - service_provision_attempts.attempt_status 值域 {success,failed,recorded} 无单一
 *   合理默认，维持无默认（DROP DEFAULT 为幂等确认），写入端恒显式赋值。
 * ALTER COLUMN ... SET/DROP DEFAULT 为元数据级操作，不重建表。
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `archive_audit_logs` ALTER COLUMN `status` SET DEFAULT 'running'");
        DB::statement("ALTER TABLE `integration_plugin_runtime_logs` ALTER COLUMN `status` SET DEFAULT 'success'");
        DB::statement('ALTER TABLE `service_provision_attempts` ALTER COLUMN `attempt_status` DROP DEFAULT');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `archive_audit_logs` ALTER COLUMN `status` DROP DEFAULT');
        DB::statement('ALTER TABLE `integration_plugin_runtime_logs` ALTER COLUMN `status` DROP DEFAULT');
        DB::statement('ALTER TABLE `service_provision_attempts` ALTER COLUMN `attempt_status` DROP DEFAULT');
    }
};
