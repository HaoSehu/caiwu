<?php

declare(strict_types=1);

namespace App\Services\Upstream\Data;

use App\Services\Upstream\Contracts\UpstreamDriver;

final readonly class UpstreamProviderDescriptor
{
    /**
     * 上游 provider 对外只暴露可审计元数据，避免业务层继续读取驱动内部实现。
     *
     * @param  array<int, class-string>  $capabilities
     */
    public function __construct(
        public string $key,
        public string $label,
        public array $capabilities = [],
    ) {}

    public static function fromDriver(UpstreamDriver $driver): self
    {
        return new self(
            key: $driver->key(),
            label: $driver->label(),
            capabilities: $driver->capabilities(),
        );
    }

    /**
     * @return array{key:string,label:string,capabilities:array<int, class-string>}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'capabilities' => $this->capabilities,
        ];
    }

    /**
     * @return array{value:string,label:string}
     */
    public function toOption(): array
    {
        return [
            'value' => $this->key,
            'label' => $this->label,
        ];
    }
}
