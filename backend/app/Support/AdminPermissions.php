<?php

namespace App\Support;

class AdminPermissions
{
    public const ALL = '*';

    public const DASHBOARD_VIEW = 'dashboard.view';

    public const USER_LIST = 'user.list';

    public const USER_DETAIL = 'user.detail';

    public const USER_MANAGE = 'user.manage';

    public const USER_RECHARGE = 'user.recharge';

    public const VERIFICATION_LIST = 'verification.list';

    public const VERIFICATION_UNBIND = 'verification.unbind';

    public const ORDER_LIST = 'order.list';

    public const ORDER_DETAIL = 'order.detail';

    public const ORDER_MANAGE = 'order.manage';

    public const INVOICE_LIST = 'invoice.list';

    public const INVOICE_DETAIL = 'invoice.detail';

    public const INVOICE_MANAGE = 'invoice.manage';

    public const TICKET_LIST = 'ticket.list';

    public const TICKET_REPLY = 'ticket.reply';

    public const TICKET_MANAGE = 'ticket.manage';

    public const PRODUCT_LIST = 'product.list';

    public const PRODUCT_MANAGE = 'product.manage';

    public const SETTINGS_MANAGE = 'settings.manage';

    public const LOG_LIST = 'log.list';

    public const REFERRAL_LIST = 'referral.list';

    public const FINANCE_REPORT = 'finance.report';

    public const FINANCE_WITHDRAW = 'finance.withdraw';

    public const MEMBER_LEVEL_MANAGE = 'member_level.manage';

    public const CONTENT_LIST = 'content.list';

    public const CONTENT_MANAGE = 'content.manage';

    public const STAFF_LIST = 'staff.list';

    public const STAFF_MANAGE = 'staff.manage';

    public const ROLE_LIST = 'role.list';

    public const ROLE_MANAGE = 'role.manage';

    public const PERMISSION_LIST = 'permission.list';

    /**
     * @return array<string, string[]>
     */
    public static function builtInRolePermissions(): array
    {
        return [
            'super_admin' => [self::ALL],
            'operator' => [
                self::DASHBOARD_VIEW,
                self::USER_LIST,
                self::USER_DETAIL,
                self::VERIFICATION_LIST,
                self::ORDER_LIST,
                self::ORDER_DETAIL,
                self::TICKET_LIST,
                self::TICKET_REPLY,
                self::PRODUCT_LIST,
                self::CONTENT_LIST,
            ],
            'finance' => [
                self::DASHBOARD_VIEW,
                self::USER_LIST,
                self::USER_DETAIL,
                self::USER_RECHARGE,
                self::ORDER_LIST,
                self::ORDER_DETAIL,
                self::INVOICE_LIST,
                self::INVOICE_DETAIL,
                self::INVOICE_MANAGE,
                self::LOG_LIST,
                self::FINANCE_REPORT,
                self::FINANCE_WITHDRAW,
            ],
        ];
    }

    /**
     * @return string[]
     */
    public static function resolveRolePermissions(?string $roleName, array $storedPermissions = []): array
    {
        $base = self::builtInRolePermissions()[trim((string) $roleName)] ?? [];
        $permissions = array_values(array_unique(array_merge($base, self::normalize($storedPermissions))));

        if (in_array(self::ALL, $permissions, true)) {
            return [self::ALL];
        }

        $expanded = [];
        foreach ($permissions as $permission) {
            $expanded[$permission] = true;

            foreach (self::impliedPermissions($permission) as $implied) {
                $expanded[$implied] = true;
            }
        }

        return array_keys($expanded);
    }

    /**
     * @return string[]
     */
    public static function impliedPermissions(string $permission): array
    {
        return match ($permission) {
            self::USER_MANAGE => [self::USER_LIST, self::USER_DETAIL],
            self::ORDER_MANAGE => [self::ORDER_LIST, self::ORDER_DETAIL],
            self::INVOICE_MANAGE => [self::INVOICE_LIST, self::INVOICE_DETAIL],
            self::TICKET_MANAGE => [self::TICKET_LIST, self::TICKET_REPLY],
            self::PRODUCT_MANAGE => [self::PRODUCT_LIST],
            self::CONTENT_MANAGE => [self::CONTENT_LIST],
            self::STAFF_MANAGE => [self::STAFF_LIST],
            self::ROLE_MANAGE => [self::ROLE_LIST, self::PERMISSION_LIST],
            default => [],
        };
    }

    /**
     * @return string[]
     */
    private static function normalize(array $permissions): array
    {
        return array_values(array_filter(array_map(
            static fn (mixed $permission) => is_string($permission) ? trim($permission) : '',
            $permissions
        )));
    }
}
