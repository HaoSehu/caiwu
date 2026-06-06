<?php

declare(strict_types=1);

namespace App\Integrations\Mofang\Support;

use App\Services\Upstream\Contracts\WebSessionCredentialParser;

final class MofangCredentialParser implements WebSessionCredentialParser
{
    public function parseWebSessionCookie(string $notes): string
    {
        foreach (preg_split('/\R/u', trim($notes)) ?: [] as $line) {
            $line = trim((string) preg_replace('/^Cookie\s*:\s*/i', '', trim($line)));
            if (preg_match('/\b(ZJMF_[A-Z0-9_]+\s*=[^\r\n]+)/i', $line, $match) === 1) {
                return trim((string) $match[1]);
            }
        }

        return '';
    }
}
