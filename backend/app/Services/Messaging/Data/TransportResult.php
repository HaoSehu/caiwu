<?php

declare(strict_types=1);

namespace App\Services\Messaging\Data;

/**
 * 传输层统一结果。
 *
 * status 取值：accepted（平台受理）、delivered（本地渠道已送达，如站内消息）、
 * failed（明确失败）、unknown（结果未知，可能已发送）。
 *
 * retry 只在 failed 且厂商明确未受理时为 true；unknown 一律 false，
 * 避免「结果未知还自动重发」造成重复触达（对齐 howstack InstalledMessageTransport）。
 */
final class TransportResult
{
    public function __construct(
        public readonly string $status,
        public readonly string $error = '',
        public readonly bool $retry = false,
        public readonly ?string $requestId = null,
    ) {}

    public static function accepted(?string $requestId = null): self
    {
        return new self('accepted', '', false, $requestId);
    }

    public static function delivered(): self
    {
        return new self('delivered');
    }

    public static function failed(string $error, bool $retry = false): self
    {
        return new self('failed', $error, $retry);
    }

    public static function unknown(string $error): self
    {
        return new self('unknown', $error);
    }

    public function isAccepted(): bool
    {
        return in_array($this->status, ['accepted', 'delivered'], true);
    }
}
