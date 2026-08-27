<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\BusinessException;
use App\Services\Integrations\Plugins\IntegrationDriverBindingResolver;
use InvalidArgumentException;

/**
 * 插件驱动注册表公共基类。
 *
 * Sms/Mail 驱动管理器的注册、解析、选项罗列逻辑逐字平行，
 * 统一收敛到本基类；子类只保留三个差异点：
 * 渠道文案、绑定解析器的候选列表与配置键。
 * 对外公开方法签名保持不变。
 *
 * @template TDriver of object
 */
abstract class PluginDriverManager
{
    /** @var array<string, TDriver> */
    private array $drivers = [];

    /**
     * @param  iterable<int, TDriver>  $drivers
     */
    protected function __construct(
        iterable $drivers = [],
        private ?IntegrationDriverBindingResolver $bindingResolver = null,
    ) {
        foreach ($drivers as $driver) {
            $this->registerDriver($driver);
        }
    }

    /**
     * 渠道中文文案，用于错误消息前缀（如“短信”“邮件”）。
     */
    abstract protected function channelLabel(): string;

    /**
     * 绑定解析器提供的候选驱动 key 列表（按优先级排序）。
     *
     * @return list<string>
     */
    abstract protected function bindingCandidates(): array;

    /**
     * 绑定解析器提供的系统配置驱动 key。
     */
    abstract protected function bindingConfiguredKey(): string;

    /**
     * 注册驱动本体；子类的公开 register() 以具体类型收窄签名后委托到这里。
     *
     * @param  TDriver  $driver
     */
    protected function registerDriver(mixed $driver): void
    {
        $key = trim($driver->key());

        if ($key === '') {
            throw new InvalidArgumentException($this->channelLabel().'驱动 key 不能为空');
        }

        if (isset($this->drivers[$key])) {
            throw new InvalidArgumentException("{$this->channelLabel()}驱动 [{$key}] 重复注册");
        }

        $this->drivers[$key] = $driver;
    }

    /**
     * 按显式 key 或绑定候选/系统配置解析驱动实例。
     */
    protected function resolveDriver(?string $key = null): object
    {
        $resolvedKey = trim((string) ($key ?? ''));
        if ($resolvedKey === '') {
            foreach ($this->bindingCandidates() as $candidate) {
                if (isset($this->drivers[$candidate])) {
                    return $this->drivers[$candidate];
                }
            }

            $resolvedKey = $this->bindingConfiguredKey();
        }

        if (isset($this->drivers[$resolvedKey])) {
            return $this->drivers[$resolvedKey];
        }

        // 两侧原实现分别为“单参构造（走默认 42200）”与“显式 42200”，实际业务码一致。
        throw new BusinessException("{$this->channelLabel()}驱动 [{$resolvedKey}] 未注册", 42200);
    }

    /**
     * 驱动下拉选项（value=key, label=名称）。
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function options(): array
    {
        $result = [];
        foreach ($this->drivers as $driver) {
            $result[] = ['value' => $driver->key(), 'label' => $driver->label()];
        }

        return $result;
    }

    final protected function bindingResolver(): IntegrationDriverBindingResolver
    {
        return $this->bindingResolver ??= app(IntegrationDriverBindingResolver::class);
    }
}
