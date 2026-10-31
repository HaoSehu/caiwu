<?php

declare(strict_types=1);

namespace App\Support;

use App\Constants\ManualPaymentGateway;
use App\Constants\PaymentGatewayCode;
use App\Models\Payment;
use App\Services\Integrations\Plugins\PaymentGatewayBindingResolver;

/**
 * 支付渠道展示名集中解析：把「插件名称」与「支付渠道（支付方式）」拆成两个维度。
 *
 * - 插件名（plugin_name）只用于管理端展示与日志记录，禁止出现在用户端；
 * - 渠道名（channel_name）是用户端唯一可读的展示名，必须与实际走的支付渠道一致。
 *
 * 历史数据里的 option_name 曾写成「易支付 - 微信」这类插件名与渠道名的拼接串，
 * 读取时按分隔符还原成渠道名，不允许再把两个维度拼在一起。
 *
 * 旧库（IDC 版本）的 payments 只有 gateway_key 与回调报文两处承载渠道事实：
 * 新库的 plugin_key/plugin_name/payment_type/option_* 快照列在旧库并不存在，
 * 迁移只搬运数据、不猜业务含义，因此这批历史行的 gateway_key 保持为空。
 * 空值不等于「没有支付渠道」——渠道写在旧库自己留下的回调标记里，
 * 展示判据必须承认这份证据，见 hasKnownChannel() / channelScopeSql()。
 */
final class PaymentChannelLabel
{
    /**
     * 插件名与渠道名的历史拼接分隔符（如「易支付 - 微信」）。
     */
    private const LEGACY_SEPARATOR = ' - ';

    /**
     * 旧库手动入账写入的回调键：值可能是 'manual'（表「这是手工入账」），
     * 也可能是具体方式（bank_transfer/cash/…）。两种都是渠道事实。
     */
    private const CALLBACK_METHOD_KEY = 'payment_gateway';

    /**
     * 旧库各入账链路自己写下的渠道标记来源键。
     */
    private const CALLBACK_SOURCE_KEY = 'source';

    /**
     * 支付宝异步通知的特征键：任一出现即可确认是支付宝渠道。
     *
     * 刻意只取这三个——notify_id/trade_status/buyer_logon_id 是支付宝通知独有，
     * 易支付、微信的报文不会带，用它做判定不会把其它渠道错认成支付宝。
     */
    public const ALIPAY_CALLBACK_KEYS = ['notify_id', 'trade_status', 'buyer_logon_id'];

    /**
     * 插件名：仅管理端与日志可用。取支付记录快照，缺失时留空，不臆造。
     */
    public static function pluginNameFromPayment(Payment $payment): string
    {
        return trim((string) ($payment->plugin_name ?? ''));
    }

    /**
     * 渠道名：用户端唯一可读的支付方式展示名。
     * 优先取支付记录已落的渠道快照（兼容历史拼接串），
     * 其次按 payment_type 精确还原，最后回落到网关级渠道名。
     */
    public static function channelNameFromPayment(Payment $payment): string
    {
        $pluginName = self::pluginNameFromPayment($payment);
        $optionName = trim((string) ($payment->option_name ?? ''));

        if ($optionName !== '') {
            $channel = self::stripPluginPrefix($optionName, $pluginName);

            // 旧链路在没有选项快照时会拿插件名填 option_name，这种值不是渠道名，必须回落。
            if ($channel !== '' && $channel !== $pluginName) {
                return $channel;
            }
        }

        return self::fromGateway(
            self::resolveGatewayKey($payment),
            trim((string) ($payment->payment_type ?? ''))
        );
    }

    /**
     * 支付记录的两个维度一次性取值，供账单、订单、支付单的读取块复用。
     *
     * @return array{plugin_name: string, channel_name: string}
     */
    public static function fromPayment(Payment $payment): array
    {
        return [
            'plugin_name' => self::pluginNameFromPayment($payment),
            'channel_name' => self::channelNameFromPayment($payment),
        ];
    }

