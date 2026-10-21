<?php

declare(strict_types=1);

namespace App\Support\Upgrade;

use RuntimeException;

/**
 * 迁移文件静态检查：命名规范与结构防护。
 *
 * 检查项：
 * 1. 文件名必须形如 `2026_10_21_000001_add_something.php`，保证执行顺序稳定；
 * 2. 含结构变更（Schema::table / Schema::create / 裸 ALTER TABLE）的文件必须带
 *    `Schema::hasTable` 或 `Schema::hasColumn` 守卫，使中断后重试不会因「列已存在」失败；
 * 3. 已在历史中执行且不可修改的迁移，通过 `database/migrations-lint-exempt.json`
 *    显式豁免，每条必须写明原因与登记日期。
 */
final class MigrationLinter
{
    private const EXEMPT_MANIFEST = 'database/migrations-lint-exempt.json';

    private const NAME_PATTERN = '/^\d{4}_\d{2}_\d{2}_\d{6}_[a-z0-9_]+\.php$/';

    private const STRUCTURE_PATTERN = '/Schema::table\(|Schema::create\(|Schema::drop|ALTER TABLE/i';

    private const GUARD_PATTERN = '/Schema::hasTable\(|Schema::hasColumn\(/';

    /**
     * 扫描迁移目录并返回违规清单。
     *
     * @param  string  $directory  迁移目录绝对路径
     * @param  string  $exemptManifest  豁免清单绝对路径
     * @return list<array{file: string, violations: list<string>}> 违规文件及原因
     *
     * @throws RuntimeException 目录不存在或豁免清单结构非法时抛出
     */
    public function lint(string $directory, string $exemptManifest): array
    {
        if (! is_dir($directory)) {
            throw new RuntimeException('迁移目录不存在：'.$directory);
        }

        $exempt = $this->loadExemptions($exemptManifest);
        $files = glob(rtrim($directory, DIRECTORY_SEPARATOR.'/\\').DIRECTORY_SEPARATOR.'*.php') ?: [];
        sort($files);

        $violations = [];
        foreach ($files as $file) {
            $name = basename($file);
            $reasons = [];

            if (preg_match(self::NAME_PATTERN, $name) !== 1) {
                $reasons[] = '文件名不符合 4 位年_月_日_6 位时分秒_描述.php 规范';
            }

            $source = (string) file_get_contents($file);
            if (preg_match(self::STRUCTURE_PATTERN, $source) === 1
                && preg_match(self::GUARD_PATTERN, $source) !== 1) {
                $reasons[] = '包含结构变更但缺少 Schema::hasTable / Schema::hasColumn 守卫';
            }

            if ($reasons !== []) {
                if (isset($exempt[$name])) {
                    continue;
                }
                $violations[] = ['file' => $name, 'violations' => $reasons];
            }
        }

        return $violations;
    }

    /**
     * 读取豁免清单。
     *
     * @return array<string, array{reason: string, recorded_at: string}>
     *
     * @throws RuntimeException 清单格式非法时抛出
     */
    public function loadExemptions(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        try {
            $decoded = json_decode((string) file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException('迁移 lint 豁免清单不是合法 JSON：'.$path, 0, $exception);
        }

        if (! is_array($decoded) || ! is_array($decoded['exemptions'] ?? null)) {
            throw new RuntimeException('迁移 lint 豁免清单缺少 exemptions 数组：'.$path);
        }

        $result = [];
        foreach ($decoded['exemptions'] as $entry) {
            if (! is_array($entry)) {
                throw new RuntimeException('迁移 lint 豁免条目必须为对象：'.$path);
            }
            $file = trim((string) ($entry['file'] ?? ''));
            $reason = trim((string) ($entry['reason'] ?? ''));
            $recordedAt = trim((string) ($entry['recorded_at'] ?? ''));
            if ($file === '' || $reason === '' || $recordedAt === '') {
                throw new RuntimeException('迁移 lint 豁免必须包含 file/reason/recorded_at：'.$path);
            }
            $result[$file] = ['reason' => $reason, 'recorded_at' => $recordedAt];
        }

        return $result;
    }

    /**
     * 默认豁免清单路径。
     */
    public static function defaultExemptPath(): string
    {
        return base_path(self::EXEMPT_MANIFEST);
    }
}
