<?php

declare(strict_types=1);

namespace Tests;

use App\Constants\PaymentGatewayCode;
use App\Contracts\Integrations\Payments\PaymentGatewayInterface;
use App\Services\Integrations\Payments\Data\PaymentPrecreateRequest;
use App\Services\Integrations\Payments\Data\PaymentPrecreateResult;
use App\Services\Integrations\Payments\Data\PaymentQueryResult;
use App\Services\Integrations\Payments\Data\PaymentRefundRequest;
use App\Services\Integrations\Payments\Data\PaymentRefundResult;
use App\Services\Integrations\Payments\PaymentGatewayManager;
use App\Services\Integrations\Payments\PaymentGatewayRegistry;
use App\Services\Integrations\Plugins\PluginInstaller;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        $this->ensureMysqlClientOnPath();

        parent::setUp();

        // 应用已存在的后续测试也再次校验，防止测试中修改连接配置后继续执行。
        $this->guardAgainstProductionDatabaseForTests();
    }

    /**
     * Laravel 在父级 setUp 中创建应用后立刻执行 RefreshDatabase 等 trait。
     * 必须在该阶段拦截错误连接，不能等父级 setUp 返回后才检查。
     */
    protected function refreshApplication()
    {
        parent::refreshApplication();

        $this->guardAgainstProductionDatabaseForTests();
    }

    /**
     * MySqlSchemaState 加载 schema dump 时通过 Symfony Process 调用 mysql CLI，
     * 该进程继承当前 PHP 进程的 PATH。开发机（宝塔环境）mysql.exe 位于
     * D:\BtSoft\mysql\MySQL8.0\bin 且未加入系统 PATH，此处按需补入，避免测试报
     * 'mysql' is not recognized。仅当目标路径真实存在时才追加，幂等。
     */
    private function ensureMysqlClientOnPath(): void
    {
        $candidates = [
            'D:\\BtSoft\\mysql\\MySQL8.0\\bin',
            '/usr/bin',
            '/usr/local/mysql/bin',
        ];

        $currentPath = (string) getenv('PATH');

        foreach ($candidates as $candidate) {
            $mysqlBinary = $candidate.DIRECTORY_SEPARATOR.'mysql'.(DIRECTORY_SEPARATOR === '\\' ? '.exe' : '');
            if (is_file($mysqlBinary) && ! str_contains($currentPath, $candidate)) {
                putenv('PATH='.$candidate.PATH_SEPARATOR.$currentPath);
                $_ENV['PATH'] = $candidate.PATH_SEPARATOR.$currentPath;

                return;
            }
        }
    }

    private function guardAgainstProductionDatabaseForTests(): void
    {
        if (! app()->environment('testing')) {
            return;
        }

        $connection = DB::connection();
        $driver = (string) $connection->getDriverName();
        $database = (string) $connection->getDatabaseName();

        if ($driver === 'sqlite' || $database === ':memory:') {
            return;
        }

        if ($database !== '' && str_contains(strtolower($database), 'test')) {
            return;
        }

        throw new \RuntimeException(
            "拒绝在非测试数据库 [{$database}] 上运行测试，请使用独立测试库或 SQLite。"
        );
    }

    protected function mirrorServiceCompatToIdc(array $payload): void
    {
        $connection = DB::connection();
        $schema = $connection->getSchemaBuilder();
        $table = $schema->hasTable('services') ? 'services' : 'service_instances';
        $columns = $schema->getColumnListing($table);
        $normalizedPayload = $payload;

        if (! array_key_exists('invoice_id', $normalizedPayload) && array_key_exists('source_invoice_id', $normalizedPayload)) {
            $normalizedPayload['invoice_id'] = $normalizedPayload['source_invoice_id'];
        }

        if (! array_key_exists('amount', $normalizedPayload) && array_key_exists('renewal_price', $normalizedPayload)) {
            $normalizedPayload['amount'] = $normalizedPayload['renewal_price'];
        }

        if (! array_key_exists('locked_pricing', $normalizedPayload) && array_key_exists('pricing_snapshot_json', $normalizedPayload)) {
            $normalizedPayload['locked_pricing'] = $normalizedPayload['pricing_snapshot_json'];
        }

        if (! array_key_exists('provision_data', $normalizedPayload) && array_key_exists('provision_snapshot_json', $normalizedPayload)) {
            $normalizedPayload['provision_data'] = $normalizedPayload['provision_snapshot_json'];
        }

        if (! array_key_exists('domain', $normalizedPayload) && array_key_exists('instance_identifier', $normalizedPayload)) {
            $normalizedPayload['domain'] = $normalizedPayload['instance_identifier'];
        }

        $filteredPayload = array_intersect_key(
            $normalizedPayload,
            array_fill_keys($columns, true)
        );

        $filteredPayload = array_merge([
            'amount' => isset($normalizedPayload['amount']) && is_numeric($normalizedPayload['amount'])
                ? number_format((float) $normalizedPayload['amount'], 2, '.', '')
                : '0.00',
            'locked_pricing' => [],
            'provision_data' => [],
            'auto_renew' => 0,
        ], $filteredPayload);

        $connection->table($table)->updateOrInsert(
            ['id' => (int) ($payload['id'] ?? 0)],
            $filteredPayload
        );
    }

    protected function makePaymentGatewayManagerForTest(?PaymentGatewayInterface $gateway = null): PaymentGatewayManager
    {
        return new PaymentGatewayManager(new PaymentGatewayRegistry([
            $gateway ?? $this->makeFakePaymentGateway(),
        ]));
    }

    protected function activateIntegrationPluginForTest(string $domain, string $slug): void
    {
        $installer = app(PluginInstaller::class);
        $plugin = $installer->install($domain, $slug);
        $installer->enable($plugin);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeFakePaymentGateway(array $overrides = []): TestPaymentGateway
    {
        return new TestPaymentGateway($overrides);
    }
}

