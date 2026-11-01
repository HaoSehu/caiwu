<?php

declare(strict_types=1);

namespace Caiwu\Plugins\Addons\Ftp\Lib;

/**
 * FTP 客户端（插件内唯一外部 IO 类）。
 *
 * 传输层通过构造器注入的 ftp_* 函数桩替换，测试不发真实网络请求。
 * 真实环境直接使用 PHP 内置 ext-ftp 函数。
 */
final class FtpClient
{
    /** @var array<string, mixed> 插件配置（已解密） */
    private readonly array $config;

    /** @var object|null 可注入的传输桩：暴露 connect/login/pasv/mkdir/put/get/delete/size/close 等 ftp 语义方法 */
    private readonly ?object $transport;

    /**
     * @param  array<string, mixed>  $config
     * @param  object|null  $transport  传输桩（测试用），null 时使用真实 ftp_* 函数
     */
    public function __construct(array $config, ?object $transport = null)
    {
        $this->config = $config;
        $this->transport = $transport;
    }

    /**
     * 读取字符串配置。
     */
    private function configString(string $key, string $default = ''): string
    {
        $value = $this->config[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : $default;
    }

    /**
     * 读取整数配置（含默认值）。
     */
    private function configInt(string $key, int $default, int $min, int $max): int
    {
        $value = $this->config[$key] ?? $default;
        if (is_int($value) || (is_string($value) && preg_match('/^\d+$/', $value) === 1)) {
            $int = (int) $value;

            return max($min, min($max, $int));
        }
        if (is_bool($value)) {
            return $default;
        }

        return $default;
    }

    /**
     * 读取布尔配置（switch 字段）。
     */
    private function configBool(string $key, bool $default): bool
    {
        $value = $this->config[$key] ?? $default;
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value !== 0;
        }
        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'on', 'yes'], true);
        }

        return $default;
    }

    /**
     * 归一化根目录：以 / 开头、无结尾斜杠（空串视作根 /）。
     */
    public function rootDir(): string
    {
        $raw = trim($this->configString('root_dir'));
        if ($raw === '' || $raw === '/') {
            return '';
        }
        $normalized = '/'.trim($raw, '/');
        if (str_contains($normalized, '..') || strlen($normalized) > 500) {
            return '';
        }

        return $normalized;
    }

    /**
     * 拼接远端完整路径：root_dir + '/' + object_key。
     */
    public function remotePath(string $objectKey): string
    {
        $root = $this->rootDir();

        return $root === '' ? '/'.$objectKey : $root.'/'.$objectKey;
    }

    /**
     * 上传文件（put）：逐级建目录 → 二进制上传 → 尺寸比对，不等则尽力删半个文件并报错。
     *
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    public function put(string $localPath, string $objectKey, string $publicDomain, string $urlPrefix): array
    {
        $session = $this->connect();
        if ($session['success'] !== true) {
            return $session;
        }
        $conn = $session['data']['conn'];

        // 逐级创建目录（已存在忽略）
        $directory = dirname($this->remotePath($objectKey));
        $mk = $this->ensureDirectory($conn, $directory);
        if ($mk['success'] !== true) {
            $this->close($conn);

            return $mk;
        }

        // 二进制上传
        $remote = $this->remotePath($objectKey);
        $uploaded = $this->callPut($conn, $remote, $localPath);
        if ($uploaded !== true) {
            $this->callDelete($conn, $remote);
            $this->close($conn);

            return FtpSupport::failure('upstream_rejected', '上传失败，远端写入被拒绝');
        }

        // 尺寸比对
        $remoteSize = $this->callSize($conn, $remote);
        $localSize = filesize($localPath);
        if ($remoteSize === false || $remoteSize < 0 || $localSize === false || $remoteSize !== $localSize) {
            $this->callDelete($conn, $remote);
            $this->close($conn);

            return FtpSupport::failure('upstream_rejected', '上传后尺寸比对不一致，已尝试清理远端残留');
        }

        $this->close($conn);

        return FtpSupport::success('上传成功', [
            'object_key' => $objectKey,
            'url' => FtpSupport::buildUrl($publicDomain, $urlPrefix, $objectKey),
            'size' => $localSize,
        ]);
    }

    /**
     * 下载文件（get）：下载到 target_dir 下随机文件名临时文件。
     *
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    public function get(string $objectKey, string $targetDir): array
    {
        $session = $this->connect();
        if ($session['success'] !== true) {
            return $session;
        }
        $conn = $session['data']['conn'];

        $remote = $this->remotePath($objectKey);
        $tempName = 'ftp_'.bin2hex(random_bytes(8));
        $tempPath = rtrim($targetDir, '/\\').DIRECTORY_SEPARATOR.$tempName;

        $downloaded = $this->callGet($conn, $remote, $tempPath);
        if ($downloaded !== true) {
            if (is_file($tempPath)) {
                @unlink($tempPath);
            }
            $this->close($conn);

            return FtpSupport::failure('upstream_rejected', '下载失败，远端文件不存在或不可读');
        }
        $this->close($conn);

        $size = filesize($tempPath);
        if ($size === false) {
            return FtpSupport::failure('upstream_rejected', '下载后本地临时文件不可读');
        }

        return FtpSupport::success('下载成功', [
            'temp_path' => $tempPath,
            'size' => $size,
        ]);
    }

    /**
     * 删除对象（delete）：远端不存在按成功 missing=true；连接级失败整体失败。
     *
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    public function delete(string $objectKey): array
    {
        $session = $this->connect();
        if ($session['success'] !== true) {
            return $session;
        }
        $conn = $session['data']['conn'];
        $remote = $this->remotePath($objectKey);

        if (! $this->callExists($conn, $remote)) {
            $this->close($conn);

            return FtpSupport::success('对象不存在，按删除成功处理', [
                'deleted' => false,
                'missing' => true,
            ]);
        }

        $deleted = $this->callDelete($conn, $remote);
        $this->close($conn);
        if ($deleted !== true) {
            return FtpSupport::failure('upstream_rejected', '删除失败，远端拒绝该操作');
        }

        return FtpSupport::success('删除成功', [
            'deleted' => true,
            'missing' => false,
        ]);
    }

    /**
     * 判断对象是否存在（exists）。
     *
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    public function exists(string $objectKey): array
    {
        $session = $this->connect();
        if ($session['success'] !== true) {
            return $session;
        }
        $conn = $session['data']['conn'];
        $exists = $this->callExists($conn, $this->remotePath($objectKey));
        $this->close($conn);

        return FtpSupport::success('查询成功', ['exists' => $exists]);
    }

    /**
     * 连通性测试（storage.test）。
     *
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    public function test(): array
    {
        $start = microtime(true);
        $session = $this->connect();
        if ($session['success'] !== true) {
            return $session;
        }
        $conn = $session['data']['conn'];
        // 探测根目录可写：尝试列目录判断可访问
        $root = $this->rootDir() === '' ? '/' : $this->rootDir();
        $raw = $this->callRawlist($conn, $root);
        $this->close($conn);
        $latency = (int) round((microtime(true) - $start) * 1000);

        if ($raw === false) {
            return FtpSupport::success('连接成功，但根目录不可读', [
                'ok' => true,
                'endpoint' => $this->endpoint(),
                'root' => $root,
                'writable' => false,
                'latency_ms' => $latency,
            ]);
        }

        return FtpSupport::success('连接成功', [
            'ok' => true,
            'endpoint' => $this->endpoint(),
            'root' => $root,
            'writable' => true,
            'latency_ms' => $latency,
        ]);
    }

    /**
     * 建立连接并登录。
     *
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    private function connect(): array
    {
        if ($this->transport !== null) {
            $conn = $this->transport->connect(
                $this->configString('host'),
                $this->configInt('port', 21, 1, 65535),
                $this->configBool('use_ssl', false),
                $this->configInt('timeout', 30, 5, 120)
            );
        } else {
            if (! function_exists('ftp_connect')) {
                return FtpSupport::failure('dependency_missing', 'PHP 扩展 ext-ftp 未安装');
            }
            if ($this->configBool('use_ssl', false)) {
                $conn = @ftp_ssl_connect(
                    $this->configString('host'),
                    $this->configInt('port', 21, 1, 65535),
                    $this->configInt('timeout', 30, 5, 120)
                );
            } else {
                $conn = @ftp_connect(
                    $this->configString('host'),
                    $this->configInt('port', 21, 1, 65535),
                    $this->configInt('timeout', 30, 5, 120)
                );
            }
        }
        if ($conn === false || $conn === null) {
            return FtpSupport::failure('connect_failed', '无法连接 FTP 服务器');
        }

        // 统一超时
        if ($this->transport === null) {
            ftp_set_option($conn, FTP_TIMEOUT_SEC, $this->configInt('timeout', 30, 5, 120));
        }

        // 登录
        $username = $this->configString('username');
        $password = $this->configString('password');
        if ($this->transport !== null) {
            $login = $this->transport->login($conn, $username, $password);
        } else {
            $login = @ftp_login($conn, $username, $password);
        }
        if ($login !== true) {
            $this->close($conn);

            return FtpSupport::failure('auth_failed', 'FTP 用户名或密码错误');
        }

        // 被动模式
        if ($this->transport !== null) {
            $this->transport->pasv($conn, $this->configBool('passive', true));
        } elseif ($this->configBool('passive', true)) {
            @ftp_pasv($conn, true);
        }

        return FtpSupport::success('已连接', ['conn' => $conn]);
    }

    /**
     * 逐级创建远端目录（已存在忽略）。
     *
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    private function ensureDirectory(mixed $conn, string $directory): array
    {
        if ($directory === '/' || $directory === '' || $directory === '.') {
            return FtpSupport::success('无需创建目录', []);
        }
        $current = '';
        foreach (explode('/', trim($directory, '/')) as $segment) {
            if ($segment === '') {
                continue;
            }
            $current .= '/'.$segment;
            if ($this->transport !== null) {
                $exists = $this->transport->isDir($conn, $current);
            } else {
                $exists = @ftp_chdir($conn, $current) !== false;
            }
            if ($exists) {
                continue;
            }
            if ($this->transport !== null) {
                $made = $this->transport->mkdir($conn, $current);
            } else {
                $made = @ftp_mkdir($conn, $current);
            }
            if ($made !== true && $made !== $current) {
                if ($this->transport !== null) {
                    $recheck = $this->transport->isDir($conn, $current);
                } else {
                    $recheck = @ftp_chdir($conn, $current) !== false;
                }
                if ($recheck !== true) {
                    return FtpSupport::failure('upstream_rejected', '远端目录创建失败：'.$current);
                }
            }
        }

        return FtpSupport::success('目录就绪', []);
    }

    /**
     * 连接地址描述。
     */
    private function endpoint(): string
    {
        $ssl = $this->configBool('use_ssl', false) ? 'ftps' : 'ftp';

        return $ssl.'://'.$this->configString('host').':'.$this->configInt('port', 21, 1, 65535);
    }

    /**
     * 传输桩或真实 ftp_put。
     */
    private function callPut(mixed $conn, string $remote, string $local): bool
    {
        if ($this->transport !== null) {
            $result = $this->transport->put($conn, $remote, $local);

            return $result === true;
        }

        return @ftp_put($conn, $remote, $local, FTP_BINARY);
    }

    /**
     * 传输桩或真实 ftp_get。
     */
    private function callGet(mixed $conn, string $remote, string $tempPath): bool
    {
        if ($this->transport !== null) {
            $result = $this->transport->get($conn, $remote, $tempPath);

            return $result === true;
        }

        return @ftp_get($conn, $tempPath, $remote, FTP_BINARY);
    }

    /**
     * 传输桩或真实 ftp_delete。
     */
    private function callDelete(mixed $conn, string $remote): bool
    {
        if ($this->transport !== null) {
            $result = $this->transport->delete($conn, $remote);

            return $result === true;
        }

        return @ftp_delete($conn, $remote);
    }

    /**
     * 传输桩或真实 ftp_size。
     */
    private function callSize(mixed $conn, string $remote): int|false
    {
        if ($this->transport !== null) {
            $result = $this->transport->size($conn, $remote);
            if (is_int($result)) {
                return $result;
            }
            if (is_string($result) && preg_match('/^-?\d+$/', $result) === 1) {
                return (int) $result;
            }

            return false;
        }

        return @ftp_size($conn, $remote);
    }

    /**
     * 传输桩或真实 ftp_size 判存在。
     */
    private function callExists(mixed $conn, string $remote): bool
    {
        if ($this->transport !== null) {
            return $this->transport->exists($conn, $remote) === true;
        }

        // ftp_size 对不存在的文件返回 -1，这里只认非负尺寸为存在
        $size = @ftp_size($conn, $remote);

        return $size >= 0;
    }

    /**
     * 传输桩或真实 ftp_rawlist。
     *
     * @return array<int, string>|false
     */
    private function callRawlist(mixed $conn, string $directory): array|false
    {
        if ($this->transport !== null) {
            $result = $this->transport->rawlist($conn, $directory);
            if (is_array($result)) {
                return $result;
            }

            return false;
        }

        return @ftp_rawlist($conn, $directory);
    }

    /**
     * 关闭连接（桩场景调用 close，若实现）。
     */
    private function callClose(mixed $conn): void
    {
        if ($this->transport !== null) {
            if (method_exists($this->transport, 'close')) {
                $this->transport->close($conn);
            }

            return;
        }

        @ftp_close($conn);
    }

    /**
     * 统一关闭入口（真实/桩双通道）。
     */
    private function close(mixed $conn): void
    {
        $this->callClose($conn);
    }
}
