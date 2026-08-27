<?php

declare(strict_types=1);

namespace App\Services\Upstream\Contracts;

/**
 * 上游自带定向状态同步能力（软契约）。
 *
 * 声明该能力的上游插件由自己的定时任务负责服务状态拉取，
 * 核心全量状态同步任务应将其 provider_key 从扫描范围排除，
 * 插件停用后自动回退全量兜底。
 */
interface ProvidesSelfStatusSync {}
