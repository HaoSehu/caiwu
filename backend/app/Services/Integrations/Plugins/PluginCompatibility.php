<?php

declare(strict_types=1);

namespace App\Services\Integrations\Plugins;

use App\Exceptions\BusinessException;
use App\Support\Upgrade\UpgradeLedger;
use Illuminate\Support\Facades\Log;

/**
 * 插件清单 requires 声明解析与宿主版本兼容性判定。
 *
 * 只支持 core（宿主程序版本）与 php 两个键，未声明视为不限版本。
 * 约束语法为空格分隔的多个条件（AND），每个条件形如 `>=1.0.0`：
 * 支持 >= > <= < = == != 七种比较符，缺省比较符表示等于。
 * 版本号允许 1~4 段数字并带预发布后缀，与 version_compare 语义一致。
 */
final class PluginCompatibility
{
    public const KEY_CORE = 'core';

    public const KEY_PHP = 'php';

    /** @var array<int, string> */
    private const SUPPORTED_KEYS = [self::KEY_CORE, self::KEY_PHP];

    /** 单个约束条件：可选比较符 + 版本号。 */
    private const CLAUSE_PATTERN = '/^(>=|<=|!=|==|>|<|=)?(v?\d+(?:\.\d+){0,3}(?:[-+][0-9A-Za-z.\-]+)?)$/';

    public function __construct(
        private readonly UpgradeLedger $ledger,
    ) {}

    /**
     * 校验并规范化 requires 声明，非法格式直接抛业务异常。
     *
     * @return array{core?: string, php?: string}
     */
    public static function normalize(mixed $requires): array
    {
        if ($requires === null || $requires === []) {
            return [];
        }

        if (! is_array($requires)) {
            throw new BusinessException('插件清单 requires 必须是数组', 42200);
        }

        $normalized = [];
        foreach ($requires as $key => $constraint) {
            $name = is_string($key) ? trim($key) : '';
            if (! in_array($name, self::SUPPORTED_KEYS, true)) {
                throw new BusinessException('插件清单 requires 只支持 core 与 php', 42200);
            }

            if (! is_string($constraint) || trim($constraint) === '') {
                throw new BusinessException("插件清单 requires 格式无效 [{$name}]", 42200);
            }

            self::assertConstraintSyntax($name, $constraint);
            $normalized[$name] = trim($constraint);
        }

        return $normalized;
    }

    /**
     * 判断给定版本是否满足约束表达式。
     */
    public static function satisfies(string $version, string $constraint): bool
    {
        $clauses = preg_split('/\s+/', trim($constraint)) ?: [];
        if ($clauses === []) {
            return false;
        }

        foreach ($clauses as $clause) {
            if (preg_match(self::CLAUSE_PATTERN, $clause, $matches) !== 1) {
                return false;
            }

            $operator = $matches[1] !== '' ? $matches[1] : '=';
            $target = ltrim($matches[2], 'v');

            if (! version_compare($version, $target, $operator)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 列出插件不满足的宿主环境条件；空数组表示兼容。
     *
     * @param  array{core?: string, php?: string}  $requires
     * @return array<int, string>
     */
    public function violations(array $requires, ?string $hostCoreVersion, string $phpVersion = PHP_VERSION): array
    {
        $problems = [];

        $core = $requires[self::KEY_CORE] ?? null;
        if (is_string($core) && $core !== '') {
            $host = $hostCoreVersion === null ? '' : trim($hostCoreVersion);
            if ($host === '') {
                // 未安装/未采纳时读不到数据库版本，此时不阻断既有能力。
                Log::debug('[plugins] 宿主程序版本未知，跳过 core 兼容性校验', ['requires' => $core]);
            } elseif (! self::satisfies($host, $core)) {
                $problems[] = "要求宿主程序版本 core {$core}，当前 {$host}";
            }
        }

        $php = $requires[self::KEY_PHP] ?? null;
        if (is_string($php) && $php !== '' && ! self::satisfies($phpVersion, $php)) {
            $problems[] = "要求 PHP 版本 {$php}，当前 {$phpVersion}";
        }

        return $problems;
    }

    /**
     * 断言插件与当前宿主兼容；安装、升级、启用与动作执行入口共用。
     */
    public function assertCompatible(PluginManifest $manifest): void
    {
        $problems = $this->violations($manifest->requires, $this->hostCoreVersion());
        if ($problems === []) {
            return;
        }

        throw new BusinessException('插件 '.$manifest->name.' 与当前运行环境不兼容：'.implode('；', $problems), 42200);
    }

    /**
     * 当前宿主程序版本（数据库已升级到的版本）；未安装/未采纳时返回 null。
     */
    public function hostCoreVersion(): ?string
    {
        return $this->ledger->currentVersion();
    }

    private static function assertConstraintSyntax(string $name, string $constraint): void
    {
        foreach (preg_split('/\s+/', trim($constraint)) ?: [] as $clause) {
            if (preg_match(self::CLAUSE_PATTERN, $clause) !== 1) {
                throw new BusinessException("插件清单 requires 格式无效 [{$name}: {$constraint}]", 42200);
            }
        }
    }
}
