<?php

declare(strict_types=1);

namespace App\Services\Upstream\Contracts;

/**
 * 上游网络相关能力：升降级、流量包、上游账单支付（软契约）。
 *
 * @method array getHostUpgradeConfigOptions(\App\Models\Supplier $supplier, int $hostId, ?string $jwt = null)
 * @method array previewHostConfigUpgrade(\App\Models\Supplier $supplier, int $hostId, array $configOption, ?string $jwt = null)
 * @method array checkoutHostConfigUpgrade(\App\Models\Supplier $supplier, int $hostId, ?string $jwt = null)
 * @method array getHostUpgradePromoPreview(\App\Models\Supplier $supplier, int $hostId, string $promoCode, ?string $jwt = null)
 * @method array removeHostUpgradePromoCode(\App\Models\Supplier $supplier, int $hostId, ?string $jwt = null)
 * @method array getHostUpgradeOptions(\App\Models\Supplier $supplier, int $hostId, ?string $jwt = null)
 * @method array previewHostUpgrade(\App\Models\Supplier $supplier, int $hostId, int $productId, string $billingCycle, ?string $jwt = null)
 * @method array applyHostUpgradePromoCode(\App\Models\Supplier $supplier, int $hostId, string $promoCode, ?string $jwt = null)
 * @method array checkoutHostUpgrade(\App\Models\Supplier $supplier, int $hostId, ?string $jwt = null)
 * @method array buyFlowPacket(\App\Models\Supplier $supplier, string $rootUrl, int $flowPacketId, int $hostId, ?string $jwt = null)
 * @method array fundInvoice(\App\Models\Supplier $supplier, int $invoiceId, ?string $jwt = null, string $action = '支付上游账单')
 * @method array purchaseTrafficPackage(\App\Models\Supplier $supplier, int $hostId, string $mode, array $configOption, int $flowPacketId, string $rootUrl, ?string $jwt = null)
 * @method array purchaseHostUpgrade(\App\Models\Supplier $supplier, int $hostId, int $productId, string $billingCycle, string $promoCode = '', ?string $jwt = null)
 */
interface ProvidesConsoleNetwork {}
