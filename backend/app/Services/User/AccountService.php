<?php

declare(strict_types=1);

namespace App\Services\User;

use App\Models\User;
use App\Models\UserAccount;
use App\Support\Money;
use App\Support\UserBalanceCache;

class AccountService
{
    /**
     * 非现金账户列白名单：现金余额已迁移到 finance_accounts，由 FinanceAccountService 独占。
     * cash_balance 保留在旧表仅作切换期热备，禁止经本类直接写入。
     */
    private const MONEY_FIELDS = [
        'credit_limit',
        'referral_frozen_balance',
        'referral_available_balance',
        'referral_pending_withdrawal_balance',
        'referral_withdrawn_balance',
    ];

    public function ensureAccount(User|int $user, bool $lockForUpdate = false): UserAccount
    {
        $userId = $this->resolveUserId($user);
        $query = UserAccount::query();

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $account = $query->find($userId);
        if ($account instanceof UserAccount) {
            return $account;
        }

        // 使用 firstOrCreate 防止并发创建导致的 duplicate key 异常
        $account = UserAccount::query()->firstOrCreate(
            ['user_id' => $userId],
            $this->defaultAttributes(),
        );

        if ($lockForUpdate) {
            $account = UserAccount::query()->lockForUpdate()->findOrFail($userId);
        }

        return $account;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateAccount(UserAccount|User|int $accountOrUser, array $attributes, bool $lockForUpdate = false): UserAccount
    {
        if (array_key_exists('cash_balance', $attributes)) {
            throw new \InvalidArgumentException('现金余额必须通过 FinanceAccountService 写入');
        }

        $account = $accountOrUser instanceof UserAccount
            ? $accountOrUser
            : $this->ensureAccount($accountOrUser, $lockForUpdate);

        if ($lockForUpdate && $accountOrUser instanceof UserAccount) {
            $account = UserAccount::query()->lockForUpdate()->findOrFail((int) $accountOrUser->user_id);
        }

        $payload = [];
        foreach (self::MONEY_FIELDS as $field) {
            if (array_key_exists($field, $attributes)) {
                $payload[$field] = $this->money($attributes[$field]);
            }
        }

        if ($payload === []) {
            return $account;
        }

        $account->forceFill($payload)->save();

        if (array_key_exists('cash_balance', $payload)) {
            UserBalanceCache::forget((int) $account->user_id);
        }

        return $account->refresh();
    }

    private function resolveUserId(User|int $user): int
    {
        return $user instanceof User ? (int) $user->id : (int) $user;
    }

    /**
     * @return array<string, string|int>
     */
    private function defaultAttributes(): array
    {
        return [
            'cash_balance' => '0.00',
            'credit_limit' => '0.00',
            'referral_frozen_balance' => '0.00',
            'referral_available_balance' => '0.00',
            'referral_pending_withdrawal_balance' => '0.00',
            'referral_withdrawn_balance' => '0.00',
            'version' => 0,
        ];
    }

    private function money(mixed $value): string
    {
        // 非 numeric 输入直接落 0.00，避免 Money 对特殊字符串转换产生歧义
        if (! is_numeric($value)) {
            return '0.00';
        }

        return Money::format($value);
    }
}
