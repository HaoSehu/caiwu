<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Jobs\SendClientLoginEmailAlertJob;
use App\Models\AdminUser;
use App\Models\User;
use App\Models\UserProfile;
use App\Support\AccountIdentifier;
use App\Support\TextSanitizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AuthService
{
    private const ADMIN_LOGIN_AS_CODE_TTL_SECONDS = 120;
    private const CLIENT_LOGIN_COLUMNS = [
        'id',
        'email',
        'phone',
        'password',
        'nickname',
        'status',
        'login_email_alert',
    ];

    public function __construct(
        private NotificationService $notificationService,
        private ReferralService $referralService,
        private OperationLogService $operationLogService,
    ) {}

    /**
     * 客户登录
     */
    public function clientLogin(string $account, string $password, string $ip, ?string $userAgent = null): array
    {
        $accountType = AccountIdentifier::detectType($account);
        if (! $accountType) {
            throw new BusinessException('请输入正确的邮箱或手机号', 42200, 422);
        }

        $normalizedAccount = AccountIdentifier::normalizeAccount($account);
        $user = $this->findClientByAccount($accountType, $normalizedAccount);
        $needsPasswordRehash = false;

        // 防止时序攻击：即使用户不存在也执行验证
        if (! $user || ! $this->verifyPassword($password, $user->password ?? '', $needsPasswordRehash)) {
            throw new BusinessException('邮箱/手机号或密码错误', 40100, 401);
        }

        if ($user->status !== 1) {
            throw new BusinessException('账号已被禁用', 40300, 403);
        }

        $loginAt = now();
        $token = $user->createToken('client-token')->plainTextToken;
        $this->finishClientLoginAfterResponse(
            userId: (int) $user->id,
            loginAt: $loginAt->format('Y-m-d H:i:s'),
            ip: $ip,
            email: trim((string) $user->email),
            displayName: (string) $user->display_name,
            userAgent: $userAgent,
            loginEmailAlertEnabled: (bool) $user->login_email_alert,
            passwordToRehash: $needsPasswordRehash ? $password : null,
        );

        return [
            'token'     => $token,
            'user'      => [
                'id'       => $user->id,
                'email'    => (string) ($user->email ?? ''),
                'phone'    => (string) ($user->phone ?? ''),
                'nickname' => $user->nickname,
                'login_email_alert' => (int) $user->login_email_alert,
                'balance'  => (string) ($user->account?->cash_balance ?? $user->balance),
            ],
        ];
    }

    /**
     * 客户注册
     */
    public function clientRegister(array $data, string $ip): array
    {
        $accountType = AccountIdentifier::detectType((string) ($data['account'] ?? ''));
        if (! $accountType) {
            throw new BusinessException('请输入正确的邮箱或手机号', 42200, 422);
        }

        $account = AccountIdentifier::normalizeAccount((string) $data['account']);
        $email = AccountIdentifier::normalizeOptionalEmail((string) ($data['email'] ?? ''));
        $phone = AccountIdentifier::normalizeOptionalPhone((string) ($data['phone'] ?? ''));

        if ($accountType === 'email') {
            $email = $account;
        } else {
            $phone = $account;
        }

        $this->ensureUniqueClientEmail($email);
        $this->ensureUniqueClientPhone($phone);

        $storablePhone = $phone ?? '';

        $user = DB::transaction(function () use ($data, $ip, $email, $storablePhone) {
            $user = User::create([
                'email'         => $email,
                'password'      => $data['password'],
                'phone'         => $storablePhone,
                'login_email_alert' => $email !== null ? 1 : 0,
                'last_login_at' => now(),
                'last_login_ip' => $ip,
            ]);

            $nickname = TextSanitizer::clean((string) ($data['nickname'] ?? ''));
            UserProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                ['nickname' => $nickname !== '' ? $nickname : null]
            );

            $this->referralService->ensureReferralCode($user);
            $this->referralService->bindReferrer($user, $data['referral_code'] ?? null, [
                'ip' => $ip,
            ]);

            if (User::profileTableAvailable()) {
                return $user->fresh(['profile']) ?? $user->loadMissing('profile');
            }

            return $user->fresh() ?? $user;
        });

        if ($user->referrer_user_id) {
            $this->operationLogService->write(
                userId: $user->id,
                userType: 'client',
                action: 'client.register.referral_bound',
                module: 'referral',
                targetId: $user->id,
                detail: [
                    'referrer_user_id' => $user->referrer_user_id,
                    'referral_code' => $data['referral_code'] ?? '',
                ],
                ipAddress: $ip,
            );
        }

        $token = $user->createToken('client-token')->plainTextToken;

        return [
            'token' => $token,
            'user'  => [
                'id'       => $user->id,
                'email'    => (string) ($user->email ?? ''),
                'phone'    => (string) ($user->phone ?? ''),
                'nickname' => $user->nickname,
                'balance'  => '0.00',
            ],
        ];
    }

    public function findClientByAccount(string $accountType, string $account): ?User
    {
        if ($accountType === 'email') {
            return User::query()
                ->withReadAggregates()
                ->select(self::CLIENT_LOGIN_COLUMNS)
                ->where('email', AccountIdentifier::normalizeEmail($account))
                ->first();
        }

        $phone = AccountIdentifier::normalizePhone($account);
        $matches = User::query()
            ->withReadAggregates()
            ->select(self::CLIENT_LOGIN_COLUMNS)
            ->where('phone', $phone)
            ->limit(2)
            ->get();

        if ($matches->count() > 1) {
            throw new BusinessException('该手机号关联了多个账号，请联系客服处理', 42200, 422);
        }

        return $matches->first();
    }

    public function ensureUniqueClientEmail(?string $email, ?int $ignoreUserId = null): void
    {
        if ($email === null || $email === '') {
            return;
        }

        $query = User::query()->where('email', $email);
        if ($ignoreUserId !== null) {
            $query->where('id', '<>', $ignoreUserId);
        }

        if ($query->exists()) {
            throw new BusinessException('邮箱已被注册', 40900, 409);
        }
    }

    public function ensureUniqueClientPhone(?string $phone, ?int $ignoreUserId = null): void
    {
        if ($phone === null || $phone === '') {
            return;
        }

        $query = User::query()->where('phone', $phone);
        if ($ignoreUserId !== null) {
            $query->where('id', '<>', $ignoreUserId);
        }

        if ($query->exists()) {
            throw new BusinessException('手机号已被注册', 40900, 409);
        }
    }

    public function resetClientPassword(User $user, string $password): void
    {
        $user->update([
            'password' => $password,
        ]);
        $user->tokens()->delete();
    }

    /**
     * 管理员登录
     */
    public function adminLogin(string $username, string $password, string $ip): array
    {
        if (! AdminUser::query()->exists()) {
            throw new BusinessException('后台管理员未初始化，请先执行数据初始化', 42200, 422);
        }

        $admin = AdminUser::with(['roles.permissionItems', 'role.permissionItems'])
            ->where('username', $username)
            ->first();

        // 防止时序攻击：即使用户不存在也执行 Hash::check
        if (!$admin || !Hash::check($password, $admin->password ?? '')) {
            throw new BusinessException('用户名或密码错误', 40100, 401);
        }

        if ($admin->status !== 1) {
            throw new BusinessException('账号已被禁用', 40300, 403);
        }

        $admin->update([
            'last_login_at' => now(),
            'last_login_ip' => $ip,
        ]);

        $this->operationLogService->write(
            userId: (int) $admin->id,
            userType: 'admin',
            action: 'admin.login',
            module: 'auth',
            targetId: (int) $admin->id,
            detail: [
                'admin_username' => (string) $admin->username,
                'admin_nickname' => (string) ($admin->nickname ?? ''),
                'role_name' => $admin->resolvedRoleLabel(),
            ],
            ipAddress: $ip,
        );

        $token = $admin->createToken('admin-token')->plainTextToken;

        return [
            'token' => $token,
            'admin' => [
                'id'          => $admin->id,
                'username'    => $admin->username,
                'nickname'    => $admin->nickname,
                'email'       => (string) ($admin->email ?? ''),
                'role'        => $admin->resolvedRoleLabel(),
                'permissions' => $admin->resolvedPermissions(),
            ],
        ];
    }

    public function issueAdminLoginAsCode(User $user, array $context = []): array
    {
        $this->ensureClientAvailable($user);

        $code = Str::random(64);
        $cacheKey = $this->buildAdminLoginAsCacheKey($code);
        $adminId = (int) ($context['admin_id'] ?? 0);
        $ipAddress = trim((string) ($context['ip_address'] ?? ''));
        $userAgentHash = $this->hashLoginAsUserAgent((string) ($context['user_agent'] ?? ''));

        Cache::put($cacheKey, [
            'user_id' => (int) $user->id,
            'admin_id' => $adminId > 0 ? $adminId : null,
            'issued_ip' => $ipAddress !== '' ? $ipAddress : null,
            'issued_user_agent_hash' => $userAgentHash !== '' ? $userAgentHash : null,
            'issued_at' => now()->format('Y-m-d H:i:s'),
        ], now()->addSeconds(self::ADMIN_LOGIN_AS_CODE_TTL_SECONDS));

        $this->operationLogService->write(
            userId: $adminId > 0 ? $adminId : null,
            userType: 'admin',
            action: 'admin.user.login_as.issue',
            module: 'auth',
            targetId: (int) $user->id,
            detail: [
                'client_user_id' => (int) $user->id,
                'expires_in_seconds' => self::ADMIN_LOGIN_AS_CODE_TTL_SECONDS,
            ],
            ipAddress: $ipAddress !== '' ? $ipAddress : null,
        );

        $frontendUrl = trim((string) config('app.frontend_url', ''));
        $redirectUrl = $frontendUrl !== ''
            ? rtrim($frontendUrl, '/') . '/client/dashboard'
            : null;

        return [
            'login_code' => $code,
            'expires_in' => self::ADMIN_LOGIN_AS_CODE_TTL_SECONDS,
            'user' => [
                'id' => (int) $user->id,
                'email' => (string) $user->email,
                'nickname' => (string) $user->nickname,
            ],
            'redirect_url' => $redirectUrl,
        ];
    }

    public function exchangeAdminLoginAsCode(string $code, string $ip, ?string $userAgent = null): array
    {
        $code = trim($code);
        if ($code === '') {
            throw new BusinessException('代登录凭证不能为空', 42200, 422);
        }

        $payload = Cache::pull($this->buildAdminLoginAsCacheKey($code));
        if (! is_array($payload)) {
            throw new BusinessException('代登录凭证已失效，请重新发起', 41000, 410);
        }

        $issuedIp = trim((string) ($payload['issued_ip'] ?? ''));
        if ($issuedIp !== '' && $ip !== '' && ! hash_equals($issuedIp, $ip)) {
            throw new BusinessException('代登录环境校验失败，请在原浏览器窗口重新发起', 40300, 403);
        }

        $issuedUserAgentHash = trim((string) ($payload['issued_user_agent_hash'] ?? ''));
        $currentUserAgentHash = $this->hashLoginAsUserAgent((string) ($userAgent ?? ''));
        if (
            $issuedUserAgentHash !== ''
            && $currentUserAgentHash !== ''
            && ! hash_equals($issuedUserAgentHash, $currentUserAgentHash)
        ) {
            throw new BusinessException('代登录环境校验失败，请在原浏览器窗口重新发起', 40300, 403);
        }

        $user = User::query()->find((int) ($payload['user_id'] ?? 0));
        if (! $user) {
            throw new BusinessException('目标用户不存在', 40400, 404);
        }

        $this->ensureClientAvailable($user);

        $user->tokens()->where('name', 'admin-login-as')->delete();
        $token = $user->createToken('admin-login-as', ['*'], now()->addHours(2));

        $this->operationLogService->write(
            userId: (int) $user->id,
            userType: 'client',
            action: 'client.login_as.exchange',
            module: 'auth',
            targetId: (int) $user->id,
            detail: [
                'admin_id' => isset($payload['admin_id']) ? (int) $payload['admin_id'] : null,
            ],
            ipAddress: $ip !== '' ? $ip : null,
        );

        return [
            'token' => $token->plainTextToken,
            'user' => [
                'id' => (int) $user->id,
                'email' => (string) $user->email,
                'nickname' => (string) $user->nickname,
            ],
        ];
    }

    /**
     * 验证密码，兼容魔方迁移的 MD5 格式（###{md5hash}）
     * 验证通过后若为旧格式，自动升级为 bcrypt
     */
    private function verifyPassword(string $plaintext, string $stored, bool &$needsPasswordRehash = false): bool
    {
        // 魔方 MD5 格式：###开头 + 32位十六进制
        if (str_starts_with($stored, '###') && strlen($stored) === 35) {
            $md5hash = substr($stored, 3);
            if (!hash_equals($md5hash, md5($plaintext))) {
                return false;
            }

            $needsPasswordRehash = true;

            return true;
        }

        return Hash::check($plaintext, $stored);
    }

    private function finishClientLoginAfterResponse(
        int $userId,
        string $loginAt,
        string $ip,
        string $email,
        string $displayName,
        ?string $userAgent,
        bool $loginEmailAlertEnabled,
        ?string $passwordToRehash = null
    ): void
    {
        app()->terminating(function () use (
            $userId,
            $loginAt,
            $ip,
            $email,
            $displayName,
            $userAgent,
            $loginEmailAlertEnabled,
            $passwordToRehash
        ): void {
            $this->persistClientLoginState($userId, $loginAt, $ip, $passwordToRehash);

            if (! $loginEmailAlertEnabled || $email === '') {
                return;
            }

            $this->dispatchClientLoginEmailAlert($userId, $email, $displayName, $loginAt, $ip, $userAgent);
        });
    }

    private function persistClientLoginState(int $userId, string $loginAt, string $ip, ?string $passwordToRehash = null): void
    {
        try {
            $payload = [
                'last_login_at' => $loginAt,
                'last_login_ip' => $ip,
            ];

            if ($passwordToRehash !== null && $passwordToRehash !== '') {
                $payload['password'] = Hash::make($passwordToRehash);
            }

            User::query()
                ->whereKey($userId)
                ->update($payload);
        } catch (\Throwable $exception) {
            Log::warning('登录后更新用户登录状态失败', [
                'user_id' => $userId,
                'ip' => $ip,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * 异步投递登录邮件提醒任务，避免 SMTP 同步阻塞登录响应
     */
    private function dispatchClientLoginEmailAlert(
        int $userId,
        string $email,
        string $displayName,
        string $loginAt,
        string $ip,
        ?string $userAgent
    ): void {
        // sync 队列驱动下降级为同步发送（开发环境兜底）
        if ((string) config('queue.default', 'sync') === 'sync') {
            try {
                $this->notificationService->sendLoginEmailAlertToAddress(
                    $email, $displayName, $loginAt, $ip, $userAgent
                );
            } catch (\Throwable $exception) {
                Log::warning('同步发送用户登录邮件提醒失败', [
                    'user_id' => $userId,
                    'email' => $email,
                    'ip' => $ip,
                    'message' => $exception->getMessage(),
                ]);
            }
            return;
        }

        try {
            SendClientLoginEmailAlertJob::dispatch(
                userId: $userId,
                email: $email,
                displayName: $displayName,
                loginAt: $loginAt,
                ip: $ip,
                userAgent: $userAgent,
            );
        } catch (\Throwable $exception) {
            Log::warning('投递用户登录邮件提醒任务失败', [
                'user_id' => $userId,
                'email' => $email,
                'ip' => $ip,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function ensureClientAvailable(User $user): void
    {
        if ((int) $user->status !== 1) {
            throw new BusinessException('该客户账号已被禁用，无法代登录', 40300, 403);
        }

        if (method_exists($user, 'trashed') && $user->trashed()) {
            throw new BusinessException('该客户账号不可用，无法代登录', 40300, 403);
        }
    }

    private function buildAdminLoginAsCacheKey(string $code): string
    {
        return 'auth:admin_login_as:' . hash('sha256', $code);
    }

    private function hashLoginAsUserAgent(string $userAgent): string
    {
        $normalized = preg_replace('/\s+/u', ' ', mb_strtolower(trim($userAgent), 'UTF-8')) ?? '';

        return $normalized !== '' ? hash('sha256', $normalized) : '';
    }
}
