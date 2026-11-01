<?php

declare(strict_types=1);

namespace Caiwu\Plugins\Addons\Ftp\Lib;

/**
 * FTP 签名/编码纯函数类。
 *
 * FTP 协议本身无签名步骤，本类仅提供外链路径段编码所需的
 * 纯函数实现，保持与七牛插件同构的目录结构（Signer 承担
 * 「编码纯函数」职责，不承担网络 IO）。
 */
final class FtpSigner
{
    /**
     * 对路径段做 rawurlencode 但保留斜杠（供外链拼接复用）。
     */
    public static function encodePath(string $path): string
    {
        return implode('/', array_map(
            static fn (string $segment): string => rawurlencode($segment),
            explode('/', $path)
        ));
    }
}
