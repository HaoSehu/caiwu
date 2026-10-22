<?php

declare(strict_types=1);

namespace App\Services\Integrations\Plugins;

use App\Exceptions\BusinessException;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * 插件包完整性摘要。
 *
 * 计算范围是插件目录下的全部运行文件（含随包交付的 vendor 依赖）：审核通过后
 * 任何内容替换都会改变摘要，让「人工审核过的代码」与「实际被实例化的代码」重新对齐。
 * 摘要只覆盖文件内容，不覆盖权限位与所有者；符号链接、秘密文件和依赖构建目录
 * 直接拒绝入库，避免用一条特殊路径逃过审核。
 */
final class PluginPackageHasher
{
    /** 单插件文件数上限：超过说明不是正常交付包，按拒绝处理。 */
    public const MAX_FILES = 10000;

    /** 单插件总字节上限（100 MiB），防止误把构建产物或数据目录打进插件包。 */
    public const MAX_BYTES = 104857600;

    /** 系统元数据文件不参与摘要，也不构成审核内容。 */
    private const IGNORED_BASENAMES = ['.DS_Store'];

    /**
     * @param  int  $maxFiles  文件数上限，仅测试注入更小值
     * @param  int  $maxBytes  总字节上限，仅测试注入更小值
     */
    public function __construct(
        private readonly int $maxFiles = self::MAX_FILES,
        private readonly int $maxBytes = self::MAX_BYTES,
    ) {}

    /**
     * 计算插件包内容摘要，写入 integration_plugins.source_hash。
     *
     * @param  string  $basePath  插件目录绝对路径
     * @return string 64 位小写十六进制摘要
     *
     * @throws BusinessException 目录不可读、含符号链接/秘密文件或超出护栏时
     */
    public function packageHash(string $basePath): string
    {
        return $this->describePackage($basePath)['hash'];
    }

    /**
     * 一次性返回摘要与统计信息，供审计命令输出。
     *
     * @param  string  $basePath  插件目录绝对路径
     * @return array{hash: string, files: int, bytes: int}
     *
     * @throws BusinessException 目录不可读、含符号链接/秘密文件或超出护栏时
     */
    public function describePackage(string $basePath): array
    {
        $root = realpath($basePath);
        if ($root === false || ! is_dir($root)) {
            throw new BusinessException('插件目录不存在，无法计算完整性摘要', 42200);
        }

        $files = $this->collectFiles($root);

        $hashes = [];
        foreach (array_keys($files) as $relative) {
            $fullPath = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
            clearstatcache(true, $fullPath);

            $hash = @hash_file('sha256', $fullPath);
            if ($hash === false) {
                throw new BusinessException("插件包文件不可读 [{$relative}]", 42200);
            }

            $hashes[$relative] = $hash;
        }

        ksort($hashes);

        return [
            'hash' => hash('sha256', (string) json_encode($hashes, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
            'files' => count($hashes),
            'bytes' => array_sum(array_column($files, 'bytes')),
        ];
    }

    /**
     * 遍历插件目录，收集参与摘要的相对路径与字节数，同时执行全部护栏校验。
     *
     * @param  string  $root  realpath 归一后的插件根目录
     * @return array<string, array{bytes: int}>
     *
     * @throws BusinessException 含符号链接、秘密文件或超出护栏时
     */
    private function collectFiles(string $root): array
    {
        $rootPrefixLength = strlen($root) + 1;
        $files = [];
        $totalBytes = 0;

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY,
            );

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                $path = $file->getPathname();
                $relative = str_replace('\\', '/', substr($path, $rootPrefixLength));

                // 先判链接：目录链接在 isFile() 上是 false，等到后面判会被静默跳过。
                if ($file->isLink()) {
                    throw new BusinessException("插件包不允许符号链接 [{$relative}]", 42200);
                }

                if (in_array($file->getBasename(), self::IGNORED_BASENAMES, true)) {
                    continue;
                }

                if (! $file->isFile()) {
                    continue;
                }

                $this->assertPathAllowed($relative);

                $bytes = (int) $file->getSize();
                $files[$relative] = ['bytes' => $bytes];
                $totalBytes += $bytes;

                if (count($files) > $this->maxFiles) {
                    throw new BusinessException('插件包文件数超过上限（'.self::MAX_FILES.' 个）', 42200);
                }

                if ($totalBytes > $this->maxBytes) {
                    throw new BusinessException('插件包总字节数超过上限（100 MiB）', 42200);
                }
            }
        } catch (BusinessException $exception) {
            throw $exception;
        } catch (\Throwable) {
            throw new BusinessException('插件包读取失败，无法计算完整性摘要', 42200);
        }

        return $files;
    }

    /**
     * 拒绝不该出现在交付包里的路径。
     *
     * vendor/ 是随包交付的第三方依赖：它同样参与摘要，只是豁免通用文件名规则，
     * 否则 SDK 自带的 .env 模板会让所有带依赖的插件无法安装。
     *
     * @param  string  $relative  相对插件根目录的路径（/ 分隔）
     *
     * @throws BusinessException 命中秘密文件或受限目录时
     */
    private function assertPathAllowed(string $relative): void
    {
        $normalized = strtolower($relative);

        if (str_starts_with($normalized, 'vendor/')) {
            return;
        }

        if (preg_match('~(^|/)\.env(?:$|[./])~', $normalized) === 1) {
            throw new BusinessException("插件包不允许包含环境变量文件 [{$relative}]", 42200);
        }

        if (preg_match('~(^|/)(config|node_modules)/~', $normalized) === 1) {
            throw new BusinessException("插件包不允许包含配置或依赖构建目录 [{$relative}]", 42200);
        }
    }
}
