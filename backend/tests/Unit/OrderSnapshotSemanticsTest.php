<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Order;
use App\Models\Product;
use Tests\TestCase;

class OrderSnapshotSemanticsTest extends TestCase
{
    public function test_it_reads_order_snapshots_from_orders_main_fields(): void
    {
        $order = new Order;
        $order->setRawAttributes([
            'config_snapshot' => json_encode(['hostname' => 'srv-test-01'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'config_pricing_snapshot' => json_encode(['base_amount' => '99.00'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'coupon_snapshot' => json_encode(['name' => 'Test Coupon'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ], true);

        $this->assertSame(['hostname' => 'srv-test-01'], $order->config_snapshot);
        $this->assertSame(['base_amount' => '99.00'], $order->config_pricing_snapshot);
        $this->assertSame(['name' => 'Test Coupon'], $order->coupon_snapshot);
    }

    public function test_it_prefers_product_spec_snapshot_for_display_fields(): void
    {
        $product = new Product([
            'purchase_requires' => [
                'upstream_default_config' => [
                    'cpu' => '2',
                    'memory' => '2048',
                ],
            ],
        ]);

        $order = new Order([
            'product_spec_snapshot' => 'Snapshot Product Spec',
        ]);
        $order->setRelation('product', $product);

        $this->assertSame('Snapshot Product Spec', $order->product_spec_snapshot);
        $this->assertSame('Snapshot Product Spec', $order->display_product_name);
    }

    public function test_it_falls_back_to_related_product_display_name_when_spec_snapshot_missing(): void
    {
        $product = new Product([
            'product_type' => 'vps',
            'purchase_requires' => [
                'upstream_default_config' => [
                    'cpu' => '2',
                    'memory' => '2048',
                ],
            ],
        ]);

        $order = new Order;
        $order->setRelation('product', $product);

        $this->assertNull($order->product_spec_snapshot);
        $this->assertSame('2 vCPU 2G', $order->display_product_name);
        $this->assertSame('vps', $order->product_type_snapshot);
    }
}