    /**
     * 渠道键的读取口径：gateway_key 优先，为空时回落回调证据。
     *
     * 兜底只认旧库自己写下的明确标记（手动入账方式、余额支付来源、
     * 网关预下单来源、支付宝通知特征键），不做概率推断：
     * 猜错会让账单上的支付方式与真实收款渠道不一致，比留空更糟。
     */
    public static function resolveGatewayKey(Payment $payment): string
    {
        $gateway = $payment->gatewayKey();

        if ($gateway !== '') {
            return $gateway;
        }

        return self::gatewayFromCallback($payment->callback_raw);
    }

    /**
     * 是否具备可展示的渠道：gateway_key 有值，或回调证据能还原出渠道。
     *
     * 这是「要不要在账单/订单/支付单上展示支付方式」的判据，
     * 与「这笔钱是否第三方真实资金流入」（PaymentGatewayCode::isThirdParty）不是同一件事，
     * 后者是资金边界审计口径，拿它做展示过滤会让整批历史单据的支付方式消失。
     */
    public static function hasKnownChannel(Payment $payment): bool
    {
        return self::resolveGatewayKey($payment) !== '';
    }

    /**
     * hasKnownChannel() 的 SQL 等价条件，供列表分页在库侧筛选。
     *
     * 同一个判据在 PHP 与 SQL 两侧分别实现，最容易出现「列表 total 与列表条数不一致」，
     * 因此条件由本类单点生成：evidenceSql() 与 gatewayFromCallback() 逐条对应，
     * PaymentChannelScopeTest 断言两侧选出的主键集合完全相同。
     *
     * @return array{0: string, 1: list<mixed>}
     */
    public static function channelScopeSql(string $table = 'payments', string $gateway = ''): array
    {
        $prefix = $table !== '' ? $table.'.' : '';
        $gateway = $gateway !== '' ? PaymentGatewayCode::normalize($gateway) : '';

        [$rawSql, $rawBindings] = self::evidenceSql($prefix.'callback_raw', $gateway);
        [$callbackSql, $callbackBindings] = self::evidenceSql('pc.payload_json', $gateway);

        // 按渠道筛选时先认列上的值，再认回调证据；不指定渠道则任一侧有证据即可见。
        $ownColumn = $gateway === ''
            ? '(('.$prefix.'gateway_key IS NOT NULL AND '.$prefix."gateway_key <> '') OR ".$rawSql.')'
            : '('.$prefix.'gateway_key = ? OR '.$rawSql.')';
        $ownBindings = $gateway === '' ? $rawBindings : [$gateway, ...$rawBindings];

        // 支付单自身的回调列，或 payment_callbacks 里同类型回调，任一侧命中即算。
        // 与 PHP 侧一致：Payment::callback_raw 访问器会把 callbacks 关系合并进来。
        $sql = '('.$ownColumn
            .' OR EXISTS (SELECT 1 FROM payment_callbacks pc WHERE pc.payment_id = '.$prefix
            ."id AND pc.callback_type = 'payment' AND ".$callbackSql.'))';

        return [$sql, [...$ownBindings, ...$callbackBindings]];
    }

    /**
     * 手动入账在回调里可能出现的全部取值。
     *
     * @return list<string>
     */
    public static function manualMethodValues(): array
    {
        return array_values(array_unique([PaymentGatewayCode::MANUAL, ...ManualPaymentGateway::values()]));
    }

    /**
     * 日志用的二维上下文：插件名与渠道名分别给出，不拼接、不脱敏。
     *
     * 有支付单时以支付单快照为准（能区分同一插件下的不同渠道）；
     * 尚无法定位支付单时按网关回填插件身份，渠道回落到网关级名称。
     *
     * @return array{plugin_name: string, channel_name: string}
     */
    public static function logContextForGateway(string $gateway, ?Payment $payment = null): array
    {
        if ($payment instanceof Payment) {
            return self::fromPayment($payment);
        }

        $snapshot = app(PaymentGatewayBindingResolver::class)->pluginSnapshotForGateway($gateway);

        return [
            'plugin_name' => trim((string) ($snapshot['plugin_name'] ?? '')),
            'channel_name' => self::fromGateway($gateway),
        ];
    }

