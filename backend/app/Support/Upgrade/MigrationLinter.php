<?php

declare(strict_types=1);

namespace App\Support\Upgrade;

use RuntimeException;

/**
 * 迁移文件静态检查：命名规范与结构防护。
 *
 * 检查项：
 * 1. 文件名必须形如 `2026_10_21_000001_add_something.php`，保证执行顺序稳定；
 * 2. 同一秒的时间戳前缀不得被多个迁移共用：Laravel 只按文件名排序执行，
 *    同秒文件之间的先后取决于描述段字母序，重命名或新增同秒文件都会改变执行顺序；
 * 3. 含结构变更（Schema::table / Schema::create / 裸 ALTER TABLE）的文件必须带
 *    `Schema::hasTable` 或 `Schema::hasColumn` 守卫，使中断后重试不会因「列已存在」失败；
 * 4. 已在历史中执行且不可修改的迁移，通过 `database/migrations-lint-exempt.json`
 *    显式豁免，每条必须写明原因与登记日期。
 */
final class MigrationLinter
{
    private const EXEMPT_MANIFEST = 'database/migrations-lint-exempt.json';

    private const NAME_PATTERN = '/^\d{4}_\d{2}_\d{2}_\d{6}_[a-z0-9_]+\.php$/';

    /** 只取时间戳前缀（不含描述段），用于识别同秒冲突。 */
    private const PREFIX_PATTERN = '/^(\d{4}_\d{2}_\d{2}_\d{6})_/';

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

        $reasonsByName = [];
        $namesByPrefix = [];
        foreach ($files as $file) {
            $name = basename($file);

            if (preg_match(self::NAME_PATTERN, $name) !== 1) {
                $reasonsByName[$name][] = '文件名不符合 4 位年_月_日_6 位时分秒_描述.php 规范';
            }

            if (preg_match(self::PREFIX_PATTERN, $name, $matches) === 1) {
                $namesByPrefix[$matches[1]][] = $name;
            }

            $source = (string) file_get_contents($file);
            if (preg_match(self::STRUCTURE_PATTERN, $source) === 1
                && preg_match(self::GUARD_PATTERN, $source) !== 1) {
                $reasonsByName[$name][] = '包含结构变更但缺少 Schema::hasTable / Schema::hasColumn 守卫';
            }
        }

        // 同秒前缀冲突：两个已执行迁移之间不存在安全的改名顺序，只能确认后豁免。
        foreach ($namesByPrefix as $prefix => $names) {
            if (count($names) < 2) {
                continue;
            }
            sort($names);
            $message = '时间戳前缀 '.$prefix.' 被 '.count($names).' 个迁移共用（'.implode('、', $names).'），执行顺序取决于描述段字母序';
            foreach ($names as $name) {
                $reasonsByName[$name][] = $message;
            }
        }

        $violations = [];
        foreach ($reasonsByName as $name => $reasons) {
            if (isset($exempt[$name])) {
                continue;
            }
            $violations[] = ['file' => $name, 'violations' => $reasons];
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
