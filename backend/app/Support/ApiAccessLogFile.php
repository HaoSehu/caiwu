<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\Carbon;

/**
 * api-json 结构化访问日志文件读写。
 *
 * 普通 API 访问日志不再写入 activity_logs，而是通过 logging.channels.api-json
 * （RotatingFileHandler + JsonFormatter）按日轮转落盘；管理端 API 日志 channel
 * 合并展示数据库审计事件与文件访问条目。
 */
class ApiAccessLogFile
{
    public const ID_PREFIX = 'file-api-';

    /** 新版 ID 携带不可逆文件定位信息，详情请求可直接 seek 到单行。 */
    private const LOCATOR_PREFIX = 'file-api-v2-';

    private const MAX_FILE_WINDOW = 366;

    /** 单次列表读取最多解析的有效 JSON 行数。 */
    public const MAX_READ_ENTRIES = 10000;

    /** 单次列表读取最多从所有日文件尾部读取的字节数。 */
    public const MAX_READ_TAIL_BYTES = 64 * 1024 * 1024;

    private const MAX_READ_LINES_PER_FILE = 2000;

    private const MAX_TAIL_BYTES_PER_FILE = 8 * 1024 * 1024;

    /** 旧版 MD5 ID 的兼容回退扫描预算，跨请求累计而非每个文件重置。 */
    private const MAX_LEGACY_FIND_BYTES = 32 * 1024 * 1024;

    private const MAX_LEGACY_FIND_LINES = 200000;

    /**
     * 读取 api-json 日文件中的最近条目（按文件修改时间取最近 $fileLimit 个日文件，
     * 每个文件读取行尾 $lineLimit 行）。默认文件窗口与 RotatingFileHandler 的
     * maxFiles 配置一致，避免列表只覆盖最近两天而详情却落在保留窗口内。
     *
     * @return list<array<string, mixed>>
     */
    public static function readRecent(int $lineLimit = 2000, ?int $fileLimit = null, ?int $entryLimit = null): array
    {
        $entries = [];

        $fileLimit ??= self::configuredFileLimit();
        $lineLimit = min(self::MAX_READ_LINES_PER_FILE, max(0, $lineLimit));
        $entryLimit = min(self::MAX_READ_ENTRIES, max(0, $entryLimit ?? self::MAX_READ_ENTRIES));

        if ($lineLimit === 0 || $entryLimit === 0) {
            return [];
        }

        $tailBudget = self::MAX_READ_TAIL_BYTES;
        foreach (self::files($fileLimit) as $path) {
            if ($tailBudget <= 0) {
                break;
            }

            foreach (self::readLastLines($path, $lineLimit, $tailBudget) as $line) {
                $entry = self::normalizeEntry($line['content'], $line['line_offset'], $path);

                if ($entry !== null) {
                    $entries[] = $entry;

                    if (count($entries) >= $entryLimit) {
                        return $entries;
                    }
                }
            }
        }

        return $entries;
    }

    public static function find(string $id): ?array
    {
        if (! self::isFileEntry($id)) {
            return null;
        }

        $locator = self::parseLocator($id);
        if ($locator !== null) {
            foreach (self::files(self::configuredFileLimit()) as $path) {
                if (self::fileToken($path) !== $locator['file_token']) {
                    continue;
                }

                return self::findAtOffset($path, $locator['offset'], $id);
            }

            return null;
        }

        // 旧版 ID 不含文件定位信息，只允许一次有界兼容扫描，避免任意
        // `file-api-*` 请求把整个保留窗口逐行读完。
        $bytesScanned = 0;
        $linesScanned = 0;
        foreach (self::files(self::configuredFileLimit()) as $path) {
            $entry = self::findInFile($path, $id, $bytesScanned, $linesScanned);
            if ($entry !== null) {
                return $entry;
            }

            if ($bytesScanned >= self::MAX_LEGACY_FIND_BYTES || $linesScanned >= self::MAX_LEGACY_FIND_LINES) {
                break;
            }
        }

        return null;
    }

    public static function isFileEntry(string $id): bool
    {
        return self::parseLocator($id) !== null
            || preg_match('/^'.preg_quote(self::ID_PREFIX, '/').'[a-f0-9]{32}$/', $id) === 1;
    }

    public static function channelPath(): string
    {
        return (string) config('logging.channels.api-json.handler_with.filename', storage_path('logs/api-json.log'));
    }

