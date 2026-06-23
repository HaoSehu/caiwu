<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Integrations\Mofang\Support\MofangLegacyPasswordVerifier;
use PHPUnit\Framework\TestCase;

class MofangLegacyPasswordVerifierTest extends TestCase
{
    public function test_it_accepts_mofang_md5_password_and_requests_rehash(): void
    {
        $needsRehash = false;

        $matched = (new MofangLegacyPasswordVerifier)->verify(
            'Secret123',
            '###'.md5('Secret123'),
            $needsRehash
        );

        $this->assertTrue($matched);
        $this->assertTrue($needsRehash);
    }

    public function test_it_rejects_invalid_mofang_md5_password(): void
    {
        $needsRehash = false;

        $matched = (new MofangLegacyPasswordVerifier)->verify(
            'Secret123',
            '###'.md5('OtherSecret'),
            $needsRehash
        );

        $this->assertFalse($matched);
        $this->assertFalse($needsRehash);
    }

    public function test_it_ignores_non_mofang_hashes(): void
    {
        $needsRehash = false;

        $matched = (new MofangLegacyPasswordVerifier)->verify(
            'Secret123',
            '$2y$10$not-a-real-bcrypt-hash',
            $needsRehash
        );

        $this->assertNull($matched);
        $this->assertFalse($needsRehash);
    }
}
