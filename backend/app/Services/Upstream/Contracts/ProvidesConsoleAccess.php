<?php

declare(strict_types=1);

namespace App\Services\Upstream\Contracts;

/**
 * 上游登录与会话能力。
 *
 * 软契约（@method 仅提供 IDE/静态分析提示，不强制实现）：
 * 平台通过鸭子类型调用，任何新实现都必须提供同名方法。
 *
 * @method string login(\App\Models\Supplier $supplier)
 * @method string refreshJwt(\App\Models\Supplier $supplier)
 * @method array loginResponse(\App\Models\Supplier $supplier)
 * @method array getUserProfile(\App\Models\Supplier $supplier)
 * @method array getBalance(\App\Models\Supplier $supplier)
 */
interface ProvidesConsoleAccess {}