    /**
     * 按 mtime 倒序返回最近的日文件列表。
     *
     * @return list<string>
     */
    private static function files(?int $limit = null): array
    {
        $channelPath = self::channelPath();
        $pathInfo = pathinfo($channelPath);
        $dir = (string) ($pathInfo['dirname'] ?? '');
        $base = (string) ($pathInfo['filename'] ?? basename($channelPath));
        $extension = isset($pathInfo['extension']) ? '.'.$pathInfo['extension'] : '';

        if ($dir === '' || $dir === '.') {
            return [];
        }

        // Monolog 的默认格式是 `{filename}-{date}`，扩展名会在格式化后追加，
        // 因此 api-json.log 的实际文件名是 api-json-YYYY-MM-DD.log。
        $pattern = rtrim($dir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$base.'-*'.$extension;
        $files = array_values(array_filter(
            glob($pattern) ?: [],
            static fn (string $path): bool => is_file($path) && ! is_link($path),
        ));

        usort($files, static function (string $a, string $b): int {
            $mtimeOrder = (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0);

            return $mtimeOrder !== 0 ? $mtimeOrder : strcmp($b, $a);
        });

        $limit ??= self::MAX_FILE_WINDOW;

        return array_slice($files, 0, min(self::MAX_FILE_WINDOW, max(0, $limit)));
    }

    /**
     * 按物理偏移从文件头查找条目，保证列表返回的旧 ID 在同一日文件
     * 追加超过尾部窗口后仍可打开详情；只保留当前行，避免整文件入内存。
     */
    private static function findInFile(string $path, string $id, int &$bytesScanned, int &$linesScanned): ?array
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return null;
        }

        try {
            $offset = 0;
            while (($rawLine = fgets($handle)) !== false) {
                $bytesScanned += strlen($rawLine);
                $linesScanned++;
                if ($bytesScanned > self::MAX_LEGACY_FIND_BYTES || $linesScanned > self::MAX_LEGACY_FIND_LINES) {
                    break;
                }

                $content = rtrim($rawLine, "\r\n");
                $lineOffset = $offset;
                $offset += strlen($rawLine);

                if ($content === '') {
                    continue;
                }

                $legacyId = self::legacyId($path, $lineOffset, $content);
                $locatorId = self::locatorId($path, $lineOffset, $content);
                if ($legacyId !== $id && $locatorId !== $id) {
                    continue;
                }

                $entry = self::normalizeEntry($content, $lineOffset, $path);
                if ($entry !== null && $legacyId === $id) {
                    // 兼容旧详情链接：返回稳定的旧 ID，避免前端路由在升级后漂移。
                    $entry['id'] = $id;
                }

                return $entry;
            }
        } finally {
            fclose($handle);
        }

