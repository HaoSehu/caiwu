<?php

declare(strict_types=1);

namespace Caiwu\Plugins\Addons\Ftp;

use Caiwu\Plugins\Addons\Ftp\Logic\Ftp;

/**
 * FTP 媒体库远程存储插件入口类。
 *
 * 运行时只认本类：PluginFileLoader 按需 require，PluginRuntimeRegistry
 * 反射调用 execute/healthCheck。具体逻辑全部收敛在 logic/Ftp.php，
 * 入口类保持一行委托，与 demo_style 插件同构。
 */
final class FtpPlugin
{
    /**
     * 插件动作统一入口。
     *
     * @param  array  $request  运行时组装的请求（domain/slug/key/action/payload/config/context）
     * @return array{success: bool, message: string, data: array<string, mixed>} 归一化返回
     */
    public function execute(array $request): array
    {
        return (new Ftp)->execute($request);
    }

    /**
     * 健康检查。
     *
     * @return array{healthy: bool, message: string, details: array<string, mixed>}
     */
    public function healthCheck(): array
    {
        return (new Ftp)->healthCheck();
    }
}
