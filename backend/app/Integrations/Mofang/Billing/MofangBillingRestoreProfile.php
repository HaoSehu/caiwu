<?php

declare(strict_types=1);

namespace App\Integrations\Mofang\Billing;

use App\Services\Upstream\Contracts\UpstreamBillingRestoreProfile;

final class MofangBillingRestoreProfile implements UpstreamBillingRestoreProfile
{
    public function confirmationPhrases(): array
    {
        return [
            $this->defaultConfirmationPhrase(),
            'RESTORE_MOFANG_BILLING',
        ];
    }

    public function defaultConfirmationPhrase(): string
    {
        return 'RESTORE_UPSTREAM_BILLING';
    }

    public function source(): string
    {
        return 'mofang_sql_restore';
    }

    public function invoiceNoPrefix(): string
    {
        return 'MF';
    }

    public function paymentNoPrefix(): string
    {
        return 'PAYMF';
    }

    public function tradeNoPrefix(): string
    {
        return 'MF-';
    }
}
