<?php

declare(strict_types=1);

namespace App\Services\User;

use App\Exceptions\BusinessException;
use App\Models\User;
use App\Services\System\SettingService;

/**
 * 账户策略断言：按管理端开关校验用户是否完成实名、绑定手机号、绑定邮箱。
 *
 * 只用于「关键写操作」前置拦截（下单、支付、充值、提现），不挂中间件、
 * 不阻断只读接口；自动续费与管理端发起的资金动作也不走这里。
 */
final class AccountPolicyService
{
    private const MISSING_VERIFICATION = 'verification';

    private const MISSING_PHONE = 'phone';

    private const MISSING_EMAIL = 'email';

    /**
     * 未完成项 → 业务码与用户提示（简体中文）。顺序即优先展示顺序。
     *
     * @var array<string, array{code: int, message: string}>
     */
    private const MISSING_DEFINITIONS = [
        self::MISSING_VERIFICATION => [
            'code' => 40301,
            'message' => '根据平台要求，请先完成实名认证后再进行操作',
        ],
        self::MISSING_PHONE => [
            'code' => 40302,
            'message' => '根据平台要求，请先绑定手机号后再进行操作',
        ],
        self::MISSING_EMAIL => [
            'code' => 40303,
            'message' => '根据平台要求，请先绑定邮箱后再进行操作',
        ],
    ];

    public function __construct(private readonly SettingService $settings) {}

    /**
     * @return array{require_verification: bool, require_phone: bool, require_email: bool}
     */
    public function policy(): array
    {
        return $this->settings->getAccountPolicyConfig();
    }

    /**
     * 返回当前策略下用户尚未满足的项，按实名、手机号、邮箱顺序排列。
     *
     * @return list<string>
     */
    public function missingItems(User $user): array
    {
        $policy = $this->policy();
        $missing = [];

        if ($policy['require_verification'] && ! $user->hasCompletedVerification()) {
            $missing[] = self::MISSING_VERIFICATION;
        }

        if ($policy['require_phone'] && trim((string) ($user->phone ?? '')) === '') {
            $missing[] = self::MISSING_PHONE;
        }

        if ($policy['require_email'] && trim((string) ($user->email ?? '')) === '') {
            $missing[] = self::MISSING_EMAIL;
        }

        return $missing;
    }

    /**
     * 关键写操作前置断言：任一策略项未满足即抛出对应业务码。
     *
     * 抛出时在 data 中带上完整缺失清单，前端可据此渲染引导弹窗，
     * 不必再从错误文案反推缺少哪几项。
     *
     * @param  string  $action  checkout|recharge|payment|withdrawal
     */
    public function assertSatisfied(User $user, string $action): void
    {
        $missing = $this->missingItems($user);

        if ($missing === []) {
            return;
        }

        $primary = self::MISSING_DEFINITIONS[$missing[0]];

        throw new BusinessException($primary['message'], $primary['code'], 403, [
            'action' => $action,
            'missing' => $missing,
        ]);
    }
}
