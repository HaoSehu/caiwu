<?php

declare(strict_types=1);

namespace App\Services\Upstream\Integration;

use App\Services\Automation\Heartbeat\Contracts\ScheduledTask;
use App\Services\Automation\Heartbeat\Contracts\ScheduledTaskProvider;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * 上游集成任务来源：向心跳注册表提供商品同步、上游开通与服务状态类任务。
 *
 * 这些任务从系统核心任务清单（CoreScheduledTaskProvider）迁入上游集成模块，
 * 在管理端任务来源中不再显示为"系统任务"，而是与插件任务同类的集成来源任务；
 * 供应商接口类任务（refresh-hosting-panel-auth）已进一步随 hosting_panel 插件
 * 插件化（config.php scheduled_tasks 声明），后续其余上游驱动彻底插件化时，
 * 可由对应插件清单接管注册。
 *
 * 隔离粒度与插件 Provider 对齐：逐任务容器解析并独立捕获异常，单个任务类
 * 实例化失败只隔离该任务，不影响其余上游集成任务的注册。
 */
final class UpstreamScheduledTaskProvider implements ScheduledTaskProvider
{
    /** @var list<class-string> */
    private const TASK_CLASSES = [
        ProductUpstreamConfigSyncTask::class,
        ProvisionRetryFailedTask::class,
        ServiceStatusSyncTask::class,
    ];

    public function __construct(
        private readonly Container $container,
    ) {}

    /**
     * @return list<ScheduledTask>
     */
    public function tasks(): array
    {
        $tasks = [];

        foreach (self::TASK_CLASSES as $taskClass) {
            try {
                $task = $this->container->make($taskClass);
                if (! $task instanceof ScheduledTask) {
                    throw new InvalidArgumentException('未实现 ScheduledTask 接口');
                }

                $tasks[] = $task;
            } catch (Throwable $exception) {
                Log::error('[定时任务] 上游集成任务实例化失败，已隔离该任务', [
                    'task_class' => $taskClass,
                    'message' => $exception->getMessage(),
                    'exception' => $exception::class,
                ]);
            }
        }

        return $tasks;
    }
}
