<?php

declare(strict_types=1);

namespace App\Services\Upstream\Contracts;

/**
 * 上游续费与账单恢复能力（软契约）。
 *
 * @method array renewHost(\App\Models\Supplier $supplier, int $hostId, string $billingCycle)
 * @method array renewServiceInvoice(\App\Models\Supplier $supplier, int $hostId, string $billingCycle)
 * @method array|null recoverRenewInvoice(\App\Models\Supplier $supplier, int $hostId, int $upstreamInvoiceId)
 * @method array|null recoverRenewInvoiceWithContext(\App\Models\Supplier $supplier, int $hostId, int $upstreamInvoiceId, array $recoveryContext = [])
 */
interface ProvidesRenewal {}
