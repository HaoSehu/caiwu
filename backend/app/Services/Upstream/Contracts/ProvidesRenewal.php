<?php

declare(strict_types=1);

namespace App\Services\Upstream\Contracts;

/**
 * 上游续费与账单恢复能力（软契约）。
 *
 * 可选能力（不要求所有实现方提供，调用方以 method_exists 探测）：
 *
 * @method array|null renewableCycles(\App\Models\Supplier $supplier, int $hostId) 读取上游认可的主机可续周期，上游不可达时返回 null
 * @method array renewHost(\App\Models\Supplier $supplier, int $hostId, string $billingCycle)
 * @method array renewServiceInvoice(\App\Models\Supplier $supplier, int $hostId, string $billingCycle)
 * @method array|null recoverRenewInvoice(\App\Models\Supplier $supplier, int $hostId, int $upstreamInvoiceId)
 * @method array|null recoverRenewInvoiceWithContext(\App\Models\Supplier $supplier, int $hostId, int $upstreamInvoiceId, array $recoveryContext = [])
 */
interface ProvidesRenewal {}
