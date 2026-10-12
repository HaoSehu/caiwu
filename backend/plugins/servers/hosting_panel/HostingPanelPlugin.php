<?php

declare(strict_types=1);

namespace Caiwu\Plugins\Servers\HostingPanel;

use Caiwu\Plugins\Servers\HostingPanel\Logic\HostingPanel;

/**
 * 主机面板接口插件入口。
 *
 * 驱动实现与能力承载见 Logic\HostingPanel；传输层（HostingPanelApiTransport）
 * 因被 zjmf_finance 插件与系统服务共享，保留在 app/Services/Upstream/Drivers/，
 * 本插件只负责把 hosting_panel_api 注册进 ProviderRegistry。
 */
final class HostingPanelPlugin extends HostingPanel {}