        return null;
    }

    /**
     * 通过新版 ID 的字节偏移读取单行，不再遍历文件内容。
     *
     * @return array<string, mixed>|null
     */
    private static function findAtOffset(string $path, int $offset, string $id): ?array
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return null;
        }

        try {
            if (fseek($handle, $offset, SEEK_SET) !== 0) {
                return null;
            }

            $rawLine = fgets($handle);
            if ($rawLine === false) {
                return null;
            }

            $content = rtrim($rawLine, "\r\n");
            if (self::locatorId($path, $offset, $content) !== $id) {
                return null;
            }

            return self::normalizeEntry($content, $offset, $path);
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return list<array{line_offset: int, content: string}>
     */
    private static function readLastLines(string $path, int $limit, int &$budget): array
    {
        if (! is_file($path) || $limit <= 0 || $budget <= 0) {
            return [];
        }

        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return [];
        }

        try {
            if (fseek($handle, 0, SEEK_END) !== 0) {
                return [];
            }

            $fileSize = ftell($handle);
            if ($fileSize === false || $fileSize <= 0) {
                return [];
            }

            // 从文件尾部按块回读，只保留足够覆盖最近 $limit 行的字节，
            // 避免 SplFileObject::seek(PHP_INT_MAX) 对整文件做线性扫描。
            $chunkSize = 8192;
            $position = $fileSize;
            $suffix = '';
            $newlineCount = 0;

            $bytesRead = 0;
            while (
                $position > 0
                && $newlineCount <= $limit
                && strlen($suffix) < self::MAX_TAIL_BYTES_PER_FILE
                && $bytesRead < $budget
            ) {
                $readSize = min($chunkSize, $position);
                $readSize = min($readSize, self::MAX_TAIL_BYTES_PER_FILE - strlen($suffix));
                $readSize = min($readSize, $budget - $bytesRead);
                if ($readSize <= 0) {
                    break;
                }
                $position -= $readSize;

                if (fseek($handle, $position, SEEK_SET) !== 0) {
                    return [];
                }

                $chunk = fread($handle, $readSize);
                if ($chunk === false) {
                    return [];
                }

                $suffix = $chunk.$suffix;
                $bytesRead += strlen($chunk);
                $newlineCount += substr_count($chunk, "\n");
            }

            $budget = max(0, $budget - $bytesRead);

            $startsAtBoundary = $position === 0;
            if (! $startsAtBoundary && fseek($handle, $position - 1, SEEK_SET) === 0) {
                $previousByte = fread($handle, 1);
                $startsAtBoundary = $previousByte === "\n";
            }

            $parts = explode("\n", $suffix);
            $lines = [];
            $offset = $position;
            $partCount = count($parts);

            foreach ($parts as $index => $part) {
                $lineOffset = $offset;
                $offset += strlen($part) + 1;

                // 若回读起点落在一行中间，首段只是被截断的旧行，不能返回。
                if ($index === 0 && ! $startsAtBoundary) {
                    continue;
                }

                // 文件以换行结尾时，explode 会产生一个虚拟空行。
                if ($index === $partCount - 1 && $part === '') {
                    continue;
                }

                $line = rtrim($part, "\r");
                if ($line !== '') {
                    $lines[] = ['line_offset' => $lineOffset, 'content' => $line];
                }
            }

            return array_slice($lines, -$limit);
        } finally {
            fclose($handle);
        }
    }

    /**
     * 将 JsonFormatter 输出的一行解析为管理端 API 日志行结构。
     *
     * @return array<string, mixed>|null
     */
    private static function normalizeEntry(string $line, int $lineOffset, string $filePath): ?array
    {
        $decoded = json_decode($line, true);

        if (! is_array($decoded)) {
            return null;
        }

        $detail = is_array($decoded['context'] ?? null) ? $decoded['context'] : [];
        $method = trim((string) ($detail['method'] ?? ''));
        $requestPath = trim((string) ($detail['path'] ?? ''));
        $action = trim($method.' '.$requestPath);

        if ($action === '') {
            return null;
        }

        return [
            // 文件追加不会改变既有行的起始字节偏移；相比物理行号，
            // 偏移无需从文件头重新计数，且在窗口滑动时保持稳定。
            'id' => self::locatorId($filePath, $lineOffset, $line),
            'source' => 'api_json',
            'user_id' => isset($detail['user_id']) ? (int) $detail['user_id'] : null,
            'user_type' => trim((string) ($detail['user_type'] ?? 'guest')) ?: 'guest',
            'actor_name' => trim((string) ($detail['actor_name'] ?? '')),
            'role_name' => '',
            'action' => $action,
            'module' => trim((string) ($detail['module'] ?? '')) ?: null,
            'target_id' => null,
            'detail' => $detail,
            'ip_address' => isset($detail['ip_address']) ? trim((string) $detail['ip_address']) : null,
            'created_at' => self::parseDate((string) ($decoded['datetime'] ?? '')),
            'request_id' => trim((string) ($detail['request_id'] ?? '')),
            'method' => $method,
            'path' => $requestPath,
            'status' => isset($detail['status']) ? (int) $detail['status'] : null,
            'params' => isset($detail['params']) && is_array($detail['params']) ? $detail['params'] : [],
            'user_agent' => trim((string) ($detail['user_agent'] ?? '')),
        ];
    }

    private static function parseDate(string $value): ?string
    {
        if (trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    private static function configuredFileLimit(): ?int
    {
        $maxFiles = (int) config('logging.channels.api-json.handler_with.maxFiles', 31);

        // Monolog 以 0 表示不限保留文件数，但管理端读取仍必须有硬上限，
        // 防止历史文件无限增长导致一次请求扫描整个日志目录。
        return $maxFiles > 0 ? min($maxFiles, self::MAX_FILE_WINDOW) : self::MAX_FILE_WINDOW;
    }

    /** @return array{file_token: string, offset: int}|null */
    private static function parseLocator(string $id): ?array
    {
        if (preg_match('/^'.preg_quote(self::LOCATOR_PREFIX, '/').'([a-f0-9]{16})-([0-9]+)-([a-f0-9]{32})$/', $id, $matches) !== 1) {
            return null;
        }

        $offset = filter_var($matches[2], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($offset === false) {
            return null;
        }

        return [
            'file_token' => $matches[1],
            'offset' => (int) $offset,
        ];
    }

    private static function fileToken(string $path): string
    {
        return substr(hash('sha256', $path), 0, 16);
    }

    private static function legacyId(string $path, int $offset, string $content): string
    {
        return self::ID_PREFIX.md5($path.'|'.$offset.'|'.$content);
    }

    private static function locatorId(string $path, int $offset, string $content): string
    {
        return self::LOCATOR_PREFIX.self::fileToken($path).'-'.$offset.'-'.md5($path.'|'.$offset.'|'.$content);
    }
}
