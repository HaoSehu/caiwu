<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Upstream\Drivers\HostingPanelApi\HostingPanelApiTransport;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionMethod;
use Tests\TestCase;

class HostingPanelApiTransportSanitizeTest extends TestCase
{
    /**
     * @return array{HostingPanelApiTransport&MockObject, ReflectionMethod}
     */
    private function method(): array
    {
        /** @var HostingPanelApiTransport&MockObject $transport */
        $transport = $this->getMockBuilder(HostingPanelApiTransport::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $method = new ReflectionMethod(HostingPanelApiTransport::class, 'sanitizePagePreview');
        $method->setAccessible(true);

        return [$transport, $method];
    }

    public function test_masks_quoted_key_value_pairs(): void
    {
        [$transport, $method] = $this->method();

        $result = (string) $method->invoke($transport, '"jwt":"eyJhbGciOiJIUzI1NiJ9.payload.sig"');

        // 值被遮蔽且保留键与两侧引号，输出结构完整。
        $this->assertStringNotContainsString('eyJhbGciOiJIUzI1NiJ9', $result);
        $this->assertSame('"jwt":"***"', $result);
    }

    public function test_masks_bare_unquoted_values(): void
    {
        [$transport, $method] = $this->method();

        $result = (string) $method->invoke($transport, 'jwt=abcdef1234567890abcdef');

        $this->assertStringNotContainsString('abcdef1234567890', $result);
        $this->assertStringContainsString('jwt=***', $result);
    }

    public function test_masks_html_hidden_input_value(): void
    {
        [$transport, $method] = $this->method();

        $html = '<input name="csrf_token" value="s3cr3tvalue">';
        $result = (string) $method->invoke($transport, $html);

        $this->assertStringNotContainsString('s3cr3tvalue', $result);
        $this->assertSame('<input name="csrf_token" value="***">', $result);
    }

    public function test_short_common_words_are_not_masked(): void
    {
        [$transport, $method] = $this->method();

        // 短词与普通业务字段不应误伤。
        $plain = 'token: short session_id=abc123 status=ok';
        $this->assertSame($plain, (string) $method->invoke($transport, $plain));
    }
}
