<?php

declare(strict_types=1);

namespace App\Services\Integrations\Plugins;

use App\Exceptions\BusinessException;

class PluginFileLoader
{
    /** 插件不得声明进核心命名空间：清单可被污染，声明进 App\ 会顶替或污染核心类。 */
    private const FORBIDDEN_CLASS_NAMESPACE_PREFIXES = ['App\\', 'Illuminate\\'];

    /**
     * @var array<string, bool>
     */
    private array $loaded = [];

    public function ensureLoaded(PluginManifest $manifest): void
    {
        if (isset($this->loaded[$manifest->basePath])) {
            return;
        }

        // require 前预检声明类：PHP 类重名是「Cannot declare class」不可捕获 fatal，
        // 必须在加载前拦截，让坏插件在安装/启用/运行时都以可捕获的 BusinessException 失败，
        // 而不是击穿扫描列表或整站请求。
        $this->assertNoClassConflicts($manifest);

        foreach (['lib', 'logic', 'controller'] as $directory) {
            $this->requirePhpFilesIn($manifest->basePath.DIRECTORY_SEPARATOR.$directory);
        }

        $vendorAutoload = $manifest->basePath.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';
        if (is_file($vendorAutoload)) {
            require_once $vendorAutoload;
        }

        foreach (glob($manifest->basePath.DIRECTORY_SEPARATOR.'*.php') ?: [] as $pluginFile) {
            if (basename($pluginFile) === 'config.php') {
                continue;
            }

            require_once $pluginFile;
        }

        $this->loaded[$manifest->basePath] = true;
    }

