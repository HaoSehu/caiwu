<?php

declare(strict_types=1);

namespace App\Console\Commands\Concerns;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * 旧库迁移子系统弃用护栏（2026-10-10 用户裁决）。
 *
 * 该子系统（6 个 *MigrationService + migrate:* 命令族）的目标 schema 来自
 * _archive/schema.old.sql，与当前 idc 实库全面失配（13 张目标表不存在、
 * 47 个字段无对应列），属可执行死代码；全部命令标注 [已弃用·旧库迁移]
 * 并拒绝执行，代码保留供历史溯源。详见《后端深度审查报告-2026-10-10》G1-01
 * 与《后端审查修复批次-2026-10-10》。
 */
trait RefusesLegacyMigrationExecution
{
    public function getDescription(): string
    {
        return '[已弃用·旧库迁移] '.parent::getDescription();
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->error('该命令属于已弃用的旧库迁移子系统（目标 schema 与当前实库不兼容，仅保留供历史溯源），已拒绝执行。');

        return self::FAILURE;
    }
}
