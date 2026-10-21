<?php

declare(strict_types=1);

namespace App\Support\Upgrade;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * 迁移清单盘点：对比磁盘迁移文件、`migrations` 账本与历史孤儿白名单。
 *
 * 三类文件必须区分开，否则会把「已归档的历史迁移」误判成「代码包缺失」：
 *
 * - **available**：`database/migrations/` 下可执行的增量迁移，参与 pending 计算；
 * - **archived**：`database/_archive/migrations*` 下的历史迁移，已固化进 schema baseline，
 *   不再执行也不再出现在待执行清单，但仍属于「已知文件」，不算缺失；
 * - **allowedMissing**：账本有记录、但连归档都没有的孤儿，命中白名单（D3）；
 * - **unexpectedMissing**：白名单外的新增缺失，说明有人误删迁移，必须拒绝升级。
 */
final class MigrationInventory
{
    private const ORPHAN_MANIFEST = 'database/migrations-orphans.json';

    private const MIGRATION_PATH = 'database/migrations';

    /**
     * 归档迁移目录（相对 backend 根）。迁移文件被固化进 schema baseline 后移入此处。
     */
    private const ARCHIVE_PATHS = [
        'database/_archive/migrations',
        'database/_archive/migrations_devin',
    ];

    /**
     * @param  list<string>  $available  可执行目录中的迁移名（不含 .php）
     * @param  list<string>  $archived  归档目录中的迁移名
     * @param  list<string>  $applied  `migrations` 账本中记录的迁移名
     * @param  list<string>  $pending  待执行迁移名（available − applied）
     * @param  list<string>  $allowedMissing  账本有记录但文件全无、且命中白名单
     * @param  list<string>  $unexpectedMissing  账本有记录但文件全无、且未命中白名单
     */
    public function __construct(
        public readonly array $available,
        public readonly array $archived,
        public readonly array $applied,
        public readonly array $pending,
        public readonly array $allowedMissing,
        public readonly array $unexpectedMissing,
    ) {}

    /**
     * 从磁盘与数据库构建盘点结果。
     *
     * @param  string|null  $migrationPath  可执行迁移目录，缺省 database/migrations
     * @param  string|null  $orphanManifest  孤儿白名单文件，缺省 database/migrations-orphans.json
     *
     * @throws RuntimeException 迁移目录不存在时抛出
     */
    public static function build(?string $migrationPath = null, ?string $orphanManifest = null): self
    {
        $directory = $migrationPath ?? base_path(self::MIGRATION_PATH);
        if (! is_dir($directory)) {
            throw new RuntimeException('迁移目录不存在：'.$directory);
        }

        $available = self::scanMigrations($directory);

        $archived = [];
        foreach (self::ARCHIVE_PATHS as $archivePath) {
            $absolute = base_path($archivePath);
            if (is_dir($absolute)) {
                $archived = array_merge($archived, self::scanMigrations($absolute));
            }
        }
        sort($archived);

        $known = array_flip(array_merge($available, $archived));

        $applied = DB::table('migrations')->orderBy('id')->pluck('migration')
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();
        $whitelist = self::loadOrphanWhitelist($orphanManifest ?? base_path(self::ORPHAN_MANIFEST));

        $pending = array_values(array_diff($available, $applied));
        sort($pending);

        $missing = array_values(array_filter(
            $applied,
            static fn (string $name): bool => ! isset($known[$name]),
        ));
        sort($missing);

        $allowed = [];
        $unexpected = [];
        foreach ($missing as $name) {
            if (isset($whitelist[$name])) {
                $allowed[] = $name;
            } else {
                $unexpected[] = $name;
            }
        }

        return new self($available, $archived, $applied, $pending, $allowed, $unexpected);
    }

    /**
     * 是否需要执行迁移或修复清单。
     *
     * @return bool 存在待执行或非白名单缺失时返回 true
     */
    public function requiresAttention(): bool
    {
        return $this->pending !== [] || $this->unexpectedMissing !== [];
    }

    /**
     * 扫描目录，返回迁移名（文件名去掉 .php），按文件名字典序。
     *
     * @param  string  $directory  目录绝对路径
     * @return list<string>
     */
    public static function scanMigrations(string $directory): array
    {
        $files = glob(rtrim($directory, DIRECTORY_SEPARATOR.'/\\').DIRECTORY_SEPARATOR.'*.php') ?: [];
        $names = [];
        foreach ($files as $file) {
            $names[] = basename($file, '.php');
        }
        sort($names);

        return $names;
    }

    /**
     * 读取孤儿白名单。每条必须包含 reason 与 recorded_at，缺一即抛错，
     * 防止后续维护者无脑追加条目掩盖真实的迁移误删。
     *
     * @param  string  $path  白名单绝对路径
     * @return array<string, array{reason: string, recorded_at: string}> 以迁移名为键
     *
     * @throws RuntimeException 文件结构不合法时抛出
     */
    public static function loadOrphanWhitelist(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException('无法读取迁移孤儿白名单：'.$path);
        }

        try {
            $decoded = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException('迁移孤儿白名单不是合法 JSON：'.$path, 0, $exception);
        }

        if (! is_array($decoded) || ! is_array($decoded['orphans'] ?? null)) {
            throw new RuntimeException('迁移孤儿白名单缺少 orphans 数组：'.$path);
        }

        $result = [];
        foreach ($decoded['orphans'] as $entry) {
            if (! is_array($entry)) {
                throw new RuntimeException('迁移孤儿白名单条目必须为对象：'.$path);
            }
            $name = trim((string) ($entry['migration'] ?? ''));
            $reason = trim((string) ($entry['reason'] ?? ''));
            $recordedAt = trim((string) ($entry['recorded_at'] ?? ''));
            if ($name === '' || $reason === '' || $recordedAt === '') {
                throw new RuntimeException('迁移孤儿白名单条目必须包含 migration/reason/recorded_at：'.$path);
            }
            $result[$name] = ['reason' => $reason, 'recorded_at' => $recordedAt];
        }

        return $result;
    }
}
