<?php

declare(strict_types=1);

namespace Caiwu\Plugins\Addons\TencentCos;

/**
 * 腾讯云 COS 存储插件入口类。
 * 平台通过 config.php 的 entry 声明加载本类，转发给 logic 层分发。
 */
final class TencentCosPlugin extends TencentCos {}