    private function requirePhpFilesIn(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
                require_once $file->getPathname();
            }
        }
    }

    /**
     * 预检插件目录内声明的类/接口/trait/enum（token 级解析，不执行插件代码）：
     * 1) 拒绝声明进 App\ / Illuminate\ 核心命名空间；
     * 2) 拒绝一切不在自身入口类命名空间前缀之下的「非全局」命名空间声明
     *    （含冒充其他插件命名空间与任意第三方命名空间）；入口类本身未声明
     *    命名空间时无法推导前缀，此时同样拒绝一切命名空间声明；全局命名空间
     *    声明放行，由已加载类冲突检查兜底；
     * 3) 拒绝插件内部重复声明同名类；
     * 4) 拒绝与当前进程已加载类同名冲突。
     */
    private function assertNoClassConflicts(PluginManifest $manifest): void
    {
        $ownPrefix = $this->ownNamespacePrefix($manifest);
        $declarations = [];

        foreach ($this->pluginPhpFiles($manifest) as $file) {
            foreach ($this->declaredClassNames($file) as $fqcn) {
                $this->assertDeclarationAllowed($manifest, $fqcn, $ownPrefix, $file, $declarations);
                $declarations[$fqcn] = true;
            }
        }
    }

    /**
     * 自身命名空间前缀：入口类 FQCN 去掉最后一段（类名）。
     * 约定入口类位于插件命名空间根部（如 Caiwu\Plugins\Gateways\AliPay\AliPayPlugin
     * → 前缀 Caiwu\Plugins\Gateways\AliPay），lib/logic/controller 下的类都必须在该前缀之下。
     */
    private function ownNamespacePrefix(PluginManifest $manifest): ?string
    {
        $entryClass = trim($manifest->entryClass);
        if ($entryClass === '') {
            return null;
        }

        $separator = strrpos($entryClass, '\\');
        if ($separator === false || $separator === 0) {
            return null;
        }

        return substr($entryClass, 0, $separator);
    }

    /**
     * @return array<int, string>
     */
    private function pluginPhpFiles(PluginManifest $manifest): array
    {
        $files = [];

        foreach (['lib', 'logic', 'controller'] as $directory) {
            $path = $manifest->basePath.DIRECTORY_SEPARATOR.$directory;
            if (! is_dir($path)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
            );

            /** @var \SplFileInfo $file */
            foreach ($iterator as $file) {
                if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }

        foreach (glob($manifest->basePath.DIRECTORY_SEPARATOR.'*.php') ?: [] as $pluginFile) {
            if (basename($pluginFile) === 'config.php') {
                continue;
            }

            $files[] = $pluginFile;
        }

        return $files;
    }

    /**
     * @param  array<string, bool>  $declarations
     */
    private function assertDeclarationAllowed(
        PluginManifest $manifest,
        string $fqcn,
        ?string $ownPrefix,
        string $file,
        array $declarations,
    ): void {
        foreach (self::FORBIDDEN_CLASS_NAMESPACE_PREFIXES as $forbidden) {
            if (str_starts_with($fqcn, $forbidden)) {
                throw new BusinessException(
                    "插件不得声明核心命名空间类 [{$fqcn}]（".basename($file).'）',
                    42200,
                );
            }
        }

        if (str_contains($fqcn, '\\')) {
            if ($ownPrefix === null) {
                // 入口类未声明命名空间时无法推导自身前缀：不再放行任意命名空间，
                // 否则「冒充其他插件/第三方命名空间」的预检会被整体绕过。
                throw new BusinessException(
                    "插件入口类未声明命名空间时，禁止声明命名空间类 [{$fqcn}]（".basename($file).'）',
                    42200,
                );
            }

            if (! str_starts_with($fqcn.'\\', $ownPrefix.'\\')) {
                // 收紧（2026-10-13 审查 P2-6）：任何「非全局」命名空间声明都必须位于自身
                // 前缀之下——核心命名空间与其他插件命名空间（冒充）一并拦截；任意第三方
                // 命名空间（如 MyLib\Foo）同样越出插件边界。全局命名空间声明放行，
                // 由下方已加载类冲突检查兜底；插件自带 vendor 依赖走独立 autoload，不经本预检。
                throw new BusinessException(
                    "插件声明的类必须位于自身命名空间 [{$ownPrefix}] 之下，发现 [{$fqcn}]（".basename($file).'）',
                    42200,
                );
            }
        }

        if (isset($declarations[$fqcn])) {
            throw new BusinessException(
                "插件内重复声明类 [{$fqcn}]，加载将触发不可恢复的类冲突（".basename($file).'）',
                42200,
            );
        }

        if (class_exists($fqcn, false)
            || interface_exists($fqcn, false)
            || trait_exists($fqcn, false)
            || enum_exists($fqcn, false)) {
            // 已加载类若就定义在本插件目录内（测试进程跨容器实例、或长驻进程重复触发），
            // require_once 本身幂等，不构成冲突，放行；只有与插件外部类同名才是真冲突。
            if ($this->declaredByThisPlugin($fqcn, $manifest)) {
                return;
            }

            throw new BusinessException(
                "插件声明的类与已加载类冲突 [{$fqcn}]（".basename($file).'）',
                42200,
            );
        }
    }

    /**
     * 已加载类的定义文件是否位于本插件目录内。
     */
    private function declaredByThisPlugin(string $fqcn, PluginManifest $manifest): bool
    {
        try {
            $reflection = new \ReflectionClass($fqcn);
        } catch (\ReflectionException) {
            return false;
        }

        $file = $reflection->getFileName();
        if ($file === false || trim($file) === '') {
            return false;
        }

        $base = rtrim(str_replace('\\', '/', (string) realpath($manifest->basePath)), '/');
        $classFile = str_replace('\\', '/', $file);

        return $base !== '' && str_starts_with($classFile.'/', $base.'/');
    }

    /**
     * token 级解析单个文件声明的类/接口/trait/enum FQCN，不执行文件。
     * 跳过 ::class 常量取值与匿名类；旧运行时无 T_ENUM 时自然跳过 enum 关键字。
     *
     * @return array<int, string>
     */
    private function declaredClassNames(string $file): array
    {
        $source = @file_get_contents($file);
        if ($source === false || trim($source) === '') {
            return [];
        }

        if (stripos($source, 'class') === false
            && stripos($source, 'interface') === false
            && stripos($source, 'trait') === false
            && stripos($source, 'enum') === false) {
            return [];
        }

        $tokens = token_get_all($source);
        $count = count($tokens);
        $namespace = '';
        $names = [];

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            if (! is_array($token)) {
                continue;
            }

            if ($token[0] === T_NAMESPACE) {
                $namespace = $this->readNamespaceName($tokens, $i, $count);

                continue;
            }

            $isClassLike = in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT], true)
                || (defined('T_ENUM') && $token[0] === T_ENUM);
            if (! $isClassLike) {
                continue;
            }

            $previous = $this->previousMeaningfulToken($tokens, $i);
            // Foo::class 常量取值与匿名类（new class）都不是类声明。
            if ($previous !== null && ! is_array($previous) && $previous === '::') {
                continue;
            }
            if (is_array($previous) && $previous[0] === T_NEW) {
                continue;
            }

            for ($j = $i + 1; $j < $count; $j++) {
                $next = $tokens[$j];
                if (is_array($next) && $next[0] === T_WHITESPACE) {
                    continue;
                }

                if (is_array($next) && in_array($next[0], [T_STRING, T_NAME_QUALIFIED], true)) {
                    $names[] = ($namespace !== '' ? $namespace.'\\' : '').$next[1];
                }

                break;
            }
        }

        return $names;
    }

    /**
     * @param  array<int, array<int, string|int>|string>  $tokens
     */
    private function readNamespaceName(array $tokens, int $startIndex, int $count): string
    {
        $namespace = '';

        for ($j = $startIndex + 1; $j < $count; $j++) {
            $token = $tokens[$j];
            if (is_array($token)) {
                if ($token[0] === T_WHITESPACE) {
                    continue;
                }

                if (in_array($token[0], [T_STRING, T_NAME_QUALIFIED], true)
                    || $token[0] === T_NS_SEPARATOR) {
                    $namespace .= $token[1];

                    continue;
                }
            }

            break;
        }

        return rtrim($namespace, '\\');
    }

    /**
     * @param  array<int, array<int, string|int>|string>  $tokens
     * @return array<int, string|int>|string|null
     */
    private function previousMeaningfulToken(array $tokens, int $index): array|string|null
    {
        for ($j = $index - 1; $j >= 0; $j--) {
            $token = $tokens[$j];
            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return $token;
        }

        return null;
    }
}
