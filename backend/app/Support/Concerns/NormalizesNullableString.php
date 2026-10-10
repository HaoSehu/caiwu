<?php

declare(strict_types=1);

namespace App\Support\Concerns;

trait NormalizesNullableString
{
    private function normalizeNullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
