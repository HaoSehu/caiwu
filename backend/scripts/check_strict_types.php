<?php

declare(strict_types=1);

/**
 * 校验 backend/app 与 backend/plugins 下的 PHP 源文件是否声明了 declare(strict_types=1)。
 *
 * 判定口径：
 *   1. 声明了类的文件视为源文件，必须带 declare(strict_types=1)；
 *   2. 尚未补齐的历史文件登记在 backend/strict-types-baseline.json，逐条豁免；
 *   3. 清单只减不增：条目对应的文件已经补上声明、或文件已不存在，都算未清理，同样失败。
 *
 * 为什么用基线而不是一次性全量补齐：给已经写在生产链路里的文件补 strict_types
 * 会改变隐式类型转换的行为（例如把 "12" 传给 int 参数会直接抛 TypeError），
 * 必须随所属模块评估与回归，不能靠一个提交批量打开。
 *
 * 退出码：0 通过；1 存在违规或清单未清理。
 *
 * 用法：php scripts/check_strict_types.php [--json]
 */
$basePath = dirname(__DIR__);
$baselinePath = $basePath.'/strict-types-baseline.json';
$scanRoots = ['app', 'plugins'];

/**
 * 递归收集指定目录下的 .php 文件（相对 backend 根的路径，统一用 / 分隔）。
 *
 * @return list<string>
 */
function collectPhpFiles(string $basePath, array $roots): array
{
    $files = [];
    foreach ($roots as $root) {
        $directory = $basePath.'/'.$root;
        if (! is_dir($directory)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($basePath) + 1));
            $files[$relative] = true;
        }
    }

    $files = array_keys($files);
    sort($files);

    return $files;
}

/**
 * 判断文件是否声明了类、接口、trait 或枚举。
 */
function declaresSymbol(string $contents): bool
{
    return preg_match('/^\s*(?:final\s+|abstract\s+|readonly\s+)*(?:class|interface|trait|enum)\s+\w+/m', $contents) === 1;
}

$baseline = is_file($baselinePath)
    ? json_decode((string) file_get_contents($baselinePath), true)
    : null;

if (! is_array($baseline) || ! isset($baseline['files']) || ! is_array($baseline['files'])) {
    fwrite(STDERR, "strict-types-baseline.json 无法解析或缺少 files 字段\n");
    exit(1);
}

$baselineFiles = [];
foreach ($baseline['files'] as $entry) {
    if (is_array($entry) && isset($entry['file']) && is_string($entry['file'])) {
        $baselineFiles[$entry['file']] = true;
    }
}

$violations = [];
$stale = [];

foreach (collectPhpFiles($basePath, $scanRoots) as $relative) {
    $contents = (string) file_get_contents($basePath.'/'.$relative);
    $hasDeclare = str_contains($contents, 'declare(strict_types=1)');
    $exempted = isset($baselineFiles[$relative]);

    if ($hasDeclare) {
        if ($exempted) {
            $stale[] = $relative.': 已声明 strict_types，请从 strict-types-baseline.json 移除该条目';
        }

        continue;
    }

    if ($exempted) {
        continue;
    }

    if (! declaresSymbol($contents)) {
        continue;
    }

    $violations[] = $relative.': 缺少 declare(strict_types=1)';
}

foreach (array_keys($baselineFiles) as $relative) {
    if (! is_file($basePath.'/'.$relative)) {
        $stale[] = $relative.': 基线登记的文件已不存在，请从 strict-types-baseline.json 移除该条目';
    }
}

$json = in_array('--json', $argv, true);
$ok = $violations === [] && $stale === [];

if ($json) {
    echo json_encode([
        'ok' => $ok,
        'violations' => $violations,
        'stale' => $stale,
        'baseline_count' => count($baselineFiles),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), PHP_EOL;
    exit($ok ? 0 : 1);
}

if ($ok) {
    echo 'strict_types 检查通过（基线 '.count($baselineFiles).' 个文件待补齐）', PHP_EOL;
    exit(0);
}

foreach ($violations as $violation) {
    fwrite(STDERR, '[缺少声明] '.$violation.PHP_EOL);
}

foreach ($stale as $item) {
    fwrite(STDERR, '[清单未清理] '.$item.PHP_EOL);
}

fwrite(STDERR, PHP_EOL.'新增文件必须写 declare(strict_types=1)；历史文件如需补齐，补完请同步从 strict-types-baseline.json 删除条目。'.PHP_EOL);
exit(1);
