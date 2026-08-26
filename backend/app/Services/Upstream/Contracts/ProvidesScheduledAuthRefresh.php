<?php

declare(strict_types=1);

namespace App\Services\Upstream\Contracts;

/**
 * 上游定时刷新会话能力（软契约）。
 *
 * @method string refreshJwt(\App\Models\Supplier $supplier)
 */
interface ProvidesScheduledAuthRefresh {}
