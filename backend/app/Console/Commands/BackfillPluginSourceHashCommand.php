<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\IntegrationPlugin;
use App\Services\Integrations\Plugins\PluginManifest;
use App\Services\Integrations\Plugins\PluginPackageHasher;
use App\Services\Integrations\Plugins\PluginScanner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 存量插件包摘要回填（PLG-1）。
 *
 * 包摘要 source_hash 在 install()/upgrade() 中写入，存量插件安装时没有该值，
 * 运行期只能记 error 放行。本命令按当前磁盘内容补齐一次基线，之后任何
 * 「审核后替换源码」都会让摘要对不上。默认 dry-run，必须显式 --execute 才写库；
 * 已回填且摘要一致的记录会被跳过，可重复执行。
 */
class BackfillPluginSourceHashCommand extends Command
{
    protected $signature = 'plugins:backfill-source-hash
        {--dry-run : 只比对并输出差异，不写库（默认行为）}
        {--execute : 按当前磁盘内容写入 source_hash}
        {--json : 以 JSON 输出结果}';

    protected $description = '回填存量插件包的完整性摘要（source_hash），默认 dry-run';

    public function handle(PluginPackageHasher $hasher, PluginScanner $scanner): int
    {
        if ((bool) $this->option('dry-run') && (bool) $this->option('execute')) {
            $this->error('--dry-run 与 --execute 不能同时使用。');

            return self::FAILURE;
        }

        $execute = (bool) $this->option('execute');
        $json = (bool) $this->option('json');

        try {
            $plugins = IntegrationPlugin::query()
                ->orderBy('domain')
                ->orderBy('slug')
                ->get();
        } catch (Throwable $exception) {
            Log::warning('[plugins] 插件包摘要回填：读取插件注册表失败', ['message' => $exception->getMessage()]);
            $this->error('读取插件注册表失败：'.$exception->getMessage());

            return self::FAILURE;
        }

        $rows = [];
        $failed = 0;

        foreach ($plugins as $plugin) {
            $domain = (string) $plugin->domain;
            $slug = (string) $plugin->slug;
            $recorded = trim((string) ($plugin->source_hash ?? ''));

            $manifest = $scanner->find($domain, $slug);
            if (! $manifest instanceof PluginManifest) {
                $failed++;
                $rows[] = $this->row($domain, $slug, $recorded, null, 'missing_files');

                continue;
            }

            try {
                $current = $hasher->packageHash($manifest->basePath);
            } catch (Throwable $exception) {
                $failed++;
                $rows[] = $this->row($domain, $slug, $recorded, null, 'hash_failed: '.$exception->getMessage());

                continue;
            }

            if ($recorded !== '' && hash_equals($recorded, $current)) {
                $rows[] = $this->row($domain, $slug, $recorded, $current, 'unchanged');

                continue;
            }

            $status = $recorded === '' ? 'backfilled' : 'updated';

            if ($execute) {
                try {
                    $plugin->forceFill(['source_hash' => $current])->save();
                } catch (Throwable $exception) {
                    Log::error('[plugins] 插件包摘要回填写入失败', [
                        'domain' => $domain,
                        'slug' => $slug,
                        'message' => $exception->getMessage(),
                    ]);
                    $failed++;
                    $rows[] = $this->row($domain, $slug, $recorded, $current, 'write_failed: '.$exception->getMessage());

                    continue;
                }
            } else {
                $status = 'pending_'.$status;
            }

            $rows[] = $this->row($domain, $slug, $recorded, $current, $status);
        }

        $pending = ['pending_backfilled', 'pending_updated'];
        $summary = [
            'mode' => $execute ? 'execute' : 'dry-run',
            'total' => count($rows),
            'written' => count(array_filter($rows, static fn (array $row): bool => in_array($row['status'], ['backfilled', 'updated'], true))),
            'pending' => count(array_filter($rows, static fn (array $row): bool => in_array($row['status'], $pending, true))),
            'unchanged' => count(array_filter($rows, static fn (array $row): bool => $row['status'] === 'unchanged')),
            'failed' => $failed,
            'plugins' => $rows,
        ];

        if ($json) {
            $this->line(json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

            return $failed === 0 ? self::SUCCESS : self::FAILURE;
        }

        foreach ($rows as $row) {
            $this->line(sprintf(
                '- %s/%s: %s',
                $row['domain'],
                $row['slug'],
                $row['status'],
            ));
        }

        $this->info(sprintf(
            '插件包摘要回填（%s）完成：共 %d 个，写入 %d 个，待写入 %d 个，未变化 %d 个，失败 %d 个。',
            $summary['mode'],
            $summary['total'],
            $summary['written'],
            $summary['pending'],
            $summary['unchanged'],
            $summary['failed'],
        ));

        if (! $execute && $summary['pending'] > 0) {
            $this->warn('当前为 dry-run，未写库；确认无误后执行 php artisan plugins:backfill-source-hash --execute。');
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array{domain: string, slug: string, recorded_hash: string, current_hash: ?string, status: string}
     */
    private function row(string $domain, string $slug, string $recorded, ?string $current, string $status): array
    {
        return [
            'domain' => $domain,
            'slug' => $slug,
            'recorded_hash' => $recorded,
            'current_hash' => $current,
            'status' => $status,
        ];
    }
}
