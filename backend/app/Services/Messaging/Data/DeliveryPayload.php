<?php

declare(strict_types=1);

namespace App\Services\Messaging\Data;

/**
 * 已渲染的投递载荷：加密存入 message_deliveries.payload_encrypted，禁止写入日志或队列。
 */
final class DeliveryPayload
{
    /**
     * @param  array<string, string>  $parameters  厂商模板变量
     */
    public function __construct(
        public readonly string $address,
        public readonly string $title = '',
        public readonly string $body = '',
        public readonly string $templateId = '',
        public readonly array $parameters = [],
        public readonly int $channelId = 0,
        public readonly int $channelRevision = 0,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'address' => $this->address,
            'title' => $this->title,
            'body' => $this->body,
            'template_id' => $this->templateId,
            'parameters' => $this->parameters,
            'channel_id' => $this->channelId,
            'channel_revision' => $this->channelRevision,
        ];
    }

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['address'] ?? ''),
            (string) ($data['title'] ?? ''),
            (string) ($data['body'] ?? ''),
            (string) ($data['template_id'] ?? ''),
            is_array($data['parameters'] ?? null) ? $data['parameters'] : [],
            (int) ($data['channel_id'] ?? 0),
            (int) ($data['channel_revision'] ?? 0),
        );
    }
}
