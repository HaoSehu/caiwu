<?php

declare(strict_types=1);

namespace App\Integrations\Mofang\Support;

use App\Services\Auth\Contracts\LegacyPasswordVerifier;

final class MofangLegacyPasswordVerifier implements LegacyPasswordVerifier
{
    public function verify(string $plaintext, string $stored, bool &$needsPasswordRehash = false): ?bool
    {
        if (! $this->isMofangMd5Hash($stored)) {
            return null;
        }

        if (! hash_equals(substr($stored, 3), md5($plaintext))) {
            return false;
        }

        $needsPasswordRehash = true;

        return true;
    }

    private function isMofangMd5Hash(string $stored): bool
    {
        return str_starts_with($stored, '###')
            && strlen($stored) === 35
            && preg_match('/^###[a-f0-9]{32}$/i', $stored) === 1;
    }
}
