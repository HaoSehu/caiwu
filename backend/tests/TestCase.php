<?php

declare(strict_types=1);

namespace Tests;

use App\Services\Integrations\Payments\Drivers\AlipayFaceToFaceGateway;
use App\Services\Integrations\Payments\PaymentGatewayManager;
use App\Services\Integrations\Payments\PaymentGatewayRegistry;
use App\Services\PaymentGateway\AlipayFaceToFaceService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->guardAgainstProductionDatabaseForTests();
    }

    private function guardAgainstProductionDatabaseForTests(): void
    {
        if (! app()->environment('testing')) {
            return;
        }

        $connection = DB::connection();
        $driver = (string) $connection->getDriverName();
        $database = (string) $connection->getDatabaseName();

        if ($driver === 'sqlite') {
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

    protected function makePaymentGatewayManagerForTest(?AlipayFaceToFaceService $alipayService = null): PaymentGatewayManager
    {
        $alipayService ??= $this->createMock(AlipayFaceToFaceService::class);

        return new PaymentGatewayManager(new PaymentGatewayRegistry([
            new AlipayFaceToFaceGateway($alipayService),
        ]));
    }
}
