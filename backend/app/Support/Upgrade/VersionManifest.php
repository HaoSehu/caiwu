<?php

declare(strict_types=1);

namespace App\Support\Upgrade;

use RuntimeException;

/**
 * 代码包版本清单。
 *
 * 版本号刻意放在项目根目录的 `version.json` 而不是 `config/version.php`：
 * 部署流程包含 `php artisan config:cache`，一旦版本进入配置缓存，发版覆盖文件后
 * 进程仍会读到缓存里的旧版本号，导致「新代码 + 旧版本号」被误判为已是最新。
 * 直接读取文件可绕开配置缓存。
 */
final class VersionManifest
{
    private const FILENAME = 'version.json';

    /**
     * @param  string|null  $minimumUpgradeVersion  允许直接升级的最低来源版本；null 表示不限制
     */
    private function __construct(
        public readonly string $version,
        public readonly ?string $minimumUpgradeVersion,
        public readonly ?string $releasedAt,
    ) {}

    /**
     * 读取并校验版本清单。
     *
     * @param  string|null  $path  显式文件路径，缺省使用项目根目录
     *
     * @throws RuntimeException 文件缺失、无法解析或字段不合法时抛出
     */
    public static function load(?string $path = null): self
    {
        $file = $path ?? self::defaultPath();

        if (! is_file($file)) {
            throw new RuntimeException('版本清单文件不存在：'.$file);
        }

        $raw = file_get_contents($file);
        if ($raw === false || trim($raw) === '') {
            throw new RuntimeException('版本清单文件为空：'.$file);
        }

        try {
            $decoded = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException('版本清单不是合法 JSON：'.$file, 0, $exception);
        }

        if (! is_array($decoded)) {
            throw new RuntimeException('版本清单结构必须为对象：'.$file);
        }

        return self::fromArray($decoded);
    }

    /**
     * 从已解析数组构造，便于测试直接注入。
     *
     * @param  array<string, mixed>  $data
     *
     * @throws RuntimeException 字段缺失或格式不合法时抛出
     */
    public static function fromArray(array $data): self
    {
        $version = trim((string) ($data['version'] ?? ''));
        if ($version === '') {
            throw new RuntimeException('版本清单缺少 version 字段');
        }
        self::assertVersion($version, 'version');

        $minimum = $data['minimum_upgrade_version'] ?? null;
        if ($minimum !== null) {
            $minimum = trim((string) $minimum);
            if ($minimum === '') {
                $minimum = null;
            } else {
                self::assertVersion($minimum, 'minimum_upgrade_version');
            }
        }

        $releasedAt = $data['released_at'] ?? null;
        $releasedAt = $releasedAt === null ? null : trim((string) $releasedAt);

        return new self($version, $minimum, $releasedAt === '' ? null : $releasedAt);
    }

    /**
     * 默认版本清单绝对路径（后端应用根目录，不依赖 Laravel 配置缓存）。
     *
     * 刻意用 base_path() 直接拼接而不是走 config()：部署流程中的 config:cache
     * 会把版本号固化在缓存里，发版覆盖文件后仍读到旧值，导致「新代码 + 旧版本号」
     * 被误判为已是最新而跳过升级。
     */
    public static function defaultPath(): string
    {
        return base_path(self::FILENAME);
    }

    /**
     * 判断来源版本是否允许直接升级到当前版本。
     *
     * @param  string  $fromVersion  数据库记录的当前版本
     * @return bool 来源版本不低于最低升级版本时返回 true
     */
    public function allowsUpgradeFrom(string $fromVersion): bool
    {
        if ($this->minimumUpgradeVersion === null) {
            return true;
        }

        return version_compare($fromVersion, $this->minimumUpgradeVersion, '>=');
    }

    /**
     * 校验版本号形如 1.2.3（允许后缀，如 1.0.0-beta）。
     *
     * @throws RuntimeException 格式不合法时抛出
     */
    private static function assertVersion(string $value, string $field): void
    {
        if (preg_match('/^\d+\.\d+\.\d+([-+][0-9A-Za-z.\-]+)?$/', $value) !== 1) {
            throw new RuntimeException("版本清单字段 {$field} 格式不合法：{$value}");
        }
    }
}