final class TestPaymentGateway implements PaymentGatewayInterface
{
    /** @var array<int, array{method: string, payload: mixed}> */
    private array $calls = [];

    /** @param array<string, mixed> $overrides */
    public function __construct(private array $overrides = []) {}

    public function key(): string
    {
        return (string) ($this->overrides['key'] ?? PaymentGatewayCode::ALIPAY);
    }

    public function name(): string
    {
        return (string) ($this->overrides['name'] ?? '支付宝当面付');
    }

    public function isEnabled(): bool
    {
        $this->record('isEnabled');

        return (bool) ($this->overrides['enabled'] ?? true);
    }

    public function matchesMerchantId(?string $merchantId): bool
    {
        $this->record('matchesMerchantId', $merchantId);

        $override = $this->overrides['matches_merchant'] ?? true;
        if (is_callable($override)) {
            return (bool) $override($merchantId, $this);
        }

        return (bool) $override;
    }

    public function precreate(PaymentPrecreateRequest $request): PaymentPrecreateResult
    {
        $this->record('precreate', $request);
        $result = $this->resolveOverride('precreate', $request, [
            'qr_code' => 'https://qr.alipay.test/default',
            'out_trade_no' => $request->outTradeNo,
        ]);

        if ($result instanceof PaymentPrecreateResult) {
            return $result;
        }

        $data = is_array($result) ? $result : [];

        return new PaymentPrecreateResult(
            qrCode: (string) ($data['qr_code'] ?? ''),
            outTradeNo: (string) ($data['out_trade_no'] ?? $request->outTradeNo),
            raw: is_array($data['raw'] ?? null) ? $data['raw'] : $data,
        );
    }

    public function query(string $outTradeNo): PaymentQueryResult
    {
        $this->record('query', $outTradeNo);
        $result = $this->resolveOverride('query', $outTradeNo, [
            'trade_status' => 'WAIT_BUYER_PAY',
            'trade_no' => '',
            'out_trade_no' => $outTradeNo,
            'total_amount' => '0.00',
        ]);

        if ($result instanceof PaymentQueryResult) {
            return $result;
        }

        $data = is_array($result) ? $result : [];

        return new PaymentQueryResult(
            tradeStatus: (string) ($data['trade_status'] ?? ''),
            tradeNo: (string) ($data['trade_no'] ?? ''),
            outTradeNo: (string) ($data['out_trade_no'] ?? $outTradeNo),
            totalAmount: (string) ($data['total_amount'] ?? ''),
            raw: is_array($data['raw'] ?? null) ? $data['raw'] : $data,
        );
    }

    public function refund(PaymentRefundRequest $request): PaymentRefundResult
    {
        $this->record('refund', $request);
        $result = $this->resolveOverride('refund', $request, [
            'trade_no' => $request->tradeNo ?? '',
            'out_trade_no' => $request->outTradeNo,
            'refund_fee' => number_format($request->refundAmount, 2, '.', ''),
            'fund_change' => '',
            'gmt_refund_pay' => '',
        ]);

        if ($result instanceof PaymentRefundResult) {
            return $result;
        }

        $data = is_array($result) ? $result : [];

        return new PaymentRefundResult(
            tradeNo: (string) ($data['trade_no'] ?? ''),
            outTradeNo: (string) ($data['out_trade_no'] ?? $request->outTradeNo),
            refundFee: (string) ($data['refund_fee'] ?? ''),
            fundChange: (string) ($data['fund_change'] ?? ''),
            gmtRefundPay: (string) ($data['gmt_refund_pay'] ?? ''),
            raw: is_array($data['raw'] ?? null) ? $data['raw'] : $data,
        );
    }

    public function verifyNotify(array $payload): bool
    {
        $this->record('verifyNotify', $payload);

        $override = $this->overrides['verify_notify'] ?? true;
        if (is_callable($override)) {
            return (bool) $override($payload, $this);
        }

        return (bool) $override;
    }

    public function buildNotifyResponse(bool $success): Response
    {
        return new Response($success ? 'success' : 'fail', 200, ['Content-Type' => 'text/plain']);
    }

    public function countCalls(string $method): int
    {
        return count(array_filter(
            $this->calls,
            static fn (array $call): bool => $call['method'] === $method
        ));
    }

    private function record(string $method, mixed $payload = null): void
    {
        $this->calls[] = [
            'method' => $method,
            'payload' => $payload,
        ];
    }

    private function resolveOverride(string $key, mixed $argument, mixed $default): mixed
    {
        $override = $this->overrides[$key] ?? $default;

        if ($override instanceof \Throwable) {
            throw $override;
        }

        if (is_callable($override)) {
            return $override($argument, $this);
        }

        return $override;
    }
}
