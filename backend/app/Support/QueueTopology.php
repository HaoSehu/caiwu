<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * 队列分组口径的唯一实现。
 *
 * queue:drain、app:serve 与定时任务页的命令清单都必须按同一套分组解析，
 * 否则本地手工启动的 Worker 会和 queue:drain 抢同一队列：900s 的 mysqldump
 * 会顺着 backup 把业务或定时任务组一起堵住。
 */
final class QueueTopology
{
    /**
     * 解析四组 Worker 队列。一个队列只能归一个 worker，
     * 按 backup → provision → automation → business 依次认领，
     * 后面的组剔除前面已认领的队列。
     *
     * 备份队列名配置非法（逗号分隔多个队列）时 backup 组为空并把原因写入 $errors，
     * 由调用方决定降级方式，不能让一处配置错误停掉其他三组。
     *
     * @param  list<string>  $errors
     * @return array{provision: list<string>, business: list<string>, backup: list<string>, automation: list<string>}
     */
    public static function resolve(array &$errors = []): array
    {
        $provision = self::normalize((string) config('queue.caiwu_provision_queues', 'provision'));
        $business = self::normalize((string) config('queue.caiwu_business_queues', 'referral,notification,coupon,default'));
        $automation = self::normalize((string) config('queue.caiwu_schedule_queue', 'automation'));

        try {
            $backup = self::normalize(BackupQueue::name());
        } catch (InvalidArgumentException $exception) {
            $backup = [];
            $errors[] = $exception->getMessage();
            Log::critical('[调度] 备份队列名配置非法，已从队列分组中剔除', [
                'message' => $exception->getMessage(),
            ]);
        }

        $provision = array_values(array_diff($provision, $backup));
        $automation = array_values(array_diff($automation, $backup, $provision));
        $business = array_values(array_diff($business, $backup, $provision, $automation));

        return [
            'provision' => $provision,
            'business' => $business,
            'backup' => $backup,
            'automation' => $automation,
        ];
    }

    /**
     * 备份队列名单值：配置非法时回退默认名并写入 $errors，用于展示型快照。
     */
    public static function backupName(array &$errors = []): string
    {
        try {
            return BackupQueue::name();
        } catch (InvalidArgumentException $exception) {
            $errors[] = $exception->getMessage();

            return BackupQueue::DEFAULT_NAME;
        }
    }

    /**
     * 把逗号分隔的队列列表规整为去空、去重、保序的数组。
     *
     * @return list<string>
     */
    public static function normalize(string $queues): array
    {
        $items = array_map('trim', explode(',', $queues));

        return array_values(array_unique(array_filter($items, static fn (string $queue): bool => $queue !== '')));
    }
}
