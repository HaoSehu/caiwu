<?php

declare(strict_types=1);

namespace App\Services\Verification\Contracts;

interface VerificationDriver
{
    public function key(): string;

    public function label(): string;

    public function initialize(string $realname, string $idcard, string $certType, string $returnUrl): array;

    public function generateScanUrl(string $certifyId): array;

    public function queryStatus(string $certifyId): array;
}
