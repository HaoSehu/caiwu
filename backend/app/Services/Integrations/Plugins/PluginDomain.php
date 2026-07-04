<?php

declare(strict_types=1);

namespace App\Services\Integrations\Plugins;

use InvalidArgumentException;

final class PluginDomain
{
    public const PAYMENT = 'payment';

    public const VERIFICATION = 'verification';

    public const CAPTCHA = 'captcha';

    public const MAIL = 'mail';

    public const SMS = 'sms';

    public const UPSTREAM = 'upstream';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return [
            self::PAYMENT,
            self::VERIFICATION,
            self::MAIL,
            self::SMS,
            self::UPSTREAM,
        ];
    }

    public static function assertValid(string $domain): string
    {
        $normalized = trim($domain);

        if (! in_array($normalized, self::values(), true)) {
            throw new InvalidArgumentException("Unsupported plugin domain [{$domain}]");
        }

        return $normalized;
    }

    public static function directoryName(string $domain): string
    {
        return match (self::assertValid($domain)) {
            self::PAYMENT => 'gateways',
            self::VERIFICATION => 'certification',
            self::MAIL => 'mail',
            self::SMS => 'sms',
            self::UPSTREAM => 'servers',
        };
    }
}
