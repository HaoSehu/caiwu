<?php

declare(strict_types=1);

namespace App\Support;

/**
 * 数据库备份队列名解析。
 *
 * `.env` 里把 `CAIWU_BACKUP_QUEUE` 显式写成空值（`CAIWU_BACKUP_QUEUE=`）时，
 * `env()` 返回空串而不是 null，config 里的默认值 `'backup'` 不会生效。
 * 此时 `Queue::pushOn('')` 会被 DatabaseQueue 回退成 default 队列，而 default
 * 正是 business 组的成员：900 秒的 mysqldump 又回到业务组造成队头阻塞，
 * 恰好是本批次要消除的问题。此处统一兜底，供 ExportDatabaseBackupJob、
 * queue:drain、app:serve 与调度命令清单共用，避免四处各写一份判断而漏改。
 *
 * 使用 config() 而非 env()：config:cache 启用后 env() 读不到 .env。
 */
final class BackupQueue
{
    /**
     * 未配置或配置为空时的队列名。
     */
    public const DEFAULT_NAME = 'backup';

    /**
     * 解析备份队列名，trim 后为空则回退 {@see self::DEFAULT_NAME}。
     *
     * 备份队列必须是单个队列名：worker 会把 `--queue` 按逗号拆成多个队列逐个 pop
     * （Illuminate\Queue\Worker::getNextJob，Worker.php:407），而 Job 投递时
     * DatabaseQueue::push 只把整串当字面队列名写入 jobs.queue。两边口径不一致时
     * （例如配置成 `backup,archive`）任务会落进一个永远不会被 pop 的队列名里，
     * 在途标记又要 3600 秒才过期，用户重提会被 409 挡回。这里直接失败，
     * 让配置错误在部署时就暴露，而不是变成「任务提交成功但永远不执行」。
     */
    public static function name(): string
    {
        $name = trim((string) config('queue.caiwu_backup_queue', self::DEFAULT_NAME));

        if ($name === '') {
            return self::DEFAULT_NAME;
        }

        if (str_contains($name, ',')) {
            throw new \InvalidArgumentException(
                '备份队列名必须是单个队列名，不能是逗号分隔的多个队列：'.$name
            );
        }

        return $name;
    }
}
