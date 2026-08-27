<?php

declare(strict_types=1);

namespace App\Services\Upstream\Support;

use App\Models\Supplier;

/**
 * servers 域上游插件公共骨架（系统侧基类）。
 *
 * 参照 BaseMailPluginService 供 mail 插件继承的先例：把各服务器插件
 * 相互复制的标准骨架收敛到系统侧，各家上游协议细节仍留在插件 logic 类中。
 *
 * 零行为变化承诺：
 * - 对外方法签名、execute 动作名、返回数组结构与文案保持不变；
 * - 差异通过受保护钩子注入（capabilityCarrier/metadataExtras/
 *   dispatchSpecificAction/unsupportedActionMessage），钩子默认值即三家
 *   历史输出的公共部分；
 * - resolve() 的能力承载对象默认是本类自身；把能力委托给 lib 内部
 *   adapter 的插件应完整覆写 supports()/resolve() 保持原有委托语义。
 *
 * 目录批量取数循环依赖软契约 ProvidesConsoleCatalog::getProductConfigTemplate，
 * 由实现了目录能力的子类提供具体内容（与接口的 @method 标注同等性质），
 * 未实现目录能力且不调用批量取数的插件不受影响。
 *
 * @method array getProductConfigTemplate(\App\Models\Supplier $supplier, int $productId)
 */
abstract class AbstractUpstreamServerPlugin
{
    abstract public function key(): string;

    abstract public function label(): string;

    /**
     * @return array<int, class-string>
     */
    abstract public function capabilities(): array;

    abstract public function supplierFormSchema(): array;

    /**
     * 能力接口实际承载者：resolve() 命中时返回该对象。
     * 默认为本类自身（demo_servers / kanghostx 的历史语义）。
     */
    protected function capabilityCarrier(): object
    {
        return $this;
    }

    public function supports(string $capability): bool
    {
        return in_array($capability, $this->capabilities(), true)
            && $this->capabilityCarrier() instanceof $capability;
    }

    public function resolve(string $capability): ?object
    {
        return $this->supports($capability) ? $this->capabilityCarrier() : null;
    }

    /**
     * 标准动作分发 + 子类特有动作优先分发。
     *
     * server.metadata / server.supports / server.resolve_capability /
     * server.supplier_form_schema 四个动作及 default 回执是三家逐字等价的公共骨架，
     * 收敛在基类；各家特有动作（refresh_card、bulk_connect、health_check 等）
     * 通过 dispatchSpecificAction 在通用分支之前声明。
     *
     * @param  array<string, mixed>  $request
     * @return array<string, mixed>
     */
    public function execute(array $request): array
    {
        $action = trim((string) ($request['action'] ?? ''));
        $payload = is_array($request['payload'] ?? null) ? $request['payload'] : [];

        $specificResponse = $this->dispatchSpecificAction($action, $request, $payload);
        if ($specificResponse !== null) {
            return $specificResponse;
        }

        return match ($action) {
            'server.metadata' => [
                'success' => true,
                'action' => $action,
                'data' => array_merge([
                    'key' => $this->key(),
                    'label' => $this->label(),
                    'capabilities' => $this->capabilities(),
                ], $this->metadataExtras($request)),
            ],
            'server.supports' => [
                'success' => true,
                'action' => $action,
                'data' => [
                    'supported' => $this->supports((string) ($payload['capability'] ?? '')),
                ],
            ],
            'server.resolve_capability' => [
                'success' => true,
                'action' => $action,
                'data' => [
                    'resolved' => $this->resolve((string) ($payload['capability'] ?? '')),
                ],
            ],
            'server.supplier_form_schema' => [
                'success' => true,
                'action' => $action,
                'data' => $this->supplierFormSchema(),
            ],
            default => [
                'success' => false,
                'action' => $action,
                'message' => $this->unsupportedActionMessage(),
                'data' => [],
            ],
        };
    }

    /**
     * 子类特有动作的分发钩子：命中时返回动作回执，未命中必须返回 null，
     * 让动作继续落入基类的通用分支或 default 回执。
     *
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    protected function dispatchSpecificAction(string $action, array $request, array $payload): ?array
    {
        return null;
    }

    /**
     * server.metadata.data 在 key/label/capabilities 之外的附加键。
     * 键序与三家历史输出一致：附加键排在本体三键之后。
     *
     * @param  array<string, mixed>  $request
     * @return array<string, mixed>
     */
    protected function metadataExtras(array $request): array
    {
        return [];
    }

    /**
     * execute 的 default 分支文案；个别上游插件可按既有对外行为覆写为自有文案。
     */
    protected function unsupportedActionMessage(): string
    {
        return '不支持的上游插件动作';
    }

    /**
     * 目录配置项批量取数循环：两家实现逐字等价，收敛为单一循环，
     * 单条模板来自软契约 ProvidesConsoleCatalog::getProductConfigTemplate，
     * 由各目录能力实现类自行提供具体内容。
     *
     * @param  array<int|string, int>  $productIds
     * @return array<int, array<string, mixed>>
     */
    public function fetchBatchProductConfigOptions(Supplier $supplier, array $productIds, int $chunkSize = 8): array
    {
        $items = [];
        foreach ($productIds as $productId) {
            $items[(int) $productId] = $this->getProductConfigTemplate($supplier, (int) $productId);
        }

        return $items;
    }

    /**
     * 标量安全转字符串：标量与 Stringable 去除首尾空白，其余类型返回空串。
     * 来源：kanghostx 原有实现，行为逐字保留。
     */
    protected function scalarString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_scalar($value)) {
            return trim((string) $value);
        }

        if ($value instanceof \Stringable) {
            return trim((string) $value);
        }

        return '';
    }

    /**
     * 取第一个非空字符串结果；来源：kanghostx 原有实现，行为逐字保留。
     */
    protected function firstScalarString(mixed ...$values): string
    {
        foreach ($values as $value) {
            $string = $this->scalarString($value);
            if ($string !== '') {
                return $string;
            }
        }

        return '';
    }

    /**
     * 宽松布尔判定：字符串走 filter_var，其余强转布尔。
     * 来源：kanghostx 原有实现，行为逐字保留。
     */
    protected function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return filter_var($value, FILTER_VALIDATE_BOOL);
        }

        return (bool) $value;
    }

    /**
     * 供应商主键安全取整（demo_servers 与 kanghostx 各持一份的逐字等价实现）。
     */
    protected function supplierId(Supplier $supplier): int
    {
        return max(0, (int) ($supplier->id ?? 0));
    }

    /**
     * 供应商卡片"最近更新时间"格式化：DateTimeInterface 直出，
     * 其余按 scalarString 处理后兜底 '-'。
     *
     * 各家插件对可能出现的输入（null / 标量 / DateTimeInterface）强转后
     * 输出完全一致，统一取 scalarString 语义更安全。
     */
    protected function formatCardDateTime(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        $string = $this->scalarString($value);

        return $string !== '' ? $string : '-';
    }
}