    /**
     * 只有网关标识时的渠道名解析（无支付记录的快照场景）。
     */
    public static function fromGateway(string $gateway, string $paymentType = ''): string
    {
        return PaymentGatewayCode::channelLabel($gateway, $paymentType);
    }

    /**
     * 回调证据 → 渠道键。规则顺序与 evidenceSql() 完全一致，改动必须同步两处。
     *
     * @param  array<string, mixed>  $raw
     */
    private static function gatewayFromCallback(array $raw): string
    {
        if ($raw === []) {
            return '';
        }

        $method = trim((string) ($raw[self::CALLBACK_METHOD_KEY] ?? ''));
        if (in_array($method, self::manualMethodValues(), true)) {
            return PaymentGatewayCode::MANUAL;
        }

        $source = strtolower(trim((string) ($raw[self::CALLBACK_SOURCE_KEY] ?? '')));

        if ($source === PaymentGatewayCode::BALANCE) {
            return PaymentGatewayCode::BALANCE;
        }

        foreach (PaymentGatewayCode::thirdPartyGateways() as $gateway) {
            if ($source !== '' && str_contains($source, $gateway)) {
                return $gateway;
            }
        }

        foreach (self::ALIPAY_CALLBACK_KEYS as $key) {
            if (array_key_exists($key, $raw)) {
                return PaymentGatewayCode::ALIPAY;
            }
        }

        return '';
    }

    /**
     * gatewayFromCallback() 的 SQL 等价条件。
     *
     * @return array{0: string, 1: list<mixed>}
     */
    private static function evidenceSql(string $jsonColumn, string $gateway = ''): array
    {
        $branches = [];
        $bindings = [];

        $methodValues = self::manualMethodValues();
        if ($gateway === '' || $gateway === PaymentGatewayCode::MANUAL) {
            $branches[] = 'JSON_UNQUOTE(JSON_EXTRACT('.$jsonColumn.", '$.payment_gateway')) IN ("
                .implode(',', array_fill(0, count($methodValues), '?')).')';
            $bindings = [...$bindings, ...$methodValues];
        }

        $sourceExpr = 'LOWER(COALESCE(JSON_UNQUOTE(JSON_EXTRACT('.$jsonColumn.", '$.source')), ''))";

        if ($gateway === '' || $gateway === PaymentGatewayCode::BALANCE) {
            $branches[] = $sourceExpr.' = ?';
            $bindings[] = PaymentGatewayCode::BALANCE;
        }

        // 每个网关一个独立 LIKE 谓词：合成 "LIKE ?,?,?,?" 会让 PDO 在预处理阶段
        // 报 1241 Cardinality violation（一个 LIKE 只能接一个模式）。
        foreach (PaymentGatewayCode::thirdPartyGateways() as $candidate) {
            if ($gateway !== '' && $gateway !== $candidate) {
                continue;
            }

            $branches[] = $sourceExpr.' LIKE ?';
            $bindings[] = '%'.$candidate.'%';
        }

        // 支付宝通知特征键：只在目标渠道为支付宝（或未指定）时参与判定。
        if ($gateway === '' || $gateway === PaymentGatewayCode::ALIPAY) {
            $alipayPaths = array_map(
                static fn (string $key): string => "'$.".$key."'",
                self::ALIPAY_CALLBACK_KEYS,
            );
            $branches[] = 'JSON_CONTAINS_PATH('.$jsonColumn.", 'one', ".implode(', ', $alipayPaths).') = 1';
        }

        return ['('.implode(' OR ', $branches).')', $bindings];
    }

    /**
     * 去掉历史拼接串里的插件名前缀：只保留最后一个分隔符之后的渠道名。
     */
    private static function stripPluginPrefix(string $optionName, string $pluginName): string
    {
        $separator = $pluginName !== '' ? $pluginName.self::LEGACY_SEPARATOR : self::LEGACY_SEPARATOR;

        if ($separator !== self::LEGACY_SEPARATOR && str_starts_with($optionName, $separator)) {
            return trim(substr($optionName, strlen($separator)));
        }

        $position = strrpos($optionName, self::LEGACY_SEPARATOR);

        return $position === false
            ? $optionName
            : trim(substr($optionName, $position + strlen(self::LEGACY_SEPARATOR)));
    }
}
