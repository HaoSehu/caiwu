<?php

namespace App\Models;

use App\Casts\LegacyEncrypted;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    private static ?bool $profileTableAvailable = null;
    private static ?bool $accountTableAvailable = null;

    protected $fillable = [
        'email', 'password', 'phone', 'status',
        'nickname', 'company', 'qq', 'admin_note',
        'referral_code', 'referrer_user_id', 'referred_at', 'member_level_id', 'total_sales_amount',
        'is_verified', 'real_name', 'id_card', 'verification_status', 'verification_message', 'verification_certify_id', 'verified_at',
        'alipay_real_name', 'alipay_account',
        'login_email_alert', 'last_login_ip', 'last_login_at',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'login_email_alert' => 'boolean',
            'last_login_at' => 'datetime',
            'referred_at' => 'datetime',
            'verified_at' => 'datetime',
            'total_sales_amount' => 'decimal:2',
            'is_verified' => 'integer',
            'verification_status' => 'integer',
            'member_level_id' => 'integer',
            'referrer_user_id' => 'integer',
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
    }

    public function getNicknameAttribute(mixed $value): string
    {
        $nickname = trim((string) $this->resolveProfileValue('nickname', $value));

        return $this->hasReadableNickname($nickname) ? $nickname : '';
    }

    public function getCompanyAttribute(mixed $value): string
    {
        return trim((string) $this->resolveProfileValue('company', $value));
    }

    public function getQqAttribute(mixed $value): string
    {
        return trim((string) $this->resolveProfileValue('qq', $value));
    }

    public function getAdminNoteAttribute(mixed $value): ?string
    {
        $normalized = trim((string) $this->resolveProfileValue('admin_note', $value));

        return $normalized === '' ? null : $normalized;
    }

    public function getBalanceAttribute(mixed $value): string
    {
        return $this->normalizeDecimalString($this->resolveAccountValue('cash_balance', $value));
    }

    public function getCreditLimitAttribute(mixed $value): string
    {
        return $this->normalizeDecimalString($this->resolveAccountValue('credit_limit', $value));
    }

    public function getRealNameAttribute(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }

    public function getIdCardAttribute(mixed $value): string
    {
        return (new LegacyEncrypted())->get($this, 'id_card', $value, $this->attributes);
    }

    public function getVerificationStatusAttribute(mixed $value): int
    {
        return (int) ($value ?? 0);
    }

    public function getVerificationMessageAttribute(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }

    public function getVerificationCertifyIdAttribute(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized === '' ? null : $normalized;
    }

    public function getVerifiedAtAttribute(mixed $value): ?CarbonInterface
    {
        if ($value instanceof CarbonInterface || $value === null) {
            return $value;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : \Illuminate\Support\Carbon::parse($normalized);
    }

    public function getIsVerifiedAttribute(mixed $value): int
    {
        return (int) ($value ?? 0);
    }

    public function getReferralCodeAttribute(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }

    public function getReferrerUserIdAttribute(mixed $value): ?int
    {
        return $this->normalizeNullableInt($value);
    }

    public function getReferredAtAttribute(mixed $value): mixed
    {
        if ($value instanceof CarbonInterface || $value === null) {
            return $value;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : \Illuminate\Support\Carbon::parse($normalized);
    }

    public function getMemberLevelIdAttribute(mixed $value): ?int
    {
        return $this->normalizeNullableInt($value);
    }

    public function getTotalSalesAmountAttribute(mixed $value): string
    {
        return $this->normalizeDecimalString($value);
    }

    public function getReferralFrozenAmountAttribute(mixed $value): string
    {
        return $this->normalizeDecimalString($this->resolveAccountValue('referral_frozen_balance', $value));
    }

    public function getReferralAvailableAmountAttribute(mixed $value): string
    {
        return $this->normalizeDecimalString($this->resolveAccountValue('referral_available_balance', $value));
    }

    public function getReferralWithdrawingAmountAttribute(mixed $value): string
    {
        return $this->normalizeDecimalString($this->resolveAccountValue('referral_pending_withdrawal_balance', $value));
    }

    public function getReferralWithdrawnAmountAttribute(mixed $value): string
    {
        return $this->normalizeDecimalString($this->resolveAccountValue('referral_withdrawn_balance', $value));
    }

    public function getAlipayRealNameAttribute(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }

    public function getAlipayAccountAttribute(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }

    public function getDisplayNameAttribute(): string
    {
        $realName = trim((string) $this->real_name);
        if ($realName !== '' && ((int) $this->verification_status === 2 || (int) $this->is_verified === 1)) {
            return $realName;
        }

        $nickname = trim((string) $this->nickname);

        if ($this->hasReadableNickname($nickname)) {
            return $nickname;
        }

        $email = trim((string) $this->email);
        if ($email !== '') {
            return $email;
        }

        return trim((string) $this->phone);
    }

    // -------- 关联 --------

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function profile()
    {
        return $this->hasOne(UserProfile::class, 'user_id');
    }

    public function verificationProfile()
    {
        return $this->hasOne(UserVerification::class, 'user_id');
    }

    public function referralProfile()
    {
        return $this->hasOne(UserReferral::class, 'user_id');
    }

    public function memberLevel()
    {
        return $this->belongsTo(MemberLevel::class, 'member_level_id');
    }

    public function account()
    {
        return $this->hasOne(UserAccount::class, 'user_id');
    }

    public function referrer()
    {
        return $this->belongsTo(User::class, 'referrer_user_id');
    }

    public function referrals()
    {
        return $this->hasMany(User::class, 'referrer_user_id');
    }

    public function referralRewards()
    {
        return $this->hasMany(ReferralReward::class, 'referrer_user_id');
    }

    public function referredRewards()
    {
        return $this->hasMany(ReferralReward::class, 'referred_user_id');
    }

    public function referralWithdrawals()
    {
        return $this->hasMany(ReferralWithdrawal::class);
    }

    public function services()
    {
        return $this->hasMany(Service::class);
    }

    public function userCoupons()
    {
        return $this->hasMany(UserCoupon::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function balanceLogs()
    {
        return $this->hasMany(BalanceLog::class);
    }

    public function accountTransactions()
    {
        return $this->hasMany(AccountTransaction::class, 'user_id');
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function verificationHistories()
    {
        return $this->hasMany(VerificationHistory::class);
    }

    // -------- 作用域 --------

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function scopeWithReadAggregates(Builder $query): Builder
    {
        $relations = [];

        if (self::accountTableAvailable()) {
            $relations[] = 'account';
        }

        if (self::profileTableAvailable()) {
            $relations[] = 'profile';
        }

        return $query->with($relations);
    }

    public function scopeSearch(Builder $query, ?string $keyword): Builder
    {
        if (!$keyword) {
            return $query;
        }

        $keyword = trim($keyword);

        return $query->where(function ($q) use ($keyword) {
            if (ctype_digit($keyword)) {
                $q->where('id', (int) $keyword)
                  ->orWhere('email', 'like', '%' . $keyword . '%')
                  ->orWhere('phone', 'like', '%' . $keyword . '%')
                  ->orWhere('nickname', 'like', '%' . $keyword . '%')
                  ->orWhere('company', 'like', '%' . $keyword . '%')
                  ->orWhere('qq', 'like', '%' . $keyword . '%')
                  ->orWhere('real_name', 'like', '%' . $keyword . '%');
                return;
            }

            $q->where('email', 'like', '%' . $keyword . '%')
              ->orWhere('phone', 'like', '%' . $keyword . '%')
              ->orWhere('nickname', 'like', '%' . $keyword . '%')
              ->orWhere('company', 'like', '%' . $keyword . '%')
              ->orWhere('qq', 'like', '%' . $keyword . '%')
              ->orWhere('real_name', 'like', '%' . $keyword . '%');
        });
    }



    private function hasReadableNickname(string $nickname): bool
    {
        if ($nickname === '') {
            return false;
        }

        return preg_replace('/[\s\?？\x{FFFD}]+/u', '', $nickname) !== '';
    }

    private function nullableValue(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    private function normalizeNullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    private function normalizeDecimal(mixed $value): string
    {
        if (! is_numeric($value)) {
            return '0.00';
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function normalizeDecimalString(mixed $value): string
    {
        return $this->normalizeDecimal($value);
    }

    private function resolveProfileValue(string $field, mixed $fallback = null): mixed
    {
        if ($this->relationLoaded('profile')) {
            $profile = $this->getRelation('profile');
            if ($profile instanceof UserProfile) {
                $resolved = $profile->{$field} ?? null;
                if ($resolved !== null && trim((string) $resolved) !== '') {
                    return $resolved;
                }
            }
        }

        return $fallback;
    }

    private function resolveAccountValue(string $field, mixed $fallback = null): mixed
    {
        if (self::accountTableAvailable() && $this->relationLoaded('account')) {
            $account = $this->getRelation('account');
            if ($account instanceof UserAccount) {
                return $account->{$field} ?? $fallback;
            }
        }

        return $fallback;
    }

    public static function profileTableAvailable(): bool
    {
        if (self::$profileTableAvailable === null) {
            self::$profileTableAvailable = Schema::hasTable('user_profiles');
        }

        return self::$profileTableAvailable;
    }

    public static function accountTableAvailable(): bool
    {
        if (self::$accountTableAvailable === null) {
            self::$accountTableAvailable = Schema::hasTable('user_accounts');
        }

        return self::$accountTableAvailable;
    }
}
