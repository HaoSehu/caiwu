<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\SensitiveDataSanitizer;
use Tests\TestCase;

class SensitiveDataSanitizerTest extends TestCase
{
    public function test_sanitize_text_redacts_oauth_client_credentials_in_urls(): void
    {
        $message = 'cURL error for https://example.test/oauth/token?client_id=api-key&client_secret=secret-value&grant_type=client_credentials';

        $sanitized = SensitiveDataSanitizer::sanitizeText($message);

        $this->assertStringNotContainsString('api-key', $sanitized);
        $this->assertStringNotContainsString('secret-value', $sanitized);
        $this->assertStringContainsString('client_id="[REDACTED]"', $sanitized);
        $this->assertStringContainsString('client_secret="[REDACTED]"', $sanitized);
    }
}
